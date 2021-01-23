<?php
if(count($argv)<2){
  die("Keine Suchanfrage");
}
$search = $argv[1];
if($search==='?'){
  if(count($argv)<3){
    die("Keine Suchanfrage");
  }
  $search = $argv[2];
  $doTitlesOnly = true;
}else if($search==='!'){
  if(count($argv)<3){
    die("Keine Suchanfrage");
  }
  $search = $argv[2];
  $doExact = true;
  $exactYear=count($argv)>3?$argv[3]:false;
}

function findDatabase(){
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
  return $dbfile;
}

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
      error_log('found "'.$row['primaryTitle'].'" ('.$row['startYear'].')');
      $knownTitles[]=$row['tconst'];
      if($GLOBALS['doTitlesOnly']===true){
        $moviedata = $row['primaryTitle'].' ('.$row['startYear'].')';
      }else{
        $moviedata = array(
          'timestamp'=> date(DATE_W3C),
          'basics' => $row
        );
        findMovieInfos($db, $moviedata);
      }

      if($head)
        echo ','.PHP_EOL;
      else
        $head=false;
      echo json_encode($moviedata,  JSON_PRETTY_PRINT);
    }
  }
}

function searchInAkas($db,$esearch){
  global $knownTitles;
  $q = <<<QUERY
  SELECT *
    FROM title_akas
    WHERE title LIKE '%$esearch%'
      AND (region = 'DE' OR region = 'AT' OR region = 'US' OR region = 'GB')
QUERY;
  error_log('searching for "'.$esearch.'" in AKA list');
  $res = $db->query($q);
  if($res===false){
    error_log('- error while searching in aka list');
  }else if($res->numColumns()===0){
    error_log('- nothing found in aka list');
  }else{
    while(false!=($row=$res->fetchArray(SQLITE3_ASSOC))){
      if(!in_array($row['titleId'],$knownTitles)){
        error_log('query new title '.$row['titleId']);
        $q = 'SELECT * FROM title_basics WHERE tconst=\''.$row['titleId'].'\''
          .' AND titleType = \'movie\'';
        queryInTitleDb($db, $q);
      // }else{
        // error_log('skipping already known title '.$row['titleId']);
      }
      if(!in_array($row['titleId'],$knownTitles)){
        error_log('- seems to be no movie');
        $knownTitles[]=$row['titleId'];
      }
    }
  }
}

function getPersondata($db, $personid){
  
  $q=<<<QUERY
    SELECT * FROM name_basics
      WHERE nconst = '$personid'
QUERY;
  $res = $db->query($q);
  if($res!==false){
    return $res->fetchArray(SQLITE3_ASSOC);
  }
  
  return array();
}

function findMovieInfos($db, &$moviedata){
  $titleId = $moviedata['basics']['tconst'];
  
  // ----- Ratings ----- ----- ----- ----- -----
  error_log(' - Ratings');
  $q=<<<QUERY
    SELECT * FROM title_ratings
      WHERE tconst = '$titleId'
QUERY;
  $res = $db->query($q);
  if($res!==false){
    $moviedata['ratings']=$res->fetchArray(SQLITE3_ASSOC);
  }
  
  // ----- Directors/Writers ----- ----- ----- ----- -----
  error_log(' - Directors and Writers');
  $q=<<<QUERY
    SELECT * FROM title_crew
      WHERE tconst = '$titleId'
QUERY;
  $res = $db->query($q);
  if($res!==false){
    $row=$res->fetchArray(SQLITE3_ASSOC);
    if($row['directors']!=='\N'){
      $moviedata['directors']=array();
      foreach(explode(',',$row['directors']) as $personId){
        $moviedata['directors'][]=getPersondata($db, $personId);
      }
    }
    if($row['writers']!=='\N'){
      $moviedata['writers']=array();
      foreach(explode(',',$row['writers']) as $personId){
        $moviedata['writers'][]=getPersondata($db, $personId);
      }
    }
  }

  // ----- Cast And Crew ----- ----- ----- ----- -----
  error_log(' - cast and crew');
  $q=<<<QUERY
    SELECT *
      FROM title_principals as p, name_basics as n
      WHERE p.tconst = '$titleId' AND p.nconst = n.nconst
      ORDER BY CAST(p.ordering as decimal)
QUERY;
  $res = $db->query($q);
  if($res!==false){
    $crew = array();
    while(false!=($row=$res->fetchArray(SQLITE3_ASSOC))){
      if(!array_key_exists($row['category'], $crew)){
        $crew[$row['category']]=array();
      }
      $crew[$row['category']][] = $row;
    }
    // foreach(explode(',',$row['writers']) as $personId){
      // $crew[]=getPersondata($db, $personId);
    // }
    $moviedata['crew'] = $crew;
  }
  
  // ----- Alternate Titles ----- ----- ----- ----- -----
  error_log(' - Alternate Titles');
  $q=<<<QUERY
    SELECT * FROM title_akas
      WHERE titleId = '$titleId'
      ORDER BY CAST(ordering as decimal)
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

$esearch = SQLite3::escapeString($search);

$knownTitles = array();
echo '['.PHP_EOL;

$db = new SQLite3(findDatabase());
if($GLOBALS['doExact']){
  if($GLOBALS['exactYear']!==false){
    $q=<<<QUERY
      SELECT *
      FROM title_basics
      WHERE (primaryTitle = '$esearch'
        OR originalTitle = '$esearch')
        AND titleType = 'movie'
        AND startYear = '{$GLOBALS['exactYear']}'
QUERY;
    error_log('searching exactly for "'.$esearch.' ('.$GLOBALS['exactYear'].')" in title list');
  }else{
    $q=<<<QUERY
      SELECT *
      FROM title_basics
      WHERE (primaryTitle = '$esearch'
        OR originalTitle = '$esearch')
        AND titleType = 'movie'
QUERY;
    error_log('searching exactly for "'.$esearch.'" in title list');
  }
}else{
  $q=<<<QUERY
    SELECT *
    FROM title_basics
    WHERE (primaryTitle LIKE '%$esearch%'
      OR originalTitle LIKE '%$esearch%')
      AND titleType = 'movie'
QUERY;
  error_log('searching for "'.$esearch.'" in title list');
}
queryInTitleDb($db, $q);

if($GLOBALS['doExact']!==true){
  searchInAkas($db,$esearch);
}
$db->close();

echo ']'.PHP_EOL;
//print_r($knownTitles);

