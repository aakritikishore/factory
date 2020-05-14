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
			for($i=0; $i < 1; $i++) {
				if($listingArray[$i]['record_status'] == "new"){
					//Get new Records
					$newrecordsDescriptions= $this-> getPropertyDescription($listingArray[$i]['url']);
					//Send records for mapping
					$this->mapPropertyRecords($newrecordsDescriptions,'new');
				} else {
					//Check if property updated
					$propertyUpdated = $this->checkIfpropertyUpdated($listingArray[$i]['updateDate'],$listingArray[$i]['propertyId'],'property');
					$propertyUpdated = true;
					if($propertyUpdated) {
						$newrecordsDescriptions= $this-> getPropertyDescription($listingArray[$i]['url']);
						$this->mapPropertyRecords($newrecordsDescriptions,'existing');
					}
				}
			}
	         
	     	//Get Rate Listing
			/*$rateListing = $this-> getListing('https://www.condo-world.com/CIO/xmladvertiserLodgingRateContentIndex.ashx','lodgingRateContentIndexEntry','lodgingRateContentUrl','lastUpdatedDate');
			//Get array with record_status like existing/new.
			$rateListingArray = $this->checkRecordType($rateListing,'property_rate_date');
			//for($i=0; $i = count($newListingArray); $i++) {
			for($i=0; $i < 20; $i++) {
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

			}*/
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
			if($lastUpdatedDate > $date['result'][0]['last_updated']) {
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
		   //To get Company Id
		   $property['CompanyID'] = $propertyModel->getCompanyId("Condo World");

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
		   		$propertyId = $propertyModel->addProperty($property); 
		   }elseif($listingType == "existing"){
		   		$propertyModel->updateProperty($property,$property['external_id']); 
		   }

		   $propertyId = $propertyModel->getPropertyId($property['external_id']); 

		   //Map Image
		   for($i = 0; $i < count($listingArray['images']['image']); $i++){
		   		$propertyPhotos['PropertyID'] = $propertyId;
		   		$propertyPhotos['FileName'] = $listingArray['images']['image'][$i]['uri'];
		   		$propertyPhotos['SortOrder'] =  0;
		   		if($i == 0){
		   			$propertyPhotos['Primary'] =  1;
		   		}else {
		   			$propertyPhotos['Primary'] =  0;
		   		}
		   		//check if image exist & if not exist then add 
		   		$existingImages = $propertyModel->getImages($propertyPhotos);
		   		if(empty($existingImages)) {
		   			$propertyModel->addPropertyPhotos($propertyPhotos);
		   		}
		   }

		   //Add Property Attribute >> Featured Values
		    for($i = 0; $i < count($listingArray['featureValues']['featureValue']); $i++){
		   		$propertyFeature['PropertyID'] = $propertyId;;
		   		
		   		$attribute = $listingArray['featureValues']['featureValue'][$i]['listingFeatureName'];
		   		//String conversion to map with Static Name of attribute
		   		if(substr($attribute, 0, 14 ) == "LOCATION_TYPE_"){
			   		$attribute =	ltrim(ltrim(str_replace("_"," ","$attribute"),'LOCATION'),'TYPE ');
			   		$attribute = strtolower($attribute); 
			   			if($attribute == "ocean front"){
							$attribute = ucfirst($attribute);			
						} else{
							$attribute = ucwords($attribute);	
						}
			   		$attribute = str_replace(" ", "", $attribute)."ViewType";
		   		}
		   		//Check if this attribute exist in list option table and get attribute id
		   		$listOptionId = $propertyModel->getListOptionId($attribute);
		   		if(isset($listOptionId) && $listOptionId != Null){
		   			$propertyFeature['ListOptionID'] = $listOptionId;
		   			//check if features is alredy exist
		   			$existingFeatures = $propertyModel->getPropertyFeatures($propertyFeature);
		   			if(empty($existingFeatures['result'])){
		   				$propertyModel->addPropertyFeatures($propertyFeature);
		   			}
		   		}
		   }
		   $attributeHeading = array();
		   $availableAttribute = array();
		   //Add property featured value 
		   for($i = 0; $i < count($listingArray['units']['unit']['featureValues']['featureValue']); $i++) {
		   		$unitFeatureName = $listingArray['units']['unit']['featureValues']['featureValue'][$i]['unitFeatureName'];
		   		$unitFeatureName = str_replace("_", " ", $unitFeatureName);
		   		$unitFeatureNameArray =  explode(' ',trim($unitFeatureName));
		   		$key = array_shift($unitFeatureNameArray);
		   		$label =  implode(" ", $unitFeatureNameArray);
		   		//Check if key exist
		   		$keyArray = $propertyModel->getHeading($key);
		   		if(!empty($keyArray)){
		   			$available_attribute['HeadingID'] = $keyArray['ID'];
		   			$available_attribute['Heading'] = $keyArray['HeadingText'];
		   		}else {
		   			$attributeHeading['HeadingText'] = ucfirst(strtolower($key));
		   			$attributeHeading['IsActive'] = 1;

		   			$attribute_heading_id = $propertyModel->addAttributeHeading($attributeHeading);
		   			$available_attribute['HeadingID'] = $attribute_heading_id;
		   			$available_attribute['Heading'] = $key;
		   		}
		   		
		   		$labelArray = $propertyModel->getLabel($label,$available_attribute['HeadingID']);
		   		$propertyAttribute['AttributeID'] = $labelArray['ID'];

		   		if(empty($labelArray)) {
		   			$available_attribute['Label'] = ucfirst(strtolower($label));
		   			$available_attribute['strName'] = $unitFeatureName;
		   			$available_attribute['IsActive'] = 1;

		   			$available_attribute_id = $propertyModel->addAvailableAttribute($available_attribute);
		   			$propertyAttribute['AttributeID'] = $available_attribute_id;
		   		}
		   		$propertyAttribute['PropertyID'] = $propertyId;
                // check if property attribute already exist
		   		$propertyAttributeId = $propertyModel->getPropertyAttribute($propertyAttribute);

		   		if(!isset($propertyAttributeId)){
		   			$propertyModel->addPropertyAttribute($propertyAttribute);
		   		}
		   }


		   //Add NearBy Places
		   for($i=0; $i< count($listingArray['location']['nearestPlaces']['nearestPlace']); $i++){
		   	$placeType = $listingArray['location']['nearestPlaces']['nearestPlace'][$i]['@attributes']['placeType'];
		   	//check if place Type Exist
		   	$placeTypeAttributeId= $propertyModel->getPlaceTypeAttribute($placeType,'Nearby');
             $propertyAttribute['PropertyID'] = $propertyId;
             $propertyAttribute['AttributeID'] = $placeTypeAttributeId;
		   	if(!$placeTypeAttributeId) {
		   		$placeTypeAttribute['HeadingID'] = 9;
		   		$placeTypeAttribute['Heading'] = "Nearby";
		   		$placeTypeAttribute['Label'] = $placeType;
		   		$placeTypeAttribute['strName']= $placeType;
		   		$placeTypeAttribute['IsActive'] = 1;
		   		$addedPlaceTypeAttributeId = $propertyModel->addAvailableAttribute($placeTypeAttribute);
		   		$propertyAttribute['AttributeID'] = $addedPlaceTypeAttributeId;
		   	}
		   	// check if property attribute already exist
		   		$propertyAttributeId = $propertyModel->getPropertyAttribute($propertyAttribute);

		   		if(!isset($propertyAttributeId)){
		   			$propertyModel->addPropertyAttribute($propertyAttribute);
		   		}
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
			    $propertyRateDate['date_added'] = date("Y-m-d h:i:s",time());
		   		$propertyRateDate['last_updated'] = date("Y-m-d h:i:s",time());

			    //Check if date range exist
			    $dateRangeExist  = $propertyModel->getDateRange($propertyRateDate);
			    if(empty($dateRangeExist['result'])) {
			    	$added_id = $propertyModel->addPropertyRateDate($propertyRateDate); 
			    	$propertyRateDatePricing['PropertyRateDateID'] = $added_id['result'];
			    	$propertyRateDatePricing['UpTo'] = 0;
			    	$propertyRateDatePricing['Rate'] = $listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['amount'];
			    	$propertyModel->addPropertyRateDatePricing($propertyRateDatePricing); 
			    } else {
			    	$propertyRateDatePricing['PropertyRateDateID'] = $dateRangeExist['result'][0]['ID'];
			    	$propertyRateDatePricing['UpTo'] = 0;
			    	$propertyRateDatePricing['Rate'] = $listingArray['lodgingRate']['nightlyRates']['nightlyOverrides']['override'][$i]['amount'];
			    	//check if property rate date pricing exist
			    	 $dateRangePricingExist  = $propertyModel->getDateRangePricing($propertyRateDatePricing['PropertyRateDateID']);
			    	 if(empty($dateRangePricingExist['result'])) {
			    	 	$propertyModel->addPropertyRateDatePricing($propertyRateDatePricing);
			    	 }else {
			    	 	$propertyRateDatePricing['id'] = $dateRangePricingExist['result'][0]['ID'];
			    		$propertyModel->upDatePropertyRateDatePricing($propertyRateDatePricing);
			    	}
			    }
	        }

	        //Add base rate & rate mode id
	       if(isset($listingArray['lodgingRate']['nightlyRates']['nightlyOverrides'])) {
				$property['RateModeID'] = 82;
				$property['BaseRate'] = $listingArray['lodgingRate']['nightlyRates']['mon'];
				$propertyModel->updatePropertyRate($property,$propertyRateDate['PropertyID']);
			} 
		}
	}

?>
