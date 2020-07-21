<?php
namespace superPDO\exceptions;

/**
 * Default exception generated when a single row query return two or more rows.
 */
class NoSingleRowException extends \Exception {
    public function __construct(string $message = null){
        if ($message === null)
            $message = 'Query returns many rows, just one was expected';
        parent::__construct($message);
    }
}