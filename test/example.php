<?php
  /**
   * example.php - Image file metadata handing exampe file
   * 
   * @project   Holiday\Metadata
   * @version   1.1
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/mit MIT
   */

/** Set error handling */
error_reporting(E_ALL);
ini_set('log_errors', false);
ini_set('display_errors', true);

/**
 * Automatically load classes
 *
 * @param string $name Name of class to load
 */
function my__autoload(string $name): void
{
  $name = str_replace("\\", '/', str_replace("Holiday\\", '', $name));
  if(file_exists("../src/$name.php")) require_once("../src/$name.php");
}
spl_autoload_register('my__autoload');
use \Holiday\Metadata;

/*** EXAMPLE ***/
$testfiles_ary = array('img.example.jpg', 'img.mlexample.jpg');
$exif_data_ary = array(Metadata::IMG_CAMERA_MAKE => 'CAMERA MAKE', Metadata::IMG_CAMERA_MODEL => 'CAMERA MODEL',
					   Metadata::IMG_CAMERA_SERIAL => 'CAMERA SERIAL', Metadata::IMG_LENS_MODEL => 'LENS MODEL', 
					   Metadata::IMG_COLOR_SPACE_FMT => 'COLOR SPACE', Metadata::IMG_ISO => 'ISO SETTING',
					   Metadata::IMG_APERTURE_FMT => 'APERTURE', Metadata::IMG_EXPOSURE_FMT => 'EXPOSURE',
					   Metadata::IMG_FOCAL_LENGTH_FMT => 'FOCAL LENGTH', Metadata::IMG_FLASH_FMT => 'FLASH USED',
					   Metadata::IMG_SIZE_FMT => 'IMAGE SIZE', Metadata::IMG_RESOLUTION_FMT => 'RESOLUTION',
					   Metadata::IMG_SOFTWARE => 'SOFTWARE');

/**
 * Use of class Metadata
 */
$metadata = new Metadata();
foreach($testfiles_ary as $filename) {
  echo "PROCESSING EXAMPLE IMAGE USING \\Holiday\\Metadata CLASS: $filename".PHP_EOL;
  echo "---".PHP_EOL;

  // Read metadata in a transparent way not extend tagged keywords
  $metadata->read($filename, extend: false);


  // Read some of the metadata (assuming metadata is available)
  $caption = $metadata->get(Metadata::CAPTION, lang: Metadata::LANG_DEFAULT);
  $caption_ary = $metadata->get(Metadata::CAPTION, lang: Metadata::LANG_ALL);
  $date_created = $metadata->get(Metadata::CREATED_DATETIME);
  $credit = $metadata->get(Metadata::CREDIT);
  $city = $metadata->get(Metadata::CITY);
  $country = $metadata->get(Metadata::COUNTRY);
  $keywords = $metadata->get(Metadata::KEYWORDS);
  $people = $metadata->get(Metadata::PEOPLE);
  $event = $metadata->get(Metadata::EVENT);
  if(!empty($caption_ary)) {
    echo "CAPTION:".PHP_EOL;
    foreach($caption_ary as $lang => $text) {
	  $lang = substr($lang.'          ', 0, 9); 
	  echo "   $lang: $text".PHP_EOL;
	}
  }
  if($credit !== false) echo "CREDIT      : $credit".PHP_EOL;
  if($city !== false && $country !== false) echo "PLACE       : $city, $country".PHP_EOL;
  if($date_created !== false) echo "CREATED     : ".date('d.m.Y', $date_created).PHP_EOL;
  if($event !== false) echo "EVENT       : $event".PHP_EOL;
  if($keywords !== false) echo "KEYWORDS    : ".implode(', ', $keywords).PHP_EOL;
  if($people !== false) echo "PEOPLE      : ".implode(', ', $people).PHP_EOL;
  echo PHP_EOL;

  echo "IMAGE DATA".PHP_EOL;
  foreach($exif_data_ary as $field_id => $field_name) {
	$data = $metadata->get($field_id);
	if($data !== false) echo '- '.substr($field_name.'               ', 0, 15).': '.$data.PHP_EOL;
  }
  echo PHP_EOL;

  // Read metadata in a transparent way and extend tagged keywords to their respective fields
  $metadata->read($filename, extend: true);
  echo "EXTENDING KEYWORD TAGS:".PHP_EOL;
  $keywords = $metadata->get(Metadata::KEYWORDS);
  $people = $metadata->get(Metadata::PEOPLE);
  if($keywords !== false) echo "KEYWORDS    : ".implode(', ', $keywords).PHP_EOL;
  if($people !== false) echo "PEOPLE      : ".implode(', ', $people).PHP_EOL;
  echo PHP_EOL;

  // Re-format caption and update information
  if($caption !== false && $date_created !== false && $city !== false && $country !== false && $credit !== false) {
	$caption = strtoupper($city).', '.strtoupper($country).' - '.strtoupper(date('F d', $date_created)).': '.
	  $caption.' (Photo by '.$credit.')';
	$metadata->set(Metadata::CAPTION, $caption, lang: Metadata::LANG_DEFAULT);
  }
  else {
	echo "NOT ALL INFORMATION AVAILABLE TO UPDATE CAPTION".PHP_EOL;
  }
  if($event !== false) {
	$metadata->set(Metadata::EVENT, strtoupper($event));
  }
  else {
	$metadata->set(Metadata::EVENT, 'Event was empty');
  }

  // Write metadata back to the image file
  $metadata->write("new.$filename");

  // Read-back the data and display modified caption
  $metadata->read("new.$filename");
  $caption_ary = $metadata->get(Metadata::CAPTION, Metadata::LANG_ALL);
  if(!empty($caption_ary)) {
	echo "NEW CAPTION:".PHP_EOL;
    foreach($caption_ary as $lang => $text) {
	  $lang = substr($lang.'          ', 0, 9);
	  echo "   $lang: $text".PHP_EOL;
	}
  }
  echo "NEW EVENT   : ".$metadata->get(Metadata::EVENT).PHP_EOL.PHP_EOL;

  // Paste original data to new file
  $metadata->read("$filename");
  $metadata->paste("new.$filename");

    // Read-back the data and display original caption
  $metadata->read("new.$filename");
  $caption_ary = $metadata->get(Metadata::CAPTION, Metadata::LANG_ALL);
  if(!empty($caption_ary)) {
	echo "PASTED CAPTION:".PHP_EOL;
    foreach($caption_ary as $lang => $text) {
	  $lang = substr($lang.'          ', 0, 9);
	  echo "   $lang: $text".PHP_EOL;
	}
  }
  echo "PASTED EVENT: ".$metadata->get(Metadata::EVENT).PHP_EOL.PHP_EOL;
}

/**
 * Use of exception handling class Metadata\Exception
 */
echo PHP_EOL;
try {
  $metadata->read('invalid.file.name.jpg');
  echo "FILE WAS SUCCESSFULLY READ ALTHOUGH IT SHOULD NOT EXIST".PHP_EOL;
}
catch(Metadata\Exception $exception) {
  echo "EXCEPTION CATCHED".PHP_EOL;
  echo "---".PHP_EOL;
  echo "CODE:    ".$exception->getCode().PHP_EOL;
  echo "MESSAGE: ".$exception->getMessage().PHP_EOL;
  echo "DATA:    ".$exception->getData().PHP_EOL;
}

/***
 * Cleanup images created
 */
foreach($testfiles_ary as $filename) {
  unlink("new.$filename");
}
?>
