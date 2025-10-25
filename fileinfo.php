<?php

function sendError($message) {
  header('Content-Type: application/json');
  echo json_encode(array(
      "status" => "error",
      "message" => str_replace("\"", "\\\"", $message)
  ));
}

function sendResult($data) {
  header('Content-Type: application/json');

  echo json_encode([
      'status' => 'ok',
      'data' => $data
  ]);
}

function getFolderPath($relative = false, $subpath = false) {
  $root = realpath(filter_input(INPUT_SERVER, 'DOCUMENT_ROOT'));
  //echo 'R:"'.$root.'"'.PHP_EOL;
  $parampath = filter_input(INPUT_GET, 'path');
  $path = realpath($parampath ? $parampath : '.');
  if ($path === false) {
    $path = realpath($root . '/' . $parampath . ($subpath ? ('/' . $subpath) : ''));
  }
  //echo 'P:"'.$path.'"'.PHP_EOL;
  if (!empty($path) && strpos($path, $root) === 0) {
    return $relative ? ($path === $root ? '.' : str_replace('\\', '/', substr($path, strlen($root) + 1))) : $path;
  }
  return $relative ? '.' : $root;
}

function findFiles() {
  $knownMovieExtensions = [
      'avi' => 'AVI',
      'mp4' => 'MPEG-4',
      'mov' => 'Quicktime',
      'wmv' => 'Windows Media Video',
      'mkv' => 'Matroska',
      'webm' => 'WebM',
      'ogv' => 'Ogg Video'
  ];
  $basedir = getFolderPath();
  $files = array();
  $folders = array();
  //$imgcount = 0;
  $dir = opendir($basedir);
  while (false !== ($file = readdir($dir))) {
    if (str_starts_with($file, '.')) {
      continue;
    } else if (is_dir($basedir . '/' . $file)) {
      $folders[] = $file;
    } else {
      $pathinfo = pathinfo($file);
      if (!array_key_exists('extension', $pathinfo)) {
        continue;
      }
      if (!array_key_exists($pathinfo['filename'], $files)) {
        $files[$pathinfo['filename']] = array();
      }
      $ext = $pathinfo['extension'];
      $files[$pathinfo['filename']][$ext] = $pathinfo['basename'];
      if (array_key_exists($ext, $knownMovieExtensions)) {
        $title = $pathinfo['filename'];
        if (str_contains($title, '[imdbid-')) {
          $title = preg_replace('/\s*\[imdb(id)?-tt\d+\]/', '', $title);
        }
        $files[$pathinfo['filename']]['/title'] = $title;
        $files[$pathinfo['filename']]['/movie'] = $ext;
        $files[$pathinfo['filename']]['/kind'] = $knownMovieExtensions[$ext];
      }
    }
  }
  closedir($dir);
  $movieFiles = array();
  $movieFiles['*basedir'] = $basedir;
  $movieFiles['*filecount'] = 0;
  if (count($folders) > 0) {
    $movieFiles['/'] = $folders;
  }
  sort($files);
  foreach ($files as $data) {
    if (!is_array($data) || !array_key_exists('/movie', $data)) {
      continue;
    } else {
      $movieFiles[] = $data;
    }
    ++$movieFiles['*filecount'];
  }
  return $movieFiles;
}

$mode = filter_input(INPUT_GET, "mode");
if (empty($mode)) {
  $mode = "list";
}

switch ($mode) {
  case 'list': {
      $filelist = findFiles();
      sendResult($filelist);
    }
    break;
}
