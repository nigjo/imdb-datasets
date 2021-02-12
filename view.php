<?php
if(preg_match('/\.(jpg|png|ico|css|js)$/', $_SERVER['REQUEST_URI'])){
  if(basename($_SERVER['REQUEST_URI'])==='view.jpg'){
    logRequest();
    echo file_get_contents('view.jpg');
    return;
  }
  if(basename($_SERVER['REQUEST_URI'])==='favicon.ico'){
    logRequest();
    echo file_get_contents('view.ico');
    return;
  }
  return false;
}
function logRequest(){
  //[::1]:52532 [200]: GET
  error_log(''
  .'['
  .$_SERVER['REMOTE_ADDR']
  .']:'
  .$_SERVER['REMOTE_PORT']
  .' ['.http_response_code()
  .']: '
  .$_SERVER['REQUEST_METHOD']
  .' '
  .$_SERVER['REQUEST_URI']
  ,4);
}

logRequest();

if($_SERVER['REQUEST_URI']==='/info.php'){
  phpinfo();
  return;
}

if(!empty($file = filter_input(INPUT_POST, 'file'))){
  // echo 'Post: ';
  // print_r($_POST);
  // echo PHP_EOL.'Request: ';
  // print_r($_REQUEST);
  // echo PHP_EOL.'Files: ';
  // print_r($_FILES);
  // echo PHP_EOL;
  
  // move_uploaded_file(
    // $_FILES["posterFile"]["tmp_name"],filter_input(INPUT_POST, 'file').'.png'
  // );
  if($_FILES["posterFile"]["error"]==UPLOAD_ERR_OK){
    // echo PHP_EOL.'file seems to be OK';
    $pngfile = imagecreatefrompng($_FILES["posterFile"]["tmp_name"]);
    imagejpeg($pngfile, 
      $_SERVER['DOCUMENT_ROOT'].'/'.filter_input(INPUT_POST, 'file').'.jpg', 80);
    echo 'File stored as '.filter_input(INPUT_POST, 'file').'.jpg';
  }else{
    http_response_code(400);
    echo 'There  seems to be some problems: ';
    switch ($_FILES['posterFile']['error']) {
      case UPLOAD_ERR_OK:
        echo 'everything OK???';
        break;
      case UPLOAD_ERR_NO_FILE:
        echo 'No file sent.';
        break;
      case UPLOAD_ERR_INI_SIZE:
      case UPLOAD_ERR_FORM_SIZE:
        echo 'Exceeded filesize limit.';
        break;
      default:
        echo 'Unknown upload errors: '. $_FILES['posterFile']['error'];
        break;
    }
  }
  echo PHP_EOL.'good bye';
  return;
  /**
Post: Array ( [file] => Split (2020.08.05, zdf, 105min) ) 
Request: Array ( [file] => Split (2020.08.05, zdf, 105min) ) 
Files: Array ( [posterFile] => Array (
 [name] => poster.png
 [type] => image/png
 [tmp_name] => C:\Users\nigjo\AppData\Local\Temp\php2B8A.tmp
 [error] => 0
 [size] => 861184
)
) 
  */
}

$file = filter_input(INPUT_GET,'file');
$title = filter_input(INPUT_GET,'title');
$query = filter_input(INPUT_GET,'query');

if(!empty($file)){
  if(!empty($title)){
    ob_start();
    $dest = '?'.http_build_query([
      'file'=>$file
    ]);
    echo '"'.$title.'" speichern  als "'.$file.'.json"<br/>';
    echo '<a href="'.$dest.'">'.$file.'</a><br/>';
    
    $data = queryData(['!',$title], false);
    echo '<pre>';print_r($data);echo '</pre>';
    
    $output = preg_replace_callback ('/^ +/m', function ($m) {
      return str_repeat (' ', strlen ($m[0]) / 2);
    },json_encode($data, JSON_UNESCAPED_SLASHES| JSON_PRETTY_PRINT));
    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/'.$file.'.json', $output);
    
    $content = ob_get_clean();
    header('Location: ./'.$dest);
    echo $content;
    return;
  }
}
//phpinfo();
//return;
?>
<!DOCTYPE html>
<html>
<head>
<title>Local IMDB View</title>
<style>
:root{--poster:url(view.jpg);}
body{font-family:Segoe UI,sans-serif;}
footer{margin-top:1rem;}
a{color:inherit;}
.movies a::before {
	display: block;
	content: ' ';
	background-image: var(--poster);
	background-size: contain;
	background-position: center;
	background-repeat: no-repeat;
	height: 10rem;
}
.movies li {
	display: inline-block;
	width: 10rem;
	height: 12rem;
	font-size: .75em;
	text-align: center;
	vertical-align: top;
	margin: .5em 1em;
}
.movies .missing{color:darksalmon;}
.poster{max-height:10em;float: left;margin: 0 .5em .5em 0;}
h2{clear:left;}
.crew{display:inline-block;margin:0;padding:0}
.crew li{display:flex;text-align:right;}
.crew li span::before{content:'';flex-grow:1;border-bottom:1px dotted gray;
  min-width:1em;margin:0 .5em;box-sizing:border-box;}
