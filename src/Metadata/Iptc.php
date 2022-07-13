<?php
  /**
   * Iptc.php - Encoding and decode IPTC data from segment APP13
   * 
   * @package   Holiday\Metadata
   * @version   1.0
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/mit MIT
   *
   * @see       https://exiftool.org/TagNames/IPTC.html
   */

  /**
   * NOTE
   * - TO DO: Currently, only Latin1 and UTF-8 IPTC data are supported. Support other IPTC encodings is missing.
   */

namespace Holiday\Metadata;
use Holiday\Metadata;

class Iptc {

  /** IPTC application record tags: IPTC Core Metadata 1.3 / most relevant ones */
  const AUTHOR = '2:080';         /** By-Line (Author) - Max 32 Characters */
  const AUTHOR_TITLE = '2:085';   /** By-Line Title (Author Position) - Max 32 characters */
  const CAPTION = '2:120';        /** Caption/Abstract - Max 2000 Characters */
  const CAPTION_WRITER = '2:122'; /** Caption Writer/Editor - Max 32 Characters */
  const CATEGORY = '2:015';       /** Category - Max 3 characters */
  const CITY = '2:090';           /** City - Max 32 Characters */
  const COPYRIGHT = '2:116';      /** Copyright Notice - Max 128 Characters */
  const COUNTRY = '2:101';        /** Country/Primary Location Name - Max 64 characters */
  const COUNTRY_CODE = '2:100';   /** Country/Primary Location Code - 3 alphabetic characters */
  const CREATED_DATE = '2:055';   /** Read only: Date Created - 8 numeric characters CCYYMMDD */
  const CREATED_TIME = '2:060';   /** Read only: Time Created - 11 characters HHMMSS±HHMM */
  const CREDIT = '2:110';         /** Credit - Max 32 Characters */
  const EDIT_STATUS = '2:007';    /** Edit Status - Max 64 characters */
  const GENRE = '2:004';          /** Genres - Max 64 Characters */
  const HEADLINE = '2:105';       /** Headline - Max 256 Characters */
  const INSTRUCTIONS = '2:040';   /** Special Instructions - Max 256 Characters */
  const KEYWORDS = '2:025';       /** Keywords - Max 64 characters */
  const LOCATION = '2:092';       /** Sub-Location - Max 32 characters */
  const OBJECT = '2:005';         /** Object Name (Title) - Max 64 characters */
  const PRIORITY = '2:010';       /** Urgency - 1 numeric character */
  const SOURCE = '2:115';         /** Source - Max 32 Characters */
  const STATE = '2:095';          /** Province/State - Max 32 Characters */
  const SUBJECT_CODE = '2:012';   /** Subject Reference - 13 to 236 characters */
  const SUPP_CATEGORY = '2:020';  /** Supplemental Category - Max 32 characters */
  const TRANSFER_REF = '2:103';   /** Original Transmission Reference - Max 32 characters */
  const DATA_ENCODING = '1:090';  /** Read only: Coded Character Set - Max 32 characters */

  const IPTC_TYPE = 'APP13';
  const IPTC_HEADER = "Photoshop 3.0\x00";
  const IPTC_HEADER_LEN = 14;

  private const IPTC_DATA_ENCODING_UTF8 = "\x1B\x25\x47"; // UTF-8 encoding
  

  /**
   * Decode IPTC data segment
   *
   * @param  string $segment Concatenated IPTC segments
   * @return array|false     Array of IPTC data, or false, if not data was found
   * @throw  \Holiday\Metadata\Exception
  */
  public static function decode(string $segment): array|false
  {
	if(empty($segment)) return false;

	$irb_data = self::unpackSegmentToIRB($segment);
	$iptc_data = self::decodeIRBToIPTC($irb_data);
	return empty($iptc_data) ? false : $iptc_data;
  }

