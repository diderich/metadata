<?php
  /**
   * Metadata.php - Image file metadata handing
   * 
   * @project   Holiday\Metadata
   * @version   1.1
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/mit MIT
   *
   * @see       https://exiftool.org/TagNames/EXIF.html
   */

namespace Holiday;

use \Holiday\Metadata\Iptc;
use \Holiday\Metadata\Xmp;
use \Holiday\Metadata\Exif;
use \Holiday\Metadata\XmpDocument;
use \Holiday\Metadata\Exception;

class Metadata {
  
  const VERSION = '1.1.0';

  /** Fielt types */
  const TYPE_INVALID = 0;
  const TYPE_STR = 1;
  const TYPE_INT = 2;
  const TYPE_FLOAT = 3;
  const TYPE_ARY = 4;

  /** File specific fields: read only */
  const FILE_NAME             = 001;            /** String: Filename */
  const FILE_EXT              = 002;            /** String: File extension */
  const FILE_SIZE             = 003;            /** Int: File size */
  const FILE_DATE             = 004;            /** Int: Last modification date */

  /** IPTC/XMP fields: read/write */
  const FIELD_ID_WRITE_FIRST  = 100;            /** First field identifier that can be modified */
  const FIELD_ID_WRITE_LAST   = 132;            /** Last field identifier that can be modified */
  
  const AUTHOR                = 101;            /** String: Creator (name of photographer) */
  const PHOTOGRAPHER          = 101;            /** - String: Creator (name of photographer) */
  const AUTHOR_TITLE          = 102;            /** String: Creator's job title */
  const PHOTOGRAPHER_TITLE    = 102;            /** - String: Creator's job title */
  const CAPTION               = 103;            /** String:  Description/Caption */
  const CAPTION_WRITER        = 104;            /** String: Description writer */
  const CATEGORY              = 105;            /** String: Category - Max 3 characters */
  const CITY                  = 106;            /** String: City */
  const COPYRIGHT             = 107;            /** String: Copyright notice */
  const COUNTRY               = 108;            /** String: Country name */
  const COUNTRY_CODE          = 109;            /** String: ISO country code*/
  const CREDIT                = 110;            /** String: Credit Line */
  const EDIT_STATUS           = 111;            /** String: Edit Status - Max 64 characters */
  const EVENT                 = 112;            /** String: Event identifier */
  const GENRE                 = 113;            /** Array: Genre */
  const HEADLINE              = 114;            /** String: Headline */
  const INSTRUCTIONS          = 115;            /** String: Instructions */
  const KEYWORDS              = 116;            /** Array: Keywords */
  const LOCATION              = 117;            /** String: Location */
  const OBJECT                = 118;            /** String: Object name (Title)*/
  const ORG_CODE              = 119;            /** Array: Code of Organization in image */
  const NAT                   = 119;            /**  - Array: Nationalities */
  const ORG_NAME              = 120;            /** Array: Name of Organization in image */
  const ORG                   = 120;            /**  - Array: Organizations/Teams in image */
  const PERSON                = 121;            /** Array: Person shown in image */
  const PEOPLE                = 121;            /** - Array: Person shown in image */
  const PERSONALITY           = 121;            /** - Array: Person shown in image (Getty terminology) */
  const PRIORITY              = 122;            /** Int: Urgency - 1 numeric character */
  const RATING                = 123;            /** Int: Numeric image rating, -1 (rejected), 0..5 */
  const SCENES                = 124;            /** Array: Scene codes*/
  const SOURCE                = 125;            /** String: Source */
  const STATE                 = 126;            /** String: Providence/State */
  const SUBJECT_CODE          = 127;            /** Array: Subject code */
  const SUPP_CATEGORY_A       = 128;            /** String: Supplemental Category 1 */
  const SUPP_CATEGORY_B       = 129;            /** String: Supplemental Category 2 */
  const SUPP_CATEGORY_C       = 130;            /** String: Supplemental Category 3 */
  const TRANSFER_REF          = 131;            /** String: Original Transmission Reference - Max 32 characters */
  const USAGE_TERMS           = 132;            /** String: Rights Usage Terms */
  
  /** IPTC/XMP fiels: read only */
  const CREATED_DATETIME      = 201;            /** Int: Timestamp when photo was created */

  /** Image data fields: read only **/
  const IMG_APERTURE          = 301;            /** Float: Aperture */
  const IMG_APERTURE_FMT      = 302;            /** String: Aperture (f/X) */
  const IMG_CAMERA_MAKE       = 303;            /** String: Camera brand */
  const IMG_CAMERA_MODEL      = 304;            /** String: Camera model */
  const IMG_CAMERA_SERIAL     = 305;            /** String: Camera serial number */
  const IMG_COLOR_SPACE_FMT   = 306;            /** String: Color space */
  const IMG_EXPOSURE          = 307;            /** Array: Exposure */
  const IMG_EXPOSURE_FMT      = 308;            /** String: Exposure  (1/X second(s))*/
  const IMG_EXPOSURE_MODE_FMT = 309;            /** String: Exposure mode */
  const IMG_EXPOSURE_PGM_FMT  = 310;            /** String: Exposure setting */
  const IMG_FLASH             = 311;            /** Int: Flash used */
  const IMG_FLASH_FMT         = 312;            /** String: Flash used (Flash | No flash)*/
  const IMG_FOCAL_LENGTH      = 313;            /** Int: Focal length */
  const IMG_FOCAL_LENGTH_FMT  = 314;            /** String: Focal length (X mm) */
  const IMG_HEIGHT            = 315;            /** Int: Image height */
  const IMG_ISO               = 316;            /** Int: ISO */
  const IMG_LENS_MAKE         = 317;            /** String: Lens brand */
  const IMG_LENS_MODEL        = 318;            /** String: Lens name */
  const IMG_LENS_SERIAL       = 319;            /** String: Lens serial number */
  const IMG_METERING_MODE_FMT = 320;            /** String: Merering model */
  const IMG_ORIENTATION       = 321;            /** Int: Orientation */
  const IMG_RESOLUTION        = 322;            /** Int: Image resolution, in resolution unit */
  const IMG_RESOLUTION_FMT    = 323;            /** String: Image resolution, in resolution unit */
  const IMG_RESOLUTION_UNIT   = 324;            /** Int: Resolution unit (1, 3=cm / 2=inch) */
  const IMG_SIZE_FMT          = 325;            /** String: Formatted image size ( W x H px - X x Y cm (x MB) */
  const IMG_SOFTWARE          = 326;            /** String: Software used */
  const IMG_TYPE              = 327;            /** Int: Image type (see imagetypes() for constants) */
  const IMG_TYPE_FMT          = 328;            /** String: Image type (jpeg) */
  const IMG_WIDTH             = 329;            /** Int: Image width */

  /** Orientation encoding: IMG_ORIENTATION */
  const IMG_ORI_VERTICAL     = 1;
  const IMG_ORI_HORIZONTAL   = 2;
  const IMG_ORI_SQUARE       = 3;
  const IMG_ORI_UNKNOWN      = -1;

  /** Languages (non exchaustive) */
  const LANG_ALL = 'x-all';                     /** Proxy constand including all languages */
  const LANG_DEFAULT = 'x-default';             /** Default language: English */
  const LANG_EN = 'en-us';                      /** Language: English */
  const LANG_DE = 'de-de';                      /** Language: German */
  const LANG_FR = 'fr-fr';                      /** Language: French */

  /** Private variables */
  protected bool          $data_read;           /** Has data been loaded/read */
  protected bool          $read_only;           /** Data has benn read in read-only mode, disabling writing back */
  protected array         $data;                /** Source agnostic data */
  protected Metadata\Jpeg $jpeg;                /** Jpeg object */

  /**
   * Consturctor
   */
  public function __construct()
  { 
	$this->data_read = false; $this->read_only = true;
	$this->data = array();
	$this->jpeg = new Metadata\Jpeg();
  }

  /**
   * Destrutor
   */
  public function __desctruct()
  {
  }

  /**
   * Read IPTC, XMP, and EXIF metadata from file and make then available in a transparent way
   *
   * @param string $filename  Filename to read metadata from
   * @param bool   $extend    Extend IPTC keyword data into XMP specific fields, e.g. Event:, Scene:, Genre:,
   * @param bool   $read_only Read JPG data in read-only model
   * @throw Exception
   */
  public function read(string $filename, bool $extend = false, bool $read_only = false): void
  {
	// Re-initialize data
	$this->data_read = false;
	$this->data = array();
	$this->read_only = $read_only;

	// Get file specific fields
	if(!file_exists($filename)) throw new Exception(_('File not found'), Exception::FILE_NOT_FOUND, $filename);

	// Read and set file specific data
	clearstatcache();
	$pathinfo = pathinfo($filename);
	$this->setRW(self::FILE_NAME, $pathinfo['basename']);
	$this->setRW(self::FILE_EXT,  $pathinfo['extension']);
	$this->setRW(self::FILE_SIZE, filesize($filename));
	$this->setRW(self::FILE_DATE, filemtime($filename));

	// Read JPG data
	$this->jpeg->read($filename, read_only: $this->read_only);

	// Import data (in decreasing order of relevance)
	$this->importIptc();
	$this->importXmp();
	$this->importExif();

	// Extend fields
	if($extend) $this->extendFields();
	$this->data_read = true;
  }