.crew li span{color:gray;display:inline-flex;flex-grow:1;}
.moviedetails{flex-wrap: wrap;}
.moviedetails dt{float:left;min-width:9em;font-variant:small-caps;}
.moviedetails dt{float:left;font-variant:small-caps;
  display:inline-flex;min-width:10em;}
.moviedetails dt:after{content:'';border-bottom:1px dotted gray;
  flex-grow:1;min-width:1em;margin:0 .5em;}
.logitem{display:list-item;list-style-type:square;margin-left:1em}
.searchlog{max-height:24em;line-height:1.2em;overflow:auto;}
.searchresult{max-height:26.4em;line-height:1.2em;overflow:auto;}
</style>
<!-- ===== ===== ===== ===== ===== ===== ===== ===== ===== ===== -->
<?php
if(empty($_SERVER['QUERY_STRING'])){
  //----- ----- ----- -----  U E B E R S I C H T  ----- ----- ----- -----
?>
<?php
}else if(!empty($file)) {
  //----- ----- ----- -----  D E T A I L S  ----- ----- ----- -----
?>
<style>
.imagePreview {position:fixed;
  top:0;left:0;width:100vw;height:100vh;
  margin:0;padding:0;box-sizing:border-box;
  background-color:rgba(0,0,0,.5);
}
.imagePreview form>*{display:block;margin-left:auto;margin-right:auto;}
.imagePreview img {
	height:70vh;max-width:60vw;
	margin-top:10vh;border: 2px solid yellow;
}
</style>
<script>
var posterBlob=null;
var posterData=null;
document.onpaste = function(event){
  var items = (event.clipboardData || event.originalEvent.clipboardData).items;
  console.log(JSON.stringify(items)); // will give you the mime types
  for (index in items) {
    var item = items[index];
    if (item.kind === 'file') {
      posterBlob = item.getAsFile();
      var reader = new FileReader();
      reader.onload = function(event){
        posterData = event.target.result;
        console.log(posterData)
        showNewImage(posterData);
      }; // data url!
      reader.readAsDataURL(posterBlob);
    }
  }
}

function showNewImage(data){
  document.body.appendChild(
      document.getElementById('imagePreview').content.cloneNode(true));
  imgDiv = document.querySelector('body>.imagePreview');
  imgDiv.addEventListener('click', (e)=>{
    console.log(e);
    imgDiv.remove()
  });
  let img = imgDiv.querySelector('img');
  img.src = data;
}

function storeImage(event){
  new Promise((ok,fail)=>{
    let xhr=new XMLHttpRequest();
    xhr.open("POST", './');
    //xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
    xhr.addEventListener('load', ok);
    xhr.addEventListener('error', fail);
    const data = new FormData(event.submitter.form);
    data.append('posterFile', posterBlob, 'poster.png');
    xhr.send(data);
  }).then(data=>{
    if(data.target.status===200){
      //console.log(data.target);
      location.reload();
    }else{
      console.warn(data.target);
    }
  })
  return false;
}
</script>
<?php
} else if(!empty($title)) {
  //----- ----- ----- -----  S U C H E  ----- ----- ----- -----
?>
<?php
} else {
  //----- ----- ----- -----  F E H L E R  ----- ----- ----- -----
?>
<?php
}
?>
</head>
<body>
<!-- ===== ===== ===== ===== ===== ===== ===== ===== ===== ===== -->
<header>
<?php
$firstmovie = null;
if(empty($_SERVER['QUERY_STRING'])){
  //----- ----- ----- -----  U E B E R S I C H T  ----- ----- ----- -----
?>
<h1>Übersicht</h1>
<?php
}else if(!empty($file)) {
  //----- ----- ----- -----  D E T A I L S  ----- ----- ----- -----
  $data = json_decode(file_get_contents(
    $_SERVER['DOCUMENT_ROOT'].'/'.$file.'.json'));
  $firstmovie = $data[0];
  
  $title = $firstmovie->basics->primaryTitle;
  $deFound = false;
  foreach($firstmovie->aka as $item){
    if($item->region==='DE'){
      if($item->types==='imdbDisplay'){
        $title =$item->title;
        break;
      }
      if($item->types==='short title' || $item->types==='promotional title'){
        if(!$deFound)
          $title =$item->title;
      }else{
        $title =$item->title;
      }
      $deFound = true;
    } else if($item->region==='XWW'){
      if(!$deFound)
      $title =$item->title; 
    }
  }
?>
<h1><?php echo $title?></h1>
<?php
} else if(!empty($title)) {
  //----- ----- ----- -----  S U C H E  ----- ----- ----- -----
  //TODO:
  ?><h1>Suche</h1><?php
} else {
  //----- ----- ----- -----  F E H L E R  ----- ----- ----- -----
  //TODO:
  ?><h1>Lokale IMDB Datenbank</h1><?php
}
?>
</header>
<!-- ===== ===== ===== ===== ===== ===== ===== ===== ===== ===== -->
<!-- ===== ===== ===== ===== ===== ===== ===== ===== ===== ===== -->
<nav>
<ul>
<?php
if(empty($_SERVER['QUERY_STRING'])){
  //----- ----- ----- -----  U E B E R S I C H T  ----- ----- ----- -----
?>
<?php
}else if(!empty($file)) {
  //----- ----- ----- -----  D E T A I L S  ----- ----- ----- -----
?>
<li><a href="./">Übersicht</a></li>
<li><a target="imdb" href="https://www.imdb.com/title/<?php echo $firstmovie->basics->tconst ?>/">IMDB Seite</a></li>
<?php
} else if(!empty($title)) {
  //----- ----- ----- -----  S U C H E  ----- ----- ----- -----
?>
<li><a href="./">Übersicht</a></li>
<?php
}else{
}
?>
</ul>
</nav>
<!-- ===== ===== ===== ===== ===== ===== ===== ===== ===== ===== -->
<!-- ===== ===== ===== ===== ===== ===== ===== ===== ===== ===== -->
<main>
<?php
if(empty($_SERVER['QUERY_STRING'])){
  //----- ----- ----- -----  U E B E R S I C H T  ----- ----- ----- -----
?>
<div>
<ul class="movies">
<?php
$dir=opendir($_SERVER['DOCUMENT_ROOT']);
while(false!==($file=readdir($dir))){
  $base = basename($file, '.mp4');
  if($base!==$file){
    echo '<li';
    if(file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$base.'.jpg')){
      echo ' style="--poster: url(\''.str_replace('\'','\\\'', $base).'.jpg\')"';
    }
    echo '>';
    if(file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$base.'.json')){
      echo '<a href="?'.http_build_query(['file'=>$base]).'">';
      echo $base;
      echo '</a>';
    }else{
      echo '<a class="missing" href="?'.http_build_query(['title'=>$base]).'">';
      echo $file;
      echo '</a>';
    }
    echo '</li>';
  }
}
?>
</ul>
</div>
<?php
}else if(!empty($file)) {
  //----- ----- ----- -----  D E T A I L S  ----- ----- ----- -----
?>
<div>
<?php
  $imgsrc=$file.'.jpg';
  if(file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$imgsrc)){
    echo '<img class="poster" alt="poster" src="'.$imgsrc.'">';
  }elseif(file_exists($_SERVER['DOCUMENT_ROOT'].'/imdb_'.$imgsrc)){
    echo '<img class="poster" alt="poster" src="imdb_'.$imgsrc.'">';
  }else{
    //Hallo
    echo '<a target="poster" href="'
    .'https://www.filmposter-archiv.de/suche.php?'
    .http_build_query([
      'filmtitel'=>$firstmovie->basics->primaryTitle
    ]).'">';
    //from https://www.studiobinder.com/blog/downloads/movie-poster-template/
    echo '<img class="poster" alt="poster" src="view.jpg">';
    echo '</a>';
  }
