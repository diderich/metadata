<?php
  /**
   * Exif.php - Decode EXIF data from JPG segment APP1
   * 
   * @package   Holiday\Metadata
   * @version   1.0
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
   *
   * @see       https://www.ozhiker.com/electronics/pjmt/
   * @see       https://exiftool.org/TagNames/EXIF.html
   */

  /**
   * NOTE:
   * - The Exif class 'encode' clears (fills them with \0x00) the four user-modifiable fields caption/description, 
   *   copyright, author/artist, and owner name, rather than updating them with user data.
   * - The class does not decode all TIFF/EXIF tags. It only focuses on those identified by constants. There is not aim
   *   to correctly decode more/other tags. Nevertheless, all tags are read by 'decode' and returned. Undecoded data is
   *   returned as human readable hexabyte strings.
   * - The class assumes that any pointer to data or an IFD block is within the same segment.
   */

namespace Holiday\Metadata;
use Holiday\Metadata;

class Exif {

  /** Tag names */
  const IFD_ROOT = 'IFD';                   /** IFD0/IFD1: Root IFD bock(s) */
  const IFD_IFD0 = 'IFD0';
  const IFD_EXIF = 'EXIF';

  // - IFD Pointers
  const TAG_PTR_SUB_IFD = 0x014a;
  const TAG_PTR_GLOBAL_PARAMETERS_IFD = 0x0190;
  const TAG_PTR_KODAK_IFD = 0x8290;
  const TAG_PTR_JPL_CARTO_IFD = 0x85d7;
  const TAG_PTR_EXIF_IFD = 0x8769;
  const TAG_PTR_LEAF_SUB_IFD = 0x888a;
  const TAG_PTR_KDC_IFD = 0xfe00;
	  
  // - IFD0 (image)
  const TAG_IFD0_PROCESSING_SOFTWARE = 0x000b;
  const TAG_IFD0_IMAGE_DESCRIPTION = 0x010e;
  const TAG_IFD0_CAMERA_MAKE = 0x010f;
  const TAG_IFD0_CAMERA_MODEL = 0x0110;
  const TAG_IFD0_ORIENTATION = 0x0112;
  const TAG_IFD0_XRESOLUTION = 0x011a;
  const TAG_IFD0_YRESOLUTION = 0x011b;
  const TAG_IFD0_RESOLUTION_UNIT = 0x0128;
  const TAG_IFD0_SOFTWARE = 0x0131;
  const TAG_IFD0_ARTIST = 0x013b;
  const TAG_IFD0_COPYRIGHT = 0x8298;
  
  // - ExifIFD
  const TAG_EXIF_EXPOSURE_TIME = 0x829a;
  const TAG_EXIF_FNUMBER = 0x829d;
  const TAG_EXIF_EXPOSURE_PROGRAM = 0x8822;
  const TAG_EXIF_ISO_SPEED = 0x8833;
  const TAG_EXIF_PHOTO_SENSITIVITY = 0x8827;
  const TAX_EXIF_SENSITIVITY_TYPE = 0x8830;
  const TAG_EXIF_DATE_TIME_ORIGINAL = 0x9003;
  const TAG_EXIF_CREATE_DATE = 0x9004;
  const TAG_EXIF_APERTURE_VALUE = 0x9202;
  const TAG_EXIF_METERING_MODE = 0x9207;
  const TAG_EXIF_FLASH = 0x9209;
  const TAG_EXIF_FOCAL_LENGTH = 0x920a;
  const TAG_EXIF_COLOR_SPACE = 0xa001;
  const TAG_EXIF_EXIF_IMAGE_WIDTH = 0xa002;
  const TAG_EXIF_EXIF_IMAGE_HEIGHT = 0xa003;
  const TAG_EXIF_EXPOSURE_MODE = 0xa402;
  const TAG_EXIF_OWNER_NAME = 0xa430;
  const TAG_EXIF_CAMERA_SERIAL_NUMBER = 0xa431;
  const TAG_EXIF_LENS_INFO = 0xa432;
  const TAG_EXIF_LENS_MAKE = 0xa433;
  const TAG_EXIF_LENS_MODEL = 0xa434;
  const TAG_EXIF_LENS_SERIAL_NUMBER = 0xa435;
  const TAG_EXIF_LENS = 0xfdea;
  const TAG_EXIF_SENSIVITY_TYPE = 0x8830;

