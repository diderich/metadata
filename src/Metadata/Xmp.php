<?php
  /**
   * Xmp.php - Encode and decode XMP data from JPG segment APP1
   * 
   * @package   Holiday\Metadata
   * @version   1.2
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/mit MIT
   *
   * @see       https://exiftool.org/TagNames/XMP.html
   */

namespace Holiday\Metadata;
use Holiday\Metadata;

class Xmp {

  public const DESCRIPTION = 'rdf:Description';              /** Main element */

  // - IPTC Core Metadata 1.3 (may by in any of the namespaces: aux, dc, Iptc4xmpCode, photoshop, xmp)
  public const AUTHOR = "dc:creator";                        /** Seq: Creator (name of photographer) */
  public const CAPTION = "dc:description";                   /** Alt Lang: Description/Caption */
  public const CITY = "Iptc4xmpCore:City";                   /** Text: City */
  public const COPYRIGHT = "dc:rights";                      /** Alt Lang: Copyright notice */
  public const COUNTRY = "Iptc4xmpCore:CountryName";         /** Text: Country name */
  public const COUNTRY_CODE = "Iptc4xmpCore:CountryCode";    /** Text: ISO country code*/
  public const CREATED_DATETIME = "xmp:CreatedDate";         /** Read only: Date and time YYYY-MM-DD HH:MM:SS+HH:MM */
  public const CREDIT = "photoshop:Credit";                  /** Text: Credit Line */
  public const GENRE = "Iptc4xmpCore:IntellectualGenre";     /** Text: Genre */
  public const INSTRUCTIONS = "photoshop:Instructions";      /** Text: Instructions */
  public const KEYWORDS = "dc:subject";                      /** Bag: Keywords */
  public const LOCATION = "Iptc4xmpCore:Location";           /** Text: Location */
  public const OBJECT = "dc:title";                          /** Alt Lang: Object name (Title)*/
  public const SCENES = "Iptc4xmpCore:Scene";                /** Bag: Scene codes*/
  public const SOURCE = "dc:source";                         /** Text: Source */
  public const STATE = "Iptc4xmpCore:ProvinceState";         /** Text: Providence/State */
  public const SUBJECT_CODE = "Iptc4xmpCore:SubjectCode";    /** Bag: Subject code */
  public const USAGE_TERMS = "xmpRights:UsageTerms";         /** Alt Lang: Rights Usage Terms */
  public const PM_EDIT_STATUS = "photomechanic:EditStatus";  /** Text: Edit status */
  public const PS_AUTHOR_TITLE = "photoshop:AuthorsPosition";/** Text: Creator's job title */
  public const PS_CAPTION_WRITER = "photoshop:CaptionWriter";/** Text: Caption Writer */
  public const PS_CATEGORY = "photoshop:Category";           /** Text: Category */
  public const PS_CITY = "photoshop:City";                   /** - Text: City */
  public const PS_COUNTRY = "photoshop:Country";             /** - Text: Country name */
  public const PS_CREATED_DATETIME = "photoshop:DateCreated";/** - Read only: Date and time YYYY-MM-DD HH:MM:SS+HH:MM */
  public const PS_HEADLINE = "photoshop:Headline";           /** - Text: Headline */
  public const PS_PRIORITY = "photoshop:Urgency";            /** Text/Int: Urgency */
  public const PS_SOURCE = "photoshop:Source";               /** - Text: Source */
  public const PS_STATE = "photoshop:State";                 /** - Text: Providence/State */
  public const PS_SUPP_CATEGORY = "photoshop:SupplementalCategories"; /** Bag: Supplemental categories */
  public const PS_TRANSFER_REF = "photoshop:TransmissionReference"; /** Text: Transmission reference */
  
  // - IPTC Extension Metadata 1.6
  public const EVENT = "Iptc4xmpExt:Event";                  /** Alt Lang: Event identifier */
  public const ORG_CODE = "Iptc4xmpExt:OrganisationInImageCode"; /** Bag: Code of Organization in image */
  public const ORG_NAME = "Iptc4xmpExt:OrganisationInImageName"; /** Bag: Name of Organization in image */
  public const PERSON = "Iptc4xmpExt:PersonInImage";         /** Bag: Person shown in image*/
  public const RATING = "xmp:Rating";                        /** Text: Numeric image rating, -1 (rejected), 0..5 */