  /***
   * Encode IPTC data segment in JPEG header array
   *
   * @param  string      $segment  Original concatenated IPTC segments
   * @param  array|false $iptc_ary Array of IPTC data to update (same format as returned by decode)
   * @return string      Updated IPTC segment data
   * @throw  \Holiday\Metadata\Exception
   */
  public static function encode(string $segment, array|false $iptc_ary): string
  {
	// Decode original data
	$irb_data = self::unpackSegmentToIRB($segment);
	$iptc_data = self::decodeIRBToIPTC($irb_data);
	$new_iptc_data = array();

	// Recover non-editable data
	$encoding = false;
	foreach($iptc_data as $iptc_elt) {
	  if(!self::isEditable($iptc_elt['tag'])) {
		if($iptc_elt['tag'] !== self::DATA_ENCODING) {
		  $new_iptc_data[] = array('tag' => $iptc_elt['tag'], 'data' => $iptc_elt['data']);
		}
		else {
		  $encoding = $iptc_elt['data'];
		}
	  }
	}
	
	// Set edited data (and look for 'caption', 'copyright', and 'author' data)
	$found_data = array(self::CAPTION => false, self::COPYRIGHT => false, self::AUTHOR => false);
	if($iptc_ary !== false) {
	  foreach($iptc_ary as $iptc_elt){
		if(self::isEditable($iptc_elt['tag'])) {
		  $new_iptc_data[] = array('tag' => $iptc_elt['tag'], 'data' => $iptc_elt['data']);
		  foreach($found_data as $tag => $value) {
			if($iptc_elt['tag'] === $tag) $found_data[$tag] = true;
		  }
		}
	  }
	}
	// Set data encoding to UTF-8 and encode data
	if($encoding !== false && $encoding !== self::IPTC_DATA_ENCODING_UTF8)
	  throw new Exception(_('Found IPTC encoding not supported'), Exception::NOT_IMPLEMENTED);
						  
	foreach($new_iptc_data as $key => $new_iptc_elt) {
	  if($encoding === false && self::isEditable($new_iptc_data[$key]['tag']) &&
		 mb_detect_encoding($new_iptc_data[$key]['data'], ['ASCII', 'UTF-8'], strict: true) === false) {
		$new_iptc_data[$key]['data'] = utf8_encode($new_iptc_data[$key]['data']);
	  }
	}
	array_unshift($new_iptc_data, array('tag' => self::DATA_ENCODING, 'data' => self::IPTC_DATA_ENCODING_UTF8));


	// Ensure that 'caption', 'copyright', and 'author' data contain data, so that their EXIF values never get picked-up
	foreach($found_data as $tag => $value) {
	  if($value === false) $new_iptc_data[] = array('tag' => $tag, 'data' => '');
	}

	// Convert IPTC data into IPTC block
	$iptc_block = '';
	foreach($new_iptc_data as $iptc_rec) {
	  list($rec, $dat) = sscanf($iptc_rec['tag'], '%d:%d');
	  $iptc_block .= pack("CCCn", 28, $rec, $dat, strlen($iptc_rec['data'])).$iptc_rec['data'];
	}
	
	// Find position of IPTC block in IRB and insert it
	$iptc_block_pos = -1;
	foreach($irb_data as $irb_pos => $irb_value) {
	  if($irb_value['id'] === 0x0404) $iptc_block_pos = $irb_pos;
	}
	if($iptc_block_pos === -1) $iptc_block_pos = count($irb_data);
	$irb_data[$iptc_block_pos] = array('id' => 0x0404, 'name' => "\x00\x00", 'data' => $iptc_block);
	
	// Pack IRB data into the segment string $irb_packed
	$irb_packed = '';
	foreach($irb_data as $irb_rec) {

	  // Cycle over no data
	  if(strlen($irb_rec['data']) === 0) continue;

	  // Append 8BIM tag and resource id
	  $irb_packed .= pack("a4n", "8BIM", $irb_rec['id']);
	  
	  // Append resource name
	  $irb_packed .= pack("c", strlen(trim($irb_rec['name'])));
	  $irb_packed .= trim($irb_rec['name']);
	  if(strlen(trim($irb_rec['name'])) % 2 === 0) $irb_packed .= "\x00";
	  
	  // Append resource size and data
	  $irb_packed .= pack("N", strlen($irb_rec['data']));
	  $irb_packed .= $irb_rec['data'];
	  if(strlen($irb_rec['data']) % 2 === 1) $irb_packed .= "\x00";
	}
	
	return $irb_packed;
  }