  /** Data types */
  private const TYPE_UBYTE = 1;
  private const TYPE_ASCII = 2;
  private const TYPE_USHORT = 3;
  private const TYPE_ULONG = 4;
  private const TYPE_URAT = 5;
  private const TYPE_SBYTE = 6;
  private const TYPE_UNDEFINED = 7;
  private const TYPE_SSHORT = 8;
  private const TYPE_SLONG = 9;
  private const TYPE_SRAT = 10;
  private const TYPE_FLOAT = 11;
  private const TYPE_DOUBLE = 12;

  /** Byte alignment */
  private const EXIF_BYTE_ALIGN_LE = 0;   /** II: Intel - Litte endian */
  private const EXIF_BYTE_ALIGN_BE = 1;   /** MM: Motorola - Big endian */

  /** TIFF identifiers */
  const EXIF_TYPE = 'APP1';
  private const TIFF_ID = 42;
  
  /**
   * Decode all EXIF data segments, assuming each segment is self-contained, i.e., no IFD pointers point outside it
   *
   * @param  array  $segments Array of segments
   * @return array|false      Array of EXIF data, one per segment, or false, if not data was found
   * @throw  \Holiday\Metadata\Exception
  */
  public static function decode(array $segments): array|false
  {
	if(empty($segments)) return false;

	$exif_data_ary = array(); $any_data = false;
	foreach($segments as $segment_id => $segment) {
	  $exif_data_ary[$segment_id] = self::decodeSegment($segment);
	  $exif_data_ary[$segment_id] = empty($exif_data_ary[$segment_id]) ? false : $exif_data_ary[$segment_id];
	  $any_data = $any_data || !empty($exif_data_ary[$segment_id]);
	}
	return $any_data ? $exif_data_ary : false;
  }

  /***
   * Encode EXIF data segment.
   * NOTE:
   * - Only the following tags can be changed: Image Description, Copyright, Artist, Owner Name 
   * - The data is cleared, rather than updated
   *
   * @param  array       $segments       Array of original EXIF segments
   * @param  array       $exif_data_ary  Decoded EXIF data
   * @param  array|false $exif_ary       Array of EXIF data to update
   * @return string      String encoding the EXIF data segment
   * @throw  \Holiday\Metadata\Exception Exception thrown if an error in the data is found
   */
  public static function encode(array $segments, array $exif_data_ary, array|false $exif_ary): array
  {
	if($exif_ary !== false) {
	  // Check that only valid data elements are specified
	  foreach($exif_ary as $data) {
		switch($data['tag']) {
		case self::TAG_IFD0_IMAGE_DESCRIPTION:
		case self::TAG_IFD0_COPYRIGHT:
		case self::TAG_EXIF_OWNER_NAME:
		  break;
		case self::TAG_IFD0_ARTIST:
		  if(is_array($data['data'])) $data['data'] = implode("\x00", $data['data']);
		  break;
		default:
		  throw new Exception(_('Specified EXIF tag name is read-only'), Exception::INVALID_FIELD_WRITE, $data['tag']);
		}
	  }
	}
	// Overwrite existing data with \x00 if set
	$data_ary = array(array('block' => 'IFD0', 'tag' => self::TAG_IFD0_IMAGE_DESCRIPTION),
					  array('block' => 'IFD0', 'tag' => self::TAG_IFD0_COPYRIGHT),
					  array('block' => 'EXIF', 'tag' => self::TAG_EXIF_OWNER_NAME),
					  array('block' => 'IFD0', 'tag' => self::TAG_IFD0_ARTIST));
	foreach($exif_data_ary as $seg_id => $seg_data) {
	  foreach($seg_data as $elt) {
		foreach($data_ary as $data) {
		  if($data['block'] === $elt['block'] && $data['tag'] === $elt['tag']) {
			for($pos = 0; $pos < $elt['size']; $pos++) {
			  $segments[$seg_id][$elt['ptr'] + $pos] = "\x00";
			}
		  }
		}
	  }
	}
	return $segments;
  }

  /**
   * Return actual tag used to reference an EXIF field
   *
   * @access protected
   * @param  string $ifd IFD
   * @param  int    $tag Tag id
   * @return string Fully qualified tag identifier
   */
  public static function tag(string $ifd, int $tag): string
  {
	return $ifd.':'.substr('0000'.dechex($tag), -4);
  }

