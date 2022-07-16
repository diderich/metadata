# CHANGELOG.md

Current version: `v1.1.0`

Notable changes to **Metadata** - A PHP class for reading and writing *Photo Metadata* from JPEG files in a transparent
way:

## v1.1.0 - 2022-07-15
Support for multi-lingual captions, that is, the CAPTION field, added. Not that using other languages than the default
`x-default`, although consistent with the IPTC standard, will not work with typical photo applications, like Photoshop
or Photomechanic. See https://iptc.org/standards/photo-metadata/interoperability-tests/ for further information.

## v1.0.3 - 2022-07-15
Added functionality to support multi-lingual caption data in the future. Functionality is not yet fully implemented
because no other software can read/write multi-lingual data (yet).

## v1.0.2 - 2022-07-13
Corrected bug in decoding EXIF data where incorrect data type information was used.

## v1.0.1 - 2022-07-10
* Improved namespace handing in `XmpDocument` class
* Added option `read_only` in `Jpeg` and `Metadata` to read the image data file without reading/importing the whole
  image, reducing memory usage and improving reading speed
* Added fields with extension _FMT in `Metadata` covering EXIF data in which data is pre-formatted
* Updated `locale` translation file

## v1.0.0 - 2022-07-10
Initial release
