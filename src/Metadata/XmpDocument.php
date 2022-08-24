<?php
  /**
   * XmpDocument.php - Functions for reading and writing XMP specific data
   * 
   * @package   Holiday\Metadata
   * @version   1.2
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/mit MIT
   *
   * @see       https://www.npes.org/pdf/xmpspecification-Jun05.pdf
   * @see       https://developer.adobe.com/xmp/docs/XMPSpecifications/
   */

namespace Holiday\Metadata;
use Holiday\Metadata;

class XmpDocument {

  /** Namespaces that may contain IPTC Code Metadata 1.3 (in descending order of preference) */
  public const NS_IPTC4XMPCORE = 'Iptc4xmpCore';
  public const NS_DC = 'dc';
  public const NS_AUX = 'aux';
  public const NS_XMP = 'xmp';
  public const NS_PHOTOSHOP = 'photoshop';
  public const NS_PHOTOMECHANIC = 'photomechanic';

  /** Languages (non exchaustive) */
  private const LANG_ALL = 'x-all';         /** All languages */
  private const LANG_DEFAULT = 'x-default'; /** Default language: English */
  
  /** Private variables */
  private array $nsPriorityAry;             /** Prioritized array of name spaces for core metadata */

  /**
   * Constructor
   *
   * @param DOMDocument DOM of XMP data
   */
  public function __construct(private \DOMDocument $dom)
  {
	$this->nsPriorityAry = [self::NS_IPTC4XMPCORE, self::NS_DC, self::NS_AUX, self::NS_XMP, self::NS_PHOTOSHOP,
							self::NS_PHOTOMECHANIC];
	
	// All namespaces supported by default, others may be added before use using 'setXmpNamespace'
	$all_ns = ['Iptc4xmpCore' => 'http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/',
			   'aux' => 'http://ns.adobe.com/exif/1.0/aux/',
			   'dc' => 'http://purl.org/dc/elements/1.1/',
			   'xmp' => 'http://ns.adobe.com/xap/1.0/',
			   'photoshop' => 'http://ns.adobe.com/photoshop/1.0/',
			   'photomechanic' => 'http://ns.camerabits.com/photomechanic/1.0/',
			   'Iptc4xmpExt' => 'http://iptc.org/std/Iptc4xmpExt/2008-02-29/',
			   'GettyImagesGIFT' => 'http://xmp.gettyimages.com/gift/1.0/',
			   'exifEX' => 'http://cipa.jp/exif/1.0/',
			   'plus' => 'http://ns.useplus.org/ldf/xmp/1.0/',
			   'xmpMM' => 'http://ns.adobe.com/xap/1.0/mm/',
			   'xmpRights' => 'http://ns.adobe.com/xap/1.0/rights/'];
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
	$descs = self::getXmpAllNodeByName($this->dom, Xmp::DESCRIPTION, $name);
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
   * @param  string       $name Node name, including prefix
   * @param  string|false $lang Language of entry
   * @return string|false First node value found, or false, if not found
   * @throw \Holiday\Metadata\Exception
   */
  public function getXmpText(string $name, string|false $lang = false): string|false
  {
	if(!str_contains($name, ':'))
	  throw new Exception(_('Node name without prefix found'), Exception::INVALID_FIELD_ID, $name);

	[$prefix, $name] = explode(':', $name, 2);

	// Seach first in specified name space (if any)
	$result = $this->getXmpTextNS($prefix, $name, lang: $lang);
	if(is_array($result)) 
	  throw new Exception(_('Multiple language support not yet implemented'), Exception::NOT_IMPLEMENTED);
	if($result !== false) return $result;
	
	// Search in other name spaces according to priority (if core element)
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $prefix) {
		$result = $this->getXmpTextNS($prefix, $name, lang: $lang);
		if(is_array($result)) 
		  throw new Exception(_('Multiple language support not yet implemented'), Exception::NOT_IMPLEMENTED);
		if($result !== false) return $result;
	  }
	}
	return false;
  }

  /**
   * Get rdf:Alt value
   *
   * @param  string       $name Node name, including prefix
   * @param  string|false $lang Language of entry
   * @return array|false  First node value found, or false, if not found
   * @throw \Holiday\Metadata\Exception
   */
  public function getXmpLangAlt(string $name, string|false $lang = false): array|false
  {
	if(!str_contains($name, ':'))
	  throw new Exception(_('Node name without prefix found'), Exception::INVALID_FIELD_ID, $name);

	[$prefix, $name] = explode(':', $name, 2);

	// Seach first in specified name space (if any)
	$result = $this->getXmpLangAltNS($prefix, $name, lang: $lang);
	if($result !== false) return $result;
	
	// Search in other name spaces according to priority (if core element)
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $prefix) {
		$result = $this->getXmpLangAltNS($prefix, $name, lang: $lang);
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
	if(!str_contains($name, ':'))
	  throw new Exception(_('Node name without prefix found'), Exception::INVALID_FIELD_ID, $name);

	[$prefix, $name] = explode(':', $name, 2);

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
	if(!str_contains($name, ':'))
	  throw new Exception(_('Node name without prefix found'), Exception::INVALID_FIELD_ID, $name);

	[$prefix, $name] = explode(':', $name, 2);
	$this->setXmpTextNS($prefix, $name, $data, update_only: false);

	// Update nodes is associated namespaces
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $ns_prefix) {
		if($this->isXmpText("$ns_prefix:$name") && $ns_prefix !== $prefix)
		  $this->setXmpTextNS($prefix, $name, $data, update_only: true);
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

	[$prefix, $name] = explode(':', $name, 2);
	$this->setXmpLiNS('Seq', $prefix, $name, $data, lang: false, update_only: false);

	// Update nodes is associated namespaces
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $ns_prefix) {
		if($this->isXmpLi('Seq', "$ns_prefix:$name") && $ns_prefix !== $prefix)
		  $this->setXmpLiNS('Seq', $ns_prefix, $name, $data, lang: false, update_only: true);
	  }
	}
  }
  
  /**
   * Set/Update req:Alt tag entries and updating values in other namespaces
   *
   * @param string           $name Node name, including prefix
   * @param string|int|false $data Node value
   * @param string|false     $lang Language of entry
   * @throw \Holiday\Metadata\Exception
   */
  public function setXmpAlt(string $name, string|int|false $data, string|false $lang = false): void
  {
	if(self::existXmpAttribute($this->dom, Xmp::DESCRIPTION, $name))
	  throw new Exception(_('Cannot set')." 'rdf:Alt' "._('node value if an attribute with the same name exists'),
						  Exception::INVALID_FIELD_ID, $name);

	[$prefix, $name] = explode(':', $name, 2);
	$this->setXmpLiNS('Alt', $prefix, $name, $data, lang: $lang, update_only: false);

	// Update nodes is associated namespaces
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $ns_prefix) {
		if($this->isXmpLi('Alt', "$ns_prefix:$name") && $ns_prefix !== $prefix)
		  $this->setXmpLiNS('Alt', $ns_prefix, $name, $data, lang: $lang, update_only: true);
	  }
	}
  }

    /**
   * Set/Update req:Alt tag entries and updating values in other namespaces
   *
   * @param string      $name Node name, including prefix
   * @param array|false $data Array of node values indexed by language
   * @throw \Holiday\Metadata\Exception
   */
  public function setXmpLangAlt(string $name, array|false $data): void
  {
	if(self::existXmpAttribute($this->dom, Xmp::DESCRIPTION, $name))
	  throw new Exception(_('Cannot set')." 'rdf:Alt' "._('node value if an attribute with the same name exists'),
						  Exception::INVALID_FIELD_ID, $name);

	[$prefix, $name] = explode(':', $name, 2);
	$this->setXmpLiLangNS($prefix, $name, $data);
	
	// Update nodes is associated namespaces
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $ns_prefix) {
		if($this->isXmpLi('Alt', "$ns_prefix:$name") && $ns_prefix !== $prefix) 
		  $this->setXmpLiLangNS($ns_prefix, $name, $data);
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
	
	[$prefix, $name] = explode(':', $name, 2);
	$this->setXmpLiNS('Bag', $prefix, $name, $data, lang: false, update_only: false);
	
	// Update nodes is associated namespaces
	if(in_array($prefix, $this->nsPriorityAry)) {
	  foreach($this->nsPriorityAry as $ns_prefix) {
		if($this->isXmpLi('Bag', "$ns_prefix:$name") && $ns_prefix !== $prefix)
		  $this->setXmpLiNS('Bag', $ns_prefix, $name, $data, lang: false, update_only: true);
	  }
	}
  }
  
  /**
   * Add an element listing all languages found throughout the DOM
   */
  public function addLanguages(): void
  {
	$languages = $this->recFindLanguages($this->dom->documentElement);
	if(!empty($languages)) $this->setXmpBag('dc:language', $languages);
  }

  /**
   * Recursively traverse a DOM, finding any node that has an attribute 'xml:lang
   *
   * @access private
   * @param  \DOMElement $dom DOM Node
   * @return array      Array of languages found
   */
  private function recFindLanguages(\DOMElement $dom): array
  {
	$result = [];
	if($dom->hasAttribute('xml:lang')) {
	  $lang = $dom->getAttribute('xml:lang');
	  if($lang !== 'x-default') $result[$lang] = $lang;
	}
	if($dom->hasChildNodes()) {
	  foreach($dom->childNodes as $child) {
		if($child->nodeType == XML_ELEMENT_NODE)
		  $result = array_merge($result, $this->recFindLanguages($child));
	  }
	}
	return $result;
  }
  
  /**
   * Add a history entry indicating that the data has been updated (in xmpMM:History
   *
   * @param string $software Software name
   */
  public function updateHistory(string $software): void
  {
	// Serch for a rdf:Description element that references to the xmpMM namespace in which histories are saves
	$desc = self::getXmpFirstNodeByName($this->dom, Xmp::DESCRIPTION, 'xmpMM');
	if($desc === false)
	  throw new Exception(_('Internal error finding')." 'rdf:Description' "._('including')." 'xmpMM' ".
						  _('as namespaces'), Exception::INTERNAL_ERROR);

	// Ensure that rdf:Description has stEvt and stRef namespace attributes set
	if(!$desc->hasAttribute('xmlns:stEvt'))
	   $desc->setAttribute('xmlns:stEvt', 'http://ns.adobe.com/xap/1.0/sType/ResourceEvent#');
	if(!$desc->hasAttribute('xmlns:stRef'))
	   $desc->setAttribute('xmlns:stRef','http://ns.adobe.com/xap/1.0/sType/ResourceRef#');

	// Create new history entry, if none exists
	$history = self::getXmpFirstNodeByName($desc, Xmp::EDIT_HISTORY);
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
   * @param string|false  $lang Language of entry
   * @return string|array|false Node value or array, if $lang === LANG_ALL
   * @throw \Holiday\Metadata\Exception
   */
  protected function getXmpTextNS(string $ns, string $name, string|false $lang = false): string|array|false
  {
	// Search text as attribute of any rdf:Description node
	$descs = self::getXmpAllNodeByName($this->dom, Xmp::DESCRIPTION, $ns);
	if($descs === false)
	  throw new Exception(_('Cannot find')." 'rdf:Description' "._('node'),  Exception::DATA_FORMAT_ERROR);
	
	// Search for Name as Attribute
	if($lang === false) {
	  foreach($descs as $desc) {
		$result = $desc->getAttribute("$ns:$name");
		if(!empty($result)) return $result;
	  }
	}
	
	// Try find Name as Node rather than Attribute
	$node = self::getXmpFirstNodeByName($this->dom, "$ns:$name");
	if($node === false) return false;
	
	//Try rdf:Alt - We only read the first list element
	$lang_result = [];
	$child_alt = self::getXmpFirstNodeByName($node, 'rdf:Alt');
	if($child_alt !== false) {
	  $subchildren = self::getXmpAllNodeByName($child_alt, 'rdf:li');
	  if($subchildren === false) return false;
	  foreach($subchildren as $subchild) {
		if($lang === false) {
		  if($subchild->hasAttribute('xml:lang')) {
			if($subchild->getAttribute('xml:lang') === self::LANG_DEFAULT) 
			  return (string)$subchild->nodeValue;
		  }
		  else {
			return (string)$subchild->nodeValue;
		  }
		}
		elseif($lang === self::LANG_ALL) {
		  if($subchild->hasAttribute('xml:lang')) {
			$lang_result[$subchild->getAttribute('xml:lang')] = (string)$subchild->nodeValue;
		  }
		  else {
			if(!isset($lang_result[self::LANG_DEFAULT])) {
			  $lang_result[self::LANG_DEFAULT] = (string)$subchild->nodeValue;
			}
		  }
		}
		else {	
		  if($subchild->hasAttribute('xml:lang')) {
			if($subchild->getAttribute('xml:lang') === $lang) 
			  return (string)$subchild->nodeValue;
		  }
		  else {
			return (string)$subchild->nodeValue;
		  }
		}
	  }
	}
	return empty($lang_result) ? false : $lang_result;
  }
  
  /**
   * Get Attribute / Node / rdf:Seq / rdf:Alt value in specific name space
   *
   * @access protected
   * @param  string       $ns   Name space
   * @param  string       $name Node name, without prefix
   * @param  string|false $lang Language to retrieve, or all
   * @return array|false  Array of nodes, indexed by language
   * @throw \Holiday\Metadata\Exception
   */
  protected function getXmpLangAltNS(string $ns, string $name, string|false $lang): array|false
  {
	// Search text as attribute of any rdf:Description node
	$descs = self::getXmpAllNodeByName($this->dom, Xmp::DESCRIPTION, $ns);
	if($descs === false)
	  throw new Exception(_('Cannot find')." 'rdf:Description' "._('node'),  Exception::DATA_FORMAT_ERROR);
	
	// Search for Name as Attribute
	foreach($descs as $desc) {
	  $result = $desc->getAttribute("$ns:$name");
	  if(!empty($result))
		throw new Exception(_('Found attribute with the same name as')." 'rdf:Alt' "._('element'),
							Exception::DATA_FORMAT_ERROR);
	}
	
	// Try find Name as Node rather than Attribute
	$node = self::getXmpFirstNodeByName($this->dom, "$ns:$name");
	if($node === false) return false;
	
	// Read rdf:Alt - We only read the first list element
	$lang_result = [];
	$child_alt = self::getXmpFirstNodeByName($node, 'rdf:Alt');
	if($child_alt !== false) {
	  $subchildren = self::getXmpAllNodeByName($child_alt, 'rdf:li');
	  if($subchildren === false)
		throw new Exception(_('Cannot find')." 'rdf:li' "._('child of')." 'rdf:Alt' "._('node in lang'),
							Exception::DATA_FORMAT_ERROR, $name);
	  foreach($subchildren as $subchild) {
		if($subchild->hasAttribute('xml:lang')) {
		  if($subchild->getAttribute('xml:lang') === $lang || $lang === self::LANG_ALL)
			$lang_result[$subchild->getAttribute('xml:lang')] = (string)$subchild->nodeValue;
		}
		else {
		  if(!isset($lang_result[self::LANG_DEFAULT]))
			$lang_result[self::LANG_DEFAULT] = (string)$subchild->nodeValue;
		}
	  }
	}
	return empty($lang_result) ? false : $lang_result;
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
	$result = [];
	$grandchildren = $child->getElementsByTagName('li');
	foreach($grandchildren as $grandchild) {
	  if($grandchild->prefix === 'rdf' && !empty($grandchild->nodeValue)) $result[] = $grandchild->nodeValue;
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
	$descs = self::getXmpAllNodeByName($this->dom, Xmp::DESCRIPTION, $ns);
	
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
		if($data === false)
		  $node->parentNode->removeChild($node);
		else
		  $node->nodeValue = (string)$data;
	  }
	  else {
		if($data !== false && !$update_only){
		  // Search for rdf:Description in the relevant name space to add attribute to
		  $status = false;
		  foreach($descs as $desc) {
			if($desc->hasAttribute("xmlns:$ns"))
			  $status = $desc->setAttribute($name, (string)$data);
		  }
		  if($status === false)
			throw new Exception(_('Error creating new attribute'), Exception::INTERNAL_ERROR, $name);
		}
 	  }
	}
	
	// Check if there exist alternate nodes with the same name
	[$prefix, $suffix] = explode(':', $name);
	$nodes = $this->dom->getElementsByTagName($suffix);
	foreach($nodes as $node) {
	  if($node->prefix !== $prefix) {
		if($data === false)
		  $node->parentNode->removeChild($node);
		else
		  $node->nodeValue = (string)$data;
	  }
	}
  }

  /**
   * Set values in a rdf:li of rd:f$tag children of node $node
   *
   * @access protected
   * @param  string                 $tag        Tag identifier of child node
   * @param  string                 $ns         Node prefix
   * @param  string                 $name       Node name, without prefix
   * @param  array|string|int|false $data       Node value(s)
   * @param  string|false           $lang       Set xml:lang language attribute to x-default
   * @param  bool                   $pdate_only Only update value, do not create new nodes
   * @throw \Holiday\Metadata\Exception
   */
  protected function setXmpLiNS(string $tag, string $ns, string $name, array|string|int|false $data,
								string|false $lang = false, bool $update_only = false): void
  {
	// Find node
	$name = "$ns:$name";
	$node = self::getXmpFirstNodeByName($this->dom, $name);
	
	// If node does not exist and we update only, then exit
	if($node === false && $update_only) return;
	
	// If node does not exists and we do not only update, create a new one
	if($node === false) {
	  // Check if $name is an attribute
	  $root = self::getXmpFirstNodeByName($this->dom, Xmp::DESCRIPTION, $ns);
	  if($root === false)
		throw new Exception(_('Cannot find')." 'rdf:Description' "._('node'), Exception::DATA_FORMAT_ERROR, $ns);
	  
	  $new_child = $this->dom->createElement($name);
	  $node = $root->appendChild($new_child);
	}

	// Check if $node has children that are not of tye rdf:$tag
	if($node->childNodes->count() !== 0) {
	  $remove_children = [];
	  foreach($node->childNodes as $child) {
		if($child->prefix === 'rdf' && $child->nodeName !== "rdf:$tag") {
		  throw new Exception(_('Incorrect element tag found'), Exception::DATA_FORMAT_ERROR, $child->nodeName);
		}
		$remove_children[] = $child;
	  }
	  if(!empty($remove_children)) {
		foreach($remove_children as $remove_child) $node->removeChild($remove_child);
	  }
	}

	// Check if $node has no children of type rdf:$tag
	$rdf_tag = self::getXmpFirstNodeByName($node, "rdf:$tag");
	if($rdf_tag === false) {
	  $new_child = $this->dom->createElement("rdf:$tag");
	  $rdf_tag = $node->appendChild($new_child);
	}
	
	// Delete all sub-nodes of $rdf_tag that are in the specified language (if any language specified)
	$all_rdf_li = self::getXmpAllNodeByName($rdf_tag, 'rdf:li');
	if($all_rdf_li !== false) {
	  $remove_children = [];
	  foreach($all_rdf_li as $rdf_li) {
		if($lang === false || $rdf_li->getAttribute('xml:lang') === $lang)
		  $remove_children[] = $rdf_li;
	  }
	  if(!empty($remove_children)) {
		foreach($remove_children as $remove_child) $rdf_tag->removeChild($remove_child);
	  }
	}
	
	// Add new rdf:li tags
	if($data !== false) {
	  if(is_array($data)) {
		foreach($data as $value) {
		  $new_child = $this->dom->createElement('rdf:li');
		  if($lang !== false) $new_child->setAttribute('xml:lang', $lang);
		  $rdf_li = $rdf_tag->appendChild($new_child);
		  $rdf_li->nodeValue = $value;
		}
	  }
	  else {
		$new_child = $this->dom->createElement('rdf:li');
		if($lang !== false) $new_child->setAttribute('xml:lang', $lang);
		$rdf_li = $rdf_tag->appendChild($new_child);
		$rdf_li->nodeValue = $data;
	  }
	}
  }
  
  /**
   * Set values in a rdf:li of rd:f$tag children of node $node
   *
   * @access protected
   * @param  string      $ns   Node prefix
   * @param  string      $name Node name, without prefix
   * @param  array|false $data Array of node values, indexed by language
   * @throw \Holiday\Metadata\Exception
   */
  protected function setXmpLiLangNS(string $ns, string $name, array|false $data): void
  {
	// Find node
	$name = "$ns:$name";
	$node = self::getXmpFirstNodeByName($this->dom, $name);
	
	// If node does not exists, create a new one
	if($node === false) {
	  // Check if $name is an attribute
	  $root = self::getXmpFirstNodeByName($this->dom, Xmp::DESCRIPTION, $ns);
	  if($root === false)
		throw new Exception(_('Cannot find')." 'rdf:Description' "._('node'), Exception::DATA_FORMAT_ERROR, $ns);
	  
	  $new_child = $this->dom->createElement($name);
	  $node = $root->appendChild($new_child);
	}

	// Check if $node has children that are not of tye rdf:Alt
	if($node->childNodes->count() !== 0) {
	  $remove_children = [];
	  foreach($node->childNodes as $child) {
		if($child->prefix === 'rdf' && $child->nodeName !== "rdf:Alt")
		  throw new Exception(_('Incorrect element tag found'), Exception::DATA_FORMAT_ERROR, $child->nodeName);
		$remove_children[] = $child;
	  }
	  if(!empty($remove_children)) {
		foreach($remove_children as $remove_child) $node->removeChild($remove_child);
	  }
	}

	// Check if $node has no children of type rdf:Alt
	$rdf_tag = self::getXmpFirstNodeByName($node, "rdf:Alt");
	if($rdf_tag === false) {
	  $new_child = $this->dom->createElement("rdf:Alt");
	  $rdf_tag = $node->appendChild($new_child);
	}
	
	// Delete all sub-nodes of $rdf_tag
	$all_rdf_li = self::getXmpAllNodeByName($rdf_tag, 'rdf:li');
	if($all_rdf_li !== false) {
	  $remove_children = [];
	  foreach($all_rdf_li as $rdf_li) $remove_children[] = $rdf_li;
	  if(!empty($remove_children)) {
		foreach($remove_children as $remove_child) $rdf_tag->removeChild($remove_child);
	  }
	}
	
	// Add new rdf:li tags
	if($data !== false) {
	  foreach($data as $lang => $value) {
		$new_child = $this->dom->createElement('rdf:li');
		$new_child->setAttribute('xml:lang', $lang);
		$rdf_li = $rdf_tag->appendChild($new_child);
		$rdf_li->nodeValue = $value;
	  }
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
	[$prefix, $name] = explode(':', $name, 2);
	$nodes = $dom->getElementsByTagName($name);
	foreach($nodes as $node) {
	  if($node->prefix === $prefix && $node->hasAttribute($att_name)) return true;
	}
	return false;
  }
  
  /**
   * Return the first element of a named element, ensuring correct prefix (and optionally the appropriate namespace)
   *
   * @access protected
   * @param  string $name   Element name (with ot without prefix)
   * @param  string $ns     Optionally, check that $name includes xmlns:$ns attribute
   * return \DOMElement|false First node matching the name, including prefix, or false
   */
  protected static function getXmpFirstNodeByName(\DOMDocument|\DOMElement|\DOMNode $dom,
												  string $name, string $ns = ''): \DOMElement|false
  {
	$prefix = '';
	if(str_contains($name, ':')) [$prefix, $name] = explode(':', $name, 2);
	if(str_contains($ns, ':')) [$ns, $dummy] = explode(':', $ns, 2);
	$childs = $dom->getElementsByTagName($name);
	foreach($childs as $child) {
	  if(empty($prefix) || $child->prefix === $prefix) {
		if(empty($ns) || $child->hasAttribute("xmlns:$ns"))	return $child;
	  }
	}
	return false;
  }

  /**
   * Return an array of all elements of a named element, ensuring correct prefix
   *
   * @access protected
   * @param  string $name Element name (with ot without prefix,  and optionally the appropriate namespace)
   * @param  string $ns     Optionally, check that $name includes xmlns:$ns attribute
   * return array|false   Array of nodes nodes matching the name, including prefix, or false
   */
  protected static function getXmpAllNodeByName(\DOMDocument|\DOMElement|\DOMNode $dom, string $name,
												string $ns = ''): array|false
  {
	$result = [];
	$prefix = '';
	if(str_contains($name, ':')) [$prefix, $name] = explode(':', $name, 2);
	if(str_contains($ns, ':')) [$ns, $dummy] = explode(':', $ns, 2);
	$childs = $dom->getElementsByTagName($name);
	foreach($childs as $child) {
	  if(empty($prefix) || $child->prefix === $prefix)  {
		if(empty($ns) || $child->hasAttribute("xmlns:$ns"))	$result[] = $child;
	  }
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
	  $elt = $this->dom->createElementNS('adobe:ns:meta/', 'x:xmpmeta');
	  $mroot = $this->dom->appendChild($elt);
	  $mroot->setAttribute('x:xmptk', 'XMP Core 5.6.0');
	}
	
	// Check that DOM has a rdf:RDF node
	$root = self::getXmpFirstNodeByName($this->dom, 'rdf:RDF');
	if($root === false) {
	  $elt = $this->dom->createElementNS('http://www.w3.org/1999/02/22-rdf-syntax-ns#', 'rdf:RDF');
	  $root = $mroot->appendChild($elt);
	}
	
	// For each namespace not found, add a rdf:Document section xmlns:$ns="$uri"
	foreach($ns_ary as $ns => $uri) $this->setXmpNamespace($ns, $uri);
	
	// Re-load to ensure namespaces are recognized
	// -- Disable warning messages from loadXML only
	$old_error_reporting = error_reporting(error_reporting() & ~E_WARNING);
	$status = $this->dom->loadXML($this->dom->saveXML());
	error_reporting($old_error_reporting);
	if($status === false) throw new Exception(_('Internal error during XML re-validation'), Exception::INTERNAL_ERROR);
  }
}