?>

<div><dl class="moviedetails">
<dt>Erscheinungsjahr</dt>
<dd><?php echo $firstmovie->basics->startYear;?></dd>
<dt>Laufzeit</dt>
<dd><?php echo $firstmovie->basics->runtimeMinutes; ?> min</dd>
<dt>Genre</dt>
<dd><?php echo $firstmovie->basics->genres; ?></dd>
<dt>Regie</dt>
<dd>
<?php 
  foreach($firstmovie->directors as $director){
    if($director !== $firstmovie->directors[0]){
      echo ', ';
    }
    echo $director->primaryName;
  }
?>
</dd>
<dt>Drehbuch</dt>
<dd>
<?php 
  foreach($firstmovie->writers as $writer){
    if($writer !== $firstmovie->writers[0]){
      echo ', ';
    }
    echo $writer->primaryName;
  }
?>
</dd>
</dl></div>

<div>
<h2>Schauspieler</h2>
<div>
<ul class="crew">
<?php
$actorsAndActress=[];
if($firstmovie->crew->actor)
foreach($firstmovie->crew->actor as $person){
  $actorsAndActress[intval($person->ordering)] = $person;
}
if($firstmovie->crew->actress)
foreach($firstmovie->crew->actress as $person){
  $actorsAndActress[intval($person->ordering)] = $person;
}
asort($actorsAndActress);
foreach($actorsAndActress as $person){
  echo '<li>';
  echo $person->primaryName;
  echo ' <span class="character">';
  echo $person->characters;
  echo '</span>';
  echo '</li>';
}
?>
</ul>
</div>
</div>
<div>
<h2>weitere Mitarbeiter</h2>
<div>
<ul class="crew">
<?php
$crew=[];
foreach($firstmovie->crew as $gname =>$group){
  if(!in_array($gname, ['actor','actress','director','writer']))
  foreach($group as $person)
    $crew[intval($person->ordering)] = $person;
}
asort($crew);
foreach($crew as $person){
  echo '<li>';
  echo $person->primaryName;
  echo ' <span class="character">';
  echo $person->category;
  echo '</span>';
  echo '</li>';
}
?>
</ul>
</div>
</div>
</div>

