<?php
	/*
	*	MYSQLI_Database is a class that performs handling of connections and various querying for a mysql database.
	*
	*	Dependencies:
	*		The PHP mysqli library
	*	
	*	Functions:
	*	(1) query - executes a database query.
	*	(2) transaction - executes one or more queries in a transaction block.
	*	(3) getColumnInformation - returns column details for a specific table.
	*	(4) validateAndFormatFieldValue - formats a field value and returns an array with information about the validation/formatting.
	*	(5) processFieldDataForInsert - processes an entire set of data for a table insert.  
	*								   The addRecord method calls this method exclusively.
	*	(6) getSpecific - returns the information for a specific row.
	*	(7) getAll - returns all records in a table.
	*	(8) addRecord - inserts a record.  It is normally used as in a call from a class that inherits from this class.
	*	(9) editRecord - edits a specific record.  It is normally used as in a call from a class that inherits from this class.
	*	(10) deleteRecord - deletes a specific record.  It is normally used as in a call from a class that inherits from this class.
	*
	*	@author			Hartman Technology - Justin Koch
	*	@version		1.0
	*	@since			11/11/2013
	*/

	class MYSQLI_Database
	{	
		protected $hostname;
		protected $user;
		protected $password;
		protected $database;
		protected $escape = false;
		//a list of mysql field types that require values to be quoted in a query
		protected $quoteFieldTypes = array("date", "datetime", "time", "varchar", "char", "text");
		
		/*
		*	constructor creates the object
		*	
		*	@param hostname (string) 			The hostname of the mysql server.
		*	@param user	    (string) 			The mysql user.
		*	@param password (string) 			The password for the mysql user.
		*	@param database (string) 			The name of the mysql database.
		*	@param escape   (bool, optional) 	An indicator of whether data values need to be escaped upon query.
		*/
		public function __construct($hostname, $user, $password, $database, $escape=null)
		{
			$this->hostname = $hostname;
			$this->user = $user;
			$this->password = $password;
			$this->database = $database;
			if (isset($escape) and true === $escape)
			{
				$this->escape = $escape;
			}
		}
		
		/*
		*	query executes a database query.
		*	
		*	@param query 		 (string)				The query to be run.
		*	@param resultAsArray (bool, optional)		An indicator of result return. 
		*												True to return an array of associative array rows.
		*												Not passed or any other data type to return a result set.
		*	@param returnQueryOnly	(bool, optional)	True to return the query only without query execution.
		*												False or not passed to run query normally.
		*
		*	@return	resultArr		(array)				An associative array containing the following elements:
		*												Index "query" (string) The query that was constructed.
		*												Index "status" (string) The result of the execution. 
		*												Values will be one of the following:
		*													"success" - indicates the query ran successfully.
		*													"error"   - indicates the query failed.
		*													"queryOnly" - only exists if returnQueryOnly param is set to true.
		*												Index "errors" (array) descriptive strings of errors.											
		*												Index "result" (result set) Contains the result returned from s successful
		*/
		public function query($query, $resultAsArray=null, $returnQueryOnly=null)
		{
			$resultArr = array();
			$resultArr['query'] = $query;
			
			//instantiate the mysqli obj
			$conn = new mysqli($this->hostname, $this->user, $this->password, $this->database);
			
			//check connection, returning an error if necessary.
			if ($conn->connect_error) 
			{
				$resultArr['status'] = "error";	
				$resultArr['errors'] = array("Connect Error: " . $conn->connect_error);
				
				//close the connection, return the error
				$conn->close();
				
				return $resultArr;
			}
			
			//output the query, if passed
			if (isset($returnQueryOnly) and true === $returnQueryOnly)
			{
				$resultArr['status'] = "queryOnly";	
			}
			else
			{
				$result = $conn->query($query);
				
				if (false === $result)
				{
					$resultArr['status'] = "error";	
					$resultArr['errors'] = array("Query execution failed.");	
				}
				else
				{
					$resultArr['status'] = "success";
					
					//if inserting, return the insert id
					if ($conn->insert_id)
					{
						$resultArr['result'] = $conn->insert_id;	
					}
					else
					{
						if (isset($resultAsArray) and true === $resultAsArray and false !== $result)
						{
							//build an array of rows
							$resultArray = array();
								while ($row = $result->fetch_assoc()) 
								{
									$resultArray[] = $row;
								}
								//free the result set
								$result->close();
								$resultArr['result'] = $resultArray;
						   
						}
						else
						{
							$resultArr['result'] = $result;
						}
					}
				}
			}
			
			return $resultArr;
		}
		
		/*
		*	transaction executes one or more queries in a transaction block. If any query fails in the transaction, 
		*	a rollback is issued. Otherwise, a commit is issued.
		*	
		*	@param  queries 	  (string or string array)	The query(s) to be run in the transaction.
		*	@param resultAsArray  (bool, optional)			An indicator of result return. 
		*													True to return an array of associative array rows.
		*													Not passed or any other data type to return a result set.
		*
		*	@return	resultArr		(array)					An associative array containing the following elements:
		*													Index "queries" (array) The queries as part of the transaction.
		*													Index "status" (string) The result of the execution. 
		*													Values will be one of the following:
		*														"success" - indicates the query ran successfully.
		*														"error"   - indicates the query failed.
		*														"queryOnly" - only exists if returnQueryOnly param is set to true.
		*													Index "errors" (array) Descriptive strings of errors.											
		*													Index "results" (array) Results of each query that was run.
		*/
		public function transaction($queries, $resultAsArray=null)
		{
			$resultArr = array();
			
			//process the query array
			$queryArr = array();
			if (is_string($queries))
			{
				$queryArr[] = $queries;
			}
			elseif(is_array($queries))
			{
				$queryArr = $queries;
			}
			
			$resultArr['queries'] = $queryArr;
		
			//instantiate the mysqli obj
			$conn = new mysqli($this->hostname, $this->user, $this->password, $this->database);
			
			//check connection, returning an error if necessary.
			if ($conn->connect_error) 
			{
				$resultArr['status'] = "error";
				$resultArr['errors'] = array("Connect Error: " . $conn->connect_error);
				
				//close the connection, return the error
				$conn->close();
				
				return $resultArr;
			}
		
			//start the transaction
			$beginStmt = "BEGIN";
			$conn->query($beginStmt);
			
			//go through each query
			$queryErrors = array();
			$queryResults = array();
			foreach($queryArr as $query)
			{
				//query the database
				$queryResult = $conn->query($query);
				
				//query failed, add to errors
				if (false === $queryResult)
				{
					$queryErrors[] = $query;
				}
				else
				{
					//if inserting, return the insert id
					if ($conn->insert_id)
					{
						$queryResults[] = $conn->insert_id;	
					}
					else
					{
						if (isset($resultAsArray) and true === $resultAsArray and false !== $queryResult)
						{
							//build an array of rows
							$resultArray = array();
							while ($row = $queryResult->fetch_assoc()) 
							{
								$resultArray[] = $row;
							}
							//free the result set
							$queryResult->close();	
							$queryResults[] = $resultArray;
						}
						else
						{
							$queryResults[] = $queryResult;
						}
					}	
				}
			}
			
			//if errors in running any query in the transaction, rollback
			if (!empty($queryErrors))
			{
				//rollback
				$rollbackStmt = "ROLLBACK";
				$conn->query($rollbackStmt);
				$resultArr['status'] = "error";
				$resultArr['errors'] = $queryErrors;
			}
			else
			{
				//commit
				$commitStmt = "COMMIT";
				$conn->query($commitStmt);
				$resultArr['status'] = "success";
				$resultArr['results'] = $queryResults;
			}
			
			return $resultArr;
		}
		
		/*
		*	getColumnInformation returns column details for a specific table.
		*
		*	@param table			(string)				The name of the database table.
		*	@param returnArray  	(boolean, optional) 	Return an array. 
		*													True to return the column details as an array.
		*													False or not passed to return the result set.
		*	@param justColumnNames 	(boolean, optional)		If returning an array:
		*													True to return the column name only.
		*													False or not passed to return all data about the column.
		*
		*	@return 				(array or result set)	Return type depends on returnArray parameter.
		*/
		public function getColumnInformation($table, $returnArray=null, $justColumnNames=null)
		{
			$qry = "SHOW COLUMNS FROM " . $table;
            
            $columnsResult = $this->query($qry);
            
			if (isset($returnArray) and true === $returnArray)
			{
				$fieldArray = array();
				if ("success" == $columnsResult['status'] and $columnsResult['result']->num_rows > 0)
				{
					while ($field = $columnsResult['result']->fetch_assoc())
					{
						$columnName = $field['Field'];
						//delete the length data from the type field
						$field['Type'] = preg_replace("/\(.*\)/", "", $field['Type']);
						
						if (isset($justColumnNames) and true === $justColumnNames)
						{	
							$fieldArray[$columnName] = $columnName;
						}
						else
						{
							$fieldArray[$columnName] = $field;
						}
					}
				}
				return $fieldArray;
			}
			else
			{
				return $columnsResult;
			}
		}
		
		/*
		*	validateAndFormatFieldValue formats a field value and returns an array with information about the validation/formatting.
		*
		*	@param fieldName	(string)	The name of the field.
		*	@param type 		(string) 	The type of the field. Examples: varchar, int
		*	@param value 		(string) 	The value of the field.
		*
		*	@return 			(array)		An array containing the properly formatted value and any errors found during validation.
		*/
		private function validateAndFormatFieldValue($fieldName, $type, $value)
		{
			$error = "";
			
			//validation regex patterns
			$datePattern = "/^\d{4}\-\d{2}\-\d{2}$/";
			$altDatePattern = "/^\d{2}\/\d{2}\/\d{4}$/"; //date pattern of MM/DD/YYYY, which we use for calendar popups
			$dateTimePattern = "/^\d{4}\-\d{2}\-\d{2} \d{2}:\d{2}:\d{2}$/";
			$timePattern = "/^\d{2}:\d{2}:\d{2}$/";
			$intPattern = "/^[-+]?\d+$/";
			$floatPattern = "/^[-+]?[0-9]*\.?[0-9]+$/";
			
			//specific validations
			if (false !== strpos($type, "int") or false !== strpos($type, "tinyint"))
			{
				if (!preg_match($intPattern, $value))
				{
					$error = "You have entered an improper value, " . $value .",  for field " . $fieldName . ", which is of type " . $type . ".";
				}
			}
			
			if (false !== strpos($type, "float"))
			{
				if (!preg_match($floatPattern, $value))
				{
					$error = "You have entered an improper value, " . $value .",  for field " . $fieldName . ", which is of type " . $type . ".";
				}
			}
			
			if ($type == "date")
			{
				//if the pattern is of MM/DD/YYYY, which we typically used on calendar popups, format it
				if (preg_match($altDatePattern, $value))
				{
					$value = date("Y-m-d", strtotime($value));
				}
				
				if (!preg_match($datePattern, $value))
				{
					$error = "You have entered an improper value, " . $value .",  for field " . $fieldName . ", which is of type " . $type . ". Proper Format: YYYY-MM-DD";
				}
				else
				{
					$value = "'" . $value . "'";
				}
			}
			
			if ($type == "datetime")
			{
				if (!preg_match($dateTimePattern, $value))
				{
					$error = "You have entered an improper value, " . $value .",  for field " . $fieldName . ", which is of type " . $type . ". Proper Format: YYYY-MM-DD HH:MM:SS";
				}
				else
				{
					$value = "'" . $value . "'";
				}
			}
			
			if ($type == "timestamp")
			{
				if (!preg_match($dateTimePattern, $value))
				{
					$error = "You have entered an improper value, " . $value .",  for field " . $fieldName . ", which is of type " . $type . ". Proper Format: YYYYMMDDHHMMSS";
				}
				else
				{
					$value = "'" . $value . "'";
				}
			}
			
			if ($type == "time")
			{
				if (!preg_match($timePattern, $value))
				{
					$error = "You have entered an improper value, " . $value .",  for field " . $fieldName . ", which is of type " . $type . ". Proper Format: HH:MM:SS";
				}
				else
				{
					$value = "'" . $value . "'";
				}
			}

			if (false !== strpos($type, "varchar") or $type == "text" or false !== strpos($type, "char"))
			{
				// if needing to escape the string
				if ($this->escape)
				{
					$conn = new mysqli($this->hostname, $this->user, $this->password, $this->database);
					$value = $conn->real_escape_string($value);
					$conn->close();
				}
				$value = "'" . $value . "'";
			}
			
			return array("value" => $value, "error" => $error); 	
		}
		
		/*
		*	processFieldDataForInsert processes an entire set of data for a table insert.  The addRecord method calls this method exclusively.
		*
		*	@param table	(string)	The name of the table.
		*	@param data		(array)		An associative one-dimensional array with field names as keys, and values as elements.
		*
		*	@return			(array)		An array containing the processed fields or errors if one or more fields failed validation.
		*/
		private function processFieldDataForInsert($table, $data)
		{
        	$columnsResult = $this->getColumnInformation($table);
			
			if ("success" == $columnsResult['status'] and $columnsResult['result']->num_rows > 0)
			{
            	$errors = array();
				$processedFieldArray = array();
				
            	while ($field = $columnsResult['result']->fetch_assoc())
                {
                	$fieldName = (string) $field['Field'];
					$extra = $field['Extra'];
					$null = $field['Null'];
					$type = $field['Type'];
					$default = $field['Default'];
					
					//if the field is an auto increment, and a value isn't passed for the field, continue to next iteration
					if (false !== strpos($extra, "auto_increment") and (!isset($data[$fieldName]) or (isset($data[$fieldName]) and !strlen($data[$fieldName]))))
					{
						continue;
					}
					
                	// if the field is not nullable, but the passed data doesn't exist or doesn't have a value
                	if ($null == "NO" and isset($data[$fieldName]) and !strlen($data[$fieldName]) )
                    {
						if (isset($default) and strlen($default))
						{
							$default = self::validateAndFormatFieldValue($fieldName, $type, $default);
							$processedFieldArray[$fieldName] = $default['value'];
						}
						else
						{
                    		$errors[] = "The data passed for field " . $fieldName . " was blank, but the field requires a value.";
						}
                    }
                    elseif ($null == "NO" and !isset($data[$fieldName]))
                    {
						if (isset($default) and strlen($default))
						{
							$default = self::validateAndFormatFieldValue($fieldName, $type, $default);
							$processedFieldArray[$fieldName] = $default['value'];
						}
						else
						{
                    		$errors[] = "There was no data passed for field " . $fieldName . ", but the field requires a value.";
						}
                    }
					elseif ($null == "YES" and isset($data[$fieldName]) and !strlen($data[$fieldName]))
					{
						if (isset($default) and strlen($default))
						{
							$default = self::validateAndFormatFieldValue($fieldName, $type, $default);
							$processedFieldArray[$fieldName] = $default['value'];
						}
						else
						{
							$processedFieldArray[$fieldName] = "NULL";
						}
					}
					elseif ($null == "YES" and !isset($data[$fieldName]))
					{
						if (isset($default) and strlen($default))
						{
							$default = self::validateAndFormatFieldValue($fieldName, $type, $default);
							$processedFieldArray[$fieldName] = $default['value'];
						}
						else
						{
							$processedFieldArray[$fieldName] = "NULL";
						}
					}
					else
					{
						$value = $data[$fieldName];
						//validate and format the value for add
						$validation = self::validateAndFormatFieldValue($fieldName, $type, $value);
						
						if (strlen($validation['error']))
						{
							$errors[] = $validation['error'];
						}
						else
						{
							$processedFieldArray[$fieldName] = $validation['value'];
						}
					}
                }
				
				if (!empty($errors))
				{
					return array("errors" => $errors);
				}
				else
				{
					return array("fields" => $processedFieldArray);
				}
				
			}
			else
            {
				return array("errors" => array("Error calling function in database.  Most likely wrong table name passed (" . $table . "), or that table doesn't exist, or the table exists and has no fields."));
            }
		}
		
		/*
		*	getSpecific returns the information for a specific row.
		*
		*	@param table			(string)			The name of the table.
		*	@param primaryValue		(string or int)		The value acting as a key to find the specific row.
		*	@param specificField	(string, optional)	A specific field, different from the primary key field, to use as the key.
		*	@param orderBy			(array, optional)	An associative array containing two elements:
		*												Index "field" (string) The name of the field.
		*												Index "direction" (string) The direction of the sort (ASC or DESC).
		*	@param fields			(array, optional)	An array of strings of fields to be returned in the query.		
		*	@param returnQueryOnly	(bool, optional)	True to return the query only without query execution.
		*												False or not passed to run query normally.
		*
		*	@return	resultArr		(array)				An associative array containing the following elements:
		*												Index "query" (string) The query that was constructed.
		*												Index "status" (string) The result of the execution. 
		*												Values will be one of the following:
		*													"success" - indicates the query ran successfully.
		*													"error"   - indicates the query failed.
		*													"queryOnly" - only exists if returnQueryOnly param is set to true.
		*												Index "errors" (array) descriptive strings of errors.											
		*												Index "result" (result set) Contains the result returned from s successful query.											
		*/
		public function getSpecific($table, $primaryValue, $specificField=null, $orderBy=null, $fields=null, $returnQueryOnly=null)
		{
			//find the primary column
			$columnsInfoArray = $this->getColumnInformation($table, true);
	
			$pkArray = array();
			foreach ($columnsInfoArray as $col)
			{
				//find the primary columns, or the specific column
				if ((isset($col['Key']) and "PRI" == $col['Key'] and !isset($specificField)) or (isset($specificField) and $specificField == $col['Field']))
				{
					$pkKey = $col['Field'];
					if (in_array($col['Type'], $this->quoteFieldTypes))
					{
						$toQuote = "quote";
					}
					else
					{
						$toQuote = "noquote";
					}
					$pkArray[$pkKey] = $toQuote;
				}
			}
			
			$id = $primaryValue;
			//construct the where clause based on passed ids, mapped to their primary columns
			$whereStmt = array();
			if (is_array($id))
			{
				$idArray = $id;
			}
			else
			{
				//if id is a string, map it to the first primary column, if it exists
				if (false !== key($pkArray))
				{
					$firstPri = key($pkArray);
					$idArray = array($firstPri => $id);
				}
				else
				{
					$idArray = array($firstPri);
				}
			}
			//map the ids to the primary columns
			foreach($idArray as $idField => $idValue)
			{
				if (isset($pkArray[$idField]))
				{
					if ("quote" == $pkArray[$idField])
					{
						if ($this->escape)
						{
							$conn = new mysqli($this->hostname, $this->user, $this->password, $this->database);
							$idValue = $conn->real_escape_string($idValue);
							$conn->close();
						}
	
						$idValue = "'" . $idValue . "'";
					}
					$whereStmt[] = "`" . $idField . "` = " . $idValue;
				}
			}
			//construct the where statement
			if (!empty($whereStmt))
			{
				$whereStmt = "WHERE " . implode(" AND ", $whereStmt);
			}
			else
			{
				$whereStmt = "";
			}

			$orderByStmt = "";
			if (isset($orderBy) and is_array($orderBy))
			{
				$obField = $orderBy['field'];
				$obDir = $orderBy['direction'];
				$orderByStmt = "ORDER BY `$obField` $obDir";
			}
			
			$fieldsStmt = "*";
			if (isset($fields) and is_array($fields))
			{
				$fieldsStmt = "";
				for ($i = 0; $i < count($fields); $i++)
				{
					$field = $fields[$i];
					$fieldsStmt .= "`" . $field . "`";
					if ($i < count($fields) - 1)
					{
						$fieldsStmt .= ", ";
					}
				}	
			}
			
			$table = "`" . $table . "`";
				
			$resultArr = array();
			
			$qry = "SELECT $fieldsStmt
				FROM $table
				$whereStmt
				$orderByStmt";
				
			$resultArr['query'] = $qry;	
			
			//output the query, if passed
			if (isset($returnQueryOnly) and true === $returnQueryOnly)
			{
				$resultArr['status'] = "queryOnly";	
			}
			else
			{
				$result = $this->query($qry);
				
				if (false === $result)
				{
					$resultArr['status'] = "error";	
					$resultArr['errors'] = array("Query execution failed.");	
				}
				else
				{
					$resultArr['status'] = "success";	
					$resultArr['result'] = $result['result'];	
				}
			}
			
			return $resultArr;
		}
		
		/*
		*	getAll returns all records in a table.
		*
		*	@param table			(string)			The name of the table.
		*	@param orderBy			(array, optional)	An associative array containing two elements:
		*												Index "field" (string) The name of the field.
		*												Index "direction" (string) The direction of the sort (ASC or DESC).
		*	@param fields			(array, optional)	An array of strings of fields to be returned in the query.		
		*   @param whereClause		(string, optional)	A specific string for the where clause to append to the query.
		*	@param returnQueryOnly	(bool, optional)	True to return the query only without query execution.
		*												False or not passed to run query normally.
		*
		*	@return	resultArr		(array)				An associative array containing the following elements:
		*												Index "query" (string) The query that was constructed.
		*												Index "status" (string) The result of the execution. 
		*												Values will be one of the following:
		*													"success" - indicates the query ran successfully.
		*													"error"   - indicates the query failed.
		*													"queryOnly" - only exists if returnQueryOnly param is set to true.
		*												Index "errors" (array) descriptive strings of errors.											
		*												Index "result" (result set) Contains the result returned from s successful query.											
		*/
		public function getAll($table, $orderBy=null, $fields=null, $whereClause=null, $returnQueryOnly=null)
		{
			$orderByStmt = "";
			if (isset($orderBy) and is_array($orderBy))
			{
				$obField = $orderBy['field'];
				$obDir = $orderBy['direction'];
				$orderByStmt = "ORDER BY `$obField` $obDir";
			}
			
			$fieldsStmt = "*";
			if (isset($fields) and is_array($fields))
			{
				$fieldsStmt = "";
				for ($i = 0; $i < count($fields); $i++)
				{
					$field = $fields[$i];
					$fieldsStmt .= "`" . $field . "`";
					if ($i < count($fields) - 1)
					{
						$fieldsStmt .= ", ";
					}
				}
			}
			
			$whereStmt = "";
			if (isset($whereClause) and strlen($whereClause))
			{
				$whereStmt = $whereClause;
			}
			
			$table = "`" . $table . "`";
			
			$resultArr = array();
			
			$qry = "SELECT $fieldsStmt
				FROM $table
				$whereStmt 
				$orderByStmt";
			
			$resultArr['query'] = $qry;	
			
			//output the query, if passed
			if (isset($returnQueryOnly) and true === $returnQueryOnly)
			{
				$resultArr['status'] = "queryOnly";	
			}
			else
			{
				$result = $this->query($qry);
				
				if (false === $result)
				{
					$resultArr['status'] = "error";	
					$resultArr['errors'] = array("Query execution failed.");	
				}
				else
				{
					$resultArr['status'] = "success";	
					$resultArr['result'] = $result['result'];	
				}
			}
			
			return $resultArr;
		}
		
		/*
		*	addRecord inserts a record.  It is normally used as in a call from a class that inherits from this class.
		*
		*	@param table			(string)			The name of the table.
		*	@param data				(array)				An associative array containing elements of field data. The keys are the field names and the values are the values for the field.
		*	@param returnQueryOnly	(bool, optional)	True to return the query only without query execution.
		*												False or not passed to run query normally.
		*
		*	@return	resultArr		(array)				An associative array containing the following elements:
		*												Index "query" (string) The query that was constructed.
		*												Index "status" (string) The result of the execution. 
		*												Values will be one of the following:
		*													"success" - indicates the query ran successfully.
		*													"error"   - indicates the query failed.
		*													"queryOnly" - only exists if returnQueryOnly param is set to true.
		*												Index "errors" (array) descriptive strings of errors.											
		*												Index "result" (result set) Contains the result returned from s successful query.											
		*/
        public function addRecord($table, $data, $returnQueryOnly=null)
        {
			//process the data that was passed, including validating data against the table field constraints, and return the
			//processed fields or an error report
			$processedFields = $this->processFieldDataForInsert($table, $data);
			
			$resultArr = array();
			
			//errors on processing, ouput a report	
			if (isset($processedFields['errors']))
			{
				$resultArr['status'] = "error";	
				$resultArr['errors'] = $processedFields['errors'];
			}
			//continue with the add
			else
			{
				$fields = array_keys($processedFields['fields']);
				$values = array_values($processedFields['fields']);
				$valuesStr = implode(", ", $values);
				$fieldsStr = "";
				for ($i = 0; $i < count($fields); $i++)
				{
					$field = $fields[$i];
					$fieldsStr .= "`" . $table . "`." . "`" . $field . "`";
					if ($i < count($fields) - 1)
					{
						$fieldsStr .= ", ";
					}
				}
				
				$qry = "INSERT INTO `" . $table . "` (" . $fieldsStr . ") VALUES (" . $valuesStr . ")";

				$resultArr['query'] = $qry;	
				
				//output the query, if passed
				if (isset($returnQueryOnly) and true === $returnQueryOnly)
				{
					$resultArr['status'] = "queryOnly";	
				}
				else
				{
					$result = $this->query($qry);
					
					if (false === $result)
					{
						$resultArr['status'] = "error";	
						$resultArr['errors'] = array("Query execution failed.");	
					}
					else
					{
						$resultArr['status'] = "success";	
						$resultArr['result'] = $result['result'];	
					}
				}
			}  

			return $resultArr;
        }
		
		/*
		*	editRecord edits a specific record.  It is normally used as in a call from a class that inherits from this class.
		*
		*	@param table			(string)			The name of the table.
		*	@param id				(string)			The value of the primary key for the record to be edited.
		*	@param data				(array)				An associative array containing elements of field data. The keys are the field names and the values are the values for the field.
		*	@param returnQueryOnly	(bool, optional)	True to return the query only without query execution.
		*												False or not passed to run query normally.
		*
		*	@return	resultArr		(array)				An associative array containing the following elements:
		*												Index "query" (string) The query that was constructed.
		*												Index "status" (string) The result of the execution. 
		*												Values will be one of the following:
		*													"success" - indicates the query ran successfully.
		*													"error"   - indicates the query failed.
		*													"queryOnly" - only exists if returnQueryOnly param is set to true.
		*												Index "errors" (array) descriptive strings of errors.											
		*												Index "result" (result set) Contains the result returned from s successful query.											
		*/
        public function editRecord($table, $id, $data, $specificField=null, $returnQueryOnly=null)
        {
			//find the primary column
			$columnsInfoArray = $this->getColumnInformation($table, true);
			$errors = array();

			$pkArray = array();
			foreach ($columnsInfoArray as $col)
			{
				//find the primary columns
				if ((isset($col['Key']) and "PRI" == $col['Key'] and !isset($specificField)) or (isset($specificField) and $specificField == $col['Field']) )
				{
					$pkKey = $col['Field'];
					if (in_array($col['Type'], $this->quoteFieldTypes))
					{
						$toQuote = "quote";
					}
					else
					{
						$toQuote = "noquote";
					}
					$pkArray[$pkKey] = $toQuote;
				}
			}
			
			if (empty($pkArray))
			{
				$errors[] = "There was no primary key found for this table nor was there a specific field identified for specification. Therefore the update query can't identify the record to be updated.";
			}
			
			//construct the where clause based on passed ids, mapped to their primary columns
			$whereStmt = array();
			if (is_array($id))
			{
				$idArray = $id;
			}
			else
			{
				//if id is a string, map it to the first primary column, if it exists
				if (false !== key($pkArray))
				{
					$firstPri = key($pkArray);
					$idArray = array($firstPri => $id);
				}
				else
				{
					$idArray = array($firstPri);
				}
			}
			//map the ids to the primary columns
			foreach($idArray as $idField => $idValue)
			{
				if (isset($pkArray[$idField]))
				{
					if ("quote" == $pkArray[$idField])
					{
						$idValue = "'" . $idValue . "'";
					}
					$whereStmt[] = "`" . $table . "`.`" . $idField . "` = " . $idValue;
				}
			}
			//construct the where statement
			if (!empty($whereStmt))
			{
				$whereStmt = "WHERE " . implode(" AND ", $whereStmt);
			}
			else
			{
				$whereStmt = "";
			}
			
			//create the string for editing the fields
			$editFieldStringArray = array();
			
			foreach ($data as $field => $value)
			{
				//if the data field is a valid field in the table,
				if (isset($columnsInfoArray[$field]))
				{
					if (!isset($value) or (isset($value) and !strlen($value)) or (isset($value) and "NULL" === $value))
					{
						$editFieldStringArray[] = "`" . $table . "`.`" . $field . "` = NULL";
					}
					else
					{
						$validation = self::validateAndFormatFieldValue($field, $columnsInfoArray[$field]['Type'], $value);
						
						if (strlen($validation['error']))
						{
							$errors[] = $validation['error'];
						}
						else
						{
							$editFieldStringArray[] = "`" . $table . "`.`" . $field . "` = " . $validation['value'];
						}
					}
				}
			}
			
			$resultArr = array();
			
			//errors on processing, return a report	
			if (!empty($errors))
			{
				$resultArr['status'] = "error";	
				$resultArr['errors'] = $errors;
			}
			//continue with the add
			else
			{
				$editFieldString = implode(", ", $editFieldStringArray);
			
				$qry = "UPDATE `" . $table . "` SET " . $editFieldString . " " . $whereStmt;
				$resultArr['query'] = $qry;
				
				//output the query, if passed
				if (isset($returnQueryOnly) and true === $returnQueryOnly)
				{
					$resultArr['status'] = "queryOnly";	
				}
				else
				{
					$result = $this->query($qry);
					
					if (false === $result)
					{
						$resultArr['status'] = "error";	
						$resultArr['errors'] = array("Query execution failed.");	
					}
					else
					{
						$resultArr['status'] = "success";	
						$resultArr['result'] = $result['result'];	
					}
				}
			}
			
			return $resultArr;
        }
		
		/*
		*	deleteRecord deletes a specific record.  It is normally used as in a call from a class that inherits from this class.
		*
		*	@param table			(string)			The name of the table.
		*	@param id				(string)			The value of the primary key for the record to be deleted.
		*	@param returnQueryOnly	(bool, optional)	True to return the query only without query execution.
		*												False or not passed to run query normally.
		*
		*	@return	resultArr		(array)				An associative array containing the following elements:
		*												Index "query" (string) The query that was constructed.
		*												Index "status" (string) The result of the execution. 
		*												Values will be one of the following:
		*													"success" - indicates the query ran successfully.
		*													"error"   - indicates the query failed.
		*													"queryOnly" - only exists if returnQueryOnly param is set to true.
		*												Index "errors" (array) descriptive strings of errors.											
		*												Index "result" (result set) Contains the result returned from s successful query.											
		*/
        public function deleteRecord($table, $id, $specificField=null, $returnQueryOnly=null)
        {
			$columnsInfoArray = $this->getColumnInformation($table, true);
			$errors = array();
			
			$pkArray = array();
			foreach ($columnsInfoArray as $col)
			{
				//find the primary columns
				if ((isset($col['Key']) and "PRI" == $col['Key'] and !isset($specificField)) or (isset($specificField) and $specificField == $col['Field']) )
				{
					$pkKey = $col['Field'];
					if (in_array($col['Type'], $this->quoteFieldTypes))
					{
						$toQuote = "quote";
					}
					else
					{
						$toQuote = "noquote";
					}
					$pkArray[$pkKey] = $toQuote;
				}
			}
			
			if (empty($pkArray))
			{
				$errors[] = "There was no primary key found for this table nor was there a specific field identified for specification. Therefore the delete query can't identify the record to be deleted.";
			}
			
			//construct the where clause based on passed ids, mapped to their primary columns
			$whereStmt = array();
			if (is_array($id))
			{
				$idArray = $id;
			}
			else
			{
				//if id is a string, map it to the first primary column, if it exists
				if (false !== key($pkArray))
				{
					$firstPri = key($pkArray);
					$idArray = array($firstPri => $id);
				}
				else
				{
					$idArray = array($firstPri);
				}
			}
			//map the ids to the primary columns
			foreach($idArray as $idField => $idValue)
			{
				if (isset($pkArray[$idField]))
				{
					if ("quote" == $pkArray[$idField])
					{
						$idValue = "'" . $idValue . "'";
					}
					$whereStmt[] = "`" . $table . "`.`" . $idField . "` = " . $idValue;
				}
			}
			//construct the where statement
			if (!empty($whereStmt))
			{
				$whereStmt = "WHERE " . implode(" AND ", $whereStmt);
			}
			else
			{
				$whereStmt = "";
			}
			
			$resultArr = array();
			
			//errors on processing, return a report	
			if (!empty($errors))
			{
				$resultArr['status'] = "error";	
				$resultArr['errors'] = $errors;
			}
			//continue with the delete
			else
			{
				$qry = "DELETE FROM `" . $table . "` " . $whereStmt;
				$resultArr['query'] = $qry;	
				
				//return the query, if passed
				if (isset($returnQueryOnly) and true === $returnQueryOnly)
				{
					$resultArr['status'] = "queryOnly";	
				}
				else
				{
					$result = $this->query($qry);
					
					if (false === $result)
					{
						$resultArr['status'] = "error";	
						$resultArr['errors'] = array("Query execution failed.");	
					}
					else
					{
						$resultArr['status'] = "success";	
						$resultArr['result'] = $result['result'];	
					}
				}
			}
			
			return $resultArr;
        }
		
	}
?>