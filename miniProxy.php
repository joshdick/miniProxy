<?php

//miniProxy - a barebones web proxy written in PHP. <https://github.com/joshdick/miniProxy>

define("PROXY_PREFIX", "http://" . $_SERVER['SERVER_NAME'] . $_SERVER['PHP_SELF'] . "?url=");

//Retrieves contents and HTTP headers of a URL with cURL.
function getFile($fileLoc)
{
  if (!function_exists("curl_init")) die ("miniProxy requires PHP's cURL extension. Please install/enable it on your server and try again.");
  //Sends user-agent of actual browser being used--unless there isn't one.
  $user_agent = $_SERVER['HTTP_USER_AGENT'];
  if (empty($user_agent)) {
    $user_agent = "Mozilla/5.0 (compatible; miniProxy)";
  }
  $ch = curl_init($fileLoc);
  curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt ($ch, CURLOPT_FAILONERROR, true);
  if ($_POST) { //If data was POSTed to the proxy, re-POST the data to the requested URL
    $postData = array();
    foreach ($_POST as $key => $value) {
        $postData[] = $key . "=" . $value;
    }
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, implode("&", $postData));
  }
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

$url = empty($_GET['url']) ? null : $_GET['url'];
if (empty($url)) die("No URL was specified.<br /><br />miniProxy should be invoked like this:<br /><br /><a href=\"" . PROXY_PREFIX . "http://en.wikipedia.org/\">" . PROXY_PREFIX . "http://en.wikipedia.org/");
if (!preg_match("@^.*://@", $url)) $url = "http://" . $url; //Assume that any supplied URLS without a scheme are HTTP URLs.

$file = getFile($url);
//TODO: Check for 0/nulls as per PHP docs for curl_getinfo()
$isHTML = strpos(strtolower($file["contentType"]), "text/html") !== false;
if ($isHTML) { //This is a web page, so modify the DOM to make things point back to the proxy
  $doc = new DomDocument();
  @$doc->loadHTML($file["data"]);
  proxifyTags($doc, $url, array("a", "link"), "href");
  proxifyTags($doc, $url, array("img", "script"), "src");
  proxifyTags($doc, $url, array("form"), "action");
  echo "<!-- Proxified page constructed by miniProxy -->\n" . $doc->saveHTML();
} else { //This isn't a web page, so serve unmodified through the proxy with the correct headers (images, CSS, JavaScript, etc.)
  header("Content-Type: " . $file["contentType"]);
  header("Content-Length: " . $file["contentLength"]);
  echo $file["data"];
}
