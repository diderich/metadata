<?php
  /**
   * Jpeg.php - JPG metadata encode and decoding functions (IPTC and XMP fields)
   * 
   * @package   Holiday\Metadata
   * @version   1.1
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/mit MIT
   */

namespace Holiday\Metadata;
use Holiday\Metadata;

class Jpeg {
  
  /** Private variables */
  private string|false      $filename;  // Filename of data read or empty if not data was read
  private array             $header;    // Array of header segments
  private string            $img;       // Compressed image data
  private array|false       $iptc_data; // Read IPTC data
  private XmpDocument|false $xmp_data;  // Read XMP data
  private array|false       $exif_data; // Read EXIF data
  private bool              $data_read; // Flag if data has been read
  private bool              $read_only; // Data read is read only (writing is disabled)
  
  /**
   * Constructor
   */
  public function __construct()
  {
	$this->filename = false; $this->header = array(); $this->img = '';
	$this->iptc_data = false; $this->xmp_data = false; $this->exif_data = false;
	$this->data_read = false; $this->read_only = true;
  }

  /**
   * Destructor
   */
  public function __destruct()
  {
  }

  /**
   * Read all data (image and metadata) from a JPG file: IF read-only is set, the image data is not read and the data
   * cannot be written back.
   *
   * @param string $filename  JPG filename
   * @param bool   $readonly Allow only reading data
   * @throw Metadata\Exception
   */
  public function read(string $filename, bool $read_only = false): void
  {
	// Initrialize all variables
	self::__construct();
	$this->read_only = $read_only;

	// Open image file for reading
	$handle = fopen($filename, 'rb');
	if($handle === false) throw new Exception(_('File not found'), Exception::FILE_NOT_FOUND, $filename);
	
	// Check if file is a JPG file
	$data = $this->dataRead($handle, 2);
	if($data !== "\xFF\xD8") {
	  fclose($handle);
	  throw new Exception(_('Invalid file type'), Exception::FILE_TYPE_ERROR, $filename);
	}

	// Check if file is not corrupt
	$data = $this->dataRead($handle, 2);
	if($data[0] !== "\xFF") {
	  fclose($handle);
	  throw new Exception(_('File is corrupt'), Exception::FILE_CORRUPT, $filename);
	}
	
	// Read image header data containing metadata)
	$this->header = array();
	$hit_img_data = false;
	while($data[1] !== "\xD9" && !$hit_img_data && !feof($handle)) {

	  // Foud a data segment
	  if(ord($data[1]) < 0xD0 || ord($data[1]) > 0xD7) {
		$size_str = $this->dataRead($handle, 2);
		$size_dec = unpack('nsize', $size_str);
		$seg_data = $this->dataRead($handle, $size_dec['size'] - 2);

		// Save data segment
		$this->header[] = array('name' => self::segmentName(ord($data[1])),'tag' => ord($data[1]), 'data' => $seg_data);
	  }

	  // Check if the segment was the last one
	  if($data[1] === "\xDA") {
		$hit_img_data = true;

		$this->img = '';
		if(!$this->read_only) {
		  // Read image data
		  do {
			$this->img .= $this->dataRead($handle, 1048576);
		  }
		  while(!feof($handle));
		  
		  // Stripp of EOI and anything thereafter
		  $eoi_pos = strpos($this->img, "\xFF\xD9");
		  if($eoi_pos === false) {
			fclose($handle);
			throw new Exception(_('Image data seems to be corrupt'), Exception::FILE_CORRUPT, $filename);
		  }
		  
		  $this->img = substr($this->img, 0, $eoi_pos);
		}
	  }
	  else {
		$data = $this->dataRead($handle, 2);
		if($data[0] !== "\xFF") {
		  fclose($handle);
		  throw new Exception(_('File seems to be corrupt'), Exception::FILE_CORRUPT, $filename);
		}
	  }
	}
	fclose($handle);
	$this->filename = $filename;

	// Extract IPTC metadata from header segments
	$this->iptc_data = Iptc::decode($this->getIptcSegment());
	
	// Extract XMP metadata from header segments
	$this->xmp_data = Xmp::decode($this->getXmpSegment());

	// Extract EXIF metadata from header segments
	$this->exif_data = Exif::decode($this->getExifSegments());

	// Mark that all data has been read
	$this->data_read = true;
  }