<template id="imagePreview">
<div class="imagePreview">
<form onsubmit="return storeImage(event);">
<input type="hidden" name="file" value="<?php echo $file;?>"/>
<img src="" alt="new image preview">
<input type="submit" value="speichern" onclick="event.stopPropagation();">
</form>
</div>
</template>

<?php
} else if(!empty($title)) {
  //----- ----- ----- -----  S U C H E  ----- ----- ----- -----
  //TODO: Filmdaten suchen
  if(empty($query))
    $query = preg_filter('/(.+) \(.*/', '$1', $title);
  if(empty($query))
    $query = $title;
  echo '<div class="searchlog">';
  $data = queryData(['?',$query]);
  echo '</div>';
  echo '<ol class="searchresult">';
  foreach($data as $item){
    echo '<li><a href="?'.http_build_query([
      'title'=>$item->tconst,
      'file'=>$title
      ]).'">'.$item->primaryTitle.' ('.$item->startYear.')</a>';
    ?>
 (<a target="imdb" href="https://www.imdb.com/title/<?php echo $item->tconst ?>/">IMDB</a>)
 <?php
    echo '</li>';
  }
  echo '</ol>';
  
?>
<div>
<form method="GET">
<input type="hidden" name="title" value="<?php echo htmlspecialchars($title);?>">
<label>Suche <input name="query" size="50" value="<?php echo htmlspecialchars($query);?>"></label>
<input type="submit" value="Erneut suchen">
</form>
</div>
<?php
  
  //echo '<pre>';print_r($data);echo '</pre>';
  
}else{
  //----- ----- ----- -----  F E H L E R  ----- ----- ----- -----
  //TODO: Filmdaten suchen
}
function queryData($argv, $doflush=true){
  array_unshift($argv, 'query.php');
  $old=set_error_handler(function($n,$m){
    if($n===1024){
      $oldcontent=ob_get_clean();
      echo '<div class="logitem">'.$m.'</div>'.PHP_EOL;
      if($doflush)flush();
      ob_start();
      echo $oldcontent;
    }
  });
  ob_start();
  try{
    //trigger_error('argv: '.print_r($argv,true));
    include __DIR__ .'/query.php';
    $rawdata = ob_get_contents();
  } catch (Exception $e) {
    trigger_error('Abbruch: '.$e->getMessage());
    $rawdata='{}';
  }
  ob_end_clean();
  restore_error_handler();
  return json_decode($rawdata);
}
?>
</main>
<!-- ===== ===== ===== ===== ===== ===== ===== ===== ===== ===== -->
<footer>
</footer>
</body>
</html>