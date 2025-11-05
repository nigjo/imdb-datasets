<?php

//Router Script for serving static files from this folder or
//from "current DOCUMENT_ROOT" if not found here.

function __moviedbMain() {
  $fullrequest = filter_input(INPUT_SERVER, 'REQUEST_URI');
  $query = '?'.filter_input(INPUT_SERVER, 'QUERY_STRING');
  //echo '#'.$query.'#'.$_SERVER['REQUEST_QUERY'].'#';
  $request = str_replace($query, '', $fullrequest );
  if (str_ends_with($request, ".php")) {
    include_once __DIR__ . '/' . basename($request);
    return;
  }
  if ('/' === $request) {
    $request = '/index.html';
  }
  if (file_exists(__DIR__ . $request)) {
    $pinfo = pathinfo($request);
//    print_r($pinfo);
//    print_r($basename);
    $mimes = [
        "css" => "text/css",
        "jpeg" => "image/jpeg",
        "jpg" => "image/jpeg",
        "png" => "image/png",
        "ico" => "image/x-icon",
        "js" => "text/javascript",
        "json" => "application/json",
        "html" => "text/html"
    ];
    $ct = array_key_exists($pinfo['extension'], $mimes) ?
            $mimes[$pinfo['extension']] :
            mime_content_type($pinfo['basename']);
    header('Content-Type: ' . $ct);
    echo file_get_contents('.' . $request);
    return;
  }

  return false;
}

if (false === __moviedbMain()) {
  return false;
}
