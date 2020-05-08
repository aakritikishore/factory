<?php

require_once("./PropertyFetcherInterface.php");

// Implement the interface
class HomeAway implements PropertyFetcherInterface 
{
	
	public function getProperty() {
		return "HOMEAWAY";
	}

	public function checkIfpropertyUpdated($lastUpdatedDate){
		return true;
	}

	public function convertToArray($data){
		return "An array";
	}

	public function MapProperties() {
		return "mapped";
	}
	
	public function updateProperties(){
		return true;
	}
}

?>

