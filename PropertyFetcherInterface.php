<?php

	// Declare the interface PropertyFetcherInterface
	Interface PropertyFetcherInterface  
	{		
			
		public function getProperty();

		public function checkIfpropertyUpdated($lastUpdatedDate,$propertId,$tableName);

		public function convertToArray($data);

		public function MapPropertyRecords($listingArray,$listingType);
		
		public function updateProperties();

	}
	
?>			