  /**
   * Return the byte alignment
   *
   * @access private
   * @param  string $byte_align_str Byte alignment string
   * @return int    Bye alignment encoding
   */
  private static function byteAlign(string $byte_align_str): int
  {
	switch($byte_align_str) {
	case 'II':
	  return self::EXIF_BYTE_ALIGN_LE;
	case 'MM':
	  return self::EXIF_BYTE_ALIGN_BE;
	default: 
	  throw new Exception(_('Invalid byte alignment read'), Exception::DATA_FORMAT_ERROR, $byte_align_str);
	}
  }
  
  /**
   * Decode a single EXIF data segment
   *
   * @access private
   * @param  string $segment Single EXIF data segment
   * @return array  Array of all read EXIF data elements
   */
  private static function decodeSegment(string $segment): array
  {
	// Check the size of the TIFF header
	if(strlen(substr($segment, 0, 8)) < 8)
	  throw new Exception(_('Invalid TIFF header size'), Exception::DATA_FORMAT_ERROR);

	// Extract the byte alignment encoding
	$byte_align =self::byteAlign(substr($segment, 0, 2));

	// Extract the TIFF ID of the segment
	$segment_id_bin = substr($segment, 2, 2);
	if(self::decodeIFDData($segment_id_bin, self::TYPE_USHORT, $byte_align) !== self::TIFF_ID)
	  throw new Exception(_('TIFF header ID not found'), Exception::DATA_FORMAT_ERROR, self::bin2hex($segment_id_bin));

	// Get the pointer to the first IFD block
	$ifd_block_pos = self::decodeIFDData(substr($segment, 4, 4), self::TYPE_ULONG, $byte_align);
	if($ifd_block_pos >= strlen($segment) && $ifd_block_pos !== 0)
	  throw new Exception(_('Error finding position of first IFD'), Exception::DATA_FORMAT_ERROR, $ifd_block_pos);

	// Decode root IFD block (and recursively all sub-blocks) and return result
	$exif_ary = self::decodeIFDBlock($segment, self::IFD_ROOT, $ifd_block_pos, $byte_align);
	return $exif_ary;
  }


  /**
   * Recursively decode a single IFD block
   *
   * @access private
   * @param  string $segment Single EXIF data segment
   * @param  string $ifd_block_name Name of the IFD block to decode
   * @param  int    $ifd_block_pos  Position where IFD block starts
   * @param  int    $byte_align     Byte alignment
   * @return array  Array of EXIF entries
   */
  private static function decodeIFDBlock(string $segment, string $ifd_block_name, int $ifd_block_pos,
										 int $byte_align): array
  {
	$exif_ary = array();
	$ifd_block_id = 0;
	do {
	  $block_name = $ifd_block_name === self::IFD_ROOT ? $ifd_block_name.$ifd_block_id : $ifd_block_name;
	  
	  // Decode numer of IDF tags
	  $nb_tags_bin = substr($segment, $ifd_block_pos, 2);
	  $nb_tags = self::decodeIFDData($nb_tags_bin, self::TYPE_USHORT, $byte_align);
	  if($nb_tags === 0) break;
	  
	  // Extract tag data from  IFD block
	  $ifd_data_tag = substr($segment, $ifd_block_pos + 2, 12 * $nb_tags);
	  for($tag = 0; $tag < $nb_tags; $tag++) {
		// Decode Tag ID
		$ifd_tag_id = self::decodeIFDData(substr($ifd_data_tag, 12 * $tag, 2), self::TYPE_USHORT, $byte_align);
		
		// Decode Tag data type
		$ifd_tag_type = self::decodeIFDData(substr($ifd_data_tag, 12 * $tag + 2, 2), self::TYPE_USHORT, $byte_align);

		// Decode Tag data size
		$ifd_tag_nb = self::decodeIFDData(substr($ifd_data_tag, 12 * $tag + 4, 4), self::TYPE_ULONG, $byte_align);

		// Decode Tag data
		$ifd_tag_data = self::decodeIFDData(substr($ifd_data_tag, 12 * $tag + 8, 4), self::TYPE_ULONG, $byte_align);
		if(self::blockName($ifd_tag_id) !== false) {
		  // We have a pointer to a new IFD block
			$exif_ary = array_merge($exif_ary, self::decodeIFDBlock($segment, self::blockName($ifd_tag_id),
																	$ifd_tag_data, $byte_align));
			continue;
		}
		elseif($ifd_tag_nb * self::getIFDTypeSize($ifd_tag_type) > 4) {
		  // We have a pointer to data
		  $ifd_tag_str = self::getIFDString(self::decodeIFDData(substr($segment, $ifd_tag_data, $ifd_tag_nb *
																	   self::getIFDTypeSize($ifd_tag_type)),
																$ifd_tag_type, $byte_align), $ifd_tag_type);
		}
		else {
		  // We have actual data
		  $ifd_tag_str = self::getIFDString($ifd_tag_data, $ifd_tag_type);
		}

		// Save data
		if($ifd_tag_nb * self::getIFDTypeSize($ifd_tag_type) > 4) {
		  $exif_ary[] = array('block' => $block_name, 'tag' => $ifd_tag_id, 'data' => $ifd_tag_str,
							  'ptr' => $ifd_tag_data, 'size' => $ifd_tag_nb * self::getIFDTypeSize($ifd_tag_type));
		}
		else {
		  $exif_ary[] = array('block' => $block_name, 'tag' => $ifd_tag_id, 'data' => $ifd_tag_str,
							  'ptr' => $ifd_block_pos + 12 * $tag, 'size' => 4);
		}
	  }

	  // Read pointer to next IFD block
	  $ifd_block_pos = self::decodeIFDData(substr($segment, $ifd_block_pos + 2 + 12 * $nb_tags, 4), self::TYPE_ULONG,
										   $byte_align);
	  $ifd_block_id++;
	}
	while($ifd_block_pos < strlen($segment) && $ifd_block_pos !== 0);
	return $exif_ary;
  }