  /**
   * Write IPTC and XMP metadata back to the file
   * Note: If no XMP metadata was read, no XMP metadata will be written, even if XMP specific fields have been
   * populated
   *
   * @param string $filename Filename to which to write metadata the metadta
   * @throw Exception
   */
  public function write(string $filename): void
  {
	if(!$this->data_read)
	  throw new Exception(_('No image and metadata previously read'), Exception::DATA_NOT_FOUND);
	if($this->read_only)
	  throw new Exception(_('Cannot write file because data was read in read-only mode'), Exception::DATA_NOT_FOUND);

	// Export data
	$this->exportIptc();
	$this->exportXmp();
	$this->exportExif();

	// Save data
	$this->jpeg->write($filename);
  }

  /**
   * Paste existing IPTC and XMP metadata to new file, which must exist
   *
   * @param string $filename Filename to which to write current metadata
   * @throw Exception
   */
  public function paste(string $filename): void
  {
	if(!$this->data_read)
	  throw new Exception(_('No image and metadata previously read'), Exception::DATA_NOT_FOUND);

	// Get file specific fields
	if(!file_exists($filename))
	  throw new Exception(_('File to paste metadata to not found'), Exception::FILE_NOT_FOUND, $filename);

	// Save metadata
	$orig_data = $this->data;

	// Read new file
	$this->read($filename);

	// Restore metadata
	$this->data = $orig_data;

	// Save new file
	$this->write($filename);
  }

  
  /**
   * Return data associated with a given field or 'false', if not value can be found
   *
   * @param  int          $field_id       Field identifier
   * @param  string|false $lang           Language of value set (if language is supported by value)
   * @return string|int|float|array|false Field value
   * @throw  Exception
   */
  public function get(int $field_id, string|false $lang = false): string|int|float|array|false
  {
	if(self::fieldType($field_id) === self::TYPE_INVALID)
	  throw new Exception(_('Invalid field identifier specified'), Exception::INVALID_FIELD_ID, $field_id);

	if($lang !== false) return self::getLang($field_id, $lang);
	if(isset($this->data[$field_id])) return $this->data[$field_id];
	return false;
  }

  /**
   * Save data associated with a given field identifier
   *
   * @param int                          $field_id    Field identifier
   * @param string|int|float|array|false $field_value Field value
   * @param string|false                 $lang        Language of value set (if language is supported by value)
   * @throw Exception
   */
  public function set(int $field_id, string|int|float|array|false $field_value, string|false $lang = false): void
  {
	$this->setRW($field_id, $field_value, $lang, ignore_write: false);
  }
  
  /**
   * Save data associated with a given field identifier
   *
   * @access private
   * @param  int                          $field_id     Field identifier
   * @param  string|int|float|array|false $field_value  Field value
   * @param  string|false                 $lang         Language of value set (if language is supported by value)
   * @param  bool                         $ignore_write Ignore write check
   * @throw  Exception
   */
  private function setRW(int $field_id, string|int|float|array|false $field_value, string|false $lang = false,
						 bool $ignore_write = true): void
  {
	if(self::fieldType($field_id) === self::TYPE_INVALID)
	  throw new Exception(_('Invalid field identifier specified'), Exception::INVALID_FIELD_ID, $field_id);
	if(!$ignore_write && ($field_id < self::FIELD_ID_WRITE_FIRST || $field_id > self::FIELD_ID_WRITE_LAST))
	  throw new Exception(_('Field is not writable'), Exception::INVALID_FIELD_WRITE, $field_id);

	if($lang !== false) { self::setLang($field_id, $field_value, $lang); return; }

	if(!self::isValidFieldType($field_id, $field_value) &&
	   !(self::fieldType($field_id) === self::TYPE_ARY && !is_array($field_value)) && $field_value !== false)
	  throw new Exception(_('Invalid type of field value identifier specified'),
						  Exception::INVALID_FIELD_ID, $field_id);
	
	// Setting field to false is identical to dropping field
	if($field_value === false) { $this->drop($field_id, $field_value, $lang); return; }

	// Add/update field value
	if(self::fieldType($field_id) === self::TYPE_ARY) {
	  if(is_array($field_value)) {
		// Replace all values
		$this->drop($field_id, false, $lang);
		foreach($field_value as $field_subvalue) {
		  $this->data[$field_id][] = $field_subvalue;
		}
	  }
	  else {
		if(isset($this->data[$field_id])) {
		  // If field type is array and field value is not, add/update the value to the array
		  if(!in_array($field_value, $this->data[$field_id], strict: true))
			$this->data[$field_id][] = $field_value;
		}
		else {
		  $this->data[$field_id][] = $field_value;
		}
	  }
	}
	else {
	  $this->data[$field_id] = $field_value;
	}
  }

  /**
   * Get All: FOR TESTING ONLY
   *
   */
  public function getData(): array|false
  {
	return $this->data;
  }

  /**
   * Drop all data
   *
   * @param string|false $lang Language of value set (if language is supported by value)
   * @throw Exception
   */
  public function dropAll(string|false $lang = false): void
  {
	if($lang === false) { $this->data = array(); return; }
	foreach($this->data as $field_id => $field_value) {
	  if(self::isLang($field_id) && isset($this->data[$field_id][$lang])) unset($this->data[$field_id][$lang]);
	  if(!self::isLang($field_id) && isset($this->data[$field_id])) unset($this->data[$field_id]);
	}
  }
  
  /**
   * Drop data associated with a given field identifier
   *
   * @param int              $field_id   Field identifier
   * @param string|int|false $fiel_value Field value
   * @param string|false     $lang       Language of value set (if language is supported by value)
   * @throw Exception
   */
  public function drop(int $field_id, string|int|false $field_value = false, string|false $lang = false): void
  {
	if(self::fieldType($field_id) === self::TYPE_INVALID)
	  throw new Exception(_('Invalid field identifier specified'), Exception::INVALID_FIELD_ID, $field_id);
	if(self::fieldType($field_id) !== self::TYPE_ARY && $field_value !== false) 
	  throw new Exception(_('Only individual values of arrays can be dropped'), Exception::INVALID_FIELD_ID, $field_id);
	
	if($lang !== false) { $this->dropLang($field_id, $field_value, $lang); return; }
	if($field_value !== false) {
	  $field_pos = array_search($field_value, $this->data[$field_id], strict: true);
	  unset($this->data[$field_id][$field_pos]);
	}
	else {
	  unset($this->data[$field_id]);
	}
  }

  /**
   * Return if a given field has already been set
   *
   * @param  int                    $field_id Field identifier
   * @param  string|int|float|false $fiel_value   Field value
   * @param  string|false           $lang Language of value set (if language is supported by value)
   * @return bool Is field already set
   * @throw Exception
   */
  public function isSet(int $field_id, string|int|float|false $field_value = false, string|false $lang = false): bool
  {
	if(self::fieldType($field_id) === self::TYPE_INVALID)
	  throw new Exception(_('Invalid field identifier specified'), Exception::INVALID_FIELD_ID, $field_id);

	if($lang !== false) return $this->isSetLang($field_id, $field_value, $lang);
	if($field_value === false) return isset($this->data[$field_id]);
	if(isset($this->data[$field_id])) {
	  $field_pos = array_search($field_value, $this->data[$field_id], strict: true);
	  return !($field_pos === false);
	}
	return false;
  }


  /**
   * Return if a given field has already been set in the corresponding language
   *
   * @param  int                   $field_id   Field identifier
   * @param string|int|float|false $fiel_value Field value
   * @param string                 $lang       Language of value set (if language is supported by value)
   * @return bool Is field already set
   * @throw Exception
   */
  private function isSetLang(int $field_id, string|int|float|false $field_value = false,
							 string $lang = self::LANG_DEFAULT): bool
  {
	if(!self::isLang($field_id))
	  throw new Exception(_('Field does not support multi-lingual data'), Exception::INVALID_FIELD_DATA, $field_id);

	if($field_value === false) return isset($this->data[$field_id][$lang]);
	if(isset($this->data[$field_id][$lang])) {
	  $field_pos = array_search($field_value, $this->data[$field_id][$lang], strict: true);
	  return !($field_pos === false);
	}
	return false;
  }

