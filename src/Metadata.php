<?php
  /**
   * Metadata.php - Image file metadata handing
   * 
   * @project   Holiday\Metadata
   * @version   1.0
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
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
  
  const VERSION = '1.0.0';

  /** Fielt types */
  const TYPE_INVALID = 0;
  const TYPE_STR = 1;
  const TYPE_INT = 2;
  const TYPE_ARY = 3;

  /** File specific fields: read only */
  const FILE_NAME            = 001;            /** String: Filename */
  const FILE_EXT             = 002;            /** String: File extension */
  const FILE_SIZE            = 003;            /** Int: File size */
  const FILE_DATE            = 004;            /** Int: Last modification date */

  /** IPTC/XMP fields: read/write */
  const FIELD_ID_WRITE_FIRST = 100;            /** First field identifier that can be modified */
  const FIELD_ID_WRITE_LAST  = 132;            /** Last field identifier that can be modified */
  
  const AUTHOR               = 101;            /** String: Creator (name of photographer) */
  const PHOTOGRAPHER         = 101;            /** - String: Creator (name of photographer) */
  const AUTHOR_TITLE         = 102;            /** String: Creator's job title */
  const PHOTOGRAPHER_TITLE   = 102;            /** - String: Creator's job title */
  const CAPTION              = 103;            /** String:  Description/Caption */
  const CAPTION_WRITER       = 104;            /** String: Description writer */
  const CATEGORY             = 105;            /** String: Category - Max 3 characters */
  const CITY                 = 106;            /** String: City */
  const COPYRIGHT            = 107;            /** String: Copyright notice */
  const COUNTRY              = 108;            /** String: Country name */
  const COUNTRY_CODE         = 109;            /** String: ISO country code*/
  const CREDIT               = 110;            /** String: Credit Line */
  const EDIT_STATUS          = 111;            /** String: Edit Status - Max 64 characters */
  const EVENT                = 112;            /** String: Event identifier */
  const GENRE                = 113;            /** Array: Genre */
  const HEADLINE             = 114;            /** String: Headline */
  const INSTRUCTIONS         = 115;            /** String: Instructions */
  const KEYWORDS             = 116;            /** Array: Keywords */
  const LOCATION             = 117;            /** String: Location */
  const OBJECT               = 118;            /** String: Object name (Title)*/
  const ORG_CODE             = 119;            /** Array: Code of Organization in image */
  const NAT                  = 119;            /**  - Array: Nationalities */
  const ORG_NAME             = 120;            /** Array: Name of Organization in image */
  const ORG                  = 120;            /**  - Array: Organizations/Teams in image */
  const PERSON               = 121;            /** Array: Person shown in image */
  const PEOPLE               = 121;            /** - Array: Person shown in image */
  const PERSONALITY          = 121;            /** - Array: Person shown in image (Getty terminology) */
  const PRIORITY             = 122;            /** Int: Urgency - 1 numeric character */
  const RATING               = 123;            /** Int: Numeric image rating, -1 (rejected), 0..5 */
  const SCENES               = 124;            /** Array: Scene codes*/
  const SOURCE               = 125;            /** String: Source */
  const STATE                = 126;            /** String: Providence/State */
  const SUBJECT_CODE         = 127;            /** Array: Subject code */
  const SUPP_CATEGORY_A      = 128;            /** String: Supplemental Category 1 */
  const SUPP_CATEGORY_B      = 129;            /** String: Supplemental Category 2 */
  const SUPP_CATEGORY_C      = 130;            /** String: Supplemental Category 3 */
  const TRANSFER_REF         = 131;            /** String: Original Transmission Reference - Max 32 characters */
  const USAGE_TERMS          = 132;            /** String: Rights Usage Terms */
  
  /** IPTC/XMP fiels: read only */
  const CREATED_DATETIME     = 201;            /** Int: Timestamp when photo was created */

  /** Image data fields: read only **/
  const IMG_APERTURE         = 301;            /** String: Aperture (f/X) */
  const IMG_CAMERA_MAKE      = 302;            /** String: Camera brand */
  const IMG_CAMERA_MODEL     = 303;            /** String: Camera model */
  const IMG_CAMERA_SERIAL    = 304;            /** Stringg: Camera serial number */
  const IMG_COLOR_SPACE      = 305;            /** String: Color space */
  const IMG_EXPOSURE         = 306;            /** String: Exposure */
  const IMG_EXPOSURE_MODE    = 307;            /** String/Int: Exposure mode */
  const IMG_EXPOSURE_PROGRAM = 308;            /** String/Int: Exposure setting */
  const IMG_FLASH            = 309;            /** Int: Flash used */
  const IMG_FOCAL_LENGTH     = 310;            /** Int: Focal length */
  const IMG_HEIGHT           = 311;            /** Int: Image height */
  const IMG_ISO              = 312;            /** Int: ISO */
  const IMG_LENS_MAKE        = 313;            /** String: Lens brand */
  const IMG_LENS_MODEL       = 314;            /** String: Lens name */
  const IMG_LENS_SERIAL      = 315;            /** String: Lens serial number */
  const IMG_ORIENTATION      = 316;            /** Int: Orientation */
  const IMG_RESOLUTION       = 317;            /** Int: Image resolution */
  const IMG_SIZE_FORMATTED   = 318;            /** String: Formatted image size ( W x H px - X x Y cm (x MB) */
  const IMG_SOFTWARE         = 319;            /** String: Software used */
  const IMG_TYPE             = 320;            /** Int: Image type (see imagetypes() for constants) */
  const IMG_WIDTH            = 321;            /** Int: Image width */
  const IMG_METERING_MODE    = 322;            /** String/Int: Merering model */

  /** Orientation encoding: IMG_ORIENTATION */
  const IMG_ORI_VERTICAL     = 1;
  const IMG_ORI_HORIZONTAL   = 2;
  const IMG_ORI_SQUARE       = 3;
  const IMG_ORI_UNKNOWN      = -1;

  /** Private variables */
  protected bool          $data_read;          /** Has data been loaded/read */
  protected array         $data;               /** Source agnostic data */
  protected Metadata\Jpeg $jpeg;               /** Jpeg object */

  /**
   * Consturctor
   */
  public function __construct()
  {
	$this->data_read = false;
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
   * @param string $filename Filename to read metadata from
   * @param bool  $extend   Extend IPTC keyword data into XMP specific fields, e.g. Event:, Scene:, Genre:,
   * @throw Exception
   */
  public function read(string $filename, bool $extend = false): void
  {
	// Re-initialize data
	$this->data_read = false;
	$this->data = array();

	// Get file specific fields
	if(!file_exists($filename)) throw new Exception(_('File not found'), Exception::FILE_NOT_FOUND, $filename);

	// Read and set file specific data
	$pathinfo = pathinfo($filename);
	$this->setRW(self::FILE_NAME, $pathinfo['basename']);
	$this->setRW(self::FILE_EXT,  $pathinfo['extension']);
	$this->setRW(self::FILE_SIZE, filesize($filename));
	$this->setRW(self::FILE_DATE, filemtime($filename));

	// Read JPG data
	$this->jpeg->read($filename);

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
   * @param  int $field_id Field identifier
   * @return string|int|array|false Field value
   * @throw  Metadate\Exception
   */
  public function get(int $field_id): string|int|array|false
  {
	if(self::fieldType($field_id) === self::TYPE_INVALID)
	  throw new Exception(_('Invalid field identifier specified'), Exception::INVALID_FIELD_ID,
								   $field_id);
	
	if(isset($this->data[$field_id])) return $this->data[$field_id];
	return false;
  }

  /**
   * Save data associated with a given field identifier
   *
   * @param int                    $field_id     Field identifier
   * @param string|int|array|false $field_value  Field value
   * @throw Exception
   */
  public function set(int $field_id, string|int|array|false $field_value): void
  {
	$this->setRW($field_id, $field_value, ignore_write: false);
  }
  
  /**
   * Save data associated with a given field identifier
   *
   * @access private
   * @param  int                    $field_id     Field identifier
   * @param  string|int|array|false $field_value  Field value
   * @param  bool                   $ignore_write Ignore write check
   * @throw  Exception
   */
  private function setRW(int $field_id, string|int|array|false $field_value, bool $ignore_write = true): void
  {
	if(self::fieldType($field_id) === self::TYPE_INVALID)
	  throw new Exception(_('Invalid field identifier specified'), Exception::INVALID_FIELD_ID,
								   $field_id);

	if(!self::isValidFieldType($field_id, $field_value) &&
	   !(self::fieldType($field_id) === self::TYPE_ARY && !is_array($field_value)) && $field_value !== false)
	  throw new Exception(_('Invalid type of field value identifier specified'),
								   Exception::INVALID_FIELD_ID, $field_id);
	
	if(!$ignore_write && ($field_id < self::FIELD_ID_WRITE_FIRST || $field_id > self::FIELD_ID_WRITE_LAST))
	  throw new Exception(_('Field is not writable'), Exception::INVALID_FIELD_WRITE, $field_id);

	// Setting field to false is identical to dropping field
	if($field_value === false) {
	  $this->drop($field_id, $field_value);
	  return;
	}

	// Add/update field value
	if(self::fieldType($field_id) === self::TYPE_ARY) {
	  if(isset($this->data[$field_id]) && !is_array($field_value)) {
		// If field type is array and field value is not, add/update the value to the array
		if(!in_array($field_value, $this->data[$field_id], strict: true))
		  $this->data[$field_id][] = $field_value;
	  }
	  else {
		// Replace all values
		$this->drop($field_id);
		foreach($field_value as $field_subvalue) {
		  $this->data[$field_id][] = $field_subvalue;
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
   */
  public function dropAll(): void
  {
	$this->data = array();
  }
  
  /**
   * Drop data associated with a given field identifier
   *
   * @param int           $field_id     Field identifier
   * @param string|false  $fiel_value   Field value
   * @param bool          $ignore_write Ignore write check
   * @throw Exception
   */
  public function drop(int $field_id, string|false $field_value = false): void
  {
	if(self::fieldType($field_id) === self::TYPE_INVALID)
	  throw new Exception(_('Invalid field identifier specified'), Exception::INVALID_FIELD_ID,
								   $field_id);
	
	if(self::fieldType($field_id) !== self::TYPE_ARY && $field_value !== false) 
	  throw new Exception(_('Only individual values of arrays can be dropped'),
								   Exception::INVALID_FIELD_ID, $field_id);
	
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
   * @param  int  $field_id Field identifier
   * @param string|false  $fiel_value   Field value
   * @return bool Is field already set
   * @throw Exception
   */
  public function isSet(int $field_id, string|false $field_value = false): bool
  {
	if(self::fieldType($field_id) === self::TYPE_INVALID)
	  throw new Exception(_('Invalid field identifier specified'), Exception::INVALID_FIELD_ID,
								   $field_id);

	if($field_value === false) return isset($this->data[$field_id]);
	$field_pos = array_search($field_value, $this->data[$field_id], strict: true);
	return !($field_pos === false);
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
	case self::IMG_TYPE:
	case self::IMG_WIDTH:
	  return self::TYPE_INT;
	  
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
	case self::IMG_APERTURE:
	case self::IMG_CAMERA_MAKE:
	case self::IMG_CAMERA_MODEL:
	case self::IMG_CAMERA_SERIAL;
	case self::IMG_COLOR_SPACE:
	case self::IMG_EXPOSURE:
	case self::IMG_EXPOSURE_MODE:
	case self::IMG_EXPOSURE_PROGRAM:
	case self::IMG_LENS_MAKE:
	case self::IMG_LENS_MODEL:
	case self::IMG_LENS_SERIAL:
	case self::IMG_SIZE_FORMATTED:
	case self::IMG_SOFTWARE:
	case self::IMG_METERING_MODE:
	  return self::TYPE_STR;
	default:
	  return self::TYPE_INVALID;
	}
  }

  /**
   * Return if the type asssociated with a given field value is valid
   *
   * @param int                    $field_id    Field identifier
   * @param string|int|array|false $field_value Field value
   * @return bool Isd the type of the value appropriate for the field
   */
  public static function isValidFieldType(int $field_id, string|int|array|false $field_value): bool
  {
	switch(self::fieldType($field_id)) {
	case self::TYPE_STR:
	  return gettype($field_value) === 'string';
	case self::TYPE_INT:
	  return gettype($field_value) === 'integer';
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
	if(isset($iptc_data[Iptc::CAPTION][0]) && !$this->isSet(self::CAPTION))
	  $this->set(self::CAPTION, $iptc_data[Iptc::CAPTION][0]);
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
													 $iptc_data[Iptc::CREATED_TIME][0]), true);
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
	if($this->isSet(self::CAPTION)) $iptc_data[Iptc::CAPTION][0] = $this->get(self::CAPTION);
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
	if(!$this->isSet(self::CAPTION) && $xmp_data->isXmpText(Xmp::CAPTION))
	  $this->set(self::CAPTION, $xmp_data->getXmpText(Xmp::CAPTION));
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
	if(!$this->isSet(self::HEADLINE) && $xmp_data->isXmpText(Xmp::HEADLINE))
	  $this->set(self::HEADLINE, $xmp_data->getXmpText(Xmp::PS_HEADLINE));
	if(!$this->isSet(self::HEADLINE) && $xmp_data->isXmpText(Xmp::PS_HEADLINE))
	  $this->set(self::HEADLINE, $xmp_data->getXmpText(Xmp::HEADLINE));
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
	if(!$this->isSet(self::CREATED_DATETIME) && $xmp_data->isXmpText(Xmp::PS_CREATED_DATETIME))
	  $this->set(self::CREATED_DATETIME, strtotime($xmp_data->getXmpText(Xmp::PS_CREATED_DATETIME)));
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
	  $this->setRW(self::IMG_CAMERA_SERIAL, $xmp_data->getXmpText(Xmp::CAMERA_SERIAL), true);
	if(!$this->isSet(self::IMG_LENS_MODEL) && $xmp_data->isXmpText(Xmp::LENS_MODEL))
	  $this->setRW(self::IMG_LENS_MODEL, $xmp_data->getXmpText(Xmp::LENS_MODEL), true);
	if(!$this->isSet(self::IMG_LENS_SERIAL) && $xmp_data->isXmpText(Xmp::LENS_SERIAL))
	  $this->setRW(self::IMG_LENS_SERIAL, $xmp_data->getXmpText(Xmp::LENS_SERIAL), true);
	if(!$this->isSet(self::IMG_COLOR_SPACE) && $xmp_data->isXmpText(Xmp::COLOR_SPACE))
	  $this->setRW(self::IMG_COLOR_SPACE, $xmp_data->getXmpText(Xmp::COLOR_SPACE), true);
	
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
	$xmp_data->setXmpAlt(Xmp::CAPTION, $this->get(self::CAPTION));
	if($xmp_data->isXmpText(Xmp::PS_CAPTION_WRITER))
	  $xmp_data->setXmpText(Xmp::PS_CAPTION_WRITER, $this->get(self::CAPTION_WRITER));
	$xmp_data->setXmpText(Xmp::PS_CATEGORY, $this->get(self::CATEGORY));
	$xmp_data->setXmpText(Xmp::CITY, $this->get(self::CITY));
	if($xmp_data->isXmpText(Xmp::PS_CITY)) $xmp_data->setXmpText(Xmp::PS_CITY, $this->get(self::CITY));
	$xmp_data->setXmpAlt(Xmp::COPYRIGHT, $this->get(self::COPYRIGHT));
	$xmp_data->setXmpText(Xmp::COUNTRY, $this->get(self::COUNTRY));
	if($xmp_data->isXmpText(Xmp::PS_COUNTRY)) $xmp_data->setXmpText(Xmp::PS_COUNTRY, $this->get(self::COUNTRY));
	$xmp_data->setXmpText(Xmp::COUNTRY_CODE, $this->get(self::COUNTRY_CODE));
	$xmp_data->setXmpText(Xmp::CREDIT, $this->get(self::CREDIT));
	if($xmp_data->isXmpText(Xmp::PM_EDIT_STATUS))
	  $xmp_data->setXmpText(Xmp::PM_EDIT_STATUS, $this->get(self::EDIT_STATUS));
	$xmp_data->setXmpText(Xmp::GENRE, self::arrayToString($this->get(self::GENRE)));
	$xmp_data->setXmpText(Xmp::HEADLINE, $this->get(self::HEADLINE));
	if($xmp_data->isXmpText(Xmp::PS_HEADLINE)) $xmp_data->setXmpText(Xmp::PS_HEADLINE, $this->get(self::HEADLINE));
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
	if($xmp_data->isXmpText(Xmp::PS_TRANSFER_REF))
	  $xmp_data->setXmpText(Xmp::PS_TRANSFER_REF, $this->get(self::TRANSFER_REF));
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
   * @return int|false Rounded value dividing x by y
   */
  protected static function calcFrac(string $data): int|false
  {
	if(strpos($data, '/') === 0) return false;
	list($nom, $denom) = explode('/', $data, 2);
	return (int)round((int)$nom / (int)$denom);
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
	$this->setRW(self::IMG_TYPE, IMG_JPG);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF,Exif::TAG_EXIF_EXIF_IMAGE_WIDTH)]) &&
	   !$this->isSet(self::IMG_WIDTH)) {
	  $width = (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXIF_IMAGE_WIDTH)];
	  $this->setRW(self::IMG_WIDTH, $width);
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXIF_IMAGE_HEIGHT)]) &&
	   !$this->isSet(self::IMG_HEIGHT)) {
	  $height = (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXIF_IMAGE_HEIGHT)];
	  $this->setRW(self::IMG_HEIGHT, $height);
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_RESOLUTION_UNIT)])) {
	  switch((int)$exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_RESOLUTION_UNIT)]) {
	  case 1:
	  case 3:
		$resolution_cm = 1.0;
		break;
	  case 2:
		$resolution_cm = 2.54;
		break;
	  }
	}
	else {
	  $resolution_cm = 2.54;
	}
	// Note: We use XResolution as the image resolution, ignoring YResolution
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_XRESOLUTION)]) &&
	   !$this->isSet(self::IMG_RESOLUTION)) {
	  $resolution = self::calcFrac($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_XRESOLUTION)]);
	  $this->setRW(self::IMG_RESOLUTION, $resolution);
	}
	if(isset($width) && isset($height)) {
	  $size = "$width x $height px";
	  if($width > $height) $this->setRW(self::IMG_ORIENTATION, self::IMG_ORI_HORIZONTAL);
	  if($width < $height) $this->setRW(self::IMG_ORIENTATION, self::IMG_ORI_VERTICAL);
	  if($width === $height) $this->setRW(self::IMG_ORIENTATION, self::IMG_ORI_SQUARE);
	  if(isset($resolution) && isset($resolution_cm)) {
		$size .= ' - '.number_format($resolution_cm * $width / $resolution, 2).' x '.
		  number_format($resolution_cm * $height / $resolution, 2).' cm ('.
		  number_format($this->get(self::FILE_SIZE) / 1024 / 1024, 0).' MB)';
	  }
	  $this->setRW(self::IMG_SIZE_FORMATTED, $size);
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_COLOR_SPACE)]) &&
	   !$this->isSet(self::IMG_COLOR_SPACE)) {
	  switch((int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_COLOR_SPACE)]) {
	  case 0x1:
		$this->setRW(self::IMG_COLOR_SPACE, _('sRGB'));
		break;
	  case 0x2:
		$this->setRW(self::IMG_COLOR_SPACE, _('Adove RGB'));
		break;
	  case 0xfffd:
		$this->setRW(self::IMG_COLOR_SPACE, _('Wide Gamut RGB'));
		break;
	  case 0xfffe:
		$this->setRW(self::IMG_COLOR_SPACE, _('ICC Profile'));
		break;
	  case 0xfffe:
		$this->setRW(self::IMG_COLOR_SPACE, _('Uncalibrated'));
		break;
		
	  }
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_CAMERA_MAKE)]) &&
	   !$this->isSet(self::IMG_CAMERA_MAKE))
	  $this->setRW(self::IMG_CAMERA_MAKE, $exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_CAMERA_MAKE)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_CAMERA_MODEL)]) &&
	   !$this->isSet(self::IMG_CAMERA_MODEL))
	  $this->setRW(self::IMG_CAMERA_MODEL, $exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_CAMERA_MODEL)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_CAMERA_SERIAL_NUMBER)]) &&
	   !$this->isSet(self::IMG_CAMERA_SERIAL))
	  $this->setRW(self::IMG_CAMERA_SERIAL,
				   $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_CAMERA_SERIAL_NUMBER)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_MAKE)]) &&
	   !$this->isSet(self::IMG_LENS_MAKE))
	  $this->setRW(self::IMG_LENS_MAKE, $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_MAKE)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_MODEL)]) &&
	   !$this->isSet(self::IMG_LENS_MODEL))
	  $this->setRW(self::IMG_LENS_MODEL, $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_MODEL)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_SERIAL_NUMBER)]) &&
	   !$this->isSet(self::IMG_LENS_SERIAL))
	  $this->setRW(self::IMG_LENS_SERIAL,
				   $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_LENS_SERIAL_NUMBER)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_SOFTWARE)]) &&
	   !$this->isSet(self::IMG_SOFTWARE))
	  $this->setRW(self::IMG_SOFTWARE, $exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_SOFTWARE)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_PROCESSING_SOFTWARE)]) &&
	   !$this->isSet(self::IMG_SOFTWARE))
	  $this->setRW(self::IMG_SOFTWARE, $exif_data[Exif::tag(Exif::IFD_IFD0, Exif::TAG_IFD0_PROCESSING_SOFTWARE)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FNUMBER)]) &&
	   !$this->isSet(self::IMG_APERTURE)) {
	  $aperture = 'f/'.
		number_format(self::calcFrac($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FNUMBER)]), 1);
	  $this->setRW(self::IMG_APERTURE, $aperture);
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_APERTURE_VALUE)]) &&
	   !$this->isSet(self::IMG_APERTURE)) {
	  $aperture = 'f/'.
		number_format(self::calcFrac($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_APERTURE_VALUE)]), 1);
	  $this->setRW(self::IMG_APERTURE, $aperture);
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_TIME)]) &&
	   !$this->isSet(self::IMG_EXPOSURE))
	  $this->setRW(self::IMG_EXPOSURE, $exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_TIME)].
				   ' '._('second(s)'));
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_PROGRAM)]) &&
	   !$this->isSet(self::IMG_EXPOSURE_PROGRAM)) {
	  switch($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_PROGRAM)]) {
	  case 0:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Not defined'));
		break;
	  case 1:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Manual'));
		break;
	  case 2:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Program AE'));
		break;
	  case 3:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Aperture-priority AE'));
		break;
	  case 4:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Shutter speed priority AE'));
		break;
	  case 5:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Creative (Slow speed)'));
		break;
	  case 6:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Action (High speed)'));
		break;
	  case 7:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Portrait'));
		break;
	  case 8:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Landscape'));
		break;
	  case 9:
		$this->setRW(self::IMG_EXPOSURE_PROGRAM, _('Bulb'));
		break;
	  }
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_MODE)]) &&
	   !$this->isSet(self::IMG_EXPOSURE_MODE)) {
	  switch($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_EXPOSURE_MODE)]) {
	  case 0:
		$this->setRW(self::IMG_EXPOSURE_MODE, _('Auto'));
		break;
	  case 1:
		$this->setRW(self::IMG_EXPOSURE_MODE, _('Manual'));
		break;
	  case 2:
		$this->setRW(self::IMG_EXPOSURE_MODE, _('Auto bracket'));
		break;
	  }
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_METERING_MODE)]) &&
	   !$this->isSet(self::IMG_METERING_MODE)) {
	  switch($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_METERING_MODE)]) {
	  case 1:
		$this->setRW(self::IMG_METERING_MODE, _('Average'));
		break;
	  case 2:
		$this->setRW(self::IMG_METERING_MODE, _('Center-weighted average'));
		break;
	  case 3:
		$this->setRW(self::IMG_METERING_MODE, _('Spot'));
		break;
	  case 4:
		$this->setRW(self::IMG_METERING_MODE, _('Multi-spot'));
		break;
	  case 5:
		$this->setRW(self::IMG_METERING_MODE, _('Multi-segment'));
		break;
	  case 6:
		$this->setRW(self::IMG_METERING_MODE, _('Partial'));
		break;
	  case 255:
		$this->setRW(self::IMG_METERING_MODE, _('Other'));
		break;
	  default:
		$this->setRW(self::IMG_METERING_MODE, _('Unknown'));
		break;
	  }
	}
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FOCAL_LENGTH)]) &&
	   !$this->isSet(self::IMG_FOCAL_LENGTH))
	  $this->setRW(self::IMG_FOCAL_LENGTH,
				   (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FOCAL_LENGTH)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FLASH)]) && !$this->isSet(self::IMG_FLASH))
	  $this->setRW(self::IMG_FLASH, (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_FLASH)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_ISO_SPEED)]) && !$this->isSet(self::IMG_ISO))
	  $this->setRW(self::IMG_ISO, (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_ISO_SPEED)]);
	if(isset($exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_ISO)]) && !$this->isSet(self::IMG_ISO))
	  $this->setRW(self::IMG_ISO, (int)$exif_data[Exif::tag(Exif::IFD_EXIF, Exif::TAG_EXIF_ISO)]);

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