  // - Camera specific records (although aux: has been dropped in 2021 in favor of exifEX:, it is still used)
  public const CAMERA_SERIAL = "aux:SerialNumber";           /** Text: Camera serial number */
  public const LENS_MODEL = "aux:Lens";                      /** Text: Lens description */
  public const LENS_SERIAL = "aux:LensSerialNumber";         /** Text: Lens serial number */
  public const COLOR_SPACE = "photoshop:ICCProfile";         /** Text: Color Profile */
  public const COLOR_MODE = "photoshop:ColorMode";           /** Text: Color Mode */

  // History specification
  public const EDIT_HISTORY = 'xmpMM:History';               /** Seq: ResourceEvebt */
  
  public const XMP_TYPE_PRV = 'APP0';
  public const XMP_TYPE = 'APP1';
  public const XMP_HEADER = "http://ns.adobe.com/xap/1.0/\x00";
  public const XMP_HEADER_LEN = 29;
  public const XMP_TYPE_TAG = 0xE1;
  
  private const XMP_XML_HEADER = '<?xml version="1.0"?>';
  private const XMP_XML_HEADER_LEN = 21;

  /**
   * Decode XMP data 
   *
   * @param  string $segment    XMP data as string
   * @return XmpDocument|false  DOM document of Xmp data, or false, if not data was found
   * @throw  \Holiday\Metadata\Exception
  */
  public static function decode(string $segment): XmpDocument|false
  {
	// Encode XMP data as XML / DOMDocument
	$dom = new \DOMDocument('1.0', 'UTF-8');

	if(empty($segment)) return new XmpDocument($dom);
	
	// Extract data from XMP block
	$xmp_block = substr($segment, Xmp::XMP_HEADER_LEN);
	$xmp_start = strpos($xmp_block, '<x:xmpmeta');
	if($xmp_start == false) throw new Exception(_('XMP metadata start tag not found'), Exception::DATA_FORMAT_ERROR);
	$xmp_end = strpos($xmp_block, '</x:xmpmeta>');
	if($xmp_end === false) throw new Exception(_('XMP metadata end tag not found'), Exception::DATA_FORMAT_ERROR);
	$xmp_data =substr($xmp_block, $xmp_start, $xmp_end - $xmp_start + 12);

	// -- Disable warning messages from loadXML only
	$old_error_reporting = error_reporting(error_reporting() & ~E_WARNING);
	$xml_status = $dom->loadXML($xmp_data);
	error_reporting($old_error_reporting);
	if($xml_status === false) throw new Exception(_('Error decoding XMP metdata as XML'), Exception::DATA_FORMAT_ERROR);
	return new XmpDocument($dom);
  }

  /***
   * Encode XMP data into a string
   *
   * @return XmpDocument|false $xmp_dom  DOM document of Xmp data, or false, if not data was found
   * @return string            XMP as string
   * @throw  \Holiday\Metadata\Exception
   */
  public static function encode(XmpDocument|false $xmp_dom): string|false
  {
	if($xmp_dom === false) return false;

	// Encode XML document
	$xmp_data = $xmp_dom->getDom()->saveXML();
	if($xmp_data === false) throw new Exception(_('Error encoding XMP metdata as XML'), Exception::DATA_FORMAT_ERROR);
	$xmp_data = html_entity_decode($xmp_data, ENT_NOQUOTES, 'UTF-8');
	
	// Build XMP block
	// - Remove xml header, if it exists
	if(strncmp($xmp_data, self::XMP_XML_HEADER, self::XMP_XML_HEADER_LEN) === 0)
	  $xmp_data = substr($xmp_data, self::XMP_XML_HEADER_LEN);
	if($xmp_data[0] === "\n") $xmp_data = substr($xmp_data, 1);

	// Build XMP block
	$xmp_block = "<?xpacket begin='\xef\xbb\xbf' id='W5M0MpCehiHzreSzNTczkc9d'?>\n";
	$xmp_block .= $xmp_data;
	  
	// Add trailing space (XMP standard recommends to add 2-4k of white space at the end)
	$xmp_block .= str_repeat(str_repeat(' ', 80)."\n", 30);
	$xmp_block .= "<?xpacket end='w'?>";

	return $xmp_block;
  }

}
