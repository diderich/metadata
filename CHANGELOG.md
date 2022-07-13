# CHANGELOG.md

Current version: `v1.0.2`

All notable changes to the **HOLIDAY - Metadata** PHP classes for reading and writing metadata from/to JPG image files.

## v1.0.2 - 2022-07-13
Correced bug in decoding EXIF data where incorrect data type information was used.

## v1.0.1 - 2022-07-10
* Improved namespace handing in `XmpDocument` class
* Added option `read_ony` in `Jpeg` and `Metadata` to read the image data file without reading/importing the whole
  image, reducing memory usage and improving reading speed
* Added fields with extension _FMT in `Metadata` covering EXIF data in which data is pre-formatted
* Updated `locale` translation file

## v1.0.0 - 2022-07-10
Initial release
