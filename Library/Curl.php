<?php

class Curl {

	public function curlCall($url) {

		//$url="https://www.condo-world.com/CIO/xmladvertiserListingContentIndex.ashx";
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);    // get the url contents

		$data = curl_exec($ch); // execute curl request
		curl_close($ch);

		$xml = simplexml_load_string($data);
		//print_r($xml)

		return $xml;
	}

		


}

?>