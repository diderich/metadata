<?php
  /**
   * XmpDocument.php - Functions for reading and writing XMP specific data
   * 
   * @package   Holiday\Metadata
   * @version   1.0
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
   *
   * @see       https://www.npes.org/pdf/xmpspecification-Jun05.pdf
   * @see       https://developer.adobe.com/xmp/docs/XMPSpecifications/
   */

namespace Holiday\Metadata;
use Holiday\Metadata;

class XmpDocument {

  /** DOMDocument to which to add functions */
  private \DOMDocument $dom;

  /** Namespaces that may contain IPTC Code Metadata 1.3 (in descending order of preference) */
  const NS_IPTC4XMPCORE = 'Iptc4xmpCore';
  const NS_DC = 'dc';
  const NS_AUX = 'aux';
  const NS_XMP = 'xmp';
  const NS_PHOTOSHOP = 'photoshop';
  const NS_PHOTOMECHANIC = 'photomechanic';

  /** Private variables */
  private array $nsPriorityAry;            /** Prioritized array of name spaces for core metadata */

  /**
   * Constructor
   *
   * @param DOMDocument DOM of XMP data
   */
  public function __construct(\DOMDocument $dom)
  {
	$this->nsPriorityAry = array(self::NS_IPTC4XMPCORE, self::NS_DC, self::NS_AUX, self::NS_XMP, self::NS_PHOTOSHOP,
								 self::NS_PHOTOMECHANIC);
	// All namespaces supported by default, others may be added before use using 'setXmpNamespace'
	$all_ns = array('Iptc4xmpCore' => 'http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/',
					'aux' => 'http://ns.adobe.com/exif/1.0/aux/',
					'dc' => 'http://purl.org/dc/elements/1.1/',
					'xmp' => 'http://ns.adobe.com/xap/1.0/',
					'photoshop' => 'http://ns.adobe.com/photoshop/1.0/',
					'photomechanic' => 'http://ns.camerabits.com/photomechanic/1.0/',
					'Iptc4xmpExt' => 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/',
					'GettyImagesGIFT' => 'http://xmp.gettyimages.com/gift/1.0/',
					'exifEX' => 'http://cipa.jp/exif/1.0/',
					'plus' => 'http://ns.useplus.org/ldf/xmp/1.0/',
					'stEvt' => 'http://ns.adobe.com/xap/1.0/sType/ResourceEvent#',
					'stRef' => 'http://ns.adobe.com/xap/1.0/sType/ResourceRef#',
					'xmpMM' => 'http://ns.adobe.com/xap/1.0/mm/',
					'xmpRights' => 'http://ns.adobe.com/xap/1.0/rights/');
	$this->dom = $dom;
	$this->validateXmpDocument($all_ns);
  }

  /**
   * Destructor
   */
  public function __destruct()
  {
  }

  /***
   * Return the DOM associated with the XmpDocument class
   *
   * @return \DOMDocument DOM Document
   */
  public function getDom(): \DOMDocument
  {
	return $this->dom;
  }

  /**
   * GET XMP METADATA
   */

  /**
   * Return is a text value is associate with the node
   *
   * @param  string $name Node name, including prefix
   * @return bool   Node exists
   * @throw \Holiday\Metadata\Exception
   */
  public function isXmpText(string $name): bool
  {
	// Search for Name as Attribute
	$descs = self::getXmpAllNodeByName($this->dom, Xmp::DESCRIPTION);
	if($descs === false)
	  throw new Exception(_('Cannot find')." 'rdf:Description' "._('node'),  Exception::DATA_FORMAT_ERROR);
	foreach($descs as $desc) {
	  if($desc->hasAttribute($name)) return true;
	}

	// Try find Name as Node rather than Attribute
	$node = self::getXmpFirstNodeByName($this->dom, $name);
	return $node !== false;
  }
  
  /**
   *Return if a value exists with the associated node
   * 
   * @param  string $tag  Bag tag (Seq, Alt, or Bag)
   * @param  string $name Node name, including prefix
   * @return bool   Node exists
   * @throw \Holiday\Metadata\Exception
   */
  public function isXmpLi(string $tag, string $name): bool
  {
	$node = self::getXmpFirstNodeByName($this->dom, $name);
	if($node === false) return false;
	$child = self::getXmpFirstNodeByName($node, "rdf:$tag");
	return $child !== false;
  }
  
