<?php
namespace superPDO\exceptions;

/**
 * Excepción generada cuando, para una consulta de fila única, se encuentra más de una.
 */
class NoSingleRowException extends \Exception {
    public function __construct(string $message = null){
        if ($message === null)
            $message = 'Consulta devuelve más de una fila, se esperaba único resultado';
        parent::__construct($message);
    }
}