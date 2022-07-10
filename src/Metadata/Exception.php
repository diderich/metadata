<?php
  /**
   * Exception.php - Metadata error handling
   * 
   * @package   Holiday\Metadata
   * @version   1.0
   * @author    Claude Diderich (cdiderich@cdsp.photo)
   * @copyright (c) 2022 by Claude Diderich
   * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
   */

namespace Holiday\Metadata;
use Holiday\Metadata;

class Exception extends \Exception {

  /** Error costants */
  const INTERNAL_ERROR = 0;
  const NOT_IMPLEMENTED = 1;

  /** - File specific errors */
  const FILE_ERROR = 10;
  const FILE_NOT_FOUND = 11;
  const FILE_TYPE_ERROR = 12;
  const FILE_CORRUPT = 13;
  const FILE_COPY_ERROR = 14;

  /** - Data specitic errors */
  const DATA_NOT_FOUND = 21;
  const DATA_FORMAT_ERROR = 22;
  const INVALID_FIELD_ID = 23;
  const INVALID_FIELD_WRITE = 24;

  /** Internal variables */
  protected mixed $data;

  /**
   * Constructor
   *
   * @param string     $message  Exception message text
   * @param int        $ode      Exception message code
   * @param mixed      $data     Exception specific data
   * @param \Throwable $previous Previously thrown exception
   */
  public function __construct(string $message = '', int $code = 0, mixed $data = null, ?\Throwable $previous = null)
  {
	$this->data = $data;
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