  /**
   * Return size of a given data type
   *
   * @access protected
   * @param  int $type Data type according to TIFF 6.0 specification
   * @return int Data type size
   */
  private static function getIFDTypeSize(int $type): int
  {
	switch($type) {
	case self::TYPE_UBYTE:
	case self::TYPE_SBYTE:
	case self::TYPE_ASCII:
	case self::TYPE_UNDEFINED:
	  return 1;
	case self::TYPE_USHORT:
	case self::TYPE_SSHORT:
	  return 2;
	case self::TYPE_ULONG:
	case self::TYPE_SLONG:
	case self::TYPE_FLOAT:
	  return 4;
	case self::TYPE_URAT:
	case self::TYPE_SRAT:
	case self::TYPE_DOUBLE:
	  return 8;
	default:
	  throw new Exception(_('Cannot calculate data size of invalid data type'), Exception::DATA_FORMAT_ERROR, $type);
	}
  }

  /**
   * Decode an IFD field value from a binary data string and return the value
   *
   * @access protected
   * @param  int    $type       Data type according to TIFF 6.0 specification
   * @param  string $data       Binary data strinc containing the IFD value
   * @param  int    $byte_align Byte alignment of data (II=Intel: Little endian/MM=Motorola: Big endian)
   * @return string|int|array   Data value
   */
  protected static function decodeIFDData(string $data, int $type, int $byte_align): string|int|array
  {
	switch($type) {
	case self::TYPE_UBYTE:
	  return ord($data[0]);
	case self::TYPE_SBYTE:
	  return ord($data[0]) > 128 ? ord($data[0]) - 256 : ord($data[0]);

	case self::TYPE_USHORT:
	  if($byte_align === self::EXIF_BYTE_ALIGN_LE)
		return ord($data[0]) + 256 * ord($data[1]);
	  else
		return ord($data[1]) + 256 * ord($data[0]);
	case self::TYPE_SSHORT:
	  if($byte_align === self::EXIF_BYTE_ALIGN_LE)
		$value =  ord($data[0]) + 256 * ord($data[1]);
	  else
		$value = ord($data[1]) + 256 * ord($data[0]);
	  return $value > 32768 ? $value - 65536 : $value;

	case self::TYPE_ULONG:
	  if($byte_align === self::EXIF_BYTE_ALIGN_LE)
		return ord($data[0]) + 256 * ord($data[1]) + 65536 * ord($data[2]) + 1677216 * ord($data[3]);
	  else
		return ord($data[3]) + 256 * ord($data[2]) + 65536 * ord($data[1]) + 1677216 * ord($data[0]);
	case self::TYPE_SLONG:
	  if($byte_align === self::EXIF_BYTE_ALIGN_LE)
		$value = ord($data[0]) + 256 * ord($data[1]) + 65536 * ord($data[2]) + 1677216 * ord($data[3]);
	  else
		$value = ord($data[3]) + 256 * ord($data[2]) + 65536 * ord($data[1]) + 1677216 * ord($data[0]);
	  return $value > 2147483648 ? $value - 4294967296 : $value;

	case self::TYPE_URAT:
	  return array('num' => self::decodeIFDData(substr($data, 0, 4), self::TYPE_ULONG, $byte_align),
				   'denom' => self::decodeIFDData(substr($data, 4, 4), self::TYPE_ULONG, $byte_align));
	  if($byte_align === self::EXIF_BYTE_ALIGN_LE)
		$value = unpack('Vnum/Vdenom', $data);
	  else
		$value = unpack('Nnum/Ndenom', $data);
	case self::TYPE_SRAT:
	  if($byte_align === self::EXIF_BYTE_ALIGN_LE)
		$value = unpack('Vnum/Vdenom', $data);
	  else
		$value = unpack('Nnum/Ndenom', $data);
	  $value = array('num' => self::decodeIFDData(substr($data, 0, 4), self::TYPE_ULONG, $byte_align),
					 'denom' => self::decodeIFDData(substr($data, 4, 4), self::TYPE_ULONG, $byte_align));
	  if($value['num'] > 2147483648) $value['num'] -= 4294967296;
	  if($value['denom'] > 2147483648) $value['denom'] -= 4294967296;
	  return $value;

	case  self::TYPE_ASCII:
	  return $data;

	case self::TYPE_UNDEFINED:
	case self::TYPE_FLOAT:
	case self::TYPE_DOUBLE:
	  return $data;

	default:
	  throw new Exception(_('Invalid IFD data type found'), Exception::DATA_FORMAT_ERROR);
	}
  }
  