  /**
   * Write all data (image and metadata) to a JPG file. A previously existing file will be overwritten
   *
   * @param string $filename JPG filename
   * @throw Metadata\Exception
   */
  public function write(string $filename): void
  {
	if(!$this->data_read)
	  throw new Exception(_('No image and metadata read'), Exception::DATA_NOT_FOUND);
	if($this->read_only)
	  throw new Exception(_('Cannot write file because data was read in read-only mode'), Exception::DATA_NOT_FOUND);

	// Check that headers are not too large
	foreach($this->header as $segment) {
	  if(strlen($segment['data']) > 0xfffd)
		throw new Exception(_('Header segment is too large to fit into JPG segment'), Exception::DATA_FORMAT_ERROR);
	}

	// Write file
	$handle = fopen($filename, 'wb');
	if($handle === false)
	  throw new Exception(_('Could not open file for writing'), Exception::FILE_ERROR, $filename);

	// Write SOI for JPEG file
	fwrite($handle, "\xFF\xD8");

	// Write header segments
	foreach($this->header as $segment) {
	  // Write segment marker
	  fwrite($handle, sprintf("\xFF%c", $segment['tag']));
	  // Write segment length
	  fwrite($handle, pack("n", strlen($segment['data']) + 2));
	  // Write data
	  fwrite($handle, $segment['data']);
	}

	// Write compressed image
	fwrite($handle, $this->img);

	// Write EOI for JPEG and close files
	fwrite($handle, "\xFF\xD9");
	fclose($handle);
  }

  /**
   * Return name of read filename or false
   *
   * @return string|false Filename of read file or false
   */
  public function getFilename(): string|false
  {
	return $this->filename;
  }

  /***
   * Extract all IRB segments (Information Resources Block) of type APP13 containing IPTC metadata and concatenate them
   *
   * @access private
   * @return string IPTC data segments (combined)
   */
  private function getIptcSegment(): string
  {
	$segment = '';
	$nb_header = count($this->header);
	for($pos = 0; $pos < $nb_header; $pos++) {
	  if($this->header[$pos]['name'] === Iptc::IPTC_TYPE &&
		 strncmp($this->header[$pos]['data'], Iptc::IPTC_HEADER, Iptc::IPTC_HEADER_LEN) === 0) {
		$segment .= substr($this->header[$pos]['data'], Iptc::IPTC_HEADER_LEN);
	  }
	}
	return $segment;
  }

  /**
   * Return IPTC metadata in the same format as used by 'iptcparse' php function (an array indexed by metadata record
   * types). The data returned will always be in UTF-8 format
   *
   * @returns array|false Return IPTC metadata, of false, if no metadata was found
   */
  public function getIptcData(): array|false
  {
	if(!$this->data_read) throw new Exception(_('No image and metadata read'), Exception::DATA_NOT_FOUND);

	if($this->iptc_data === false) return false;

	// Re-format IPTC for easier access: $iptc[$tag] = array($data)
	$output = array();
	foreach($this->iptc_data as $iptc_elt) $output[$iptc_elt['tag']][] = $iptc_elt['data'];
	return $output;
  }

