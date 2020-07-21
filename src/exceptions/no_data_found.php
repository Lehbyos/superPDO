<?php
namespace superPDO\exceptions;

/**
 * Excepción generada cuando no se encuentran datos para una consulta.
 */
class NoDataFoundException extends \Exception {
    public function __construct(string $message = null){
        if ($message === null)
            $message = 'Sin datos';
        parent::__construct($message);
    }
}