  /**
   * Save data associated with a given field identifier is a specific language
   *
   * @param int $field_id Field identifier
   * @param string|int|float|array|false $field_value  Field value
   * @param string $lang Language of value set (if language is supported by value)
   * @throw Exception
   */
  private function setLang(int $field_id, string|int|float|array|false $field_value, string $lang): void
  {
	if(!self::isLang($field_id))
	  throw new Exception(_('Field does not support multi-lingual data'), Exception::INVALID_FIELD_DATA, $field_id);

	// Setting field to false is identical to dropping field
	if($field_value === false) { self::dropLang($field_id, $field_value, $lang); return; }

	// Add/update field value
	if(is_array($field_value)) {
	  foreach($field_value as $lang_value => $data_value) {
		if($lang === self::LANG_ALL || $lang_value === $lang) {
		  $this->data[$field_id][$lang_value] = $data_value;
		}
	  }
	}
	else {
	  $this->data[$field_id][$lang] = $field_value;
	}
  }

  /**
   * Return data associated with a given field is a specific language or 'false', if not value can be found
   *
   * @param  int    $field_id Field identifier
   * @param  string $lang     Language of value set (if language is supported by value)
   * @return string|int|float|array|false Field value
   * @throw  Exception
   */
  private function getLang(int $field_id, string $lang): string|int|float|array|false
  {
	if(!self::isLang($field_id))
	  throw new Exception(_('Field does not support multi-lingual data'), Exception::INVALID_FIELD_DATA, $field_id);

	if(isset($this->data[$field_id])) {
	  if($lang === self::LANG_ALL) return $this->data[$field_id];
	  if(isset($this->data[$field_id][$lang])) return $this->data[$field_id][$lang];
	}
	return false;
  }

  /**
   * Drop data associated with a given field identifier in a specific language
   *
   * @param int                    $field_id   Field identifier
   * @param string|int|float|false $fiel_value Field value
   * @param string                 $lang       Language of value set (if language is supported by value)
   * @throw Exception
   */
  private function dropLang(int $field_id, string|int|float|false $field_value, string $lang): void
  {
	if(!self::isLang($field_id))
	  throw new Exception(_('Field does not support multi-lingual data'), Exception::INVALID_FIELD_DATA, $field_id);

	if($lang === self::LANG_ALL) {
	  if($field_value !== false) {
		foreach($this->data[$field_id] as $field_lang => $value) {
		  $field_pos = array_search($field_value, $this->data[$field_id][$field_lang], strict: true);
		  if($field_pos !== false) unset($this->data[$field_id][$field_lang][$field_pos]);
		}
	  }
	  else {
		unset($this->data[$field_id]);
	  }
	}
	else {
	  if($field_value !== false) {
		$field_pos = array_search($field_value, $this->data[$field_id][$lang], strict: true);
		unset($this->data[$field_id][$lang][$field_pos]);
	  }
	  else {
		unset($this->data[$field_id][$lang]);
	  }
	}
  }
  
  /**
   * Returns true, if the field supports multiple languages, i.e., if the XMP Standard defines it as 'Lang Alt' type
   * NOTE:
   *   Currently only the CAPTION field is supported. Other elements that would be relevant for translation, like
   *   HEADLINE, CITY, or COUNTRY are not of type 'Lang Alt'. As such the XMP Standard does not explicitely support
   *    their translation
   *
   * @param  int  $field_id Field identifier
   * @return bool Return true, if the field supports multiple languages
   */
  public static function isLang(int $field_id): bool
  {
	switch($field_id) {
	case self::CAPTION:                      // XMP name - dc:description (Lang Alt)
	  return true;
	default:
	  return false;
	}
  }

  /**
   * Return the type associaed with a given field identifier
   *
   * @param  int $field_id Field identifier
   * @return int Field type constant
   */
  public static function fieldType(int $field_id): int
  {
	switch($field_id) {
	case self::GENRE:
	case self::KEYWORDS:
	case self::ORG_CODE:
	case self::ORG_NAME:
	case self::PERSON:
	case self::SCENES:
	case self::SUBJECT_CODE:
	case self::IMG_EXPOSURE:
	  return self::TYPE_ARY;

	case self::FILE_DATE:
	case self::FILE_SIZE:
	case self::CREATED_DATETIME:
	case self::PRIORITY:
	case self::RATING:
	case self::IMG_FLASH:
	case self::IMG_FOCAL_LENGTH:
	case self::IMG_HEIGHT:
	case self::IMG_ISO:
	case self::IMG_ORIENTATION;
	case self::IMG_RESOLUTION:
	case self::IMG_RESOLUTION_UNIT:
	case self::IMG_TYPE:
	case self::IMG_WIDTH:
	  return self::TYPE_INT;

	case self::IMG_APERTURE:
	  return self::TYPE_FLOAT;
	  
	case self::FILE_NAME:
	case self::FILE_EXT:
	case self::AUTHOR:
	case self::AUTHOR_TITLE:
	case self::CAPTION:
	case self::CAPTION_WRITER:
	case self::CATEGORY:
	case self::CITY:
	case self::COPYRIGHT:
	case self::COUNTRY:
	case self::COUNTRY_CODE:
	case self::CREDIT:
	case self::EDIT_STATUS:
	case self::EVENT:
	case self::HEADLINE:
	case self::INSTRUCTIONS:
	case self::LOCATION:
	case self::OBJECT:
	case self::SOURCE:
	case self::STATE:
	case self::SUPP_CATEGORY_A:
	case self::SUPP_CATEGORY_B:
	case self::SUPP_CATEGORY_C:
	case self::TRANSFER_REF:
	case self::USAGE_TERMS:
	case self::IMG_APERTURE_FMT:
	case self::IMG_CAMERA_MAKE:
	case self::IMG_CAMERA_MODEL:
	case self::IMG_CAMERA_SERIAL;
	case self::IMG_COLOR_SPACE_FMT:
	case self::IMG_EXPOSURE_FMT:
	case self::IMG_EXPOSURE_MODE_FMT:
	case self::IMG_EXPOSURE_PGM_FMT:
	case self::IMG_FLASH_FMT:
	case self::IMG_FOCAL_LENGTH_FMT:
	case self::IMG_LENS_MAKE:
	case self::IMG_LENS_MODEL:
	case self::IMG_LENS_SERIAL:
	case self::IMG_METERING_MODE_FMT:
	case self::IMG_RESOLUTION_FMT:
	case self::IMG_SIZE_FMT:
	case self::IMG_SOFTWARE:
	case self::IMG_TYPE_FMT:
	  return self::TYPE_STR;
	default:
	  return self::TYPE_INVALID;
	}
  }

  /**
   * Return if the type asssociated with a given field value is valid
   *
   * @param int $field_id Field identifier
   * @param string|int|float|array|false $field_value Field value
   * @return bool Isd the type of the value appropriate for the field
   */
  public static function isValidFieldType(int $field_id, string|int|float|array|false $field_value): bool
  {
	switch(self::fieldType($field_id)) {
	case self::TYPE_STR:
	  return gettype($field_value) === 'string';
	case self::TYPE_INT:
	  return gettype($field_value) === 'integer';
	case self::TYPE_FLOAT:
	  return gettype($field_value) === 'double' || gettype($field_value) === 'integer';
	case self::TYPE_ARY:
	  return gettype($field_value) === 'array';
	default:
	  return false;
	}
  }

  /**
   * Decompose a string containing a list of comma-separated sub-strings into an array of thos sub-strings, removing 
   * leading and trailing spaces.
   *
   * @param  string|false $input Input string containing comma-separated sub-strings
   * @return array|false  Array of sub-strings
   */
  public function stringToArray(string|false $input): array|false
  {
	if($input === false || empty($input)) return false;
	$output_ary = array();
	$substr_ary = explode(',', $input);
	foreach($substr_ary as $substr) {
	  $substr = trim($substr);
	  $output_ary[$substr] = $substr;
	}
	return $output_ary;
  }

  /**
   * Convert an array to a comma-separated string
   *
   * @param  array|false $input_ary Array of strings
   * @return string|false Comma-separated string
   */
  public function arrayToString(array|false $input_ary): string|false
  {
	if($input_ary === false) return false;
	$output = '';
	foreach($input_ary as $field_value) {
	  if(!empty($output)) $output .= ', ';
	  $output .= $field_value;
	}
	return $output;
  }

  
  /**
   * PROTECTED AND PRIVATE FUNCTIONS
   */

