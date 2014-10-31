<?php

/*** Helper functions ***/

/**
 * This function gets a host name and returns a URL pattern matching that host name.
 * For example if we want to get a pattern that will match 'example.com' and all of it sub-domains 
 * we will call the function like so: getHostnamePattern('example.com');
 * The returned pattern will match both http and https URLs.
 */
function getHostnamePattern($hostname) {
    $escapedHostname = str_replace('.', '\.', $hostname);
    return '@^https?://([a-z0-9-]+\.)*' . $escapedHostname . '@i';
}

/*** Configuration ***/

/**
 * If you want to allow proxying any URL, then set $validDestPatterns to an empty array.
 * If you want to limit the service to specific URLs (whitelist), add a regex pattern that matches that URL to the 
 * following array. Try to enter the most specific pattern to prevent possible abuse.
 */
$validDestPatterns = array(
    // Example to support Google URLs, including sub-domains, uncomment the following line: 
    // getHostnamePattern('google.com')
);
