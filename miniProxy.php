<?php

//miniProxy - a barebones web proxy written in PHP. <https://github.com/joshdick/miniProxy>

define("URL_PARAM", "___mp_url");
define("PROXY_PREFIX", "http://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'] . $_SERVER['PHP_SELF'] . "?" . URL_PARAM . "=");

//Retrieves contents and HTTP headers of a URL with cURL.
function getFile($fileLoc)
{
  if (!function_exists("curl_init")) die ("miniProxy requires PHP's cURL extension. Please install/enable it on your server and try again.");
  //Sends user-agent of actual browser being used--unless there isn't one.
  $user_agent = $_SERVER['HTTP_USER_AGENT'];
  if (empty($user_agent)) {
    $user_agent = "Mozilla/5.0 (compatible; miniProxy)";
  }
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt ($ch, CURLOPT_FAILONERROR, true);
  //If data was POSTed to the proxy, re-POST the data to the requested URL
  $postData = array();
  foreach ($_POST as $key => $value) {
    $postData[] = $key . "=" . $value;
  }
  if (count($postData) > 0) {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, implode("&", $postData));
  }
  //If data was GETed to the proxy, re-GET the data to the requested URL
  $getData = array();
  foreach ($_GET as $key => $value) {
    if ($key == URL_PARAM) continue; //Strip out any data added by proxifyGETForms()
    $getData[] = $key . "=" . $value;
  }
  if (count($getData) > 0) $fileLoc .= "?" . implode("&", $getData);
  curl_setopt($ch, CURLOPT_URL, $fileLoc);
  $data = curl_exec($ch);
  $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
  curl_close($ch);
  return array("data" => $data, "contentType" => $contentType, "contentLength" => $contentLength);
}

//Converts relative URLs to absolute ones, given a base URL.
//Modified version of code Found at http://nashruddin.com/PHP_Script_for_Converting_Relative_to_Absolute_URL
function rel2abs($rel, $base)
{
  //Return if already an absolute URL
  if (parse_url($rel, PHP_URL_SCHEME) != "") return $rel;

  //Queries and anchors
  if ($rel[0] == "#" || $rel[0] == "?") return $base.$rel;

  //Parse base URL and convert to local variables: $scheme, $host, $path
  extract(parse_url($base));

  //Remove non-directory element from path
  $path = isset($path) ? preg_replace('#/[^/]*$#', "", $path) : "/";

  //Destroy path if relative url points to root
  if ($rel[0] == '/') $path = "";

  //Dirty absolute URL
  $abs = "$host$path/$rel";

  //Replace '//' or '/./' or '/foo/../' with '/'
  $re = array("#(/\.?/)#", "#/(?!\.\.)[^/]+/\.\./#");
  for ($n = 1; $n > 0; $abs = preg_replace($re, "/", $abs, -1, $n)) {}

  //Absolute URL is ready.
  return $scheme . "://" . $abs;
}

//For the $attrName attribute of each tag in $tags, change all relative URLs to absolute ones in the DomDocument $doc given its corresponding $baseURL.
function proxifyTags(&$doc, $baseURL, $tags, $attrName) {
  foreach($tags as $tagName) {
    foreach($doc->getElementsByTagName($tagName) as $node) {
      $attrContent = $node->getAttribute($attrName);
      if (!empty($attrContent)) {
        $attrContent = rel2abs($attrContent, $baseURL);
        $attrContent = PROXY_PREFIX . $attrContent;
        $node->setAttribute($attrName, $attrContent);
      }
    }
  }
}

//Rewrite GET forms so that their actions point back to the proxy, and add a hidden form field containing the original action.
function proxifyGETForms(&$doc, $baseURL) {
  foreach($doc->getElementsByTagName("form") as $form) {
    $method = $form->getAttribute("method");
    if (empty($method) || strtolower($method) != "post") { //Only modify GET forms
      $action = rel2abs($form->getAttribute("action"), $baseURL); //Store an absolute version of the form action
      $form->setAttribute("action", ""); //Wipe out the action, forcing The form to submit back to the proxy
      //Add a hidden form field containing the original form action
      $proxyField = $doc->createElement("input");
      $proxyField->setAttribute("type", "hidden");
      $proxyField->setAttribute("name", URL_PARAM);
      $proxyField->setAttribute("value", $action);
      $form->appendChild($proxyField);
    }
  }
}

$url = empty($_GET[URL_PARAM]) ? null : $_GET[URL_PARAM];
if (empty($url)) die("No URL was specified.<br /><br />miniProxy should be invoked like this:<br /><br /><a href=\"" . PROXY_PREFIX . "http://en.wikipedia.org/\">" . PROXY_PREFIX . "http://en.wikipedia.org/");
if (!preg_match("@^.*://@", $url)) $url = "http://" . $url; //Assume that any supplied URLS without a scheme are HTTP URLs.

$file = getFile($url);
//TODO: Check for 0/nulls as per PHP docs for curl_getinfo()
$isHTML = strpos(strtolower($file["contentType"]), "text/html") !== false;
if ($isHTML) { //This is a web page, so modify the DOM to make things point back to the proxy
  $doc = new DomDocument();
  @$doc->loadHTML($file["data"]);
  proxifyGETForms($doc, $url);
  proxifyTags($doc, $url, array("a", "link"), "href");
  proxifyTags($doc, $url, array("img", "script", "iframe", "frame"), "src");
  proxifyTags($doc, $url, array("form"), "action"); //This will only affect POST forms; GET form actions were blanked by proxifyGETForms() above
  echo "<!-- Proxified page constructed by miniProxy -->\n" . $doc->saveHTML();
} else { //This isn't a web page, so serve unmodified through the proxy with the correct headers (images, CSS, JavaScript, etc.)
  header("Content-Type: " . $file["contentType"]);
  header("Content-Length: " . $file["contentLength"]);
  echo $file["data"];
}
