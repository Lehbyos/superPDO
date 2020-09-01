# superPDO
Wrapper class to PDO, with helper methods to common task in CRUD operations

## General porpuse
This class provides a very thin layer over PDO, giving some helpful methods to manipulate data.

### Getting data
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
//$conn now is a superPDO instance; note that superPDO extends PDO.
try{
  $data = $conn->customQuery(
    'select  from some_table where some_field = :value and other_field = :other_value',
    [':value' => $value1, ':other_value' => $value2] 
  );
}
catch(Exception $e){
   //handle error; an exception will be throw if the SQL has error or if the execution of the statement fails
}
~~~

As you can see, the last code is cleaner and shorter than the first one.



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
//$conn is now a superPDO instance
try{
  $rows = $conn->executeStatement(
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
