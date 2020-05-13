<?php

	require_once('core/BaseModel.php');
	require_once('core/db-module/Database.php');

	class Property extends BaseModel {
		
		public function __construct() {
			$this->table = get_class(); // feed class name to the base class.

			//create a database interface for use by this model class
			$db = new Database('mysqli', 'localhost','root', '', 'cio_villas',true);
			$this->databaseInterface = $db->databaseInterface;
		}

		public function addAttributeHeading($array) {
			
			$qry = "INSERT INTO attribute_heading(HeadingText,IsActive) VALUES ('".$array['HeadingText']."','".$array['IsActive']."');";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'];

		}

		public function addAvailableAttribute($array) {


			$qry = "INSERT INTO available_attribute(HeadingID,HeadingText,Label,strName,IsActive) VALUES ('".$array['HeadingID']."', '".$array['HeadingText']."','".$array['Label']."','".$array['strName']."','".$array['IsActive']."');";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'];


		}

		public function addProperty($array) {

			$qry = "INSERT INTO property (CompanyID, Destination, Name, Summary, Details, UnitTypeID, BedroomsID, BathroomsID, GuestMax, Address1, Address2, City, Province, PostalCode, CountryId, YouTubeVideoCode, FloorPlanPDF, BaseRate, RateModeID, MinimumPeople, MinimumNights, external_id, IsActive, StatusID, date_added, last_updated) VALUES ('".$array['CompanyID']."','" .$array['Destination']."','" .$array['Name']."','".$array['Summary']."','".$array['Details']."'," .$array['UnitTypeID']."," .$array['BedroomsID']."," .$array['BathroomsID']."," .$array['GuestMax'].",'" .$array['Address1']."','" .$array['Address2']."','" .$array['City']."','" .$array['Province']."','" .$array['PostalCode']."','" .$array['CountryId']."','" .$array['YouTubeVideoCode']."','" .$array['FloorPlanPDF']."','" .$array['BaseRate']."',".$array['RateModeId'].",'" .$array['MinimumPeople']."'," .$array['MinimumNights']."," .$array['external_id'].",'" .$array['IsActive']."','" .$array['StatusID']."','" .$array['date_added']."','" .$array['last_updated']."');";

			$result = $this->databaseInterface->query($qry,true);
			return $result['result'];

		}

		public function addPropertyAttribute($array) {


			$qry = "INSERT INTO property_attribute(PropertyID,AttributeID) VALUES ('".$array['PropertyID']."', '".$array['AttributeID']."');";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'];


		}

		public function addPropertyFeatures ($array) {

			$qry = "INSERT INTO property_features(`PropertyID`, `ListOptionID`) VALUES ('".$array['PropertyID']."','" .$array['ListOptionID']."');";
			$result = $this->databaseInterface->query($qry,true);
			return $result;

		}

		public function addPropertyPhotos($array) {

			$qry = "INSERT INTO property_photos(`PropertyID`, `FileName`, `SortOrder`, `Primary`) VALUES ('".$array['PropertyID']."','" .$array['FileName']."','" .$array['SortOrder']."','".$array['Primary']."');";

			$result = $this->databaseInterface->query($qry,true);
			return $result;

		}

		public function addPropertyRateDate($array) {
			
			$qry = "INSERT INTO property_rate_date(PropertyID, external_id, StartDate, EndDate, date_added, last_updated) VALUES ('".$array['PropertyID']."','" .$array['external_id']."','" .$array['StartDate']."','".$array['EndDate']."','".$array['date_added']."','".$array['last_updated']."');";

			$result = $this->databaseInterface->query($qry,true);
			return $result;

		}

		public function addPropertyRateDatePricing($array) {
			$qry = "INSERT INTO property_rate_date_pricing(PropertyRateDateID, UpTo, Rate) VALUES ('".$array['PropertyRateDateID']."','" .$array['UpTo']."','" .$array['Rate']."');";

			$result = $this->databaseInterface->query($qry,true);
			return $result;

		}


		public function getBathRoomTypeId($param) {	
			$whereClause = 'WHERE StaticName= = '."'".$param."'";
			
			
			if (isset($where)) 
			{
				$whereClause = "WHERE $where";
			}
			$qry = "SELECT id
					FROM list_option
					$whereClause";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'][0]['id'];
			
		}

		public function getBedRoomTypeId($param) {	
			$whereClause = 'WHERE StaticName = '."'".$param."'";
			
			
			if (isset($where)) 
			{
				$whereClause = "WHERE $where";
			}
			$qry = "SELECT id
					FROM list_option
					$whereClause";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'][0]['id'];
			
		}

		public function getCountryId($param) {	
			$whereClause = 'WHERE Name = '."'".$param."'";
			
			
			if (isset($where)) 
			{
				$whereClause = "WHERE $where";
			}
			$qry = "SELECT id
					FROM country
					$whereClause";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'][0]['id'];
			
		}

		public function getDateRange($array) {
			$qry = "Select * from property_rate_date where PropertyID = '".$array['PropertyID']."' AND StartDate ='".$array['StartDate']."'AND EndDate = '".$array['EndDate']."';";

			$result = $this->databaseInterface->query($qry,true);
			
			return $result;

		}

		public function getHeading($key){
			$qry = "SELECT * from attribute_heading where HeadingText LIKE '".$key."%';";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'][0];
		}

		public function getImages($array){

			$qry = "SELECT * from property_photos where FileName = '".$array['FileName']."' and PropertyID = '".$array['PropertyID']."';";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'];
		}

		public function getExternalIds($tableName) {
			
			$qry = "SELECT external_id from ".$tableName;
			$result = $this->databaseInterface->query($qry,true);
			return $result;

		}

		public function getLabel($label,$keyid) {
			$qry = "SELECT * from available_attribute where Label LIKE '".$label."%' and HeadingID = '".$keyid."';";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'][0];
		}

		public function getLastUpdateDate($propertyId,$tableName) {
			
			$qry = "SELECT last_updated from ".$tableName." where external_id = ".$propertyId;
			$result = $this->databaseInterface->query($qry,true);
			return $result;

		}

		public function getListOptionId($attribute) {

			$qry = "SELECT id from list_option where StaticName='".$attribute."';";
			$result = $this->databaseInterface->query($qry,true);
			if(isset($result['result'][0]['id'])){
				return $result['result'][0]['id'];
			} 
		}

		public function getPlaceTypeAttribute($attribute, $key) {
			$qry = "SELECT id from available_attribute where Label='".$attribute."' AND Heading = '".$key."';";
			$result = $this->databaseInterface->query($qry,true);
			if(isset($result['result'][0]['id'])) {
				return $result['result'][0]['id'];
			} 

		}

		public function getPropertyAttribute($attribute) {
			$qry = "SELECT id from property_attribute where PropertyID='".$attribute['PropertyID']."' AND AttributeID = '".$attribute['AttributeID']."';";
			$result = $this->databaseInterface->query($qry,true);
			if(isset($result['result'][0]['id'])) {
				return $result['result'][0]['id'];
			} 

		}

		public function getPropertyId($extenalId) {
			
			$qry = "SELECT ID from property where external_id =".$extenalId;
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'][0]['ID'];

		}

		public function getPropertyFeatures($array){

			$qry = "SELECT * from property_features where PropertyID = '".$array['PropertyID']."' and ListOptionID = '".$array['ListOptionID']."';";
			$result = $this->databaseInterface->query($qry,true);
			return $result;
		}
		
		public function getUnitTypeId($param) {	
			$whereClause = 'WHERE name = '."'".$param."'";
			
			if (isset($where)) 
			{
				$whereClause = "WHERE $where";
			}
			$qry = "SELECT id
					FROM list_option
					$whereClause";
			$result = $this->databaseInterface->query($qry,true);
			return $result['result'][0]['id'];
			
		}

		public function updateProperty($array, $id) {
			$qry = "UPDATE property set 
						CompanyID = '".$array['CompanyID']."', 
						Destination ='".$array['Destination']."', 
						Name ='".$array['Name']."', 
						Summary ='".$array['Summary']."', 
						Details ='".$array['Details']."' ,
						UnitTypeID = '".$array['UnitTypeID']."',
						BedroomsID = '".$array['BedroomsID']."',
						BathroomsID = '".$array['BathroomsID']."',
						GuestMax = '".$array['GuestMax']."',
						Address1 = '".$array['Address1']."',
						Address2 = '".$array['Address2']."',
						City = '".$array['City']."',
						Province = '".$array['Province']."',
						PostalCode = '".$array['PostalCode']."',
						CountryId = '".$array['CountryId']."',
						YouTubeVideoCode = '".$array['YouTubeVideoCode']."', 
						FloorPlanPDF = '".$array['FloorPlanPDF']."',
						MinimumPeople = '".$array['MinimumPeople']."',
						MinimumNights = '".$array['MinimumNights']."',
						IsActive = '".$array['IsActive']."',
						StatusID = '".$array['StatusID']."',
						last_updated = '".$array['last_updated']."' 
					where external_id = ".$id.";";
			$result = $this->databaseInterface->query($qry,true);
			return $result;

		}

		public function upDatePropertyRateDatePricing($array) {

			$qry = "UPDATE property_rate_date_pricing set 
						PropertyRateDateID = '".$array['PropertyRateDateID']."', 
						UpTo ='".$array['UpTo']."', 
						Rate ='".$array['Rate']."'
						
					where id = ".$array['id'].";";
			$result = $this->databaseInterface->query($qry,true);
			return $result;
		
		}
		
	}	
?>	