<?php
namespace superPDO\exceptions;

/**
 * Default exception generated when no data has been found for an SQL query.
 */
class NoDataFoundException extends \Exception {
    public function __construct(string $message = null){
        if ($message === null)
            $message = 'No data found';
        parent::__construct($message);
    }
}