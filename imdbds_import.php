<?php

$dbfile = 'data/imdbds-'.date("Ymd").'.sqlite3';
if(file_exists($dbfile)){
  unlink($dbfile);
}

/**
 * get the filesize of the uncompressed file.
 * Source: http://p.lvesu.com/blog/php/function.gzread.php#110078
 * Hint: Die letzten 4 Byte der .gz ist die unkomprimierte Dateigroesse.
 */
function gzfilesize($filename) {
  $gzfs = FALSE;
  if(($zp = fopen($filename, 'r'))!==FALSE) {
    if(@fread($zp, 2) == "\x1F\x8B") { // this is a gzip'd file
      fseek($zp, -4, SEEK_END);
      if(strlen($datum = @fread($zp, 4))==4)
        extract(unpack('Vgzfs', $datum));
    }
    else // not a gzip'd file, revert to regular filesize function
      $gzfs = filesize($filename);
    fclose($zp);
  }
  return($gzfs);
}

/** @deprecate works only for uncompressed files <2GB */
function gzend($fh){
  $d   = 1<<10;
  $eof = 0;
  while (false!==gzgetc($fh)){
    $eof += $d;
    gzseek($fh, $eof);
  } 
  while ( $d > 1 )
  {
    gzgetc($fh);
    $d >>= 1;
    $eof += $d * (gzeof($fh)? -1 : 1);
    gzseek($fh, $eof);
  }
  gzrewind($fh);
  return $eof;
}

function addIndex($db,$tablename, $header, $mainindex){
  $idx=$mainindex;
  if(in_array('ordering', $header))
    $idx.=',ordering';
  $q = 'CREATE UNIQUE INDEX idx_'.$tablename.' ON '.$tablename.' ('.$idx.')';
  echo 'creating index on '.$idx.PHP_EOL;
  $db->exec($q);
}

function loadDataset($db, $filepath){
  echo $filepath.PHP_EOL;
  $count=0;
  $progress = 0;
  $in = gzopen($filepath, "r");
  echo 'preparing file...'."\r";
  $filesize = gzfilesize($filepath);
  $progressdelta = 1000;
  $tablename = str_replace('.','_',basename($filepath, '.tsv.gz'));
  $db->exec('BEGIN TRANSACTION');
  $header=array();
  while(false!=($line=gzgets($in))){
    $cols = explode("\t", SQLite3::escapeString(trim($line)));
    if($count===0){
      $header=$cols;
      echo implode(';', $cols).PHP_EOL;
      $q = 'CREATE TABLE'
        .'\''.$tablename.'\' ('
        .'\''.implode('\' TEXT, \'', $cols).'\''
        .' TEXT);';
      // echo $q.PHP_EOL;
      $db->exec($q);
    }
    $q = 'INSERT INTO '.'\''.$tablename.'\' VALUES('
      .'\''.implode('\', \'', $cols).'\''
      .');';
    $db->exec($q);
    ++$count;
    if($count%$progressdelta===0){
      $loadposition = ftell($in);
      $progress = floor(($loadposition / $filesize)*100);
      if($progress>0)
        echo 'line '.$count.' - '.$progress."%\r";
      else
        echo 'line '.$count."\r";
    }
  }
  echo 'line '.$count.' - 100%'."\r";
  echo number_format($count)." lines read.".PHP_EOL;
  $db->exec('COMMIT TRANSACTION');
  if(in_array('tconst', $header)){
    addIndex($db,$tablename,$header, 'tconst');
  } elseif(in_array('titleId', $header)){
    addIndex($db,$tablename,$header, 'titleId');
  } elseif(in_array('nconst', $header)){
    addIndex($db,$tablename,$header, 'nconst');
  }
  
  gzclose($in);
}

echo 'creating database '.$dbfile.PHP_EOL;
$db = new SQLite3($dbfile);
$db->busyTimeout(2000);

$dh=opendir("data");
while (($file = readdir($dh)) !== false) {
  if(is_file("data/".$file) && strpos($file, ".tsv.gz")!==false) {
    loadDataset($db, "data/".$file);
  }
}
closedir($dh);

echo 'closing database.'.PHP_EOL;
$db->close();