  /**
   * Set/Update editable IPTC metadata. Non editable IPTC data is ignored
   *
   * @param array|false $iptc_data_ary IPTC data array, or false
   * @throw Metadata\Exception
   */
  public function setIptcData(array|false $iptc_data_ary): void
  {
	if(!$this->data_read) throw new Exception(_('No image and metadata read'), Exception::DATA_NOT_FOUND);

	// Re-format IPTC data to internal format and save it for future reference
	$iptc_ary = array();
	if($iptc_data_ary !== false) {
	  foreach($iptc_data_ary as $tag => $iptc_elt_ary) {
		foreach($iptc_elt_ary as $iptc_elt) {
		  $iptc_ary[] = array('tag' => $tag, 'data' => $iptc_elt);
		}
	  }
	}
	$this->iptc_data = empty($iptc_ary) ? false : $iptc_ary;

	// Encode new data
	$irb_packed = Iptc::encode($this->getIptcSegment(), $this->iptc_data);
	
	// Delete all existing IPTC IRB blocks (new ones will replace them)
	for($pos = 0; $pos < count($this->header); $pos++) {
	  if($this->header[$pos]['name'] === Iptc::IPTC_TYPE &&
		 strncmp($this->header[$pos]['data'], Iptc::IPTC_HEADER, Iptc::IPTC_HEADER_LEN) === 0) {
		array_splice($this->header, $pos, 1);
	  }
	}
	
	// Find position where to insert IRB data segment into header
	$pos = count($this->header) - 1;
	while($pos >= 0 && ($this->header[$pos]['tag'] > 0xED || $this->header[$pos]['tag'] < 0xE0)) {
	  $pos--;
	}
	
	// Output blocks of size maximal 32000
	while(strlen($irb_packed) > 32000) {
	  array_splice($this->header, $pos + 1, 0, array('tag' => 0xED, 'name' => Iptc::IPTC_TYPE,
													 'data' => Iptc::IPTC_HEADER.substr($irb_packed, 0, 32000)));
	  $irb_packed = substr_replace($irb_packed, '', 0, 32000);
	  $pos++;
	}
	array_splice($this->header, $pos + 1, 0, "");
	$this->header[$pos + 1] = array('tag' => 0xED, 'name' => Iptc::IPTC_TYPE, 'data' => Iptc::IPTC_HEADER.$irb_packed);

  }

  /***
   * Extract XMP data
   *
   * @access private
   * @return string XMP data as string
   */
  private function getXmpSegment(): string
  {
	$nb_header = count($this->header);
	for($pos = 0; $pos < $nb_header; $pos++) {
	  if($this->header[$pos]['name'] === Xmp::XMP_TYPE &&
		 strncmp($this->header[$pos]['data'], Xmp::XMP_HEADER, Xmp::XMP_HEADER_LEN) === 0){
		return substr($this->header[$pos]['data'], Xmp::XMP_HEADER_LEN);
	  }
	}
	return '';
  }

  /**
   * Return XMP metadata
   *
   * @returns  XmpDocument|false Return XMP metadata, or false, if the data could not be found
   * @throw Metadata\Exception
   */
  public function getXmpData(): XmpDocument|false
  {
	if(!$this->data_read) throw new Exception(_('No image and metadata read'), Exception::DATA_NOT_FOUND);

	return $this->xmp_data;
  }

  /**
   * Set/Update XMP metadata (all data must be in UTF-8 or ASCII format, HTML entities are not supported)
   *
   * @param  XmpDocument|false $xmp_data XMP metadata block name, or false
   * @throw Metadata\Exception
   */
  public function setXmpData(XmpDocument|false $xmp_data): void
  {
	if(!$this->data_read) throw new Exception(_('No image and metadata previously read'), Exception::DATA_NOT_FOUND);

	// Encode data
	$xmp_block = Xmp::encode($xmp_data);
	$this->xmp_data = $xmp_data;
	
	// Find existing segment and repace or delete it
	$nb_header = count($this->header);
	for($pos = 0; $pos < $nb_header; $pos++) {
	  if($this->header[$pos]['name'] === Xmp::XMP_TYPE &&
		 strncmp($this->header[$pos]['data'], Xmp::XMP_HEADER, Xmp::XMP_HEADER_LEN) === 0) {
		if($xmp_data === false || $xmp_block === false) {
		  // Remove segment
		  unset($this->header[$pos]);
		}
		else {
		  // Replace existing segment
		  $this->header[$pos]['data'] = Xmp::XMP_HEADER.$xmp_block;
		}
		return;
	  }
	}

	// Add new segment (if it has not been found)
	$pos = 0;
	while($this->header[$pos]['name'] ===  Xmp::XMP_TYPE_PRV || $this->header[$pos]['name'] ===  Xmp::XMP_TYPE)
	  $pos++;
	array_splice($this->header, $pos, 0, array(array('tag' => Xmp::XMP_TYPE_TAG, 'name' => Xmp::XMP_TYPE,
													 'data' => Xmp::XMP_HEADER.$xmp_block)));
	$this->xmp_data = $xmp_data;
  }