  /**
   * Expand tagged keywords into respctive fields
   *
   * @access private
   * @param  array $kwd_ary Array of keywords
   */
  private function extendFields(): void
  {
	$kwd_ary = $this->get(self::KEYWORDS);
	foreach($kwd_ary as $kwd) {
	  if(strpos($kwd, ':') !== false) {
		list($field_name, $field_value) = explode(':', $kwd, 2);
		switch(trim(strtolower($field_name))) {
		case 'event':
		  $this->drop(self::KEYWORDS, $kwd);
		  $this->set(self::EVENT, $field_value);
		  break;
		case 'scence':
		  $this->drop(self::KEYWORDS, $kwd);
		  $this->set(self::SCENES, $field_value);
		  break;
		case 'genre':
		  $this->drop(self::KEYWORDS, $kwd);
		  $this->set(self::GENRE, $field_value);
		  break;
		case 'people':
		case 'peo':
		  $this->drop(self::KEYWORDS, $kwd);
		  $this->set(self::PERSON, $field_value);
		  break;
		case 'org':
		case 'organization':
		  $this->drop(self::KEYWORDS, $kwd);
		  $this->set(self::ORG, $field_value);
		  break;
		case 'nat':
		case 'nationality':
		  $this->drop(self::KEYWORDS, $kwd);
		  $this->set(self::NAT, $field_value);
		  break;
		default:
		  break;
		}
	  }
	}
  }
  
  /**
   * 1: READ/WRITE IPTC DATA
   */

  /**
   * Import IPTC metadata from JPG object
   *
   * @access private
   * @throw  Exception Invalid metadata read
   */
  private function importIptc(): void
  {
	$iptc_data = $this->jpeg->getIptcData();
	if($iptc_data === false) return;

	// Set IPTC writable fields (if they have not yet bee set)
	if(isset($iptc_data[Iptc::AUTHOR][0]) && !$this->isSet(self::AUTHOR))
	  $this->set(self::AUTHOR, $iptc_data[Iptc::AUTHOR][0]);
	if(isset($iptc_data[Iptc::AUTHOR_TITLE][0]) && !$this->isSet(self::AUTHOR_TITLE))
	  $this->set(self::AUTHOR_TITLE, $iptc_data[Iptc::AUTHOR_TITLE][0]);
	if(isset($iptc_data[Iptc::CAPTION][0]) && !$this->isSet(self::CAPTION, self::LANG_DEFAULT))
	  $this->set(self::CAPTION, $iptc_data[Iptc::CAPTION][0], self::LANG_DEFAULT);
	if(isset($iptc_data[Iptc::CAPTION_WRITER][0]) && !$this->isSet(self::CAPTION_WRITER))
	  $this->set(self::CAPTION_WRITER, $iptc_data[Iptc::CAPTION_WRITER][0]);
	if(isset($iptc_data[Iptc::CATEGORY][0]) && !$this->isSet(self::CATEGORY))
	  $this->set(self::CATEGORY, $iptc_data[Iptc::CATEGORY][0]);
	if(isset($iptc_data[Iptc::CITY][0]) && !$this->isSet(self::CITY))
	  $this->set(self::CITY, $iptc_data[Iptc::CITY][0]);
	if(isset($iptc_data[Iptc::COUNTRY][0]) && !$this->isSet(self::COUNTRY))
	  $this->set(self::COUNTRY, $iptc_data[Iptc::COUNTRY][0]);
	if(isset($iptc_data[Iptc::COUNTRY_CODE][0]) && !$this->isSet(self::COUNTRY_CODE))
	  $this->set(self::COUNTRY_CODE, $iptc_data[Iptc::COUNTRY_CODE][0]);
	if(isset($iptc_data[Iptc::CREDIT][0]) && !$this->isSet(self::CREDIT))
	  $this->set(self::CREDIT, $iptc_data[Iptc::CREDIT][0]);
	if(isset($iptc_data[Iptc::EDIT_STATUS][0]) && !$this->isSet(self::EDIT_STATUS))
	  $this->set(self::EDIT_STATUS, $iptc_data[Iptc::EDIT_STATUS][0]);
	if(isset($iptc_data[Iptc::GENRE][0]) && !$this->isSet(self::GENRE))
	  $this->set(self::GENRE, $this->stringToArray($iptc_data[Iptc::GENRE][0]));
	if(isset($iptc_data[Iptc::HEADLINE][0]) && !$this->isSet(self::HEADLINE))
	  $this->set(self::HEADLINE, $iptc_data[Iptc::HEADLINE][0]);
	if(isset($iptc_data[Iptc::INSTRUCTIONS][0]) && !$this->isSet(self::INSTRUCTIONS))
	  $this->set(self::INSTRUCTIONS, $iptc_data[Iptc::INSTRUCTIONS][0]);
	if(isset($iptc_data[Iptc::KEYWORDS]) && !$this->isSet(self::KEYWORDS))
	  $this->set(self::KEYWORDS, $iptc_data[Iptc::KEYWORDS]);
	if(isset($iptc_data[Iptc::LOCATION][0]) && !$this->isSet(self::LOCATION))
	  $this->set(self::LOCATION, $iptc_data[Iptc::LOCATION][0]);
	if(isset($iptc_data[Iptc::OBJECT][0]) && !$this->isSet(self::OBJECT))
	  $this->set(self::OBJECT, $iptc_data[Iptc::OBJECT][0]);
	if(isset($iptc_data[Iptc::PRIORITY][0]) && !$this->isSet(self::PRIORITY))
	  $this->set(self::PRIORITY, (int)$iptc_data[Iptc::PRIORITY][0]);
	if(isset($iptc_data[Iptc::SOURCE][0]) && !$this->isSet(self::SOURCE))
	  $this->set(self::SOURCE, $iptc_data[Iptc::SOURCE][0]);
	if(isset($iptc_data[Iptc::STATE][0]) && !$this->isSet(self::STATE))
	  $this->set(self::STATE, $iptc_data[Iptc::STATE][0]);
	if(isset($iptc_data[Iptc::SUBJECT_CODE]) && !$this->isSet(self::SUBJECT_CODE))
	  $this->set(self::SUBJECT_CODE, $iptc_data[Iptc::SUBJECT_CODE]);
	if(isset($iptc_data[Iptc::SUPP_CATEGORY][0]) && !$this->isSet(self::SUPP_CATEGORY_A))
	  $this->set(self::SUPP_CATEGORY_A, $iptc_data[Iptc::SUPP_CATEGORY][0]);
	if(isset($iptc_data[Iptc::SUPP_CATEGORY][1]) && !$this->isSet(self::SUPP_CATEGORY_B))
	  $this->set(self::SUPP_CATEGORY_B, $iptc_data[Iptc::SUPP_CATEGORY][1]);
	if(isset($iptc_data[Iptc::SUPP_CATEGORY][2]) && !$this->isSet(self::SUPP_CATEGORY_C))
	  $this->set(self::SUPP_CATEGORY_C, $iptc_data[Iptc::SUPP_CATEGORY][2]);
	if(isset($iptc_data[Iptc::TRANSFER_REF][0]) && !$this->isSet(self::TRANSFER_REF))
	  $this->set(self::TRANSFER_REF, $iptc_data[Iptc::TRANSFER_REF][0]);

	// Set IPTC read-only fields
	if(isset($iptc_data[Iptc::CREATED_DATE][0]) &&
	   isset($iptc_data[Iptc::CREATED_TIME][0]) && !$this->isSet(self::CREATED_DATETIME))
	  $this->setRW(self::CREATED_DATETIME, strtotime($iptc_data[Iptc::CREATED_DATE][0].' '.
													 $iptc_data[Iptc::CREATED_TIME][0]));
  }

