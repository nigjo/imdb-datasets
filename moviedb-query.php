<?php

function queryDatabase() {

  header('Content-Type: application/json');
  try {
    $dbfile = findDatabase();
  } catch (Exception $ex) {
    return sendError($ex);
  }

  if ($dbfile) {

    $mode = filter_input(INPUT_POST, 'mode');
    if (empty($mode)) {
      $mode = 'likeTitle';
    }
    $search = filter_input(INPUT_POST, 'query');
    if (empty($search)) {
      return sendError('no query string');
    }

    $esearch = SQLite3::escapeString(trim($search));
    $q = findQuery($mode, $esearch);
    $results = array();
    if ($q) {
      //TODO: Query and Result
      $db = new SQLite3($dbfile);
      $res = $db->query($q);
      if ($res === false) {
        return sendError('error while searching in title list: ' . $db->lastErrorMsg());
      } else if ($res->numColumns() === 0) {
        return sendError('nothing found');
      } else {
        while (false != ($row = $res->fetchArray(SQLITE3_ASSOC))) {
          //trigger_error('found "' . $row['primaryTitle'] . '" (' . $row['startYear'] . ')');
          $results[] = $row;
        }
      }
    }

    return json_encode([
        'mode' => filter_input(INPUT_POST, 'mode'),
        'query' => filter_input(INPUT_POST, 'query'),
        'database' => basename($dbfile),
        'results' => $results
    ]);
  } else {
    return sendError('no database found');
  }

  return '{"result":"undefined"}';
}

function findQuery($mode, $esearch) {
  $q = false;
  switch ($mode) {
    case 'imdbid':
      $q = <<<QUERY
        SELECT *
        FROM title_basics
        WHERE tconst='$esearch'
QUERY;
      break;
    case 'title':
      $q = <<<QUERY
        SELECT *
        FROM title_basics
        WHERE (primaryTitle = '$esearch' COLLATE NOCASE
          OR originalTitle = '$esearch' COLLATE NOCASE)
          AND titleType = 'movie'
QUERY;
      break;
    case 'likeTitle':
      $q = <<<QUERY
        SELECT *
        FROM title_basics
        WHERE (primaryTitle LIKE '%$esearch%'
          OR originalTitle LIKE '%$esearch%')
            COLLATE NOCASE
          AND titleType = 'movie'
QUERY;
      break;
    case 'aka':
      $q = <<<QUERY
SELECT 
    b.*, 
    MAX(a.title) AS akaTitle
FROM 
    title_basics AS b
INNER JOIN 
    title_akas AS a ON b.tconst = a.titleId
WHERE a.title = '$esearch' COLLATE NOCASE
  AND b.titleType = 'movie'
  AND (a.region = 'DE' OR a.region = 'AT' OR a.region = 'XWG'
    OR a.region = 'US' OR a.region = 'GB')
GROUP BY 
    b.tconst;
QUERY;
      break;
    case 'likeAka':
      $q = <<<QUERY
SELECT 
    b.*, 
    MAX(a.title) AS akaTitle
FROM 
    title_basics AS b
INNER JOIN 
    title_akas AS a ON b.tconst = a.titleId
WHERE a.title LIKE '%$esearch%' COLLATE NOCASE
  AND b.titleType = 'movie'
  AND (a.region = 'DE' OR a.region = 'AT' OR a.region = 'XWG'
    OR a.region = 'US' OR a.region = 'GB')
GROUP BY 
    b.tconst;
QUERY;
      break;
  }
  return $q;
}

function sendError($message) {
  return json_encode([
      'mode' => filter_input(INPUT_POST, 'mode'),
      'query' => filter_input(INPUT_POST, 'query'),
      'database' => '',
      'error' => $message,
      'results' => []
  ]);
}

function findDatabase() {
  $all = scandir('data');
  $dbfile = null;
  foreach ($all as $file) {
    if (is_file('data/' . $file) && basename($file, '.sqlite3') !== $file) {
      $dbfile = 'data/' . $file;
    }
  }
  if ($dbfile === null) {
    throw new Exception("Keine Datenbank gefunden");
  }
//trigger_error('found database ' . $dbfile);
  return $dbfile;
}

//file_put_contents('moviedb_request.dump', print_r($_REQUEST, true));
//file_put_contents('moviedb_get.dump', print_r($_GET, true));
//file_put_contents('moviedb_post.dump', print_r($_POST, true));

echo queryDatabase();
