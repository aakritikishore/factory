<?php
	class BaseModel
	{
		public $table;
		
		public function __construct()
		{
			//create a database interface for use by this model class
			$db = new Database(DB_TYPE, DB_HOSTNAME, DB_USER, DB_PASSWORD, DB_DATABASE, DB_ESCAPE_DATA);
			$this->databaseInterface = $db->databaseInterface;
		}
		
		/*
		*	OVERRIDE FUNCTIONS AS NECESSARY
		*
		*	These overrideable function pass the name of this class (the name of the database table) to the 
		*	database interface's general functions: getSpecific, getAll, addRecord, editRecord, deleteRecord
		*/
		public function getSpecific($primaryValue, $specificField=null, $orderBy=null, $fields=null, $returnQueryOnly=null)
		{
			$result = $this->databaseInterface->getSpecific($this->table, $primaryValue, $specificField, $orderBy, $fields, $returnQueryOnly);
			
			return $result;
		}
		public function getAll($orderBy=null, $fields=null, $whereClause=null, $returnQueryOnly=null)
		{
			$result = $this->databaseInterface->getAll($this->table, $orderBy, $fields, $whereClause, $returnQueryOnly);
			
			return $result;
		}
		public function addRecord($data, $output=null)
		{
			$result = $this->databaseInterface->addRecord($this->table, $data, $output);
			
			return $result;
		}
		public function editRecord($id, $data, $specificField=null, $output=null)
		{
			$result = $this->databaseInterface->editRecord($this->table, $id, $data, $specificField, $output);
			
			return $result;
		}
		public function deleteRecord($id, $specificField=null, $output=null)
		{
			$result = $this->databaseInterface->deleteRecord($this->table, $id, $specificField, $output);
			
			return $result;
		}
	}
	
	function checkParam($array, $key)
	{
		if (array_key_exists($key, $array) && $array[$key]) 
		{
			if (is_numeric($array[$key])) 
			{
				return ctype_digit($array[$key]);
			}
		}
		return false;
	}
?>