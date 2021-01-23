<?php
if(count($argv)<2){
  die("Keine Suchanfrage");
}
$search = $argv[1];

$all=scandir('.');
$dbfile=null;
foreach($all as $file){
  if(is_file($file) && basename($file,'.sqlite3')!==$file){
    $dbfile=$file;
  }
}
if($dbfile===null){
  die("Keine Datenbank gefunden");
}

$esearch = SQLite3::escapeString($search);

$knownTitles = array();
echo '['.PHP_EOL;

$db = new SQLite3($dbfile);
$q=<<<QUERY
  SELECT *
  FROM title_basics
  WHERE (primaryTitle LIKE '%$esearch%'
    OR originalTitle LIKE '%$esearch%')
    AND titleType = 'movie'
QUERY;
error_log('searching for "'.$esearch.'" in title list');
queryInTitleDb($db, $q);
function queryInTitleDb($db, $q){
  global $knownTitles;
  //echo $q.PHP_EOL;
  //error_log($q);
  $res = $db->query($q);
  if($res===false){
    error_log('error while searching in title list');
  }else if($res->numColumns()===0){
    error_log('nothing found in title list');
  }else{
    $head=false;
    while(false!=($row=$res->fetchArray(SQLITE3_ASSOC))){
      error_log('found "'.$row['primaryTitle'].'"');
      $knownTitles[]=$row['tconst'];

      $moviedata = array(
        'basics' => $row
      );
      findMovieInfos($db, $moviedata);
      
      if($head)
        echo ','.PHP_EOL;
      else
        $head=false;
      echo json_encode($moviedata,  JSON_PRETTY_PRINT);
    }
  }
}

searchInAkas($db,$esearch);
function searchInAkas($db,$esearch){
  global $knownTitles;
  $q = <<<QUERY
  SELECT *
    FROM title_akas
    WHERE title LIKE '%$esearch%'
      AND (region = 'DE' OR region = 'AT' OR language = 'US' OR language = 'GB')
QUERY;
  error_log('searching for "'.$esearch.'" in AKA list');
  $res = $db->query($q);
  if($res===false){
    error_log('error while searching in aka list');
  }else if($res->numColumns()===0){
    error_log('nothing found in aka list');
  }else{
    while(false!=($row=$res->fetchArray(SQLITE3_ASSOC))){
      if(!in_array($row['titleId'],$knownTitles)){
        error_log('query new title '.$row['titleId']);
        $q = 'SELECT * FROM title_basics WHERE tconst=\''.$row['titleId'].'\'';
        queryInTitleDb($db, $q);
      // }else{
        // error_log('skipping already known title '.$row['titleId']);
      }
      if(!in_array($row['titleId'],$knownTitles)){
        error_log('adding known title '.$row['titleId']);
        $knownTitles[]=$row['titleId'];
      }
    }
  }
}
$db->close();

echo ']'.PHP_EOL;
//print_r($knownTitles);

function findMovieInfos($db, &$moviedata){
  $titleId = $moviedata['basics']['tconst'];
  $q=<<<QUERY
    SELECT * FROM title_akas
      WHERE titleId = '$titleId'
      ORDER BY ordering
QUERY;
  $res = $db->query($q);
  if($res!==false){
    $moviedata['aka']=array();
    while(false!=($row=$res->fetchArray(SQLITE3_ASSOC))){
      $title = '"'.$row['title'].'"';
      if($row['region']!=='\N') $title.=' '.$row['region'];
      if($row['language']!=='\N') $title.=' '.$row['language'];
      //error_log('  aka '.$title);
      $moviedata['aka'][]=$row;
    }
    error_log('found '.count($moviedata['aka']).' aka entries');
  }
}