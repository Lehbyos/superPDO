<?php
namespace superPDO;

include_once __DIR__ . '/exceptions/no_data_found.php';
include_once __DIR__ . '/exceptions/no_single_row.php';

use superPDO\exceptions;

/**
 * Wrapper class to PDO, adding some utility methods useful in common CRUD operations.
 * Being an PDO extension, this class is database agnostic too.
 * Compatible with PHP 8+
 * @author Alejandro Sandoval Véjar.
 */
class SuperPDO extends \PDO{
    protected static array $connections = [];

    /**
     * Add a new connection.
     * SuperPDO can handle multiple connections, keeping just one instance (singleton) for every connection name.
     * @param string $name The name/alias of the connection
     * @param string $dsn The DSN to the connection, as required by PDO.
     * @param string|null $username Username to log in (if required)
     * @param string|null $password Password to log in (if required)
     * @return void
     * @throws \Exception If a connection with name <tt>name</tt> is already defined.
     */
    public static function
    addConnection(string $name, string $dsn, ?string $username = null, ?string $password = null): void {
        if (isset(self::$connections[$name]))
            throw new \Exception("A connection with name $name is already defined");

        self::$connections[$name] = new SuperPDO($dsn, $username, $password);
    }

    /**
     * Get the connection with the given name.
     * @param string $name The connection name. By convention, if no name is specified, <tt>default</tt> will be used.
     * @return SuperPDO The connection with the given name.
     * @throws \Exception If there is no connection with name <tt>name</tt>
     */
    public static function connection(string $name = "default"): SuperPDO {
        if (!isset(self::$connections[$name]))
            throw new \Exception("There is no connection with name " . $name);

        return self::$connections[$name];
    }


    /**
	 * Constructor
     * @param string $dsn DSN connection string (like PDO).
     * @param string $username Database name user, if needed.
     * @param string $password User password, if needed.
	 */
    protected function __construct(string $dsn, string $username = null, string $password = null){
        parent::__construct(
            $dsn,
            $username,
            $password,
            [
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ
            ]
        );
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
     * Get a prepared an executed query.
     * This method first prepare the sql statement, execute it, and return it.
     * @param string $sql SQL to execute
     * @param array|null $params Params to the SQL. If they are not required, this could be <tt>null</tt> or an
     * empty array.
     * @return \PDOStatement The statement prepared, with params binded and executed.
     * @throws \Exception If the statement cannot be prepared, or an execution error was found
     */
    protected function getExecutedStatement(string $sql, ?array $params = null): \PDOStatement{
        $stmt = $this->createStatement($sql, $params);
        if (!$stmt->execute())
            throw new \PDOException("Error executing query: " . $this->error($stmt));
        return $stmt;
    }

    /**
     * Creates an statement to execute.
     * @param string $sql SQL sentence to prepare.
     * @param array|null $params Parameters required to the statement.
     * Array, in name=>value form, designed as input parameters is the prefered way (named parameters), but could be
     * a list of parameters if the SQL uses anonymous (?) parameters.
     * Also, the <i>type</i> of the parameters is verified: this method can change it to <tt>PDO::PARAM_INT</tt>,
     * <tt>PDO::PARAM_BOOL</tt> or <tt>PDO::PARAM_STR</tt> automatically.
     * @return \PDOStatement Statement prepared to execution.
     */
    public function createStatement(string $sql, ?array $params = null): \PDOStatement {
        $stmt = $this->prepare($sql);
        if ($params === null || empty($params))
            return $stmt;

        $numParam = 1;
        $type = \PDO::PARAM_STR;
        $realName = null;

        foreach($params as $name => $value){
            if (is_int($name)){
                //is not a param name...
                $realName = $numParam;
            } else {
                $realName = $name;
            }

            if (is_int($value))
                $type = \PDO::PARAM_INT;
            else if (is_bool($value))
                $type = \PDO::PARAM_BOOL;

            if (!$stmt->bindValue($realName, $value, $type))
                throw new \PDOException("Error binding parameter " . $realName);

            $numParam++;
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
    public function selectQuery(string $sql, array $params = null, callable $format = null): array{
        $stmt = $this->getExecutedStatement($sql, $params);

        $resp = array();
        $formatting = $format !== null;


        while($row = $stmt->fetchObject()){
            if ($formatting)
                $resp[] = $format($row);
            else
                $resp[] = $format($row);
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
    public function singleRowQuery(string $sql, array $params = null, bool $nullFieldsIfNoData = false): ?object{
        $stmt = $this->getExecutedStatement($sql, $params);

        $resp = $stmt->fetchObject();
        if ($resp === null && $nullFieldsIfNoData){
            for($i = 0, $maxCols = $stmt->columnCount(); $i < $maxCols; ++$i){
                $col = $stmt->getColumnMeta($i)["name"];
                $resp->$col = null;
            }
        }

        $stmt->closeCursor();
        unset($stmt);

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
    public function uniqueRowQuery(string $sql, array $params = null, bool $nullFieldsIfNoData = false) : ?object {
        $stmt = $this->getExecutedStatement($sql, $params);

        if ($stmt->rowCount() > 1)
            throw new exceptions\NoSingleRowException();

        $resp = $stmt->fetchObject();
        if ($resp === null && $nullFieldsIfNoData){
            for($i = 0, $maxCols = $stmt->columnCount(); $i < $maxCols; ++$i){
                $col = $stmt->getColumnMeta($i)["name"];
                $resp->$col = null;
            }
        }

        $stmt->closeCursor();
        unset($stmt);

        return $resp;
    }

    /**
     * Execute an SQL statement that does not return data rows, like <i>insert</i>, <i>update</i>, <i>delete</i>.
     * @param string $sql    SQL sentence to execute.
     * @param array  $params Parameters to the SQL sentence (if any)
     * @return int Affected number of rows.
     */
    public function executeStatement(string $sql, array $params = null) : int{
        $stmt = $this->getExecutedStatement($sql, $params);
        $resp = $stmt->rowCount();
        $stmt->closeCursor();
        unset($stmt);
        return $resp;
    }

}