  /**
   * Export IPTC data, replacing existing data
   *
   * @access private
   * @throw  Exception Invalid metadata read
   */
  private function exportIptc(): void
  {
	$iptc_data = array();
	if($this->isSet(self::AUTHOR)) $iptc_data[Iptc::AUTHOR][0] = $this->get(self::AUTHOR);
	if($this->isSet(self::AUTHOR_TITLE)) $iptc_data[Iptc::AUTHOR_TITLE][0] = $this->get(self::AUTHOR_TITLE);
	if($this->isSet(self::CAPTION, self::LANG_DEFAULT))
	  $iptc_data[Iptc::CAPTION][0] = $this->get(self::CAPTION, self::LANG_DEFAULT);
	if($this->isSet(self::CAPTION_WRITER))
	  $iptc_data[Iptc::CAPTION_WRITER][0] = $this->get(self::CAPTION_WRITER);
	if($this->isSet(self::CATEGORY)) $iptc_data[Iptc::CATEGORY][0] = $this->get(self::CATEGORY);
	if($this->isSet(self::CITY)) $iptc_data[Iptc::CITY][0] = $this->get(self::CITY);
	if($this->isSet(self::COPYRIGHT)) $iptc_data[Iptc::COPYRIGHT][0] = $this->get(self::COPYRIGHT);
	if($this->isSet(self::COUNTRY)) $iptc_data[Iptc::COUNTRY][0] = $this->get(self::COUNTRY);
	if($this->isSet(self::COUNTRY_CODE)) $iptc_data[Iptc::COUNTRY_CODE][0] = $this->get(self::COUNTRY_CODE);
	if($this->isSet(self::CREDIT)) $iptc_data[Iptc::CREDIT][0] = $this->get(self::CREDIT);
	if($this->isSet(self::EDIT_STATUS)) $iptc_data[Iptc::EDIT_STATUS][0] = $this->get(self::EDIT_STATUS);
	if($this->isSet(self::GENRE)) $iptc_data[Iptc::GENRE][0] = $this->arrayToString($this->get(self::GENRE));
	if($this->isSet(self::HEADLINE)) $iptc_data[Iptc::HEADLINE][0] = $this->get(self::HEADLINE);
	if($this->isSet(self::INSTRUCTIONS)) $iptc_data[Iptc::INSTRUCTIONS][0] = $this->get(self::INSTRUCTIONS);
	if($this->isSet(self::KEYWORDS)) $iptc_data[Iptc::KEYWORDS] = $this->get(self::KEYWORDS);
	if($this->isSet(self::LOCATION)) $iptc_data[Iptc::LOCATION][0] = $this->get(self::LOCATION);
	if($this->isSet(self::OBJECT)) $iptc_data[Iptc::OBJECT][0] = $this->get(self::OBJECT);
	if($this->isSet(self::PRIORITY)) $iptc_data[Iptc::PRIORITY][0] = $this->get(self::PRIORITY);
	if($this->isSet(self::SOURCE)) $iptc_data[Iptc::SOURCE][0] = $this->get(self::SOURCE);
	if($this->isSet(self::STATE)) $iptc_data[Iptc::STATE][0] = $this->get(self::STATE);
	if($this->isSet(self::SUBJECT_CODE)) $iptc_data[Iptc::SUBJECT_CODE] = $this->get(self::SUBJECT_CODE);
	if($this->isSet(self::CATEGORY)) $iptc_data[Iptc::CATEGORY][0] = $this->get(self::CATEGORY);
	if($this->isSet(self::SUPP_CATEGORY_A))
	  $iptc_data[Iptc::SUPP_CATEGORY][0] = $this->get(self::SUPP_CATEGORY_A);
	if(!$this->isSet(self::SUPP_CATEGORY_A) && $this->isSet(self::SUPP_CATEGORY_B))
	  $iptc_data[Iptc::SUPP_CATEGORY][0] = '';
	if($this->isSet(self::SUPP_CATEGORY_B))
	  $iptc_data[Iptc::SUPP_CATEGORY][1] = $this->get(self::SUPP_CATEGORY_B);
	if(!$this->isSet(self::SUPP_CATEGORY_B) && $this->isSet(self::SUPP_CATEGORY_C))
	  $iptc_data[Iptc::SUPP_CATEGORY][1] = '';
	if($this->isSet(self::SUPP_CATEGORY_C))
	  $iptc_data[Iptc::SUPP_CATEGORY][2] = $this->get(self::SUPP_CATEGORY_C);
	if($this->isSet(self::TRANSFER_REF)) $iptc_data[Iptc::TRANSFER_REF][0] = $this->get(self::TRANSFER_REF);

	if(empty($iptc_data)) $this->jpeg->setIptcData(false); else $this->jpeg->setIptcData($iptc_data);
  }

  
  /**
   * 2./ READ/WRITE XMP DATA
   */

