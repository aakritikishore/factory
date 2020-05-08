<?php
       require_once("PropertyFetcherDOA.php");

	class GetPropertyController 
	{		
		
		
		public function getThirdPartyCustomers() 
		{
			
			//Check ThirdPartyCustomer Table in databse	
			$customerList = $this->getCustomerList();
			//print_r($customerList);
			$i = 0;
			while($i < count($customerList)){
				$pfDao = new PropertyFetcherDOA();
				$pf = $pfDao->getPropertyFetcher($customerList[$i]);
			    //print_r($pf)  
				$data =  $pf->getProperty();
				echo "<pre>";
				print_r($data); 
				 echo "<br>";		
				$i++;	
			}
			

		}
		
		public function getCustomerList()
		{
			//$customerList = $this->thirdPartyCustomer->getRecords();
			$customerList = array("HA", "CW");

			return $customerList;
		}

	}
	
?>			