  /**
   * Return is a text value is associate with the bag node
   *
   * @param  string $name Node name, including prefix
   * @return bool   Node bag exists
   * @throw \Holiday\Metadata\Exception
   */
  public function isXmpBag(string $name): bool
  {
	return $this->isXmpLi('Bag', $name);
  }
  
  /**
   * Get Attribute / Node / rdf:Seq / rdf:Alt value
   *
   * @param  string $name Node name, including prefix
   * @return string|false First node value found, or false, if not found
   * @throw \Holiday\Metadata\Exception
   */
  public function getXmpText(string $name): string|false
  {
	if(strpos($name, ':') === false)
	  throw new Exception(_('Node name without prefix found'), Exception::INVALID_FIELD_ID, $name);

	list($prefix, $name) = explode(':', $name, 2);

	// Seach first in specified name space (if any)
	$result = $this->getXmpTextNS($prefix, $name);
	if($result !== false) return $result;
	
	// Search in other name spaces according to priority (if core element)
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $prefix) {
		$result = $this->getXmpTextNS($prefix, $name);
		if($result !== false) return $result;
	  }
	}
	return false;
  }

  /**
   * Get array of Bag node values
   *
   * @param string $name Node name, including prefix
   * @return array|false Array of node values, as rdf:li in rdf:Bag, or false if not found
   * @throw \Holiday\Metadata\Exception
   */
  public function getXmpBag(string $name): array|false
  {
	if(strpos($name, ':') === false)
	  throw new Exception(_('Node name without prefix found'), Exception::INVALID_FIELD_ID, $name);

	list($prefix, $name) = explode(':', $name, 2);

	// Seach first in specified name space (if any)
	$result = $this->getXmpBagNS($prefix, $name);
	if($result !== false) return $result;
	
	// Search in other name spaces according to priority (if core element)
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $prefix) {
		$result = $this->getXmpBagNS($prefix, $name);
		if($result !== false) return $result;
	  }
	}
	return false;
  }
  
  /**
   * SET XMP METADATA
   */

  /**
   * Define a new / ensure the existence of a given namespace
   *
   * @param string $ns  Namespace identifier
   * @param string $uri Namespace URI
   */
  public function setXmpNamespace(string $ns, string $uri): void
  {
	$descs = self::getXmpAllNodeByName($this->dom, Xmp::DESCRIPTION);

	// Search for an rdf:Description element with the specified namespace definition as attribute
	$found = false;
	if($descs !== false) {
	  foreach($descs as $desc) {
		if($desc->hasAttribute("xmlns:$ns")) {
		  $found = true;
		  if(!$desc->hasAttribute('rdf:about')) $desc->setAttribute('rdf:about', '');
		}
	  }
	}

	// Create a new rdf:Description element under rdf:RDF including the namespace definition
	if(!$found) {
	  $root = self::getXmpFirstNodeByName($this->dom, 'rdf:RDF');
	  if($root === false)
		throw new Exception(_('Internal error finding')." 'rdf:RDF' "._('element'), Exception::INTERNAL_ERROR);
	  $elt = $this->dom->createElement(Xmp::DESCRIPTION);
	  $desc = $root->appendChild($elt);
	  $desc->setAttribute("xmlns:$ns", $uri);
	  $desc->setAttribute('rdf:about', '');
	}
  }
  
  /**
   * Set Attribute (removing identically named nodes) and updating values in other namespaces
   *
   * @param string           $name Node name, including prefix
   * @param string|int|false $data Node value
   * @param bool             $pdate_only Only update value, do not create new nodes
   * @throw \Holiday\Metadata\Exception
   */
  public function setXmpText(string $name, string|int|false $data): void
  {
	if(strpos($name, ':') === false)
	  throw new Exception(_('Node name without prefix found'), Exception::INVALID_FIELD_ID, $name);

	list($prefix, $name) = explode(':', $name, 2);
	$this->setXmpTextNS($prefix, $name, $data, update_only: false);

	// Update nodes is associated namespaces
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $ns_prefix) {
		if($this->isXmpText("$ns_prefix:$name") && $ns_prefix !== $prefix) {
		  $this->setXmpTextNS($prefix, $name, $data, update_only: true);
		}
	  }
	}
  }
  
  /**
   * Set/Update req:Seq tag entries and updating values in other namespaces
   *
   * @param string           $name Node name, including prefix
   * @param string|int|false $data Node value
   * @throw \Holiday\Metadata\Exception
   */
  public function setXmpSeq(string $name, string|int|false $data): void
  {
	if(self::existXmpAttribute($this->dom, Xmp::DESCRIPTION, $name))
	  throw new Exception(_('Cannot set')." 'rdf:Seq' "._('node value if an attribute with the same name exists'),
						  Exception::INVALID_FIELD_ID, $name);

	list($prefix, $name) = explode(':', $name, 2);
	$this->setXmpLiNS('Seq', $prefix, $name, $data, lang: false, update_only: false);

	// Update nodes is associated namespaces
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $ns_prefix) {
		if($this->isXmpLi('Seq', "$ns_prefix:$name") && $ns_prefix !== $prefix) {
		  $this->setXmpLiNS('Seq', $ns_prefix, $name, $data, lang: false, update_only: true);
		}
	  }
	}
  }
  
  /**
   * Set/Update req:Alt tag entries and updating values in other namespaces
   *
   * @param string           $name Node name, including prefix
   * @param string|int|false $data Node value
   * @throw \Holiday\Metadata\Exception
   */
  public function setXmpAlt(string $name, string|int|false $data): void
  {
	if(self::existXmpAttribute($this->dom, Xmp::DESCRIPTION, $name))
	  throw new Exception(_('Cannot set')." 'rdf:Alt' "._('node value if an attribute with the same name exists'),
						  Exception::INVALID_FIELD_ID, $name);

	list($prefix, $name) = explode(':', $name, 2);
	$this->setXmpLiNS('Alt', $prefix, $name, $data, lang: true, update_only: false);

	// Update nodes is associated namespaces
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $ns_prefix) {
		if($this->isXmpLi('Alt', "$ns_prefix:$name") && $ns_prefix !== $prefix) {
		  $this->setXmpLiNS('Alt', $ns_prefix, $name, $data, lang: true, update_only: true);
		}
	  }
	}
  }
  
  /**
   * Set/Update req:Bag tag entries and updating values in other namespaces
   *
   * @param string      $name Node name, including prefix
   * @param array|false $data Node value
   * @throw \Holiday\Metadata\Exception
   */
  public function setXmpBag(string $name, array|false $data): void
  {
	if(self::existXmpAttribute($this->dom, Xmp::DESCRIPTION, $name))
	  throw new Exception(_('Cannot set')." 'rdf:Bag' "._('node value if an attribute with the same name exists'),
						  Exception::INVALID_FIELD_ID, $name);

	list($prefix, $name) = explode(':', $name, 2);
	$this->setXmpLiNS('Bag', $prefix, $name, $data, lang: false, update_only: false);

	// Update nodes is associated namespaces
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $ns_prefix) {
		if($this->isXmpLi('Bag', "$ns_prefix:$name") && $ns_prefix !== $prefix) {
		  $this->setXmpLiNS('Bag', $ns_prefix, $name, $data, lang: false, update_only: true);
		}
	  }
	}
  }

  /**
   * Add a history entry indicating that the data has been updated (in xmpMM:History
   *
   * @param string $software Software name
   */
  public function updateHistory(string $software): void
  {
	// Serch for a rdf:Description element that referneces to the xmpMM namespace in which histories are saves
	$desc = self::getXmpFirstNodeByName($this->dom, Xmp::DESCRIPTION);
	if($desc === false)
	  throw new Exception(_('Internal error finding')." 'rdf:Description' "._('including')." 'xmpMM' ".
						  _('as namespaces'), Exception::INTERNAL_ERROR);
	$history = self::getXmpFirstNodeByName($desc, Xmp::EDIT_HISTORY);

	// Create new history entry, if none exists
	if($history === false) {
	  $new_child = $this->dom->createElement('xmpMM:History');
	  $history = $desc->appendChild($new_child);
	}

	// Create new sequency, if none exists
	$seq = self::getXmpFirstNodeByName($history, 'rdf:Seq');
	If($seq === false) {
	  $new_child = $this->dom->createElement('rdf:Seq');
	  $seq = $history->appendChild($new_child);
	}

	// Add list element child to seq
	$new_child = $this->dom->createElement('rdf:li');
	$entry = $seq->appendChild($new_child);
	$entry->setAttribute('stEvt:action', 'saved');
	$entry->setAttribute('stEvt:parameters', 'updated metadata');
	$entry->setAttribute('stEvt:softwareAgent', $software);
	$entry->setAttribute('stEvt:when', date('c'));
  }

  /***
   * XML DATA ENCODING AND DECODING SUPPORT FUNCTIONS
   */

  /**
   * Get Attribute / Node / rdf:Seq / rdf:Alt value in specific name space
   *
   * @access protected
   * @param  string       $ns   Name space
   * @param  string       $name Node name, without prefix
   * @return string|false Node value
   * @throw \Holiday\Metadata\Exception
   */
  protected function getXmpTextNS(string $ns, string $name): string|false
  {
	// Search text as attribute of any rdf:Description node
	$descs = self::getXmpAllNodeByName($this->dom, Xmp::DESCRIPTION);
	if($descs === false)
	  throw new Exception(_('Cannot find')." 'rdf:Description' "._('node'),  Exception::DATA_FORMAT_ERROR);

	// Search for Name as Attribute
	foreach($descs as $desc) {
	  $result = $desc->getAttribute("$ns:$name");
	  if(!empty($result)) return $result;
	}

	// Try find Name as Node rather than Attribute
	$node = self::getXmpFirstNodeByName($this->dom, "$ns:$name");
	if($node === false) return false;

	//Try rdf:Alt - We only read the first list element
	$child_alt = self::getXmpFirstNodeByName($node, 'rdf:Alt');
	if($child_alt !== false) {
	  $subchild = self::getXmpFirstNodeByName($child_alt, 'rdf:li');
	  if($subchild === false)
		throw new Exception(_('Cannot find')." 'rdf:li' "._('child of')." 'rdf:Alt' "._('node'),
							Exception::DATA_FORMAT_ERROR, $name);
	  return (string)$subchild->nodeValue;
	}
	
	// Try rdf:Seq - We only read the first list element
	$child_seq = self::getXmpFirstNodeByName($node, 'rdf:Seq');
	if($child_seq !== false) {
	  $subchild = self::getXmpFirstNodeByName($child_seq, 'rdf:li');
	  if($subchild === false)
		throw new Exception(_('Cannot find')." 'rdf:li' "._('child of')." 'rdf:Seq' "._('node'),
							Exception::DATA_FORMAT_ERROR, $name);
	  return (string)$subchild->nodeValue;
	}

	// Return value of node
	return (string)$node->nodeValue;
  }

  /**
   * Get array of Bag node values
   *
   * @access protected
   * @param  string           $ns   Name space
   * @param  string $name Node name, without prefix
   * @return array|false  Array of node values, as rdf:li in rdf:Bag, or false if not found
   * @throw \Holiday\Metadata\Exception
   */
  protected function getXmpBagNS(string $ns, string $name): array|false
  {
	$node = self::getXmpFirstNodeByName($this->dom, "$ns:$name");
	if($node === false) return false;

	// Find rdf:Bag
	$child = self::getXmpFirstNodeByName($node, "rdf:Bag");
	if($child === false)
	  throw new Exception(_('Cannot find')." 'rdf:Bag' "._('node'), Exception::DATA_FORMAT_ERROR, $name);

	// Find all rdf:li elements
	$result = array();
	$grandchildren = $child->getElementsByTagName('li');
	foreach($grandchildren as $grandchild) {
	  if($grandchild->prefix === 'rdf' && !empty($grandchild->nodeValue))
		$result[] = $grandchild->nodeValue;
	}
	return !empty($result) ? $result : false;
  }

  /**
   * Set Attribute (removing identically named nodes) and updating values in other namespaces
   *
   * @access protected
   * @param  string           $ns   Name space
   * @param  string           $name Node name, including prefix
   * @param  string|int|false $data Node value
   * @param  bool             $pdate_only Only update value, do not create new nodes
   * @throw \Holiday\Metadata\Exception
   */

  protected function setXmpTextNS(string $ns, string $name, string|int|false $data, bool $update_only = false): void
  {
	$name = "$ns:$name";

	// Check if $name is an attribute
	$descs = self::getXmpAllNodeByName($this->dom, Xmp::DESCRIPTION);
	
	if($descs === false)
	  throw new Exception(_('Cannot find')." 'rdf:Description' "._('node'),  Exception::DATA_FORMAT_ERROR);

	$att_found = false;
	foreach($descs as $desc) {
	  // Check that we are in the correct namespace
	  if($desc->hasAttribute("xmlns:$ns")) {
		if($desc->hasAttribute($name)) {
		  $att_found = true;
		  
		  // Update or delete attribute
		  $status = $data !== false ? $desc->setAttribute($name, (string)$data) : $desc->removeAttribute($name);
		  if($status === false)
			throw new Exception(_('Error').($data === false ? _('deleting') : _('updating'))._('attribute'),
								Exception::INTERNAL_ERROR, $name);
		  
		  // Delete any node with the same name
		  do {
			$node = self::getXmpFirstNodeByName($this->dom, $name);
			if($node === false) break;
			$node->parentNode->removeChild($node);
		  }
		  while(true);
		}
	  }
	}
	
	if(!$att_found) {
	  // Check if node eixts
	  $node = self::getXmpFirstNodeByName($this->dom, $name);
	  if($node !== false) {
		// Update node value
		if($data === false) {
		  $node->parentNode->removeChild($node);
		}
		else {
		  $node->nodeValue = (string)$data;
		}
		
	  }
	  else {
		if($data !== false && !$update_only){
		  // Search for rdf:Description in the relevant name space to add attribute to
		  $status = false;
		  foreach($descs as $desc) {
			if($desc->hasAttribute("xmlns:$ns")) {
			  $status = $desc->setAttribute($name, (string)$data);
			}
		  }
		  if($status === false)
			throw new Exception(_('Error creating new attribute'), Exception::INTERNAL_ERROR, $name);
		}
 	  }
	}
	
	// Check if there exist alternate nodes with the same name
	list($prefix, $suffix) = explode(':', $name);
	$nodes = $this->dom->getElementsByTagName($suffix);
	foreach($nodes as $node) {
	  if($node->prefix !== $prefix) {
		if($data === false) {
		  $node->parentNode->removeChild($node);
		}
		else {
		  $node->nodeValue = (string)$data;
		}
	  }
	}
  }

  /**
   * Set values in a rdf:li of rd:f$tag children of node $node
   *
   * @access protected
   * @param  string                  $tag        Tag identifier of child node
   * @param  string                  $name       Node name, with prefix
   * @param  array|string|int|false  $data       Node value(s)
   * @param  bool                    $lang       Set xml:lang language attribute to x-default
   * @param  bool                    $pdate_only Only update value, do not create new nodes
   * @throw \Holiday\Metadata\Exception
   */
  protected function setXmpLiNS(string $tag, string $ns, string $name, array|string|int|false $data,
								bool $lang = false, bool $update_only = false): void
  {
	$name = "$ns:$name";

	$node = self::getXmpFirstNodeByName($this->dom, $name);
	if($data === false) {
	  if($node !== false) $this->deleteXmpChildren($node->parentNode);
	  return;
	}

	// If node does not exist and we update only, then exit
	if($node === false && $update_only) return;
	
	// If node does not exists, create a new one
	if($node === false) {
	  // Check if $name is an attribute
	  $descs = self::getXmpAllNodeByName($this->dom, Xmp::DESCRIPTION);
	  $root = false;
	  foreach($descs as $desc) {
		if($desc->hasAttribute("xmlns:$ns")) {
		  $root = $desc;
		}
	  }
	  if($root === false)
		throw new Exception(_('Cannot find')." 'rdf:Description' "._('node'), Exception::DATA_FORMAT_ERROR, $ns);
	  
	  $new_child = $this->dom->createElement($name);
	  $node = $root->appendChild($new_child);
	}
	
	// Delete all sub-nodes
	$this->deleteXmpChildren($node);
	
	// Add new tag rdf:$tag
	$new_child = $this->dom->createElement("rdf:$tag");
	$child = $node->appendChild($new_child);
	if(is_array($data)) {
	  foreach($data as $value) {
		$new_child = $this->dom->createElement('rdf:li');
	  if($lang) $new_child->setAttribute('xml:lang', 'x-default');
		$grandchild = $child->appendChild($new_child);
		$grandchild->nodeValue = $value;
	  }
	}
	else {
	  $new_child = $this->dom->createElement('rdf:li');
	  if($lang) $new_child->setAttribute('xml:lang', 'x-default');
	  $grandchild = $child->appendChild($new_child);
	  $grandchild->nodeValue = $data;
	}
  }

  /**
   * Delete all child nodes of a node
   *
   * @access protected
   * @param  \DOMDocument|\DOMElement|\DOMNode $dom Node
   * @throw \Holiday\Metadata\Exception
   */
  protected function deleteXmpChildren(\DOMDocument|\DOMElement|\DOMNode $node): void
  {
	while($node->hasChildNodes()) {
	  $node->removeChild($node->firstChild);
	}
  }

  /**
   * Return if a given node contains a given attribure
   *
   * @access protected
   * @param  string $name     Node name, including prefix
   * @param  string $att_name Name of the attribute to search, including prefix
   * @return bool   If node includes attribute
   * @throw \Holiday\Metadata\Exception
   */
  protected static function existXmpAttribute(\DOMDocument|\DOMElement|\DOMNode $dom,
											  string $name, string $att_name): bool
  {
	// Search node with name $name
	list($prefix, $name) = explode(':', $name, 2);
	$nodes = $dom->getElementsByTagName($name);
	foreach($nodes as $node) {
	  if($node->prefix === $prefix && $node->hasAttribute($att_name)) return true;
	}
	return false;
  }
  
  /**
   * Return the first element of a named element, ensuring correct prefix
   *
   * @access protected
   * @param  string $name   Element name (with ot without prefix)
   * return \DOMElement|false First node matching the name, including prefix, or false
   */
  protected static function getXmpFirstNodeByName(\DOMDocument|\DOMElement|\DOMNode $dom,
												  string $name): \DOMElement|false
  {
	$prefix = '';
	if(strpos($name, ':') !== false) list($prefix, $name) = explode(':', $name, 2);
	$childs = $dom->getElementsByTagName($name);
	foreach($childs as $child) {
	  if(empty($prefix) || $child->prefix === $prefix) return $child;
	}
	return false;
  }

  /**
   * Return an array of all elements of a named element, ensuring correct prefix
   *
   * @access protected
   * @param  string $name   Element name (with ot without prefix)
   * return array|false   Array of nodes nodes matching the name, including prefix, or false
   */
  protected static function getXmpAllNodeByName(\DOMDocument|\DOMElement|\DOMNode $dom, string $name): array|false
  {
	$result = array();
	$prefix = '';
	if(strpos($name, ':') !== false) list($prefix, $name) = explode(':', $name, 2);
	$childs = $dom->getElementsByTagName($name);
	foreach($childs as $child) {
	  if(empty($prefix) || $child->prefix === $prefix) $result[] = $child;
	}
	return empty($result) ? false : $result;
  }

  /**
   * Validate XMP document, i.e., ensure that is has the appropriate structure and rdf:Documents entries with
   * the referenced namespaces. If no DOM document exists, a new one is created from scratch
   *
   * @access protected
   * @param  array $ns_ary Array of all namespaces that must exist
   */
  protected function validateXmpDocument(array $ns_ary): void
  {
	// Find xmpmeta root node
	$mroot = self::getXmpFirstNodeByName($this->dom, 'x:xmpmeta');
	if($mroot === false) {
	  $elt = $this->dom->createElement('x:xmpmeta');
	  $mroot = $this->dom->appendChild($elt);
	  $mroot->setAttribute('xmlns:x', 'adobe:ns:meta/');
	  $mroot->setAttribute('x:xmptk', 'XMP Core 5.6.0');
	}
	
	// Check that DOM has a rdf:RDF node
	$root = self::getXmpFirstNodeByName($this->dom, 'rdf:RDF');
	if($root === false) {
	  $elt = $this->dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:RDF');
	  $root = $mroot->appendChild($elt);
	}
	
	// For each namespace not found, add a rdf:Document section xmlns:$ns="$uri"
	foreach($ns_ary as $ns => $uri) {
	  $this->setXmpNamespace($ns, $uri);
	}
	
	// Re-load to ensure namespaces are recognized
	$status = $this->dom->loadXML($this->dom->saveXML());
	if($status === false)
	  throw new Exception(_('Internal error during XML re-validation'), Exception::INTERNAL_ERROR);
	echo str_replace("><", ">\n<", $this->dom->saveXML());
  }
}
