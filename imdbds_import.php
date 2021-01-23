<?php

$dbfile = 'imdbds-'.date("Ymd").'.sqlite3';
if(file_exists($dbfile)){
  unlink($dbfile);
}

function loadDataset($db, $filepath){
  echo $filepath.PHP_EOL;
  $count=0;
  $in = gzopen($filepath, "r");
  $tablename = str_replace('.','_',basename($filepath, '.tsv.gz'));
  $db->exec('BEGIN TRANSACTION');
  while(false!=($line=gzgets($in))){
    $cols = explode("\t", SQLite3::escapeString(trim($line)));
    if($count===0){
      echo implode(';', $cols).PHP_EOL;
      $q = 'CREATE TABLE'
        .'\''.$tablename.'\' ('
        .'\''.implode('\' TEXT, \'', $cols).'\''
        .' TEXT);';
      // echo $q.PHP_EOL;
      $db->exec($q);
    }else{
      $q = 'INSERT INTO '.'\''.$tablename.'\' VALUES('
        .'\''.implode('\', \'', $cols).'\''
        .');';
      $db->exec($q);
    }
    ++$count;
    if($count % 100000 == 0){
      $db->exec('COMMIT TRANSACTION');
      $db->exec('BEGIN TRANSACTION');
    }
    echo 'line '.$count."\r";
  }
  $db->exec('COMMIT TRANSACTION');
  gzclose($in);
  echo $count." Zeilen gelesen".PHP_EOL;
}

$db = new SQLite3($dbfile);
$db->busyTimeout(2000);

$dh=opendir("data");
while (($file = readdir($dh)) !== false) {
  if(is_file("data/".$file) && strpos($file, ".tsv.gz")!==false) {
    loadDataset($db, "data/".$file);
  }
}
closedir($dh);

$db->close();
