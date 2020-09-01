<?php
namespace superPDO;

include_once __DIR__ . '/exceptions/no_data_found.php';
include_once __DIR__ . '/exceptions/no_single_row.php';

use superPDO\exceptions;

/**
 * Wrapper class to PDO, adding some utility methods useful in common CRUD operations.
 * Being an PDO extension, this class is database agnostic too.
 * Compatible with PHP 7+
 * @author Alejandro Sandoval Véjar.
 */
class SuperPDO extends \PDO{
    /**
	 * Constructor
     * @param string $dsn DSN connection string (like PDO).
     * @param string $username Database name user, if needed.
     * @param string $password User password, if needed.
	 */
    public function __construct(string $dsn, string $username = null, string $password = null){
        parent::__construct($dsn, $username, $password);
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

	/**
	 * Common handler of execution errors on statements.
	 * Get some information about the error when statement execution fail.
	 * @param mixed $stmt Statement with failed execution
     * @throws \Exception In every case. The exception message is the error message obtained from the database.
	 */ 
    private function error(\PDOStatement &$stmt){
        $err = $stmt->errorInfo();
        $stmt->closeCursor();
		unset($stmt);
        throw new \Exception($err[2]);
    }

    /**
     * Creates an statement to execute.
     * @param string $sql SQL sentence to prepare.
     * @param array $params Parameters required to the statement. Array, in name=>value form, designed as input parameters.
     * @return \PDOStatement Statement prepared to execution.
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
	 * Get an scalar value from a query.
	 * Method providing an easy way to make queries like count(), sum(), max() and also when the query just return a single value.
     * This method also free the used resources required to execution.
	 * @param string $sql     Query to execute.
	 * @param array  $params  Optional parameters to the query.
	 * @return mixed The value of the query.
     * @throws NoDataFoundException When query does not return any data
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
        $resp = $row[0];
        unset($stmt);
        unset($row);
        return $resp;
    }

    /**
     * Execute a query.
     * Every row/tuple is converted to an object, when every column name becomes an object attribute/field.
     * @param string $sql SQL Query (usually, a select-like sentence)
     * @param array $params Parameters to the SQL Sentence.
     * @return array Results of the execution (every row is converted to an object)
     */
    public function customQuery(string $sql, array $params = null): array{
        $stmt = $this->createStatement($sql, $params);
        if (!$stmt->execute())
            $this->error($stmt);
        $resp = array();
        //$stmt->setFetchMode(\PDO::FETCH_OBJ);
        while($row = $stmt->fetchObject()){            
            $resp[] = $row;
        }
        $stmt->closeCursor();
		unset($stmt);
        return $resp;
    }

    /**
     * Gets a single row of an SQL statement.
     * The row/tuple is converted to an object, when every column is converted to an object field/attribute.
     * If the query gets more than one row, just the first one is used.
     * @param string $sql SQL Query to execute.
     * @param array $params Parameters required by the query (if any)
     * @return object The data row/tuple converted to an object, <code>null</code> if query does not return data.
     */
    public function singleRowQuery(string $sql, array $params = null){
        $stmt = $this->createStatement($sql, $params);

        if (!$stmt->execute())
            $this->error($stmt);

        $resp = $stmt->fetchObject();
        $stmt->closeCursor();
        unset($stmt);

        if ($resp === false)
            $resp = null;

        return $resp;
    }

    /**
     * Gets the <i>unique</i> result row of a query converted to an object.
     * An Exception will be thrown if the query returns more than a single data row.
     * @param string $sql SQL query.
     * @param array $params Parameters required by query (if any)
     * @return object Object representation of the row/tuple, <code>null</code> if query gets no data.
     * @throws NoSingleRowException When query return more than ONE row
     */
    public function uniqueRowQuery(string $sql, array $params = null) : object {
        $stmt = $this->createStatement($sql, $params);

        if (!$stmt->execute())
            $this->error($stmt);

        $resp = $stmt->fetchObject();
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
     * Execute an SQL statement that does not return data rows, like <i>insert</i>, <i>update</i>, <i>delete</i>.
     * @param string $sql    SQL sentence to execute.
     * @param array  $params Parameters to the SQL sentence (if any) 
     * @return int Affected number of rows.
     */
    public function executeStatement(string $sql, array $params = null) : int{
        $stmt = $this->createStatement($sql, $params);
        $resp = $stmt->execute();
        if ($resp === false)
            $this->error($stmt);
        $stmt->closeCursor();
        unset($stmt);
        return $resp;
    }
    
}