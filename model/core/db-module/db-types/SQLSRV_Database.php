<?php
	/*
	*	SQLSRV_Database is a class that performs handling of connections and various querying for a Microsoft SQL Server database.
	*
	*	Dependencies:
	*		The PHP sqlsrv library - a Microsoft SQL Server Driver for PHP
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

	class SQLSRV_Database
	{	
		protected $hostname;
		protected $user;
		protected $password;
		protected $database;
		protected $escape = false;
		//a list of sql server field types that require values to be quoted in a query
		protected $quoteFieldTypes = array("date", "time", "datetime", "datetime2", "varchar", "nvarchar", "char", "text");
		
		/*
		*	constructor creates the object
		*	
		*	@param hostname (string) 			The hostname of the SQL server.
		*	@param user	    (string) 			The SQL server user.
		*	@param password (string) 			The password for the SQL server user.
		*	@param database (string) 			The name of the SQL server database.
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
			
			//instantiate the sql server connection
			$serverName = $this->hostname; //serverName\instanceName
			$connectionInfo = array("Database" => $this->database, "UID" => $this->user, "PWD" => $this->password);
			$conn = sqlsrv_connect( $serverName, $connectionInfo);
			
			//check connection, returning an error if necessary.
			if (false === $conn) 
			{
				$resultArr['status'] = "error";	
				$resultArr['errors'] = sqlsrv_errors();
				
				//close the connection, return the error
				sqlsrv_close($conn);
				
				return $resultArr;
			}
			
			//output the query, if passed
			if (isset($returnQueryOnly) and true === $returnQueryOnly)
			{
				$resultArr['status'] = "queryOnly";	
			}
			else
			{
				//query the database
				$params = array();
				$options =  array( "Scrollable" => SQLSRV_CURSOR_KEYSET );
				$result = sqlsrv_query($conn, $query, $params, $options);
				
				if (false === $result)
				{
					$resultArr['status'] = "error";	
					$resultArr['errors'] = array("Query execution failed.");	
				}
				else
				{
					$resultArr['status'] = "success";
					
					//if inserting, return the insert id
					if (false !== stripos($query, "INSERT INTO") and false !== $result)
					{
						$idResource = sqlsrv_query($conn, "SELECT SCOPE_IDENTITY() AS AddedIdentity");
						$addedIdentity = sqlsrv_fetch_array($idResource, SQLSRV_FETCH_ASSOC);
						$resultArr['result'] = $addedIdentity['AddedIdentity'];
						sqlsrv_free_stmt($idResource);
					}
					else
					{
						if (isset($resultAsArray) and true === $resultAsArray and false !== $result)
						{
							//build an array of rows
							$resultArray = array();
							while ($row = sqlsrv_fetch_array($result, SQLSRV_FETCH_ASSOC)) 
							{
								$resultArray[] = $row;
							}
							$resultArr['result'] = $resultArray;
							sqlsrv_free_stmt($result);
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
		
			//instantiate the sql server connection
			$serverName = $this->hostname; //serverName\instanceName
			$connectionInfo = array("Database" => $this->database, "UID" => $this->user, "PWD" => $this->password);
			$conn = sqlsrv_connect( $serverName, $connectionInfo);
			
			//check connection, returning an error if necessary.
			if (false === $conn) 
			{
				$resultArr['status'] = "error";
				$resultArr['errors'] = sqlsrv_errors();
				
				//close the connection, return the error
				sqlsrv_close($conn);
				
				return $resultArr;
			}
		
			//start the transaction
			sqlsrv_begin_transaction($conn);
			
			//go through each query
			$queryErrors = array();
			$queryResults = array();
			foreach($queryArr as $query)
			{
				//query the database
				$queryResult = sqlsrv_query($conn, $query);
				
				//query failed, add to errors
				if (false === $queryResult)
				{
					$queryErrors[] = $query;
				}
				else
				{
					//if inserting, return the insert id
					if (false !== stripos($query, "INSERT INTO") and false !== $queryResult)
					{
						$idResource = sqlsrv_query($conn, "SELECT SCOPE_IDENTITY() AS AddedIdentity");
						$addedIdentity = sqlsrv_fetch_array($idResource, SQLSRV_FETCH_ASSOC);
						$queryResults[] = $addedIdentity['AddedIdentity'];
						sqlsrv_free_stmt($idResource);
					}
					else
					{
						if (isset($resultAsArray) and true === $resultAsArray and false !== $queryResult)
						{
							//build an array of rows
							$resultArray = array();
							while ($row = sqlsrv_fetch_array($queryResult, SQLSRV_FETCH_ASSOC)) 
							{
								$resultArray[] = $row;
							}
							//free the result set
							sqlsrv_free_stmt($queryResult);
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
				sqlsrv_rollback($conn);
				$resultArr['status'] = "error";
				$resultArr['errors'] = $queryErrors;
			}
			else
			{
				//commit
				sqlsrv_commit($conn);
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
			$qry = "SELECT 
			   ORDINAL_POSITION
			  ,COLUMN_NAME
			  ,COLUMNPROPERTY(OBJECT_ID(TABLE_NAME),COLUMN_NAME,'IsIdentity') AS IDENTITY_COLUMN
			  ,DATA_TYPE
			  ,CHARACTER_MAXIMUM_LENGTH
			  ,IS_NULLABLE
			  ,COLUMN_DEFAULT
			FROM   
			  INFORMATION_SCHEMA.COLUMNS 
			WHERE   
			  TABLE_NAME = '$table' 
			ORDER BY 
			  ORDINAL_POSITION ASC";
            
            $columnsResult = $this->query($qry, true);
            
			if (isset($returnArray) and true === $returnArray)
			{
				$fieldArray = array();
				if ("success" == $columnsResult['status'] and !empty($columnsResult['result']))
				{
					foreach ($columnsResult['result'] as $field)
					{
						$columnName = $field['COLUMN_NAME'];
						//delete the length data from the type field
						$field['DATA_TYPE'] = preg_replace("/\(.*\)/", "", $field['DATA_TYPE']);
						
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
					$value = self::ms_escape_string($value);
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
            
            if ($columnsResult and sqlsrv_num_rows($columnsResult))
            {
            	$errors = array();
				$processedFieldArray = array();
				
            	while ($field = sqlsrv_fetch_array($columnsResult, SQLSRV_FETCH_ASSOC))
                {
                	$fieldName = $field['COLUMN_NAME'];
					$identity = $field['IDENTITY_COLUMN'];
					$null = $field['IS_NULLABLE'];
					$type = $field['DATA_TYPE'];
					$default = $field['COLUMN_DEFAULT'];
					
					//if the field is an auto increment, and a value isn't passed for the field, continue to next iteration
					if (1 == $identity and (!isset($data[$fieldName]) or (isset($data[$fieldName]) and !strlen($data[$fieldName]))))
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
				if ((isset($col['IDENTITY_COLUMN']) and 1 == $col['IDENTITY_COLUMN'] and !isset($specificField)) or (isset($specificField) and $specificField == $col['COLUMN_NAME']))
				{
					$pkKey = $col['COLUMN_NAME'];
					if (in_array($col['DATA_TYPE'], $this->quoteFieldTypes))
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
							$idValue = self::ms_escape_string($idValue);
						}
	
						$idValue = "'" . $idValue . "'";
					}
					$whereStmt[] = "[" . $idField . "] = " . $idValue;
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
				$orderByStmt = "ORDER BY [$obField] $obDir";
			}
			
			$fieldsStmt = "*";
			if (isset($fields) and is_array($fields))
			{
				$fieldsStmt = "";
				for ($i = 0; $i < count($fields); $i++)
				{
					$field = $fields[$i];
					$fieldsStmt .= "[" . $field . "]";
					if ($i < count($fields) - 1)
					{
						$fieldsStmt .= ", ";
					}
				}	
			}
			
			$table = "[" . $table . "]";
				
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
				$orderByStmt = "ORDER BY [$obField] $obDir";
			}
			
			$fieldsStmt = "*";
			if (isset($fields) and is_array($fields))
			{
				$fieldsStmt = "";
				for ($i = 0; $i < count($fields); $i++)
				{
					$field = $fields[$i];
					$fieldsStmt .= "[" . $field . "]";
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
			
			$table = "[" . $table . "]";
			
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
					$fieldsStr .= "[" . $table . "]." . "[" . $field . "]";
					if ($i < count($fields) - 1)
					{
						$fieldsStr .= ", ";
					}
				}
				
				$qry = "INSERT INTO [" . $table . "] (" . $fieldsStr . ") VALUES (" . $valuesStr . ")";
				
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
				if ((isset($col['IDENTITY_COLUMN']) and 1 == $col['IDENTITY_COLUMN'] and !isset($specificField)) or (isset($specificField) and $specificField == $col['COLUMN_NAME']))
				{
					$pkKey = $col['COLUMN_NAME'];
					if (in_array($col['DATA_TYPE'], $this->quoteFieldTypes))
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
				$errors[] = "There was no identity column found for this table nor was there a specific field identified for specification. Therefore the update query can't identify the record to be updated.";
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
					$whereStmt[] = "[" . $table . "].[" . $idField . "] = " . $idValue;
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
						$editFieldStringArray[] = "[" . $table . "].[" . $field . "] = NULL";
					}
					else
					{
						$validation = self::validateAndFormatFieldValue($field, $columnsInfoArray[$field]['DATA_TYPE'], $value);
						
						if (strlen($validation['error']))
						{
							$errors[] = $validation['error'];
						}
						else
						{
							$editFieldStringArray[] = "[" . $table . "].[" . $field . "] = " . $validation['value'];
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
			
				$qry = "UPDATE [" . $table . "] SET " . $editFieldString . " " . $whereStmt;
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
				if ((isset($col['IDENTITY_COLUMN']) and 1 == $col['IDENTITY_COLUMN'] and !isset($specificField)) or (isset($specificField) and $specificField == $col['COLUMN_NAME']))
				{
					$pkKey = $col['COLUMN_NAME'];
					if (in_array($col['DATA_TYPE'], $this->quoteFieldTypes))
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
				$errors[] = "There was no identity column found for this table nor was there a specific field identified for specification. Therefore the delete query can't identify the record to be deleted.";
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
					$whereStmt[] = "[" . $table . "].[" . $idField . "] = " . $idValue;
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
				$qry = "DELETE FROM [" . $table . "] " . $whereStmt;
				$resultArr['query'] = $qry;	
				
				//return the query, if passed
				if (isset($returnQueryOnly) and $returnQueryOnly)
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
		*	ms_escape_string is a customized function for escaping data for insertion into a database query.
		*
		*	@param string (string)	The string to be escaped.
		*
		*	@return string (string) The escaped string.
		*/
		private static function ms_escape_string($string) 
		{
			if (!isset($string) or empty($string)) 
			{
				return "";
			}
			
			if (is_numeric($string)) 
			{
				return $string;
			}
	
			$nonDisplayables = array
			(
				'/%0[0-8bcef]/',            // url encoded 00-08, 11, 12, 14, 15
				'/%1[0-9a-f]/',             // url encoded 16-31
				'/[\x00-\x08]/',            // 00-08
				'/\x0b/',                   // 11
				'/\x0c/',                   // 12
				'/[\x0e-\x1f]/'             // 14-31
			);
			foreach ($nonDisplayables as $regex)
			{
				$string = preg_replace($regex, '', $string);
			}
			$string = str_replace("'", "''", $string );
			
			return $string;
		}
	}
?>