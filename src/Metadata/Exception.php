<?php
  /**
   * Exception.php - Metadata error handling
   * 
   * @package   Holiday\Metadata
   * @version   1.2
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/mit MIT
   */

namespace Holiday\Metadata;
use Holiday\Metadata;

class Exception extends \Exception {

  /** Error costants */
  public const INTERNAL_ERROR = 0;
  public const NOT_IMPLEMENTED = 1;

  /** - File specific errors */
  public const FILE_ERROR = 10;
  public const FILE_NOT_FOUND = 11;
  public const FILE_TYPE_ERROR = 12;
  public const FILE_CORRUPT = 13;
  public const FILE_COPY_ERROR = 14;

  /** - Data specitic errors */
  public const DATA_NOT_FOUND = 21;
  public const DATA_FORMAT_ERROR = 22;
  public const INVALID_FIELD_ID = 23;
  public const INVALID_FIELD_WRITE = 24;
  public const INVALID_FIELD_DATA = 25;
  public const INVALID_LANG = 26;

  /**
   * Constructor
   *
   * @param string     $message  Exception message text
   * @param int        $ode      Exception message code
   * @param mixed      $data     Exception specific data
   * @param \Throwable $previous Previously thrown exception
   */
  public function __construct(string $message = '', int $code = 0, protected mixed $data = null, 
    ?\Throwable $previous = null)
  {
	parent::__construct($message, $code, $previous);
  }

  /**
   * Return error specific data
   *
   * @return mixed Exception specific data formatted as string
   */
  public function getData(): string
  {
	switch(gettype($this->data)) {
	case 'string':
	case 'integer':
	case 'double':
	  return (string)$this->data;
	case 'array':
	  $result = '';
	  foreach($this->data as $value) {
		if(!empty($result)) $result .= ', ';
		switch(gettype($value)) {
		case 'string':
		case 'integer':
		case 'double':
		  $result .= (string)$this->data;
		  break;
		default:
		  $result .= gettype($value);
		  break;
		}
	  }
	  return "array($result)";
	case 'boolean':
	case 'NULL':
	  return '';
	default:
	  return gettype($this->data);
	}
  }
}
?>
