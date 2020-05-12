<?php

	require_once("./PropertyFetcherInterface.php");
	require_once("Library/Curl.php");
	require_once("model/Property.php");

	// Implement the interface
	class CondoWorld implements PropertyFetcherInterface {
		
		public function getProperty() {
			
			//Get Property Listing
			$propertyListing = $this-> getListing('https://www.condo-world.com/CIO/xmladvertiserListingContentIndex.ashx','listingContentIndexEntry','listingUrl','lastUpdatedDate');
			//Get array with record_status like existing/new.
			$listingArray = $this->checkRecordType($propertyListing,'property');
			//for($i=0; $i = count($newListingArray); $i++) {
			for($i=0; $i < 2; $i++) {
				if($listingArray[$i]['record_status'] == "new"){
					//Get new Records
					$newrecordsDescriptions= $this-> getPropertyDescription($listingArray[$i]['url']);
					//Send records for mapping
					$this->mapPropertyRecords($newrecordsDescriptions,'new');
				} else {
					//Check if property updated
					$propertyUpdated = $this->checkIfpropertyUpdated($listingArray[$i]['updateDate'],$listingArray[$i]['propertyId'],'property');
					if($propertyUpdated) {
						$newrecordsDescriptions= $this-> getPropertyDescription($listingArray[$i]['url']);
						$this->mapPropertyRecords($newrecordsDescriptions,'existing');
					}
				}
			}
	         
	     	//Get Rate Listing
			$rateListing = $this-> getListing('https://www.condo-world.com/CIO/xmladvertiserLodgingRateContentIndex.ashx','lodgingRateContentIndexEntry','lodgingRateContentUrl','lastUpdatedDate');
			//Get array with record_status like existing/new.
			$rateListingArray = $this->checkRecordType($rateListing,'property_rate_date');
			//for($i=0; $i = count($newListingArray); $i++) {
			for($i=0; $i < 1; $i++) {
				if($rateListingArray[$i]['record_status'] == "new"){
					//Get new Records
					$newrecordsDescriptions= $this-> getPropertyDescription($rateListingArray[$i]['url']);
					//Send records for mapping
					$this->mapRateRecords($newrecordsDescriptions,'new');
				} else {
					$propertyUpdated = $this->checkIfpropertyUpdated($rateListingArray[$i]['updateDate'],$rateListingArray[$i]['propertyId'],'property_rate_date');
					if($propertyUpdated) {
						$newrecordsDescriptions= $this-> getPropertyDescription($rateListingArray[$i]['url']);
						$this->mapRateRecords($newrecordsDescriptions,'existing');
					}
				}

			}

			//Get Availablity Listing
			//Code goes here..

		}

		public function callingApi($url) {

			$CurlObj = new Curl();				
			$data = $CurlObj->curlCall($url);
			$data = $this->convertToArray($data);
			return $data;

		}

		public function checkIfpropertyUpdated($lastUpdatedDate,$propertyId,$tableName) {
			
			$propertyModel = New Property();
			//Get last update date of property
			$date = $propertyModel->getLastUpdateDate($propertyId,$tableName);
			$lastUpdateDate = str_replace("T"," ",$lastUpdatedDate);
			//Check if property is updated
			if($lastUpdatedDate > $date) {
				return true;
			}else{
				return false;
			}
		
		}	

		public function checkRecordType($listingArray,$tableName) {
			//Get existing Properties Id
			$propertyModel = New Property();
			$externalIds = $propertyModel->getExternalIds($tableName);
			$external_ids_array = array();
			for($i = 0; $i < count($externalIds['result']); $i++){
				$external_ids_array[$i] = $externalIds['result'][$i]['external_id'];
			}
			//Check if the property exist
			for($i = 0; $i < count($listingArray); $i++){
				if(in_array ($listingArray[$i]['propertyId'], $external_ids_array)){
					 $listingArray[$i]['record_status'] = 'existing';
				}else{
					$listingArray[$i]['record_status'] = 'new';
				}
			}
			return $listingArray;
		}

		public function convertToArray($data) {

			$data = json_decode(json_encode($data), true);
			return $data;

		}

		public function getListing($url,$contentIndex,$listingUrlIndex,$updatedDateIndex) {

			$data = $this->callingApi($url);
			$listing = array();
			//Get listing url & updated date
			$listingIndexCount = count($data['advertiser'][$contentIndex]); 
			for($i=0; $i < $listingIndexCount; $i++) {
				 $listing[$i]['propertyId'] = $data['advertiser'][$contentIndex][$i]['listingExternalId'];
				 $listing[$i]['url'] = $data['advertiser'][$contentIndex][$i][$listingUrlIndex];
				 $listing[$i]['updateDate'] = $data['advertiser'][$contentIndex][$i][$updatedDateIndex];
			}
			return $listing;

		}

		public function getPropertyDescription($listingUrl) {

			$data = $this->callingApi($listingUrl);
			return $data;

		}

		public function mapPropertyRecords($listingArray,$listingType) {
	       
		   $property['CompanyID'] = 76;
		   $property['Destination'] =  $listingArray['adContent']['headline']['texts']['text']['textValue'];
		   $property['Name'] = $listingArray['adContent']['propertyName']['texts']['text']['textValue'];
		   if(is_array($listingArray['adContent']['accommodationsSummary']['texts']['text']['textValue']) && empty($listingArray['adContent']['accommodationsSummary']['texts']['text']['textValue'])){
		   		 $property['Summary'] = ' ';
		   }else {
		   		$property['Summary'] = $listingArray['adContent']['accommodationsSummary']['texts']['text']['textValue'];
		   }

		   if(is_array($listingArray['adContent']['description']['texts']['text']['textValue']) && empty($listingArray['adContent']['description']['texts']['text']['textValue'])){
		   		$property['Details'] = ' ';
		   }else {
		  
		   		$property['Details'] = $listingArray['adContent']['description']['texts']['text']['textValue'];
			}

		   $propertyModel = new Property();
		   //To get unit type Id
		   $unitType = $listingArray['units']['unit']['propertyType'];
		   $unitTypeId = $propertyModel->getUnitTypeId($unitType);
		   if($unitTypeId != null) {
		   		$property['UnitTypeID'] = $unitTypeId;
		   } else {
		   		$property['UnitTypeID'] = 94;
		   }

		   //To getBedroomId
		   $bedroomType = count($listingArray['units']['unit']['bedrooms']['bedroom']);
		   $bedroomTypeId = $propertyModel->getBedroomTypeId($bedroomType."BedroomType");
		   if($bedroomTypeId != null) {
		   		$property['BedroomsID'] = $bedroomTypeId;
		   } else {
		   		$property['BedroomsID'] = '';
		   }

		   //To getBathroom
		   $bathroomType = count($listingArray['units']['unit']['bathrooms']['bathroom']);
		   $bathroomTypeId = $propertyModel->getBedroomTypeId($bathroomType."BedroomType");
		   if($bathroomTypeId != null) {
		   		$property['BathroomsID'] = $bathroomTypeId;
		   } else {
		   		$property['BathroomsID'] = '';
		   }
		   $property['GuestMax'] = 0;  
		   $property['Address1'] = $listingArray['location']['address']['addressLine1'];
		   $property['Address2'] = '';
		   if(is_array($listingArray['location']['address']['city']) && empty($listingArray['location']['address']['city'])){
		   		 $property['City'] = " ";
		   }else {
		   		$property['City'] =  $listingArray['location']['address']['city'];
		   }
		   $property['Province'] = $listingArray['location']['address']['stateOrProvince'];
		   $property['PostalCode'] = $listingArray['location']['address']['postalCode'];

		   //To get country id
		   $country = $listingArray['location']['address']['country'];
		   if($country = 'US') {
		   	$country = "USA";
		   }
		   $countryId = $propertyModel->getCountryId($country);
		   $property['CountryId'] = $countryId;

		   $property['YouTubeVideoCode'] = '';
		   $property['FloorPlanPDF'] = '';
		   $property['BaseRate'] = '0.00';
		   $property['RateModeId'] = 0;
		   $property['MinimumPeople'] = 1;
		   $property['MinimumNights'] = 1;
		   $property['external_id'] = $listingArray['externalId'];
		   $property['IsActive'] = 1;
		   $property['StatusID'] = 1;
		   $property['date_added'] = date("Y-m-d h:i:s",time());
		   $property['last_updated'] = date("Y-m-d h:i:s",time());
		   if($listingType == "new"){
		   		$propertyModel->addProperty($property); 
		   }elseif($listingType == "existing"){
		   		$propertyModel->updateProperty($property,$property['external_id']); 
		   }

		}

		public function mapRateRecords($listingArray,$listingType) {
	        $rateIntervalCount = count($listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override']);
	        for($i=0; $i < $rateIntervalCount; $i++ ) {
	        	$propertyRateDate['external_id'] = $listingArray['listingExternalId'];
			    $propertyModel = new Property();
			    $propertyRateDate['PropertyID'] = $propertyModel->getPropertyId($propertyRateDate['external_id']);
			    $countDateRange = count($listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['nights']['range']);
			    
			    if(!isset($listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['nights']['range'][0])){
			    	$propertyRateDate['StartDate'] = $listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['nights']['range']['min'];
			    	$propertyRateDate['EndDate'] =   $listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['nights']['range']['max'];
			    }else {
			    	$propertyRateDate['StartDate'] = $listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['nights']['range'][0]['max'];
			    	$propertyRateDate['EndDate'] =   $listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['nights']['range'][$countDateRange - 1]['max'];
			    }

			    //Check if date range exist
			    $dateRangeExist  = $propertyModel->getDateRange($propertyRateDate);
			    if(empty($dateRangeExist['result'])) {
			    	$added_id = $propertyModel->addPropertyRateDate($propertyRateDate); 
			    	 $propertyRateDatePricing['PropertyRateDateID'] = $added_id['result'];
			    	$propertyRateDatePricing['UpTo'] = 0;
			    	$propertyRateDatePricing['Rate'] = $listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['amount'];
			    	$propertyModel->addPropertyRateDatePricing($propertyRateDatePricing); 
			    } else {
			    	$propertyRateDatePricing['PropertyRateDateID'] = $dateRangeExist['result']['id'];
			    	$propertyRateDatePricing['UpTo'] = 0;
			    	$propertyRateDatePricing['Rate'] = $listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['amount'];

			    	$propertyModel->upDatePropertyRateDatePricing($propertyRateDatePricing);
			    }
	        }
		}
	}

?>
