<?php

	// Declare the interface PropertyFetcherInterface
	Interface PropertyFetcherInterface  
	{		
			
		public function getProperty();

		public function checkIfpropertyUpdated($lastUpdatedDate);

		public function convertToArray($data);

		public function MapProperties();
		
		public function updateProperties();

	}
	
?>			