<?php

require_once("./PropertyFetcherInterface.php");
require_once("Library/Curl.php");

// Implement the interface
class CondoWorld implements PropertyFetcherInterface 
{
	
	
	public function getProperty() {
		
		//Get Property Listing
		$propertyListing = $this-> getListing('https://www.condo-world.com/CIO/xmladvertiserListingContentIndex.ashx','listingContentIndexEntry','listingUrl','lastUpdatedDate');
		$propertyListingDetail = $this->getDetail($propertyListing);
		
		//Get Rate Listing
		$rateListing = $this-> getListing('https://www.condo-world.com/CIO/xmladvertiserLodgingRateContentIndex.ashx','lodgingRateContentIndexEntry','lodgingRateContentUrl','lastUpdatedDate');
		$rateListingDetail = $this->getDetail($rateListing);


		//Get Availablity Listing
		$availablityListing = $this-> getListing('https://www.condo-world.com/CIO/xmladvertiserAvailabilityContentIndex.ashx','unitAvailabilityContentIndexEntry','unitAvailabilityContentUrl','lastUpdatedDate');
		$availablityListingDetail = $this->getDetail($availablityListing);
		

		return $availablity;

	}


	public function checkIfpropertyUpdated($lastUpdatedDate) {
		// Check if updated >> if updated return true
		
		return true;
	}

	public function getDetail($listingArray){

		$listingDetail = array();
		//for ($i=0; $i < count($listing); $i++) {
		for ($i=0; $i < 3; $i++){	
			//check if property data is updated
			$updated = $this->checkIfpropertyUpdated($listingArray[$i]['updateDate']);
			
			if($updated){
				//Get Property Description
				$listingDetail[$i]= $this-> getPropertyDescription($listingArray[$i]['url']);
			}
		}

		return $listingDetail;

	}

	public function convertToArray($data) {

		$data = json_decode(json_encode($data), true);
		return $data;

	}

	public function MapProperties() {
		return "mapped";
	}

	public function updateProperties() {
		return true;
	}

	public function callingCurlCall($url) {

		$CurlObj = new Curl();				
		$data = $CurlObj->curlCall($url);
		$data = $this->convertToArray($data);
		return $data;

	}

	public function getPropertyDescription($listingUrl) {

		$data = $this->callingCurlCall($listingUrl);

		return $data;

	}
	
	public function getListing($url,$contentIndex,$listingUrlIndex,$updatedDateIndex) {

		//$url = "https://www.condo-world.com/CIO/xmladvertiserListingContentIndex.ashx";
		$data = $this->callingCurlCall($url);
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

	/*public function getPropertyRateListing() {

		$url = "https://www.condo-world.com/CIO/xmladvertiserLodgingRateContentIndex.ashx";
		$data = $this->callingCurlCall($url);
		$listing = array();
		//Get listing url & updated date
		$ratelistingIndexCount = count($data['advertiser']['lodgingRateContentIndexEntry']); 
		for($i=0; $i < $ratelistingIndexCount; $i++) {
			 $listing[$i]['url'] = $data['advertiser']['lodgingRateContentIndexEntry'][$i]['lodgingRateContentUrl'];
			 $listing[$i]['updateDate'] = $data['advertiser']['lodgingRateContentIndexEntry'][$i]['lastUpdatedDate'];
		}
		return $listing;

	}*/
}

?>

