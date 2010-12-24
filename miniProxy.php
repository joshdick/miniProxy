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
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  if ($httpCode != 200) {
    $data = "Error: Server at " . $fileLoc . " sent HTTP response code " . $httpCode . ".";
  }
  $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
  curl_close($ch);
  return array("data" => $data, "contentType" => $contentType, "contentLength" => $contentLength);
}

//Converts relative URLs to absolute ones, given a base URL.
//Modified version of code Found at http://nashruddin.com/PHP_Script_for_Converting_Relative_to_Absolute_URL
function rel2abs($rel, $base)
{
  if (parse_url($rel, PHP_URL_SCHEME) != "") return $rel; //Return if already an absolute URL
  if ($rel[0] == "#" || $rel[0] == "?") return $base.$rel; //Queries and anchors
  extract(parse_url($base)); //Parse base URL and convert to local variables: $scheme, $host, $path
  $path = isset($path) ? preg_replace('#/[^/]*$#', "", $path) : "/"; //Remove non-directory element from path
  if ($rel[0] == '/') $path = ""; //Destroy path if relative url points to root
  $abs = "$host$path/$rel"; //Dirty absolute URL
  for ($n = 1; $n > 0; $abs = preg_replace(array("#(/\.?/)#", "#/(?!\.\.)[^/]+/\.\./#"), "/", $abs, -1, $n)) {} //Replace '//' or '/./' or '/foo/../' with '/'
  return $scheme . "://" . $abs; //Absolute URL is ready.
}

//For the $attrName attribute of each tag in $tags, change all relative URLs to absolute ones in the DomDocument $doc given its corresponding $baseURL.
function proxifyTags(&$doc, $baseURL, $tags, $attrName) {
  foreach($tags as $tagName) {
    foreach($doc->getElementsByTagName($tagName) as $node) {
      $attrContent = $node->getAttribute($attrName);
      if (!empty($attrContent)) {
        $attrContent = rel2abs($attrContent, $baseURL);
        //Replace any leftmost question mark with an ampersand to blend an existing query string into the proxified URL
        $attrContent = preg_replace("/\?/", "&", $attrContent, 1);
        $attrContent = PROXY_PREFIX . $attrContent;
        $node->setAttribute($attrName, $attrContent);
      }
    }
  }
}

//Rewrite GET forms so that their actions point back to the proxy, and add a hidden form field with the original form action so that the proxy knows where to GET.
function proxifyGETForms(&$doc, $baseURL) {
  foreach($doc->getElementsByTagName("form") as $form) {
    $method = $form->getAttribute("method");
    if (empty($method) || strtolower($method) != "post") { //Only modify GET forms
      $action = rel2abs($form->getAttribute("action"), $baseURL); //Store an absolute version of the form action
      $form->setAttribute("action", ""); //Wipe out the form action, forcing the form to submit back to the proxy
      //Add a hidden form field containing the original form action
      $proxyField = $doc->createElement("input");
      $proxyField->setAttribute("type", "hidden");
      $proxyField->setAttribute("name", URL_PARAM);
      $proxyField->setAttribute("value", $action);
      $form->appendChild($proxyField);
    }
  }
}

//Proxify contents of url() references in blocks of CSS text.
function proxifyCSS($css, $baseURL) {
  return preg_replace_callback(
    '/url\((.*?)\)/i',
    function($matches) use ($baseURL) {
        $url = $matches[1];
        //Remove any surrounding single or double quotes from the URL so it can be passed to rel2abs - the quotes are optional in CSS
        //Assume that if there is a leading quote then there should be a trailing quote, so just use trim() to remove them
        if (strpos($url, "'") === 0) {
          $url = trim($url, "'");
        }
        if (strpos($url, "\"") === 0) {
          $url = trim($url, "\"");
        }
        return "url(" . rel2abs($url, $baseURL) . ")";
    },
    $css);
}

//Wrapper for proxifyCSS() to handle <style> tags rather than stylesheet files.
function proxifyStyleTags(&$doc, $baseURL) {
  foreach($doc->getElementsByTagName("style") as $style) {
    if ($style->getAttribute("type") == "text/css") { //If this is a CSS stylesheet...
      $style->nodeValue = proxifyCSS($style->nodeValue, $baseURL);
    }
  }
}

$url = empty($_GET[URL_PARAM]) ? null : $_GET[URL_PARAM];
if (empty($url)) die("No URL was specified.<br /><br />miniProxy should be invoked like this:<br /><br /><a href=\"" . PROXY_PREFIX . "http://en.wikipedia.org/\">" . PROXY_PREFIX . "http://en.wikipedia.org/");
if (!preg_match("@^.*://@", $url)) $url = "http://" . $url; //Assume that any supplied URLs without a scheme are HTTP URLs.

$file = getFile($url);
header("Content-Type: " . $file["contentType"]);
if (strpos($file["contentType"], "text/html") !== false) { //This is a web page, so proxify the DOM.
  $doc = new DomDocument();
  @$doc->loadHTML($file["data"]);
  proxifyGETForms($doc, $url);
  proxifyStyleTags($doc, $url);
  proxifyTags($doc, $url, array("a", "link"), "href");
  proxifyTags($doc, $url, array("img", "script", "iframe", "frame"), "src");
  proxifyTags($doc, $url, array("form"), "action"); //This will only affect POST forms; GET form actions were blanked by proxifyGETForms() above.
  echo "<!-- Proxified page constructed by miniProxy -->\n" . $doc->saveHTML();
} else if (strpos($file["contentType"], "text/css") !== false) { //This is CSS, so proxify url() references.
  echo proxifyCSS($file["data"], $url);
} else { //This isn't a web page or CSS, so serve unmodified through the proxy with the correct headers (images, JavaScript, etc.)
  header("Content-Length: " . $file["contentLength"]);
  echo $file["data"];
}
