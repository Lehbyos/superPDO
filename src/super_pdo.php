<?php
namespace superPDO;

include_once __DIR__ . '/exceptions/no_data_found.php';
include_once __DIR__ . '/exceptions/no_single_row.php';

use superPDO\exceptions;

/**
 * Clase base para objetos encargadados de tener acceso a datos.
 * Permite definir una API común para todos los objetos de este tipo.
 * Compatible con PHP 7+
 */
class SuperPDO extends \PDO{
	/**
	 * Constructor.
	 * Inicialización común.
	 */
    public function __construct(string $dsn, string $username = null, string $password = null){
        parent::__construct($dsn, $username, $password);
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

	/**
	 * Gestiona error en una sentencia.
	 * Se encarga de obtener datos de error cuando se ha confirmado que la ejecución de una sentencia falló.
	 * Recibe el statement por referencia, ya que realiza algunas operaciones sobre el mismo.
	 * @param mixed $stmt Sentencia a la cual falló su ejecución.
	 */ 
    public function error(\PDOStatement &$stmt){
        $err = $stmt->errorInfo();
        $stmt->closeCursor();
		unset($stmt);
        throw new \Exception($err[2]);
    }

    /**
     * Crea un statement para una instrucción SQL.
     * @param string $sql Sentencia SQL a preparar.
     * @param array $params parámetros para la sentencia SQL.
     * @return PDOStatement Objeto preparado para ejecución de sentencia.
     */
    public function createStatement(string $sql, array $params = null): \PDOStatement {
        $stmt = $this->prepare($sql);
        if ($params !== null){
            foreach($params as $name => $value){
                $stmt->bindValue($name, $value);
            }
        }
        return $stmt;
    }

    /**
	 * Obtener valor único de una consulta.
	 * Se utiliza para consultas que obtienen un único valor, tales como count(), sum(), max(), etc.
	 * @param string $sql     Consulta a ejecutar.
	 * @param array  $params  Parámetros de la consulta (opcionales)
	 * @return mixed Valor de la consulta.
	 */
    public function scalarQuery(string $sql, array $params = null){
        $stmt = $this->createStatement($sql, $params);
        if (!$stmt->execute())
            $this->error($stmt);

        $row = $stmt->fetch(\PDO::FETCH_NUM);
        if ($row === false){
            $stmt->closeCursor();
            throw new exceptions\NoDataFoundException();
        }

        $stmt->closeCursor();
		unset($stmt);
        return $row[0];
    }

    /**
     * Realiza una consulta a la base de datos.
     * Cada fila/registro es obtenida como un objeto.
     * @param string $sql Sentencia SQL a ejecutar (select)
     * @param array $params Parámetros (opcionales) de la sentencia SQL.
     * @return array Arreglo de filas (convertidas en objeto) obtenidas de la sentencia.
     */
    public function customQuery(string $sql, array $params = null): array{
        $stmt = $this->createStatement($sql, $params);
        if (!$stmt->execute())
            $this->error($stmt);
        $resp = array();
        $stmt->setFetchMode(\PDO::FETCH_OBJ); //PDO_FETCH_OBJ en PHP < 5.1
        while($registro = $stmt->fetch()){            
            $resp[] = $registro;
        }
        $stmt->closeCursor();
		unset($stmt);
        return $resp;
    }

    /**
     * Devuelve una fila de datos desde la sentencia indicada.
     * Se espera que sentencia retorne al menos una fila de datos, la cual será devuelta
     * convertida en objeto. Si no hay data, se genera excepción NoDataFoundException.
     * @param string $sql Sentencia SQL a ejecutar.
     * @param array $params Parámetros (opcionales) de la sentencia
     * @return Fila de datos obtenida desde BD, <code>null</code> si no hay datos.
     */
    public function singleRowQuery(string $sql, array $params = null){
        $stmt = $this->createStatement($sql, $params);

        if (!$stmt->execute())
            $this->error($stmt);

        $stmt->setFetchMode(\PDO::FETCH_OBJ);
        $resp = $stmt->fetch();
        $stmt->closeCursor();
        unset($stmt);

        if ($resp === false)
            $resp = null;

        return $resp;
    }

    /**
     * Devuelve la única fila de datos que debiera obtenerse de una sentencia SQL.
     * De haber más de una, se genera excepción NoSingleRowException
     * @param string $sql Sentencia SQL a ejecutar para obtener dato.
     * @param array $params Parámetros (opcionales) de la sentencia SQL.
     * @return Objeto representando la fila de datos, <code>null</code> si no hay datos.
     * @throws NoSingleRowException Si sentencia retorna más de una fila de datos.
     */
    public function uniqueRowQuery(string $sql, array $params = null) : object {
        $stmt = $this->createStatement($sql, $params);

        if (!$stmt->execute())
            $this->error($stmt);

        $stmt->setFetchMode(\PDO::FETCH_OBJ);
        $resp = $stmt->fetch();
        $aux = false;
        if ($resp !== false){
            $aux = $stmt->fetch();
            $stmt->closeCursor();
            unset($stmt);
            if ($aux !== false)
                throw new exceptions\NoSingleRowException();
        }
        else
            $resp = null;
        
        return $resp;
    }
    
    /**
     * Realiza ejecución típica para sentencias Insert, Update o Delete.
     * Básicamente, se trata de ejecutar la sentencia y verificar si sólo una fila se ve afectada,
     * manejando además cualquier error que pudiera derivarse de la ejecución de la sentencia.
     * @param PDOStatement $stmt Sentencia a ejecutar
     * @return <code>true</code> si sólo una fila se vio afectada por sentencia SQL, <code>false</code> en caso contrario.
     */
    public function executeStatement(string $query, array $params = null) : int{
        $stmt = $this->createStatement($query, $params);
        $resp = $stmt->execute();
        if ($resp === false)
            $this->error($stmt);
        $stmt->closeCursor();
        unset($stmt);
        return $resp;
    }
    
}