  /**
   * Return an array of all exif segments, stripped from its header
   */
  private function getExifSegments(): array
  {
	$exif_segments = array();
	$nb_header = count($this->header);
	for($pos = 0; $pos < $nb_header; $pos++) {
	  if($this->header[$pos]['name'] === Exif::EXIF_TYPE &&
		 (strncmp($this->header[$pos]['data'], "Exif\x00\x00", 6) === 0 ||
		  strncmp($this->header[$pos]['data'], "Exif\x00\xFF", 6) === 0)) {
		$exif_segments[$pos] = substr($this->header[$pos]['data'], 6);
	  }
	}
	return $exif_segments;
  }
  
  /**
   * Return EXIF data
   *
   * @return array|false Array of EXIF data, or false, if not data was found
   * @throw \Holiday\Metadata\Exception
   */
  public function getExifData(): array|false
  {
	if(!$this->data_read) throw new Exception(_('No image and metadata read'), Exception::DATA_NOT_FOUND);

	if($this->exif_data === false) return false;
	
	// Re-format IPTC for easier access
	$output = array();
	foreach($this->exif_data as $segment_data) {
	  foreach($segment_data as $elt) {
		$output[$elt['block'].':'.substr('0000'.dechex($elt['tag']), -4)] = $elt['data'];
	  }
	}
	return $output;
  }

  /**
   * Update EXIF data
   *
   * @param array|false $exif_ary New exif data (ony writable fields must be specified)
   * @throw \Holiday\Metadata\Exception
   */
  public function setExifData(array|false $exif_data_ary): void
  {
	// Re-format IPTC data to internal format
	$exif_ary = array();
	if($exif_data_ary !== false) {
	  foreach($exif_data_ary as $block_tag => $exif_elt) {
		list($block, $tag) = explode(':', $block_tag);
		$exif_ary[] = array('block' => $block, 'tag' => hexdec("0x$tag"), 'data' => $exif_elt);
	  }
	}
	$exif_ary = empty($exif_ary) ? false : $exif_ary;

	// Encode data and replace old segments
	if($this->exif_data === false) return;
	$exif_segments = Exif::encode($this->getExifSegments(), $this->exif_data, $exif_ary);
	foreach($exif_segments as $segment_pos => $segment_data) {
	  $this->header[$segment_pos]['data'] = "Exif\x00\x00".$segment_data;
	}
  }
  
  /**
   * SUPPORT FUNCTIONS
   */

  /**
   * Read data from file
   *
   * @access private
   * @param  resource $handle File handle
   * @param  int      $length Number of characters/bytes to read from file
   * @return string   Data read
   */
  private function dataRead(mixed $handle, int $length): string
  {
	$data = '';
	while(!feof($handle) && strlen($data) < $length) {
	  $data .= fread($handle, $length - strlen($data));
	}
	return $data;
  }

  /**
   * Return the name of any APP? segment, otherwise return the hex code of the segment
   *
   * @access private
   * @param  int $tag Segment tag
   * @return string Segment name or hex code of segment tag
   */
  private static function segmentName(int $tag): string
  {
	if($tag >= 0xE0 && $tag <= 0xEF) return 'APP'.($tag - 0xE0);
	return '0x'.dechex($tag);
  }

}
