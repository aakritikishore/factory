<?php
	require_once("model/core/BaseModel.php");
	
	//include any type requirements
	$relativeTypesPath = "model/core/db-module/db-types/";
	$typeFiles = scanDir($relativeTypesPath);
	foreach($typeFiles as $file)
	{
		if (is_file($relativeTypesPath . $file))
		{
			require_once($relativeTypesPath . $file);
		}
	}

	class Database
	{	
		protected $validTypes = array("MYSQLI", "MYSQL", "SQLSRV", "MSSQL");
		protected $type;
		protected $hostname;
		protected $user;
		protected $password;
		protected $database;
		protected $escape = false;
		public $databaseObj;
		
		/*
		*	constructor creates the object
		*	
		*	@param type (string) 				The type of database.
		*	@param hostname (string) 			The hostname of the mysql server.
		*	@param user	    (string) 			The mysql user.
		*	@param password (string) 			The password for the mysql user.
		*	@param database (string) 			The name of the mysql database.
		*	@param escape   (bool, optional) 	An indicator of whether data values need to be escaped upon query.
		*/
		public function __construct($type, $hostname, $user, $password, $database, $escape=null)
		{
			$passedType = $type;
			$this->type = strtoupper($type);
			if (!in_array($this->type, $this->validTypes))
			{
				//passed type is not supported, throw an exception
				throw new Exception($passedType . " is not a valid database type.");
			}
			$this->hostname = $hostname;
			$this->user = $user;
			$this->password = $password;
			$this->database = $database;
			if (isset($escape) and true === $escape)
			{
				$this->escape = $escape;
			}
			
			$evalStmt = "\$this->databaseInterface = new $this->type" . "_Database(\$this->hostname, \$this->user, \$this->password, \$this->database, \$this->escape);";
			eval($evalStmt);
		}
	}
?>