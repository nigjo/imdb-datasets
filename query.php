<?php
if(count($argv)<2){
  throw new Exception("Keine Suchanfrage");
}
$oh=set_error_handler(function($n,$m){
  if($n===1024){
    error_log($m);
  }else{
    echo 'Fehler:'.$n.':'.$m;
  }
});
if($oh){
  restore_error_handler();
}

return queryDatabase(...$argv);

function checkMethod(...$argv){
  $search = $argv[1];
  if($search==='?'){
    trigger_error('do title search');
    if(count($argv)<3){
      throw new Exception("Keine Suchanfrage");
    }
    $search = $argv[2];
    if(empty($search)){
      throw new Exception("Suchanfrage ungültig");
    }
    $GLOBALS['doTitlesOnly'] = true;
  }else if($search==='!'){
    trigger_error('do exact search');
    if(count($argv)<3){
      throw new Exception("Keine Suchanfrage");
    }
    $search = $argv[2];
    if(empty($search)){
      throw new Exception("Suchanfrage ungültig");
    }
    $GLOBALS['doExact'] = true;
    $GLOBALS['exactYear']=count($argv)>3?$argv[3]:false;
  }else{
    if(empty($search)){
      throw new Exception("Suchanfrage ungültig");
    }
    trigger_error('full search');
  }
  return $search;
}

function findDatabase(){
  $all=scandir('data');
  $dbfile=null;
  foreach($all as $file){
    if(is_file('data/'.$file) && basename($file,'.sqlite3')!==$file){
      $dbfile='data/'.$file;
    }
  }
  if($dbfile===null){
    throw new Exception("Keine Datenbank gefunden");
  }
  trigger_error('found databae '.$dbfile);
  return $dbfile;
}

function queryInTitleDb($db, $q){
  global $knownTitles, $firstEntryWritten;
  //echo $q.PHP_EOL;
  //trigger_error($q);
  $res = $db->query($q);
  if($res===false){
    trigger_error('error while searching in title list: '. $db->lastErrorMsg());
  }else if($res->numColumns()===0){
    trigger_error('nothing found in title list');
  }else{
    $head=false;
    while(false!=($row=$res->fetchArray(SQLITE3_ASSOC))){
      trigger_error('found "'.$row['primaryTitle'].'" ('.$row['startYear'].')');
      $knownTitles[]=$row['tconst'];
      if($GLOBALS['doTitlesOnly']===true){
        $moviedata = [
          'tconst'=>$row['tconst'],
          'primaryTitle'=>$row['primaryTitle'],
          'startYear'=>$row['startYear'],
        ];
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
      if($firstEntryWritten)
        echo ',';
      else
        $firstEntryWritten = true;      
      echo json_encode($moviedata,  JSON_PRETTY_PRINT);
    }
  }
}

function searchInAkas($db,$esearch){
  global $knownTitles;
  if($GLOBALS['scanTitles']){
    $q = <<<QUERY
    SELECT *
      FROM title_akas
      WHERE title = '$esearch'
        AND (region = 'DE' OR region = 'AT' OR region = 'XWG'
          OR region = 'US' OR region = 'GB')
QUERY;
    trigger_error('searching title "'.$esearch.'" in AKA list');
  } else {
    $q = <<<QUERY
    SELECT *
      FROM title_akas
      WHERE title LIKE '%$esearch%'
        AND (region = 'DE' OR region = 'AT' OR region = 'XWG'
          OR region = 'US' OR region = 'GB')
QUERY;
    trigger_error('searching for "'.$esearch.'" in AKA list');
  }
  $res = $db->query($q);
  if($res===false){
    trigger_error('- error while searching in aka list: '. $db->lastErrorMsg());
  }else if($res->numColumns()===0){
    trigger_error('- nothing found in aka list');
  }else{
    while(false!=($row=$res->fetchArray(SQLITE3_ASSOC))){
      if(!in_array($row['titleId'],$knownTitles)){
        trigger_error('query aka title "'.$row['title'].'"');
        $q = 'SELECT * FROM title_basics WHERE tconst=\''.$row['titleId'].'\''
          .' AND titleType = \'movie\'';
        queryInTitleDb($db, $q);
      // }else{
        // trigger_error('skipping already checked id '.$row['titleId']);
      }
      if(!in_array($row['titleId'],$knownTitles)){
        trigger_error(' - seems to be no movie');
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
  trigger_error(' - Ratings');
  $q=<<<QUERY
    SELECT * FROM title_ratings
      WHERE tconst = '$titleId'
QUERY;
  $res = $db->query($q);
  if($res!==false){
    $moviedata['ratings']=$res->fetchArray(SQLITE3_ASSOC);
  }
  
  // ----- Directors/Writers ----- ----- ----- ----- -----
  trigger_error(' - Directors and Writers');
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
  trigger_error(' - cast and crew');
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
  trigger_error(' - Alternate Titles');
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
      //trigger_error('  aka '.$title);
      $moviedata['aka'][]=$row;
    }
    trigger_error('   - found '.count($moviedata['aka']).' aka entries');
  }
}

function queryDatabase(...$argv){
global $knownTitles,$firstEntryWritten;

$search = checkMethod(...$argv);
$esearch = SQLite3::escapeString($search);

$start = time();

$knownTitles = array();
$firstEntryWritten = false;
echo '['.PHP_EOL;

$db = new SQLite3(findDatabase());
if($GLOBALS['doExact']){
  if(preg_match('/tt\d+/',$esearch)===1){
    $q=<<<QUERY
      SELECT *
      FROM title_basics
      WHERE tconst='$esearch'
QUERY;
    trigger_error('getting data for movie "'.$esearch.'"');
  }elseif($GLOBALS['exactYear']!==false){
    $q=<<<QUERY
      SELECT *
      FROM title_basics
      WHERE (primaryTitle = '$esearch'
        OR originalTitle = '$esearch')
        AND titleType = 'movie'
        AND startYear = '{$GLOBALS['exactYear']}'
QUERY;
    trigger_error('searching exactly for "'.$esearch.' ('.$GLOBALS['exactYear'].')" in title list');
  }else{
    $q=<<<QUERY
      SELECT *
      FROM title_basics
      WHERE (primaryTitle = '$esearch'
        OR originalTitle = '$esearch')
        AND titleType = 'movie'
QUERY;
    trigger_error('searching exactly for "'.$esearch.'" in title list');
  }
}else{
  if($GLOBALS['scanTitles']) {
    $q=<<<QUERY
      SELECT *
      FROM title_basics
      WHERE (primaryTitle = '$esearch'
        OR originalTitle = '$esearch')
        AND titleType = 'movie'
QUERY;
    trigger_error('searching title "'.$esearch.'" in title list');
  }else{
    $q=<<<QUERY
      SELECT *
      FROM title_basics
      WHERE (primaryTitle LIKE '%$esearch%'
        OR originalTitle LIKE '%$esearch%')
        AND titleType = 'movie'
QUERY;
    trigger_error('searching for "'.$esearch.'" in title list');
  }
}
queryInTitleDb($db, $q);

if($GLOBALS['doExact']!==true){
  searchInAkas($db,$esearch);
}
$db->close();

echo ']'.PHP_EOL;
//print_r($knownTitles);
trigger_error("search time ".(time()-$start).'sec');

}