  /***
   * Unpack IPTC data segment into IRBs
   *
   * @access private
   * @param  string $segment Segment containing IPTC data (with header stripped)
   * @return array  Array of IRBs
   * @throw  \Holiday\Metadata\Exception
   */
  private static function unpackSegmentToIRB(string $segment): array
  {
	$pos = 0;
	$data_irb = array();
	while($pos <strlen($segment) && ($pos = strpos($segment, "8BIM", $pos)) !== false) {

	  //Skip 8BIM header
	  $pos += 4;

	  // Get record ID
	  $id = ord($segment[$pos]) * 256 + ord($segment[$pos+1]);
	  $pos += 2;

	  // Get record Name
	  $name_start = $pos;
	  $name_len = ord($segment[$name_start]);
	  if($name_len % 2 === 0) $name_len++;
	  $name = trim(substr($segment, $name_start + 1, $name_len));
	  $pos += $name_len + 1;

	  // Get record Data Length
	  $data_len = ord($segment[$pos]) * 16777216 + ord($segment[$pos+1]) * 65536 +
		ord($segment[$pos+2]) * 256 + ord($segment[$pos+3]);
	  $pos += 4;

	  // Get record Data
	  $data_len = $data_len + ($data_len % 2);
	  $data = substr($segment, $pos, $data_len);
	  $pos += $data_len;
	  
	  // Save data
	  $data_irb[] = array('id' => $id, 'name' => $name, 'data' => $data);
	}
	return $data_irb;
  }

  /***
   * Decode unpacked IRBs and extract IPTC data
   *
   * @access private
   * @param  array $dat_irb Array of IRBs
   * @return array Arrayr of UTF-8 encoded IPTC data
   * @throw  \Holiday\Metadata\Exception
   */
  private static function decodeIRBToIPTC(array $data_irb): array
  {
	$data_iptc = array();
	foreach($data_irb as $data_irb_elt) {
	  if($data_irb_elt['id'] === 0x0404) { // IRB IPTC block
		$pos = 0;
	  $data_elt = $data_irb_elt['data'];
		while($pos < strlen($data_elt)) {
		  
		  // Check if there is still data to read
		  if(strlen(substr($data_elt, $pos)) < 5) break;
		  $raw = unpack("Ctag/Crec/Cdat/nsize", substr($data_elt, $pos));
		  $pos += 5;

		  // Decode data tag
		  $tag = sprintf("%01d:%03d", $raw['rec'], $raw['dat']);
		  if(strlen(substr($data_elt, $pos, $raw['size'])) !== $raw['size']) {
			throw new Exception(_('IPTC data seems to be corrupt while decoding data tag'), Exception::FILE_CORRUPT);
		  }

		  // Decode and save actual data
		  $data = substr($data_elt, $pos, $raw['size']);
		  $data_iptc[] = array('tag' => $tag, 'data' => $data);
		  $pos += $raw['size'];
		}
	  }
	}
	
	// Find current encoding
	$encoding = false;
	foreach($data_iptc as $iptc_elt) {
	  if($iptc_elt['tag'] === self::DATA_ENCODING) {
		$encoding = $iptc_elt['data'];
	  }
	}
	if($encoding !== false && $encoding !== self::IPTC_DATA_ENCODING_UTF8)
	  throw new Exception(_('Found IPTC encoding not supported'), Exception::NOT_IMPLEMENTED);
	
	// Encode data into UTF-8 format, if the current format is Latin1
	foreach($data_iptc as $key => $iptc_elt) {
	  if($encoding == false && self::isEditable($data_iptc[$key]['tag']) &&
		 mb_detect_encoding($data_iptc[$key]['data'], ['ASCII', 'UTF-8'], strict: true) === false) {
		$data_iptc[$key]['data'] = utf8_encode($data_iptc[$key]['data']);
	  }
	}
	return $data_iptc;
  }

  /***
   * Return if a given IPTC tag is editable or not
   *
   * @access private
   * @param  string $tac IPTC application record tag
   * @return bool   True, if the IPTC tag is editable
   */
  private static function isEditable(string $tag): bool
  {
	switch($tag) {
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
	case self::GENRE:
	case self::HEADLINE:
	case self::INSTRUCTIONS:
	case self::KEYWORDS:
	case self::LOCATION:
	case self::OBJECT:
	case self::PRIORITY:
	case self::SOURCE:
	case self::STATE:
	case self::SUBJECT_CODE:
	case self::SUPP_CATEGORY:
	case self::TRANSFER_REF:
	  return true;
	default:
	  return false;
	}
  }

  
}