  /**
   * Read XMP metadata from file and preserve original XMP data for future use
   * Function may be extended to read additional fields and/or recoginiez additional tags
   *
   * @access protected
   * @param  string $filename   Filename to read metadata from
   * @throw  Exception Invalid metadata read
   */
  protected function importXmp(): void
  {
	$xmp_data = $this->jpeg->getXmpData();
	if($xmp_data === false) return;

	// Set XMP writable fields (if they have not yet bee set)
	if(!$this->isSet(self::AUTHOR) && $xmp_data->isXmpText(Xmp::AUTHOR))
	  $this->set(self::AUTHOR, $xmp_data->getXmpText(Xmp::AUTHOR));
	if(!$this->isSet(self::AUTHOR_TITLE) && $xmp_data->isXmpText(Xmp::PS_AUTHOR_TITLE))
	  $this->set(self::AUTHOR_TITLE, $xmp_data->getXmpText(Xmp::PS_AUTHOR_TITLE));
	$this->setLang(self::CAPTION, $xmp_data->getXmpLangAlt(Xmp::CAPTION, self::LANG_ALL), self::LANG_ALL);
	if(!$this->isSet(self::CATEGORY) && $xmp_data->isXmpText(Xmp::PS_CATEGORY))
	  $this->set(self::CATEGORY, $xmp_data->getXmpText(Xmp::PS_CATEGORY));
	if(!$this->isSet(self::CITY) && $xmp_data->isXmpText(Xmp::CITY))
	  $this->set(self::CITY, $xmp_data->getXmpText(Xmp::CITY));
	if(!$this->isSet(self::COPYRIGHT) && $xmp_data->isXmpText(Xmp::COPYRIGHT))
	  $this->set(self::COPYRIGHT, $xmp_data->getXmpText(Xmp::COPYRIGHT));
	if(!$this->isSet(self::COUNTRY) && $xmp_data->isXmpText(Xmp::COUNTRY))
	  $this->set(self::COUNTRY, $xmp_data->getXmpText(Xmp::COUNTRY));
	if(!$this->isSet(self::COUNTRY) && $xmp_data->isXmpText(Xmp::PS_COUNTRY))
	  $this->set(self::COUNTRY, $xmp_data->getXmpText(Xmp::PS_COUNTRY));
	if(!$this->isSet(self::COUNTRY_CODE) && $xmp_data->isXmpText(Xmp::COUNTRY_CODE))
	  $this->set(self::COUNTRY_CODE, $xmp_data->getXmpText(Xmp::COUNTRY_CODE));
	if(!$this->isSet(self::CREDIT) && $xmp_data->isXmpText(Xmp::CREDIT))
	  $this->set(self::CREDIT, $xmp_data->getXmpText(Xmp::CREDIT));
	if(!$this->isSet(self::GENRE) && $xmp_data->isXmpText(Xmp::GENRE))
	  $this->set(self::GENRE, $this->stringToArray($xmp_data->getXmpText(Xmp::GENRE)));
	if(!$this->isSet(self::HEADLINE) && $xmp_data->isXmpText(Xmp::PS_HEADLINE))
	  $this->set(self::HEADLINE, $xmp_data->getXmpText(Xmp::PS_HEADLINE));
	if(!$this->isSet(self::INSTRUCTIONS) && $xmp_data->isXmpText(Xmp::INSTRUCTIONS))
	  $this->set(self::INSTRUCTIONS, $xmp_data->getXmpText(Xmp::INSTRUCTIONS));
	if(!$this->isSet(self::KEYWORDS) && $xmp_data->isXmpBag(Xmp::KEYWORDS))
	  $this->set(self::KEYWORDS, $xmp_data->getXmpBag(Xmp::KEYWORDS));
	if(!$this->isSet(self::LOCATION) && $xmp_data->isXmpText(Xmp::LOCATION))
	  $this->set(self::LOCATION, $xmp_data->getXmpText(Xmp::LOCATION));
	if(!$this->isSet(self::OBJECT) && $xmp_data->isXmpText(Xmp::OBJECT))
	  $this->set(self::OBJECT, $xmp_data->getXmpText(Xmp::OBJECT));
	if(!$this->isSet(self::PRIORITY) && $xmp_data->isXmpText(Xmp::PS_PRIORITY))
	  $this->set(self::PRIORITY, (int)$xmp_data->getXmpText(Xmp::PS_PRIORITY));
	if(!$this->isSet(self::SCENES) && $xmp_data->isXmpBag(Xmp::SCENES))
	  $this->set(self::SCENES, $xmp_data->getXmpBag(Xmp::SCENES));
	if(!$this->isSet(self::SOURCE) && $xmp_data->isXmpText(Xmp::SOURCE))
	  $this->set(self::SOURCE, $xmp_data->getXmpText(Xmp::SOURCE));
	if(!$this->isSet(self::SOURCE) && $xmp_data->isXmpText(Xmp::PS_SOURCE))
	  $this->set(self::SOURCE, $xmp_data->getXmpText(Xmp::PS_SOURCE));
	if(!$this->isSet(self::SUBJECT_CODE) && $xmp_data->isXmpBag(Xmp::SUBJECT_CODE))
	  $this->set(self::SUBJECT_CODE, $xmp_data->getXmpBag(Xmp::SUBJECT_CODE));
	if(!$this->isSet(self::USAGE_TERMS) && $xmp_data->isXmpText(Xmp::USAGE_TERMS))
	  $this->set(self::USAGE_TERMS, $xmp_data->getXmpText(Xmp::USAGE_TERMS));
	if(!$this->isSet(self::EVENT) && $xmp_data->isXmpText(Xmp::EVENT))
	  $this->set(self::EVENT, $xmp_data->getXmpText(Xmp::EVENT));
	if(!$this->isSet(self::ORG_CODE) && $xmp_data->isXmpBag(Xmp::ORG_CODE))
	  $this->set(self::ORG_CODE, $xmp_data->getXmpBag(Xmp::ORG_CODE));
	if(!$this->isSet(self::ORG_NAME) && $xmp_data->isXmpBag(Xmp::ORG_NAME))
	  $this->set(self::ORG_NAME, $xmp_data->getXmpBag(Xmp::ORG_NAME));
	if(!$this->isSet(self::PERSON) && $xmp_data->isXmpBag(Xmp::PERSON))
	  $this->set(self::PERSON, $xmp_data->getXmpBag(Xmp::PERSON));
	if(!$this->isSet(self::RATING) && $xmp_data->isXmpText(Xmp::RATING))
	  $this->set(self::RATING, (int)$xmp_data->getXmpText(Xmp::RATING));

	// Set PhotoShop specific XMP writeables fields
	if(!$this->isSet(self::AUTHOR_TITLE) && $xmp_data->isXmpText(Xmp::PS_AUTHOR_TITLE))
	  $this->set(self::AUTHOR_TITLE, $xmp_data->getXmpText(Xmp::PS_AUTHOR_TITLE));
	if(!$this->isSet(self::CAPTION_WRITER) && $xmp_data->isXmpText(Xmp::PS_CAPTION_WRITER))
	  $this->set(self::CAPTION_WRITER, $xmp_data->getXmpText(Xmp::PS_CAPTION_WRITER));
	if(!$this->isSet(self::CATEGORY) && $xmp_data->isXmpText(Xmp::PS_CATEGORY))
	  $this->set(self::CATEGORY, $xmp_data->getXmpText(Xmp::PS_CATEGORY));
	if(!$this->isSet(self::CITY) && $xmp_data->isXmpText(Xmp::PS_CITY))
	  $this->set(self::CITY, $xmp_data->getXmpText(Xmp::PS_CITY));
	if(!$this->isSet(self::COUNTRY) && $xmp_data->isXmpText(Xmp::PS_COUNTRY))
	  $this->set(self::COUNTRY, $xmp_data->getXmpText(Xmp::PS_COUNTRY));
	if(!$this->isSet(self::EDIT_STATUS) && $xmp_data->isXmpText(Xmp::PM_EDIT_STATUS))
	  $this->set(self::EDIT_STATUS, $xmp_data->getXmpText(Xmp::PM_EDIT_STATUS));
	if(!$this->isSet(self::HEADLINE) && $xmp_data->isXmpText(Xmp::PS_HEADLINE))
	  $this->set(self::HEADLINE, $xmp_data->getXmpText(Xmp::PS_HEADLINE));
	if(!$this->isSet(self::SOURCE) && $xmp_data->isXmpText(Xmp::PS_SOURCE))
	  $this->set(self::SOURCE, $xmp_data->getXmpText(Xmp::PS_SOURCE));
	if(!$this->isSet(self::STATE) && $xmp_data->isXmpText(Xmp::PS_STATE))
	  $this->set(self::STATE, $xmp_data->getXmpText(Xmp::PS_STATE));
	if($xmp_data->isXmpBag(Xmp::PS_SUPP_CATEGORY)) {
	  $supp_categories = $xmp_data->getXmpBag(Xmp::PS_SUPP_CATEGORY);
	  if(!$this->isSet(self::SUPP_CATEGORY_A) && isset($supp_categories[0]))
		$this->set(self::SUPP_CATEGORY_A, $supp_categories[0]);
	  if(!$this->isSet(self::SUPP_CATEGORY_B) && isset($supp_categories[1]))
		$this->set(self::SUPP_CATEGORY_B, $supp_categories[1]);
	  if(!$this->isSet(self::SUPP_CATEGORY_C) && isset($supp_categories[2]))
		$this->set(self::SUPP_CATEGORY_C, $supp_categories[2]);
	}
	if(!$this->isSet(self::PRIORITY) && $xmp_data->isXmpText(Xmp::PS_PRIORITY))
	  $this->set(self::PRIORITY, (int)$xmp_data->getXmpText(Xmp::PS_PRIORITY));
	if(!$this->isSet(self::TRANSFER_REF) && $xmp_data->isXmpText(Xmp::PS_TRANSFER_REF))
	  $this->set(self::TRANSFER_REF, $xmp_data->getXmpText(Xmp::PS_TRANSFER_REF));
	
	
	// Set XMP read-only fields
	if(!$this->isSet(self::CREATED_DATETIME) && $xmp_data->isXmpText(Xmp::CREATED_DATETIME))
	  $this->setRW(self::CREATED_DATETIME, strtotime($xmp_data->getXmpText(Xmp::CREATED_DATETIME)));
	if(!$this->isSet(self::CREATED_DATETIME) && $xmp_data->isXmpText(Xmp::PS_CREATED_DATETIME))
	  $this->setRW(self::CREATED_DATETIME, strtotime($xmp_data->getXmpText(Xmp::PS_CREATED_DATETIME)));

	// Set XMP read-only fields related to IMG_ data fields
	if(!$this->isSet(self::IMG_CAMERA_SERIAL) && $xmp_data->isXmpText(Xmp::CAMERA_SERIAL))
	  $this->setRW(self::IMG_CAMERA_SERIAL, $xmp_data->getXmpText(Xmp::CAMERA_SERIAL));
	if(!$this->isSet(self::IMG_LENS_MODEL) && $xmp_data->isXmpText(Xmp::LENS_MODEL))
	  $this->setRW(self::IMG_LENS_MODEL, $xmp_data->getXmpText(Xmp::LENS_MODEL));
	if(!$this->isSet(self::IMG_LENS_SERIAL) && $xmp_data->isXmpText(Xmp::LENS_SERIAL))
	  $this->setRW(self::IMG_LENS_SERIAL, $xmp_data->getXmpText(Xmp::LENS_SERIAL));
	if(!$this->isSet(self::IMG_COLOR_SPACE_FMT) && $xmp_data->isXmpText(Xmp::COLOR_SPACE))
	  $this->setRW(self::IMG_COLOR_SPACE_FMT, $xmp_data->getXmpText(Xmp::COLOR_SPACE));
	
  }

