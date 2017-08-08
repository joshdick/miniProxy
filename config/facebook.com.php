<?php
//Fix for the security message 
curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Forwarded-For: $ip"));
	//Tells Facebook to use a old version for better compatiblity with MiniProxy
	$user_agent = "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)";
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
?>