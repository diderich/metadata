[![Latest Stable Version](https://img.shields.io/packagist/v/diderich/metadata)](https://packagist.org/packages/diderich/metadata)
[![Minimum PHP version](https://img.shields.io/packagist/php-v/diderich/metadata)](https://packagist.org/packages/diderich/metadata)
[![License: MIT](https://img.shields.io/badge/license-MIT-blueviolet.svg)](https://github.com/diderich/metadata/blob/master/LICENSE)
[![Standard](https://img.shields.io/badge/standard-IPTC%20Photo%20Metadata%20Standard%202021.1-yellow)](https://iptc.org/standards/photo-metadata/interoperability-tests/)


# Name
**Metadata** - A PHP class for reading and writing *Photo Metadata* from JPEG files in a transparent way


# Version
See `CHANGELOG.md` and php constant `\Holiday\Metadata::VERSION` for the latest version number.


# Description
The **Metadata** class and its sub-classes implement transparent read and write access to IPTC Photo Metadata based on
the *IPTC Photo Metadata Standard 2021.1* and read access to EXIF data from JPEG files, focusing on those metadata
elements that are relevant for searching and managing photos within a photo database or similar application. The class
is unique in the sense that it supports multiple concurrent languages for the caption/description information.

There exist many implementations for reading and/or writing metadata from photos of various formats. Typically, these
packages and programs (`exiftool` being the most prominent one) focus on decoding raw data in the context of its origin,
e.g., decoding EXIF data or decoding IPTC/APP13 data. This project takes a different approach! It takes a user-centric
approach and exposes IPTC and EXIF photo metadata in a transparent way to the user, i.e, the user does not have to worry
where the data is coming from and/or how it is encoded.

The package has been developed in the context of the proprietary HOLIDAY photo database software (see
https://www.cdsp.photo/technology/holiday-database/) as the end-user. As such it may lack functionality relevant for
different uses. This explains also the use of the top-level namespace prefix `\Holiday`.

The decoding and encoding of the JPEG image file into different header segments as well as the decoding and encoding of
the IPTC data is inspired in part by **The PHP JPEG Metadata Toolkit** (https://www.ozhiker.com/electronics/pjmt/),
which is hereby duly acknowledged.


## Transparent access to JPEG image metadata
The class `\Holiday\Metadata` allows reading and writing the most relevant (from the author's perspective) photo
metadata in JPEG files and access them in a transparent way, independent of where/how they are stored. Data for a field
FIELD_ID can be read from file FILENAME using the following code. If no data is found, `$data` will be equal to `false`,
otherwise it will contain the data read, which may be a string, an integer, or an array:

```php
$metadata = new \Holiday\Metadata();

$metadata->read(FILENAME);
$data = $metadata->get(\Holiday\Metadata::FIELD_ID);
```

Data in field FIELD_ID can be updated to NEW_DATA and written back to the same file or a new different file NEW_FILENAME
using the code:

```php 
$metadata->set(\Holiday\Metadata::FIELD_ID, NEW_DATA); 
$metadata->write(NEW_FILENAME);
``` 

The file NEW_FILENAME is automatically overwritten.

Pasting the editable part of the metadata from one file FILENAME to another one named PASTE_FILENAME (which must exist),
can be done using the following code:

```php
$metadata = new \Holiday\Metadata();

$metadata->read(FILENAME);
$metadata->paste(PASTE_FILENAME);
```

The caption/description field `\Holiday\Metadata::CAPTION` supports multiple languages, the default language being named
according to the standard) `\Holiday\Metadata::LANG_DEFAULT`. Access the field's data is done using the following code:
```php
// Retrieve the default data
$caption = $metadata->get(\Holiday\Metadata::CAPTION, lang: \Holiday\Metadata::LANG_DEFAULT);

// Retrieve the data in a specific language, e.g., 'de-de' (German)
$caption = $metadata->get(\Holiday\Metadata::CAPTION, lang: 'de-de');

// Retrieve the data in all available languages (an array, indexed with the respective language identifier is returned)
$caption_ary = $metadata->get(\Holiday\Metadata::CAPTION, lang: \Holiday\Metadata::LANG_ALL);
```


The metadata is read in the following order, the first data read taking priority, i.e, if the CAPTION data is stored in
the IPTC/APP13 segment as well as in the XMP/APP1 and EXIF/APP1 segment, the data from the IPTC/APP13 will prevail.

1. Data found in the IPTC/APP13 segment
2. Data found the XMP/APP1 segment
3. Data found in the EXIF/APP1 segment

When writing the metadata back, both IPTC/APP13 and XMP/APP1 fields will be updated to ensure consistency of the
data. In the current implementation, user modifiable data stored in the EXIF/APP1 segment is cleared, i.e., replaced by
0x00, rather than updated to the actual value. This choice is due to a) the opinion that EXIF/APP1 should not contain
user-editable data and b) the complexity of the EXIF/APP1 data format.

## Exception handling
All functions return `false`in case of non-fatal errors. Fatal errors trigger the exception
`\Holiday\Metadata\Exception` to be thrown (which extends the generic `Exception` class). The numerical code associated
with an error (`$exception->getCode()`) allows identifying the type of the error. Constants are defined in the exception
class `\Holiday\Metadata\Exception`.

The function `\Holiday\Metadata\Exception::getData()`returns additional data relevant to the thrown exception in human
readable form.

All exception messages are translatable using the `gettext` library. A translation template is provided in the `locale`
directory.


# Example
The following example shows how to read, modify, and write metadata from JPEG files in a transparent way (see also
`test/example.php`). Is requires/assumes a PSR-4 compliant mechanism for loading the class files.

```php
// Create an instance of the Metadata class
$metadata = new \Holiday\Metadata();

try {
  // Read metadata from the image file FILENAME
  $metadata->read(FILENAME);

  // Read some of the metadata (assuming metadata is available)
  $caption = $metadata->get(\Holiday\Metadata::CAPTION, lang: \Holiday\Metadata::LANG_DEFAULT);
  $caption_ary = $metadata->get(\Holiday\Metadata::CAPTION);
  $date_created = $metadata->get(\Holiday\Metadata::CREATED_DATETIME);
  $credit = $metadata->get(\Holiday\Metadata::CREDIT);
  $city = $metadata->get(\Holiday\Metadata::CITY);
  $country = $metadata->get(\Holiday\Metadata::COUNTRY);
  $people = $metadata->get(\Holiday\Metadata::PEOPLE);
  $keywords = $metadata->get(\Holiday\Metadata::KEYWORDS);
  $event = $metadata->get(\Holiday\Metadata::EVENT);
  if(!empty($caption_ary)) {
    echo "CAPTION:".PHP_EOL;
    foreach($caption_ary as $lang => $text) 
      echo "   $lang: $text".PHP:EOL;
  }
  if($credit !== false) echo "CREDIT:   $credit".PHP_EOL;
  if($city !== false && $country !== false) echo "PLACE:    $city, $country".PHP_EOL;
  if($date_created !== false) echo "CREATED:  ".date('d.m.Y', $date_created).PHP_EOL;
  if($event !== false) echo "EVENT:    $event".PHP_EOL;
  if($keywords !== false) echo "KEYWORDS: ".implode(', ', $keywords).PHP_EOL;
  if($people !== false) echo "PEOPLE:   ".implode(', ', $people).PHP_EOL;

  // Update some data
  if($caption !== false && $date_created !== false && $city !== false && $country !== false && $credit !== false) {
    $caption = strtoupper($city).', '.strtoupper($country).' - '.strtoupper(date('F d', $date_created)).': '.
      $caption.' (Photo by '.$credit.')';
    $metadata->set(\Holiday\Metadata::CAPTION, $caption, lang: \Holiday\Metadata::LANG_DEFAULT);
  }
  if($event !== false) $metadata->set(\Holiday\Metadata::EVENT, strtoupper($event));


  // Write metadata back to the image file
  $metadata->write(FILENAME);

  // Paste user modifiable metadata do a different file
  $metadata->paste(ALTERNATE_FILE);
```

# File and directory structure
The directory structure shown describes the most relevant directories and files of the library.

All class files are commended in a phpDocumenter (https://www.phpdoc.org/) compliant way.
```
|-- src/                    Directory containing all class files required to use the library
  |-- Metadata.php          Class implementing transparent read/write access to JPEG metadata
    |-- Metadata
      |-- Exception.php     Exception handling class
      |-- Exif.php          Class reading and writing EXIF/APP1 specific raw data
      |-- Iptc.php          Class reading and writing IPTC/APP13 specific raw data
      |-- Jpeg.php          Class reading and writing JPEG header segment data
      |-- XmpDocument.php   Class encoding and decoding Xmp data in a DOMDocument class format
      |-- Xmp.php           Class reading and writing XMP/APP1 specific data
|-- test/
  |-- example.php           Sample program using the metadata class libraries
  |-- example.txt           Sample program output
  |-- img.example.jpg       Sample image including data in all supported fields
  |-- img.mlexample.jpg     Sample image containing caption data in two languages
|-- locale/
  |-- metadata.pot          Untranslated text messages generated by the classes
|-- README.md
|-- CHANGELOG.md
|-- LICENSE
|-- composer.json
```

# Installation
The metadata class can be installed using composer

```shell
composer require diderich/metadata
```

# Testing
The classes have been successfully tested on a number of JPEG images written by a non-exhaustive list of different
camera models. As decoding data is highly dependent on the choices made when encoding the data, the class may be unable
to decode some less common JPEG file encodings. This is especially the case for data stored (in a less compliant way)
using th XMP format.

No exhaustive test concept for the classes exists and/or is planned.

If you find a JPEG file that is not correctly decoded, please raise an issue in the `Issue` section AND include a copy
of the JPEG file. Issus without accompanying JPEG data will be closed by the author without consideration.


# Open issues
The following limitations currently exist and are acknowledged as such:
* IPTC/APP13: Only Latin 1 and UTF-8 encoded data can be read. Other data formats will throw an exception.
* XMP/APP1: The classes Xmp and/or XmpDocument may nor recognize all poorly/incorrectly formatted XMP/APP1 data.
* XMP/APP1: If a data element TAG is updated in the namespace NS, then it will also be updated in the namespaces
  Iptc4xmpCore, dc, aux, xmp, photoshop, and photomechanic, if a data entry exists in those name spaces. Other name
  spaces, for example, used by other applications, are not updated/synchronized. This may result in inconsistent data.
* EXIF/APP1: Although all data read is returned, only the data considered relevant is decoded. For example, thumbnails
  or markernotes are not decoded. Data not decoded is returned as a human readable hexadecimal string.
* EXIF/APP1: Due to the complexity of writing EXIF/APP1 data, the IPTC/NAA records in the EXIF IFD are not updated. They
  are overwritten with \x00.
The author is not aiming a removing these limitations in the future.

# Important note
Files containing caption/description data in more than one language may not be correctly read/saved by most (it not all)
photo editing software, like Photoshop. To the author's knowledge, no of-the-shelve software currently supports multiple
concurrent languages, as described by the *XMP Specification* (referenced as *Lang Alt*). See
https://iptc.org/standards/photo-metadata/interoperability-tests/ for more details about interoperability.


# Support
Free community support is available on `github.com` using the Issues and Discussion sections. The author may
participate in providing free support, but does not guarantee to do so. Guaranteed paid support is available from
the author.


# Project status and road-map
The project is actively maintained. Not new features are currently planned.


# Author
Claude Diderich (cdiderich@cdsp.photo), https://www.cdsp.photo.
The author will respond to e-mails at his own discretion.


# License
MIT https://opensource.org/licenses/mit
