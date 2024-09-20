# SuperPDO
Wrapper class to PDO, with helper methods to common task in CRUD operations

## General purpose
This class provides a very thin layer over PDO, giving some helpful methods to manipulate data.

## Configuration
SuperPDO has a singleton implementation, and can manage multiple database connections, identifying every one of them
with a name.

As a convention, SuperPDO asumes that the main connection is called _default_, using that name as a default value.

To add a new connection to SuperPDO, you must use the static addConnection() method.
~~~
SuperPDO::addConnection(string $name, string $dsn, ?string $username = null, ?string $password = null)
~~~
- **$name** is the name (alias) of the connection.This name must be unique, and SuperPDO will throw an exception if the name
was already added.
- **$dsn** is the connection string, as required by PDO.
- **$username** and **$password** are the login data to the database, if required.

The connections have _lazy initialization_, thus they are created only when required by the connection() method.

~~~
SuperPDO::connection(string $name = "default"): SuperPDO
~~~

This is the method to get a connection, with the given name. This call will throw an exception if there is no connection
with the given name, or if the connection could not be fulfilled.


### Getting data

#### Multiple rows
It's a very common scenario to perform the next steps to get some data from PDO:

~~~
//$conn is a PDO instance.
$stmt = $conn->prepare('select * from some_table where some_field = :value and other_field = :other_value');
$stmt->bindValue(':value' => $value1);
$stmt->bindValue(':other_value' => $value2);
if (!$stmt->execute()){
  //handle the error, free resources  
}
$data = array();
while($obj = $stmt->fetchObject()){
  $resp[] = $obj;
}
$stmt->closeCursor();

//$data has all the data
~~~

On every query, you must to repeat this lines, creating a lot of boilerplate code.

With superPDO, all the code above can be rewritten as

~~~
//Asumming that SuperPDO only have a connection configured with the name "default"
try{
  $data = SuperPDO::connection()->selectQuery(
    'select * from some_table where some_field = :value and other_field = :other_value',
    [':value' => $value1, ':other_value' => $value2] 
  );
}
catch(Exception $e){
   //handle error; an exception will be throw if the SQL has error or if the execution of the statement fails
}
~~~

As you can see, the last code is cleaner and shorter than the first one.

The parameters could also be positional (not named), witch generate even shorter code.
~~~
try{
  $data = SuperPDO::connection()->selectQuery(
    'select * from some_table where some_field = ? and other_field = ?',
    [$value1, $value2] 
  );
}
catch(Exception $e){
   //handle error; an exception will be throw if the SQL has error or if the execution of the statement fails
}
~~~

#### One data row

SuperPDO can get just one data row. 
~~~
try{
  $usr = SuperPDO::connection()->singleRowQuery('select * from users where user_id = ?', [$id]);
}
catch(Exception $e){
   //handle error; an exception will be throw if the SQL has error or if the execution of the statement fails
}
~~~

In a very restrictive way, SuperPDO can ensure that a query just create ONE row, throwing and exception if the
query gives zero or more than one data row.
~~~
try{
  $usr = SuperPDO::connection()->uniqueRowQuery('select * from users where user_creation_date = ?', [$date]);
}
catch(Exception $e){
   //handle error; an exception will be throw if the SQL has error or if the execution of the statement fails
}
~~~



### Inserting, updating or deleting data
Insert, update or delete data from a database usually requires almost the same steps to be performed.

~~~
//$conn is a PDO instance
$stmt = $conn->prepare('insert into my_table values(:field1, :field2, :field3, :field4, :field5');
$stmt->bindValue(':field1' => $value1);
$stmt->bindValue(':field2' => $value2);
$stmt->bindValue(':field3' => $value3);
$stmt->bindValue(':field4' => $value4);
$stmt->bindValue(':field5' => $value5);

$rows = $stmt->execute();
if ($rows === false){
  //error executing SQL; handle this
}
if ($rows !== 1){
  //usually, PDO return the number of affected rows; 1 insert means that 1 rows should be affected.
  //if that is not the case, then an error has occurred; handle it.
}

//the rest of your code
~~~

The same result can be achieved with superPDO on this way
~~~
try{
  $rows = SuperPDO::connection()->executeStatement(
    'insert into my_table values(:field1, :field2, :field3, :field4, :field5',
    [':field1' => $value1, ':field2' => $value2, ':field3' => $value3, ':field4' => $value4, ':field5' => $value5]
  );
  if ($rows !== 1){
    //Rows affected doesn't match with the current operation
  }
}
catch(Exception $e){
  //Execution error of the SQL statement; handle it.
}
~~~