  /**
   * Return IFD value as formatted text string
   *
   * @access protected
   * @param  string $data IFD data
   * @param  int    $type Data type according to TIFF 6.0 specification
   * @return string Formatted IFD data
   */
  protected static function getIFDString(string|in|array $data, int $type): string
  {
	switch($type) {
	case self::TYPE_UBYTE:
	case self::TYPE_SBYTE:
	case self::TYPE_USHORT:
	case self::TYPE_SSHORT:
	case self::TYPE_ULONG:
	case self::TYPE_SLONG:
	  return $data;
	case self::TYPE_ASCII:
	  return trim($data);
	case self::TYPE_URAT:
	case self::TYPE_SRAT:
	  return isset($data['num']) && isset($data['denom']) ? $data['num'].'/'.$data['denom'] : 'N/A';
	case self::TYPE_UNDEFINED:
	case self::TYPE_FLOAT:
	case self::TYPE_DOUBLE:
	  return strlen($data).' '._('bytes of binary data').': '.self::bin2hex($data);
	default:
	  throw new Exception(_('Invalid IFD data type found'), Exception::DATA_FORMAT_ERROR);
	}
  }

  /**
   * Return the name of a IFD block pointer tag, or false if the tag is not a pointer
   *
   * @access protected
   * @param  int $tag Numeric identifier of the EXIF tags according to the TIFF specification
   * @result string   Human readable string representing the tag
   */
  protected static function blockName(int $tag): string|false
  {
	switch($tag) {
    case self::TAG_PTR_SUB_IFD: return 'SUB';
    case self::TAG_PTR_GLOBAL_PARAMETERS_IFD: return 'GLOBAL';
    case self::TAG_PTR_KODAK_IFD: return 'KODAK';
    case self::TAG_PTR_JPL_CARTO_IFD: return 'JPL';
    case self::TAG_PTR_EXIF_IFD: return 'EXIF';
    case self::TAG_PTR_LEAF_SUB_IFD: return 'LEAF';
    case self::TAG_PTR_KDC_IFD: return 'KDC';
	default:
	  return false;
	}
  }

  /**
   * Convert a binary string into a string of hexadecimal numbers, every byte separated by a space
   *
   * @access protected
   * @param  string|false $bin Binary string or false
   * @resunt string Hexadecima readable representation of binary string
   */
  protected static function bin2hex(string|false $bin): string
  {
	if($bin === false) return '** false **';
	$str = '';
	for($pos = 0; $pos < strlen($bin); $pos ++) {
	  $str .= bin2hex($bin[$pos]).' ';
	}
	return trim($str);
  }

}
