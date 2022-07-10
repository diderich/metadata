<?php
  /**
   * example.php - Image file metadata handing exampe file
   * 
   * @project   Holiday\Metadata
   * @version   1.0
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
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
$testfiles_ary = array('img.example.jpg');

/**
 * Use of class Metadata
 */
$metadata = new \Holiday\Metadata();

foreach($testfiles_ary as $filename) {
  echo "PROCESSING EXAMPLE IMAGE USING \\Holiday\\Metadata: $filename".PHP_EOL;
  echo "---".PHP_EOL;

  // Read metadata in a transparent way and extend tagged keywords to their respective fields
  $metadata->read($filename, extend: true);

  // Read some of the metadata (assuming metadata is available)
  $caption = $metadata->get(\Holiday\Metadata::CAPTION);
  $date_created = $metadata->get(\Holiday\Metadata::CREATED_DATETIME);
  $credit = $metadata->get(\Holiday\Metadata::CREDIT);
  $city = $metadata->get(\Holiday\Metadata::CITY);
  $country = $metadata->get(\Holiday\Metadata::COUNTRY);
  $people = $metadata->get(\Holiday\Metadata::PEOPLE);
  $keywords = $metadata->get(\Holiday\Metadata::KEYWORDS);
  $event = $metadata->get(\Holiday\Metadata::EVENT);
  if($caption !== false) echo "CAPTION:  $caption".PHP_EOL;
  if($credit !== false) echo "CREDIT:   $credit".PHP_EOL;
  if($city !== false && $country !== false) echo "PLACE:    $city, $country".PHP_EOL;
  if($date_created !== false) echo "CREATED:  ".date('d.m.Y', $date_created).PHP_EOL;
  if($event !== false) echo "EVENT:    $event".PHP_EOL;
  if($keywords !== false) echo "KEYWORDS: ".implode(', ', $keywords).PHP_EOL;
  if($people !== false) echo "PEOPLE:   ".implode(', ', $people).PHP_EOL;
  echo PHP_EOL;
  
  // Re-format caption and update information
  if($caption !== false && $date_created !== false && $city !== false && $country !== false && $credit !== false) {
	$caption = strtoupper($city).', '.strtoupper($country).' - '.strtoupper(date('F d', $date_created)).': '.
	  $caption.' (Photo by '.$credit.')';
	$metadata->set(\Holiday\Metadata::CAPTION, $caption);
  }
  else {
	echo "NOT ALL INFORMATION AVAILABLE TO UPDATE CAPTION".PHP_EOL;
  }
  if($event !== false) {
	$metadata->set(\Holiday\Metadata::EVENT, strtoupper($event));
  }
  else {
	$metadata->set(\Holiday\Metadata::EVENT, 'New demo event name');
  }

  // Write metadata back to the image file
  $metadata->write("new.$filename");

  // Read-back the data and display modified caption
  $metadata->read("new.$filename");
  echo "NEW CAPTION: ".$metadata->get(\Holiday\Metadata::CAPTION).PHP_EOL;
  echo "NEW EVENT:   ".$metadata->get(\Holiday\Metadata::EVENT).PHP_EOL.PHP_EOL;

  // Paste original data to new file
  $metadata->read("$filename");
  $metadata->paste("new.$filename");

    // Read-back the data and display original caption
  $metadata->read("new.$filename");
  echo "PASTED CAPTION: ".$metadata->get(\Holiday\Metadata::CAPTION).PHP_EOL;
  echo "PASTED EVENT:   ".$metadata->get(\Holiday\Metadata::EVENT).PHP_EOL.PHP_EOL;
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
