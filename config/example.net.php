<?php
/*This is an example config file for miniProxy with some examples, these configs are most usefull for changing php cURL options.
Read more on cURL
http://php.net/manual/en/book.curl.php
http://php.net/manual/en/ref.curl.php

To make your own config file create a file called website_domain.php;
For example to make a config for Facebook you would make a config file called facebook.com.php, prefixes to domains such as 'm.' must be taken into consideration.
If you wanted a config for mobile facebok the file would be called m.facebok.com.php. The only prefix that must not be taken into consideration is 'www.'

Put you cURL config and other scripts into the .php file you created and surround them with(just like this one)
<?php

?>
*/

	//Atempt to report the users real IP (fixes security message on Facebook)
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("X-Forwarded-For: $ip"));


/*Some user agents you may want
I.E 8.0:					Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)
I.E 11.0:					Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)
Firefox on windows: 		Mozilla/5.0 (Windows NT 6.2; WOW64; rv:21.0) Gecko/20100101 Firefox/21.0
Nexus 7 (Android phone) 	Mozilla/5.0 (Linux; Android 4.1.1; Nexus 7 Build/JRO03D) AppleWebKit/535.19 (KHTML, like Gecko) Chrome/18.0.1025.166 Safari/535.19
Galaxy S4(Android phone)	Mozilla/5.0 (Linux; U; Android 4.0.4; en-gb; GT-I9300 Build/IMM76D) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30
Firefox on Android phone	Mozilla/5.0 (Android; Mobile; rv:14.0) Gecko/14.0 Firefox/14.0
Firefox on Android tablet	Mozilla/5.0 (Android; Tablet; rv:14.0) Gecko/14.0 Firefox/14.0
	*/
//Override the user agent. List of user agents: https://udger.com/resources/ua-list (this scripts uses I.E 8.0)
	$user_agent = "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0)";
	curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
	
	//cURL Cookies (Please see https://github.com/joshdick/miniProxy/pull/69)
	//session_start();
	//curl_setopt($ch, CURLOPT_COOKIEJAR, cookies.txt);
    //curl_setopt($ch, CURLOPT_COOKIEFILE, cookies.txt);
//We don't want to make cookies :) ^^^
?>