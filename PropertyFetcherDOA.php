<?php
	
	require_once("PropertyFetcherInterface.php");
	require_once("ThirdPartyCustomers/HomeAway.php");
	require_once("ThirdPartyCustomers/CondoWorld.php");

	class PropertyFetcherDOA {		
		
		public function getPropertyFetcher($name) {
			switch ($name) {
			  case 'HA':
			    return new HomeAway();
			  break;
			  case 'CW':
			  	return new CondoWorld();
			  break;		
			}
		}

	}

?>			
