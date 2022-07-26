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


/*** EXAMPLE ***/
$testfiles_ary = array('img.example.jpg', 'img.mlexample.jpg');

/**
 * Use of class Metadata
 */
$metadata = new \Holiday\Metadata();

foreach($testfiles_ary as $filename) {
  echo "PROCESSING EXAMPLE IMAGE USING \\Holiday\\Metadata CLASS: $filename".PHP_EOL;
  echo "---".PHP_EOL;

  // Read metadata in a transparent way not extend tagged keywords
  $metadata->read($filename, extend: false);


  // Read some of the metadata (assuming metadata is available)
  $caption = $metadata->get(\Holiday\Metadata::CAPTION, lang: \Holiday\Metadata::LANG_DEFAULT);
  $caption_ary = $metadata->get(\Holiday\Metadata::CAPTION, lang: \Holiday\Metadata::LANG_ALL);
  $date_created = $metadata->get(\Holiday\Metadata::CREATED_DATETIME);
  $credit = $metadata->get(\Holiday\Metadata::CREDIT);
  $city = $metadata->get(\Holiday\Metadata::CITY);
  $country = $metadata->get(\Holiday\Metadata::COUNTRY);
  $keywords = $metadata->get(\Holiday\Metadata::KEYWORDS);
  $people = $metadata->get(\Holiday\Metadata::PEOPLE);
  $event = $metadata->get(\Holiday\Metadata::EVENT);
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

  // Read metadata in a transparent way and extend tagged keywords to their respective fields
  $metadata->read($filename, extend: true);
  echo "EXTENDING KEYWORD TAGS:".PHP_EOL;
  $keywords = $metadata->get(\Holiday\Metadata::KEYWORDS);
  $people = $metadata->get(\Holiday\Metadata::PEOPLE);
  if($keywords !== false) echo "KEYWORDS    : ".implode(', ', $keywords).PHP_EOL;
  if($people !== false) echo "PEOPLE      : ".implode(', ', $people).PHP_EOL;
  echo PHP_EOL;

  // Re-format caption and update information
  if($caption !== false && $date_created !== false && $city !== false && $country !== false && $credit !== false) {
	$caption = strtoupper($city).', '.strtoupper($country).' - '.strtoupper(date('F d', $date_created)).': '.
	  $caption.' (Photo by '.$credit.')';
	$metadata->set(\Holiday\Metadata::CAPTION, $caption, lang: \Holiday\Metadata::LANG_DEFAULT);
  }
  else {
	echo "NOT ALL INFORMATION AVAILABLE TO UPDATE CAPTION".PHP_EOL;
  }
  if($event !== false) {
	$metadata->set(\Holiday\Metadata::EVENT, strtoupper($event));
  }
  else {
	$metadata->set(\Holiday\Metadata::EVENT, 'Event was empty');
  }

  // Write metadata back to the image file
  $metadata->write("new.$filename");

  // Read-back the data and display modified caption
  $metadata->read("new.$filename");
  $caption_ary = $metadata->get(\Holiday\Metadata::CAPTION, \Holiday\Metadata::LANG_ALL);
  if(!empty($caption_ary)) {
	echo "NEW CAPTION:".PHP_EOL;
    foreach($caption_ary as $lang => $text) {
	  $lang = substr($lang.'          ', 0, 9);
	  echo "   $lang: $text".PHP_EOL;
	}
  }
  echo "NEW EVENT   : ".$metadata->get(\Holiday\Metadata::EVENT).PHP_EOL.PHP_EOL;

  // Paste original data to new file
  $metadata->read("$filename");
  $metadata->paste("new.$filename");

    // Read-back the data and display original caption
  $metadata->read("new.$filename");
  $caption_ary = $metadata->get(\Holiday\Metadata::CAPTION, \Holiday\Metadata::LANG_ALL);
  if(!empty($caption_ary)) {
	echo "PASTED CAPTION:".PHP_EOL;
    foreach($caption_ary as $lang => $text) {
	  $lang = substr($lang.'          ', 0, 9);
	  echo "   $lang: $text".PHP_EOL;
	}
  }
  echo "PASTED EVENT: ".$metadata->get(\Holiday\Metadata::EVENT).PHP_EOL.PHP_EOL;
}

/**
 * Use of exception handling class \Holiday\Metadata\Exception
 */
echo PHP_EOL;
try {
  $metadata->read('invalid.file.name.jpg');
  echo "FILE WAS SUCCESSFULLY READ ALTHOUGH IT SHOULD NOT EXIST".PHP_EOL;
}
catch(\Holiday\Metadata\Exception $exception) {
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