  /**
   * Write XMP data, merging with existing data (if no data exists, no data is exported to XMP)
   *
   * @access protected
   * @param  string $filename  Filename to replace metadata in
   * @throw  Exception Invalid metadata read
   */
  protected function exportXmp(): void
  {
	$xmp_data = $this->jpeg->getXmpData();
	if($xmp_data === false) return;
	
	$xmp_data->setXmpSeq(Xmp::AUTHOR, $this->get(self::AUTHOR));
	$xmp_data->setXmpText(Xmp::PS_AUTHOR_TITLE, $this->get(self::AUTHOR_TITLE));
	$xmp_data->setXmpLangAlt(Xmp::CAPTION, $this->getLang(self::CAPTION, self::LANG_ALL));
	$xmp_data->setXmpText(Xmp::PS_CAPTION_WRITER, $this->get(self::CAPTION_WRITER));
	$xmp_data->setXmpText(Xmp::PS_CATEGORY, $this->get(self::CATEGORY));
	$xmp_data->setXmpText(Xmp::CITY, $this->get(self::CITY));
	if($xmp_data->isXmpText(Xmp::PS_CITY)) $xmp_data->setXmpText(Xmp::PS_CITY, $this->get(self::CITY));
	$xmp_data->setXmpAlt(Xmp::COPYRIGHT, $this->get(self::COPYRIGHT));
	$xmp_data->setXmpText(Xmp::COUNTRY, $this->get(self::COUNTRY));
	if($xmp_data->isXmpText(Xmp::PS_COUNTRY)) $xmp_data->setXmpText(Xmp::PS_COUNTRY, $this->get(self::COUNTRY));
	$xmp_data->setXmpText(Xmp::COUNTRY_CODE, $this->get(self::COUNTRY_CODE));
	$xmp_data->setXmpText(Xmp::CREDIT, $this->get(self::CREDIT));
	$xmp_data->setXmpText(Xmp::PM_EDIT_STATUS, $this->get(self::EDIT_STATUS));
	$xmp_data->setXmpText(Xmp::GENRE, self::arrayToString($this->get(self::GENRE)));
	$xmp_data->setXmpText(Xmp::PS_HEADLINE, $this->get(self::HEADLINE));
	$xmp_data->setXmpText(Xmp::INSTRUCTIONS, $this->get(self::INSTRUCTIONS));
	$xmp_data->setXmpBag(Xmp::KEYWORDS, $this->get(self::KEYWORDS));
	$xmp_data->setXmpText(Xmp::LOCATION, $this->get(self::LOCATION));
	$xmp_data->setXmpText(Xmp::PS_PRIORITY, (string)$this->get(self::PRIORITY));
	$xmp_data->setXmpAlt(Xmp::OBJECT, $this->get(self::OBJECT));
	$xmp_data->setXmpBag(Xmp::SCENES, $this->get(self::SCENES));
	$xmp_data->setXmpText(Xmp::SOURCE, $this->get(self::SOURCE));
	if($xmp_data->isXmpText(Xmp::PS_SOURCE)) $xmp_data->setXmpText(Xmp::PS_SOURCE, $this->get(self::SOURCE));
	$xmp_data->setXmpText(Xmp::STATE, $this->get(self::STATE));
	if($xmp_data->isXmpText(Xmp::PS_STATE)) $xmp_data->setXmpText(Xmp::PS_STATE, $this->get(self::STATE));
	$xmp_data->setXmpBag(Xmp::SUBJECT_CODE, $this->get(self::SUBJECT_CODE));

	$supp_cat = array();
	if($this->isSet(self::SUPP_CATEGORY_A)) $supp_cat[0] = $this->get(self::SUPP_CATEGORY_A);
	if(!$this->isSet(self::SUPP_CATEGORY_A) && $this->isSet(self::SUPP_CATEGORY_B)) $supp_cat[0] = '';
	if($this->isSet(self::SUPP_CATEGORY_B)) $supp_cat[1]  = $this->get(self::SUPP_CATEGORY_B);
	if(!$this->isSet(self::SUPP_CATEGORY_B) && $this->isSet(self::SUPP_CATEGORY_C)) $supp_cat[1] = '';
	if($this->isSet(self::SUPP_CATEGORY_C)) $supp_cat[2] = $this->get(self::SUPP_CATEGORY_C);
	$supp_cat = empty($supp_cat) ? false : $supp_cat;
	$xmp_data->setXmpBag(Xmp::PS_SUPP_CATEGORY, $supp_cat);
	$xmp_data->setXmpAlt(Xmp::USAGE_TERMS, $this->get(self::USAGE_TERMS));
	$xmp_data->setXmpAlt(Xmp::EVENT, $this->get(self::EVENT));
	$xmp_data->setXmpBag(Xmp::ORG_CODE, $this->get(self::ORG_CODE));
	$xmp_data->setXmpBag(Xmp::ORG_NAME, $this->get(self::ORG_NAME));
	$xmp_data->setXmpBag(Xmp::PERSON, $this->get(self::PERSON));
	$xmp_data->setXmpText(Xmp::RATING, (string)$this->get(self::RATING));
	$xmp_data->setXmpText(Xmp::PS_TRANSFER_REF, $this->get(self::TRANSFER_REF));

	// Add/Update languages supported
	$xmp_data->addLanguages();

	// Update history log
	$xmp_data->updateHistory('Metadata '.self::VERSION);
	$this->jpeg->setXmpData($xmp_data);
  }
	  

  /**
   * 3. READ EXIF DATA
   */

  /**
   * Calculate the rounded value of a string formatted "x / y"
   *
   * @access protected
   * @param  string $data String formatted "x/y"
   * @return float|false Rounded value dividing x by y
   */
  protected static function calcFrac(string $data): float|false
  {
	if(strpos($data, '/') === 0) return false;
	list($nom, $denom) = explode('/', $data, 2);
	return (float)((int)$nom / (int)$denom);
  }

  /**
   * Normalize fraction to a 1/x fraction
   *
   * @access protected
   * @param  string $frac Fraction as a string x/y
   * @return string Fraction as a string 1/y (where y is rounded)
   */
 protected static function nrmFrac(string $frac): string
  {
	if(strpos($frac, '/') === false) return $frac;
	list($num, $denom) = explode('/', $frac);
	$num = (int)trim($num); $denom = (int)trim($denom);
	$new_denom = $denom / $num;
	return '1/'.round($new_denom);
  }

 /**
  * Decompose fraction into array
  *
   * @access protected
   * @param  string $frac Fraction as a string x/y
   * @return array  Array with two elements of fraction
  */
 protected static function fracToArray(string $frac): array
 {
	if(strpos($frac, '/') === false)
	  throw new Exception(_('Invalid fraction specified'), Exception::DATA_FORMAT_ERROR, $frac);
	list($num, $denom) = explode('/', $frac);
	return array($num, $denom);
 }
 
  /**
   * Read EXIF metadata from file
   * Function may be extended to read additional fields and/or recoginiez additional tags
   *
   * @access protected
   * @throw  Exception Invalid metadata read
   */
  protected function importExif(): void
  {
	$exif_data = $this->jpeg->getExifData();
	if($exif_data === false) return;

	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FNUMBER)])) {
	  $aperture = self::calcFrac($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FNUMBER)]);
	  if(!$this->isSet(self::IMG_APERTURE)) $this->setRW(self::IMG_APERTURE, $aperture);
	  if(!$this->isSet(self::IMG_APERTURE_FMT)) $this->setRW(self::IMG_APERTURE_FMT, 'f/'.number_format($aperture, 1));
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_CAMERA_MAKE)]) && !$this->isSet(self::IMG_CAMERA_MAKE))
	  $this->setRW(self::IMG_CAMERA_MAKE, $exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_CAMERA_MAKE)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_CAMERA_MODEL)]) &&
	   !$this->isSet(self::IMG_CAMERA_MODEL))
	  $this->setRW(self::IMG_CAMERA_MODEL, $exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_CAMERA_MODEL)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_CAMERA_SERIAL_NUMBER)]) &&
	   !$this->isSet(self::IMG_CAMERA_SERIAL))
	  $this->setRW(self::IMG_CAMERA_SERIAL, $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_CAMERA_SERIAL_NUMBER)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_COLOR_SPACE)]) &&
	   !$this->isSet(self::IMG_COLOR_SPACE_FMT)) {
	  switch((int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_COLOR_SPACE)]) {
	  case 0x1:
		$this->setRW(self::IMG_COLOR_SPACE_FMT, _('sRGB')); break;
	  case 0x2:
		$this->setRW(self::IMG_COLOR_SPACE_FMT, _('Adobe RGB')); break;
	  case 0xfffd:
		$this->setRW(self::IMG_COLOR_SPACE_FMT, _('Wide Gamut RGB')); break;
	  case 0xfffe:
		$this->setRW(self::IMG_COLOR_SPACE_FMT, _('ICC Profile')); break;
	  case 0xfffe:
		$this->setRW(self::IMG_COLOR_SPACE_FMT, _('Uncalibrated')); break;
		
	  }
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_TIME)])) {
	  $exposure = $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_TIME)];
	  if(!$this->isSet(self::IMG_EXPOSURE)) $this->setRW(self::IMG_EXPOSURE, self::fracToArray($exposure));
	  if(!$this->isSet(self::IMG_EXPOSURE_FMT))
		$this->setRW(self::IMG_EXPOSURE_FMT, self::nrmFrac($exposure).' '._('second(s)'));
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_MODE)]) &&
	   !$this->isSet(self::IMG_EXPOSURE_MODE_FMT)) {
	  switch($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_MODE)]) {
	  case 0:
		$this->setRW(self::IMG_EXPOSURE_MODE_FMT, _('Auto')); break;
	  case 1:
		$this->setRW(self::IMG_EXPOSURE_MODE_FMT, _('Manual')); break;
	  case 2:
		$this->setRW(self::IMG_EXPOSURE_MODE_FMT, _('Auto bracket')); break;
	  }
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_PROGRAM)]) &&
	   !$this->isSet(self::IMG_EXPOSURE_PGM_FMT)) {
	  switch($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_PROGRAM)]) {
	  case 0:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Not defined')); break;
	  case 1:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Manual')); break;
	  case 2:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Program')); break;
	  case 3:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Aperture-priority')); break;
	  case 4:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Shutter speed priority')); break;
	  case 5:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Creative (slow speed)')); break;
	  case 6:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Action (high speed)')); break;
	  case 7:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Portrait')); break;
	  case 8:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Landscape')); break;
	  case 9:
		$this->setRW(self::IMG_EXPOSURE_PGM_FMT, _('Bulb')); break;
	  }
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FLASH)])) {
	  $flash  =$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FLASH)];
	  if(!$this->isSet(self::IMG_FLASH)) $this->setRW(self::IMG_FLASH, (int)$flash);
	  if(!$this->isSet(self::IMG_FLASH_FMT))
		$this->setRW(self::IMG_FLASH_FMT, (int)$flash === 1 ? _('Flash') : _('No flash'));
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FOCAL_LENGTH)])) {
	  $focal_length = (int)self::calcFrac($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FOCAL_LENGTH)]);
	  if(!$this->isSet(self::IMG_FOCAL_LENGTH)) $this->setRW(self::IMG_FOCAL_LENGTH, $focal_length);
	  if(!$this->isSet(self::IMG_FOCAL_LENGTH_FMT))
		$this->setRW(self::IMG_FOCAL_LENGTH_FMT, $focal_length.' '._('mm'));
	}
	if(!$this->isSet(self::IMG_HEIGHT)) {
	  if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXIF_IMAGE_HEIGHT)])) {
		$height = (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXIF_IMAGE_HEIGHT)];
		$this->setRW(self::IMG_HEIGHT, $height);
	  }
	}
	else {
	  $height = $this->get(self::IMG_HEIGHT);
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_ISO_SPEED)]) && !$this->isSet(self::IMG_ISO))
	  $this->setRW(self::IMG_ISO, (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_ISO_SPEED)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_PHOTO_SENSITIVITY)]) && !$this->isSet(self::IMG_ISO)) {
	  // Note: SensitivtyType should be 2-7 or 0
	  $photo_sensitivity = (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_PHOTO_SENSITIVITY)];
	  $this->setRW(self::IMG_ISO, $photo_sensitivity);
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_MAKE)]) &&
	   !$this->isSet(self::IMG_LENS_MAKE))
	  $this->setRW(self::IMG_LENS_MAKE, $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_MAKE)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_MODEL)]) &&
	   !$this->isSet(self::IMG_LENS_MODEL))
	  $this->setRW(self::IMG_LENS_MODEL, $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_MODEL)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_SERIAL_NUMBER)]) &&
	   !$this->isSet(self::IMG_LENS_SERIAL))
	  $this->setRW(self::IMG_LENS_SERIAL, $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_SERIAL_NUMBER)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_METERING_MODE)]) &&
	   !$this->isSet(self::IMG_METERING_MODE_FMT)) {
	  switch($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_METERING_MODE)]) {
	  case 1:
		$this->setRW(self::IMG_METERING_MODE_FMT, _('Average')); break;
	  case 2:
		$this->setRW(self::IMG_METERING_MODE_FMT, _('Center-weighted average')); break;
	  case 3:
		$this->setRW(self::IMG_METERING_MODE_FMT, _('Spot')); break;
	  case 4:
		$this->setRW(self::IMG_METERING_MODE_FMT, _('Multi-spot')); break;
	  case 5:
		$this->setRW(self::IMG_METERING_MODE_FMT, _('Multi-segment')); break;
	  case 6:
		$this->setRW(self::IMG_METERING_MODE_FMT, _('Partial')); break;
	  case 255:
		$this->setRW(self::IMG_METERING_MODE_FMT, _('Other')); break;
	  default:
		$this->setRW(self::IMG_METERING_MODE_FMT, _('Unknown')); break;
	  }
	}
	if(!$this->isSet(self::IMG_RESOLUTION_UNIT)) {
	  if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_RESOLUTION_UNIT)])) {
		$resolution_unit = (int)$exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_RESOLUTION_UNIT)];
		$this->setRW(self::IMG_RESOLUTION_UNIT, $resolution_unit);
	  }
	  else {
		$resolution_unit = 0;
	  }
	}
	else {
	  $resolution_unit = $this->get(self::IMG_RESOLUTION_UNIT);
	}
	switch($resolution_unit) {
	case 1:
	case 3:
	  $resolution_per_cm = 1.0; break;
	default:
	  $resolution_per_cm = 2.54; break;
	}
	// Note: We give preference to XResolution ofer YResolution
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_XRESOLUTION)]) &&
	   !$this->isSet(self::IMG_RESOLUTION)) {
	  $resolution = (int)self::calcFrac($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_XRESOLUTION)]);
	  $this->setRW(self::IMG_RESOLUTION, $resolution);
	  $this->setRW(self::IMG_RESOLUTION_FMT, $resolution.' '.($resolution_per_cm == 1 ? _('dpcm') : _('dpi')));
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_YRESOLUTION)]) &&
	   !$this->isSet(self::IMG_RESOLUTION)) {
	  $resolution = (int)self::calcFrac($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_YRESOLUTION)]);
	  $this->setRW(self::IMG_RESOLUTION, $resolution);
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_SOFTWARE)]) && !$this->isSet(self::IMG_SOFTWARE))
	  $this->setRW(self::IMG_SOFTWARE, $exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_SOFTWARE)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_PROCESSING_SOFTWARE)]) &&
	   !$this->isSet(self::IMG_SOFTWARE))
	  $this->setRW(self::IMG_SOFTWARE, $exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_PROCESSING_SOFTWARE)]);
	$this->setRW(self::IMG_TYPE, IMG_JPG);
	$this->setRW(self::IMG_TYPE_FMT, 'jpeg');
	if(!$this->isSet(self::IMG_WIDTH)) {
	  if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXIF_IMAGE_WIDTH)])) {
		$width = (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXIF_IMAGE_WIDTH)];
		$this->setRW(self::IMG_WIDTH, $height);
	  }
	}
	else {
	  $width = $this->get(self::IMG_WIDTH);
	}
	if(isset($width) && isset($height)) {
	  if($width > $height) $this->setRW(self::IMG_ORIENTATION, self::IMG_ORI_HORIZONTAL);
	  elseif($width < $height) $this->setRW(self::IMG_ORIENTATION, self::IMG_ORI_VERTICAL);
	  else $this->setRW(self::IMG_ORIENTATION, self::IMG_ORI_SQUARE);
	}
	else {
	  $this->setRW(self::IMG_ORIENTATION, self::IMG_ORI_UNKNOWN);
	}
	if(isset($width) && isset($height)) {
	  $size_fmt = "$width x $height "._('px');
	  if(isset($resolution)) {
		$size_fmt .= ' - '.number_format($resolution_per_cm * $width / $resolution, 2).' x '.
		  number_format($resolution_per_cm * $height / $resolution, 2).' '._('cm');
	  }
	  if($this->isSet(self::FILE_SIZE)) {
		$file_size = $this->get(self::FILE_SIZE);
		if($file_size > 1024 * 1024)
		  $size_fmt .= ' ('.number_format($file_size / 1024 / 1024, 0).' MB)';
		elseif($file_size > 1024)
		  $size_fmt .= ' ('.number_format($file_size / 1024, 0).' KB)';
		else
		  $size_fmt .= ' ('.number_format($file_size / 1024, 2).' KB)';
	  }
	  $this->setRW(self::IMG_SIZE_FMT, $size_fmt);
	}

	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_DATE_TIME_ORIGINAL)]) &&
	   !$this->isSet(self::CREATED_DATETIME))
	  $this->setRW(self::CREATED_DATETIME,
				   strtotime($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_DATE_TIME_ORIGINAL)]));
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_CREATE_DATE)]) &&
	   !$this->isSet(self::CREATED_DATETIME))
	  $this->setRW(self::CREATED_DATETIME,
				   strtotime($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_CREATE_DATE)]));
  }

  /**
   * Read EXIF metadata from file
   * Function may be extended to read additional fields and/or recoginiez additional tags
   *
   * @access protected
   * @throw  Exception Invalid metadata read
   */
  protected function exportExif(): void
  {
	$exif_ary = array();
	if($this->isSet(self::CAPTION))
	  $exif_ary[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_IMAGE_DESCRIPTION)] = $this->get(self::CAPTION);
	if($this->isSet(self::COPYRIGHT))
	  $exif_ary[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_COPYRIGHT)] = $this->get(self::COPYRIGHT);
	if($this->isSet(self::AUTHOR))
	  $exif_ary[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_ARTIST)] = $this->get(self::AUTHOR);
	if($this->isSet(self::AUTHOR))
	  $exif_ary[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_OWNER_NAME)] = $this->get(self::AUTHOR);

	if(!empty($exif_ary)) $this->jpeg->setExifData($exif_ary);
  }
}
?>
