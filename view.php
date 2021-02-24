<?php

function serveStaticFiles() {
  $request = filter_input(INPUT_SERVER, 'REQUEST_URI');
  if (preg_match('/\.(jpg|png|ico|css|js)$/', $request)) {
    if (file_exists(basename($request))) {
      logRequest();
      header('Content-Type: '
      .mime_content_type (basename($request)));
      echo file_get_contents(basename($request));
      return;
    }
    if (basename($request) === 'favicon.ico') {
      logRequest();
      echo file_get_contents('view.ico');
      return;
    }
    return false;
  }
}

function getFolderPath($relative=false, $subpath=false){
  $root = realpath(filter_input(INPUT_SERVER, 'DOCUMENT_ROOT'));
  //echo 'R:"'.$root.'"'.PHP_EOL;
  $parampath=filter_input(INPUT_GET, 'path');
  $path = realpath($parampath);
  if($path===false) {
    $path = realpath($root.'/'.$parampath.($subpath?('/'.$subpath):''));
  }
  //echo 'P:"'.$path.'"'.PHP_EOL;
  if(!empty($path) && strpos($path, $root) === 0){
    return $relative
        ?($path===$root?'.'
          :str_replace('\\','/', substr($path, strlen($root)+1)))
        :$path;
  }
  return $relative?'.':$root;
}

function logRequest() {
//[::1]:52532 [200]: GET
  error_log(''
          . '['
          . filter_input(INPUT_SERVER, 'REMOTE_ADDR')
          . ']:'
          . filter_input(INPUT_SERVER, 'REMOTE_PORT')
          . ' [' . http_response_code()
          . ']: '
          . filter_input(INPUT_SERVER, 'REQUEST_METHOD')
          . ' '
          . filter_input(INPUT_SERVER, 'REQUEST_URI')
          , 4);
}

function uploadPosterImage() {
  if ($_FILES["posterFile"]["error"] == UPLOAD_ERR_OK) {
// echo PHP_EOL.'file seems to be OK';
    $pngfile = imagecreatefrompng($_FILES["posterFile"]["tmp_name"]);
    imagejpeg($pngfile,
            getFolderPath()
            . '/' . filter_input(INPUT_POST, 'file') . '.jpg', 80);
    echo 'File stored as ' . filter_input(INPUT_POST, 'file') . '.jpg';
  } else {
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
        echo 'Unknown upload errors: ' . $_FILES['posterFile']['error'];
        break;
    }
  }
  echo PHP_EOL . 'good bye';
}

function saveJsonData($rootdir, $file, $title) {
  ob_start();
  $dest = '?' . buildQuery([
              'file' => $file
  ]);
  echo '"' . $title . '" speichern  als "' . $file . '.json"<br/>';
  echo '<a href="' . $dest . '">' . $file . '</a><br/>';

  $data = queryData(['!', $title], false);
  echo '<pre>';
  print_r($data);
  echo '</pre>';

  $output = preg_replace_callback('/^ +/m', function ($m) {
    return str_repeat(' ', strlen($m[0]) / 2);
  }, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
  file_put_contents($rootdir . '/' . $file . '.json', $output);

  $content = ob_get_clean();
  header('Location: ./' . $dest);
  echo $content;
}

function queryData($argv, $doflush = true) {
  array_unshift($argv, 'query.php');
  set_error_handler(function($n, $m) {
    if ($n === 1024) {
      $oldcontent = ob_get_clean();
      echo '<div class="logitem">' . $m . '</div>' . PHP_EOL;
      if ($doflush) {
        flush();
      }
      ob_start();
      echo $oldcontent;
    }
  });
  ob_start();
  try {
    if(function_exists('queryDatabase')){
      queryDatabase(...$argv);
    }else{
      //trigger_error('argv: '.print_r($argv,true));
      include_once __DIR__ . '/query.php';
    }
    $rawdata = ob_get_contents();
  } catch (Exception $e) {
    trigger_error('Abbruch: ' . $e->getMessage());
    $rawdata = '{}';
  }
  ob_end_clean();
  restore_error_handler();
  return json_decode($rawdata);
}

class PageContent {

  function __construct(){
  }

  function writeHeaderContent() {
    writeCommonCSS();
  }

  function writeHeadContent() {
    ?><h1>Lokale IMDB Datenbank</h1><?php
  }

  function writeNavigationItems() {
    
  }

  function writeMainContent() {
    
  }

}

class Overview extends PageContent {

  function writeHeadContent() {
    ?><h1>Übersicht - <?php
      echo basename(getFolderPath());
    ?></h1><?php
  }

  function writeNavigationItems(){
    ?>
      <li><a href="view.php?<?php echo buildQuery(['list'=>'acting']);?>">Schauspieler*innen</a></li>
      <li><a href="view.php?<?php echo buildQuery(['list'=>'directing']);?>">Regiseur*innen</a></li>
      <li><a href="view.php?<?php echo buildQuery(['list'=>'producing']);?>">Produzent*innen</a></li>
      <li><a href="view.php?<?php echo buildQuery(['list'=>'composing']);?>">Komponist*innen</a></li>
      <li><a href="view.php?<?php echo buildQuery(['list'=>'genre']);?>">Genre</a></li>
    </ul><ul>
    <?php
      $folder = getFolderPath();
      $withParent = getRelativePath('.')!=='./.';
      $dir = opendir($folder);
      while (false !== ($file = readdir($dir))) {
        if(is_dir($folder.'/'.$file)){
          if($withParent&&$file==='..'){
            ?><li><a href="?<?php
              echo buildQuery(['path'=>getRelativePath($file)]);
            ?>"><?php echo $file.'#'.getFolderPath(true,'..');?></a></li><?php
          }else if($file[0]!=='.'){
            ?><li><a href="?<?php
              echo buildQuery(['path'=>getRelativePath($file)]);
            ?>"><?php echo $file;?></a></li><?php
          }
        }
      }
  }

  function writeHeaderContent() {
    parent::writeHeaderContent();
  }

  function writeMainContent() {
    ?>
    <div>
      <ul class="movies">
        <?php
        $dir = opendir(getFolderPath());
        while (false !== ($file = readdir($dir))) {
          Overview::writeListItem(getFolderPath(), $file);
        }
        ?>
      </ul>
    </div>
    <?php
  }

  static function writeListItem($rootdir, $file){
    $ext = filter_input(INPUT_GET, 'ext');
    $base = basename($file, '.'.($ext?$ext:'mp4'));
    if ($base !== $file) {
      echo '<li';
      if (file_exists($rootdir . '/' . $base . '.jpg')) {
        $imgfile = getRelativePath($base. '.jpg');
        echo ' style="--poster: url(\'' . 
            str_replace('\'', '\\\'', $imgfile) . '\')"';
      }
      echo '>';
      if (file_exists($rootdir . '/' . $base . '.json')) {
        echo '<a href="?' . buildQuery(['file' => $base]) . '">';
        echo $base;
        echo '</a>';
      } else {
        echo '<a class="missing" href="?' . buildQuery(['title' => $base]) . '">';
        echo $file;
        echo '</a>';
      }
      echo '</li>';
    }
  }

}

function getRelativePath($file){
  return getFolderPath(true).'/'.$file;
}

function buildQuery($data=array()){
  $ext = filter_input(INPUT_GET, 'ext');
  $result = array();
  if($ext)$result['ext']=$ext;
  //$base = basename($file, '.'.($ext?$ext:'mp4'));
  if(!array_key_exists('path', $data)) {
    $path = filter_input(INPUT_GET, 'path');
    if(!empty($path))$result['path']=$path;
  }
  if(!empty($result)){
    $merged = array_merge($result, $data);
    return http_build_query($merged);
  }
  return http_build_query($data);
}

class Details extends PageContent {

  function writeHeaderContent() {
    parent::writeHeaderContent();
    ?>
      <style>
      .poster{max-height:10em;float: left;margin: 0 .5em .5em 0;}
      h2{clear:left;}
      .character{color:gray;}
      .crew{display:inline-block;margin:0;padding:0}
      .crew li{display:flex;text-align:right;}
      .crew li .character::before{content:'';flex-grow:1;border-bottom:1px dotted gray;
                                  min-width:1em;margin:0 .5em;box-sizing:border-box;}
      .crew li .character{display:inline-flex;flex-grow:1;}
      .moviedetails{flex-wrap: wrap;}
      .moviedetails dt{float:left;min-width:9em;font-variant:small-caps;}
      .moviedetails dt{float:left;font-variant:small-caps;
                       display:inline-flex;min-width:10em;}
      .moviedetails dt:after{content:'';border-bottom:1px dotted gray;
                             flex-grow:1;min-width:1em;margin:0 .5em;}
      .imagePreview {position:fixed;top:0;left:0;width:100vw;height:100vh;
        margin:0;padding:0;box-sizing:border-box;background-color:rgba(0,0,0,.5);}
      .imagePreview form>*{display:block;margin-left:auto;margin-right:auto;}
      .imagePreview img{height:70vh;max-width:60vw;
        margin-top:10vh;border: 2px solid yellow;}
      .stand{font-family:monospace;color:gray;}
    </style>
    <script>
      var posterBlob = null;
      var posterData = null;
      document.onpaste = function (event) {
        var items = (event.clipboardData || event.originalEvent.clipboardData).items;
        console.log(JSON.stringify(items)); // will give you the mime types
        for (index in items) {
          var item = items[index];
          if (item.kind === 'file') {
            posterBlob = item.getAsFile();
            var reader = new FileReader();
            reader.onload = function (event) {
              posterData = event.target.result;
              console.log(posterData);
              showNewImage(posterData);
            }; // data url!
            reader.readAsDataURL(posterBlob);
          }
        }
      };

      function showNewImage(data) {
        document.body.appendChild(
                document.getElementById('imagePreview').content.cloneNode(true));
        imgDiv = document.querySelector('body>.imagePreview');
        imgDiv.addEventListener('click', (e) => {
          console.log(e);
          imgDiv.remove();
        });
        let img = imgDiv.querySelector('img');
        img.src = data;
      }

      function storeImage(event) {
        new Promise((ok, fail) => {
          let xhr = new XMLHttpRequest();
          xhr.open("POST", './?'+<?php echo '"'.buildQuery().'"';?>);
          //xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
          xhr.addEventListener('load', ok);
          xhr.addEventListener('error', fail);
          const data = new FormData(event.submitter.form);
          data.append('posterFile', posterBlob, 'poster.png');
          xhr.send(data);
        }).then(data => {
          if (data.target.status === 200) {
            //console.log(data.target);
            location.reload();
          } else {
            console.warn(data.target);
          }
        });
        return false;
      }
    </script>
    <?php
  }

  function writeHeadContent() {
    global $file;
    $data = json_decode(file_get_contents(
                    getFolderPath() . '/' . $file . '.json'));
    $this->firstmovie = $data[0];

    $title = $this->firstmovie->basics->primaryTitle;
    $deFound = false;
    $someFound = false;
    foreach ($this->firstmovie->aka as $item) {
      if ($item->region === 'DE') {
        if ($item->types === 'imdbDisplay') {
          $title = $item->title;
          break;
        }
        if ($item->types === 'short title' || $item->types === 'promotional title') {
          if (!$deFound) {
            $title = $item->title;
          }
        } else {
          $title = $item->title;
        }
        $deFound = true;
        $someFound = true;
      } else if ($item->region === 'XWG') {
        if (!$deFound) {
          $title = $item->title;
          $someFound = true;
        }
      } else if ($item->region === 'AT') {
        if (!$deFound) {
          $title = $item->title;
          $someFound = true;
        }
      } else if ($item->region === 'XWW') {
        if (!$someFound) {
          $title = $item->title;
        }
      }
    }
    ?>
    <h1><?php echo $title ?></h1>
    <?php
    $this->title = $title;
  }

  function writeNavigationItems() {
    ?>
    <li><a href="?<?php echo buildQuery();?>">Übersicht</a></li>
    <li><a target="imdb" href="https://www.imdb.com/title/<?php echo $this->firstmovie->basics->tconst; ?>/">IMDB Seite</a></li>
    <li><a target="ofdb" href="https://ssl.ofdb.de/view.php?page=suchergebnis&Kat=IMDb&SText=<?php echo $this->firstmovie->basics->tconst; ?>">OFDb Seite</a></li>
    <?php
  }

  function writeMainContent() {
    global $file;
    $firstmovie = $this->firstmovie;
    ?>
    <div class="stand">Stand: <?php echo $firstmovie->timestamp; ?></div>
    <div>
      <?php
      $imgsrc = $file . '.jpg';
      if (file_exists(getFolderPath() . '/' . $imgsrc)) {
        echo '<img class="poster" alt="poster" src="' . getRelativePath($imgsrc) . '">';
      } elseif (file_exists(getFolderPath() . '/imdb_' . $imgsrc)) {
        echo '<img class="poster" alt="poster" src="'.getRelativePath('imdb_' . $imgsrc) . '">';
      } else {
        //Hallo
        echo '<a target="poster" href="'
        . 'https://www.filmposter-archiv.de/suche.php?'
        . buildQuery([
            'filmtitel' => $this->title
        ]) . '">';
        //from https://www.studiobinder.com/blog/downloads/movie-poster-template/
        echo '<img class="poster" alt="poster" src="view.jpg">';
        echo '</a>';
      }
      ?>

      <div><dl class="moviedetails">
          <dt>Erscheinungsjahr</dt>
          <dd><?php echo $firstmovie->basics->startYear; ?></dd>
          <dt>Laufzeit</dt>
          <dd><?php echo $firstmovie->basics->runtimeMinutes; ?> min</dd>
          <dt>Genre</dt>
          <dd><?php echo $firstmovie->basics->genres; ?></dd>
          <dt>Regie</dt>
          <dd>
            <?php
            foreach ($firstmovie->directors as $director) {
              if ($director !== $firstmovie->directors[0]) {
                echo ', ';
              }
              echo $director->primaryName;
            }
            ?>
          </dd>
          <dt>Drehbuch</dt>
          <dd>
            <?php
            foreach ($firstmovie->writers as $writer) {
              if ($writer !== $firstmovie->writers[0]) {
                echo ', ';
              }
              echo $writer->primaryName;
              if ($firstmovie->crew->writer) {
                foreach ($firstmovie->crew->writer as $person) {
                  if ($person->nconst === $writer->nconst && 
                          strpos($person->job, 'screenplay') === false && 
                          $person->job!=='written by'&& 
                          $person->job!=='\\N') {
                    echo ' <span class="character">(';
                    echo $person->job;
                    echo ')</span>';
                  }
                }
              }
            }
            ?>
          </dd>
        </dl></div>

      <div>
        <h2>Schauspieler</h2>
        <div>
          <ul class="crew">
            <?php
            $actorsAndActress = [];
            if ($firstmovie->crew->actor) {
              foreach ($firstmovie->crew->actor as $person) {
                $actorsAndActress[intval($person->ordering)] = $person;
              }
            }
            if ($firstmovie->crew->actress) {
              foreach ($firstmovie->crew->actress as $person) {
                $actorsAndActress[intval($person->ordering)] = $person;
              }
            }
            asort($actorsAndActress);
            foreach ($actorsAndActress as $person) {
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
            $crew = [];
            foreach ($firstmovie->crew as $gname => $group) {
              if (!in_array($gname, ['actor', 'actress', 'director', 'writer'])) {
                foreach ($group as $person) {
                  $crew[intval($person->ordering)] = $person;
                }
              }
            }
            asort($crew);
            foreach ($crew as $person) {
              echo '<li>';
              echo $person->primaryName;
              echo ' <span class="character">';
              echo $person->category;
              if (!empty($person->job) &&
                      $person->job !== '\\N' &&
                      $person->job !== $person->category) {
                echo '/' . $person->job;
              }
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
          <input type="hidden" name="file" value="<?php echo $file; ?>"/>
          <img src="" alt="new image preview">
          <input type="submit" value="speichern" onclick="event.stopPropagation();">
        </form>
      </div>
    </template>

    <?php
  }

}

class Search extends PageContent {

  function writeHeadContent() {
    echo '<h1>Suche</h1>';
  }

  function writeNavigationItems() {
    echo '<li><a href="./?'.buildQuery().'">Übersicht</a></li>';
  }

  function writeHeaderContent() {
    parent::writeHeaderContent();
    ?>
    <style>
      .logitem{display:list-item;list-style-type:square;margin-left:1em}
      .searchlog{max-height:24em;line-height:1.2em;overflow:auto;}
      .searchresult{max-height:26.4em;line-height:1.2em;overflow:auto;}
      form{display:inline-block;}
      #autosearchtimeout:before,#autosearchtimeout:after{white-space:nowrap;
        width:var(--progress);display:block;position:absolute;height:1rem;
        font-size:.8rem;line-height:1rem;text-align:center;border-radius:.5rem;}
      #autosearchtimeout:before{content:'';background-color:green;}
      #autosearchtimeout:after{content:attr(data-progress);color:white;background:var(--barstyle);}
      #autosearchtimeout{
        --barstyle:linear-gradient(180deg, transparent, rgba(255,255,255,.4), transparent, transparent);
        position:relative;display:block;width:100%;height:1rem;margin:.5rem 0;
        border-radius:.5rem;background:var(--barstyle);background-color:gray;}
    </style>
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        const fulltime = 10000;
        const tick = 10;
        var start = Date.now();

        var bar = document.getElementById('autosearchtimeout');
        function progressTick() {
          let current = Date.now() - start;
          if (fulltime - current < 0) {
            current = fulltime;
          }
          let prog = ((fulltime - current) * 100) / fulltime;
          bar.dataset.progress = (current === fulltime ? 'reload'
                  : Math.floor((fulltime - current) / 1000 + 1) + ' sec');
          bar.style.setProperty('--progress', prog + '%');
          if (current < fulltime) {
            setTimeout(progressTick, tick);
          } else {
            console.log(bar);
            bar.parentNode.submit();
          }
        }
        if (bar) {
          setTimeout(progressTick, tick);
        }
      });
    </script>
    <?php
  }

  function writeMainContent() {
    global $title;
    //TODO: Filmdaten suchen
    $query = filter_input(INPUT_GET, 'query');
    $doSearch = !empty($query);
    if ($doSearch) {
      echo '<div class="searchlog">';
      if (preg_match('/tt\d+/', $query)) {
        $data = queryData(['!', $query]);
      } else {
        // $GLOBALS['scanTitles'] = true;
        // $data1 = queryData(['?', $query]);
        // $GLOBALS['scanTitles'] = false;
        $data = queryData(['?', $query]);
        // $data = array_merge($data1, $data2);
      }
      echo '</div>';
      echo '<ol class="searchresult">';
      usort($data, function($a, $b) {
        return strcasecmp($a->primaryTitle, $b->primaryTitle);
      });
      foreach ($data as $item) {
        if (property_exists($item, 'basics')) {
          $item = $item->basics;
        }
        echo '<li><a href="?' . buildQuery([
            'title' => $item->tconst,
            'file' => $title
        ]) . '">' . $item->primaryTitle . ' (' . $item->startYear . ')</a>';
        ?>
        (<a target="imdb" href="https://www.imdb.com/title/<?php echo $item->tconst ?>/">IMDB</a>)
        <?php
        echo '</li>';
      }
      echo '</ol>';
    }
    if (empty($query)) {
      $query = preg_filter('/(.+) \(.*/', '$1', $title);
    }
    if (empty($query)) {
      $query = $title;
    }
    ?>
    <div>
      <form method="GET">
        <input type="hidden" name="ext" value="<?php echo filter_input(INPUT_GET, 'ext'); ?>">
        <input type="hidden" name="path" value="<?php echo getRelativePath('.'); ?>">
        <input type="hidden" name="title" value="<?php echo htmlspecialchars($title); ?>">
        <label>Suche <input name="query" size="50" value="<?php echo htmlspecialchars($query); ?>"></label>
        <input type="submit" value="Erneut suchen">
        <?php
        if (!$doSearch) {
          ?><div id="autosearchtimeout" style="--progress:0%" data-progress=""></div><?php
        }else{
          ?><div><a target="imdb" href="https://www.imdb.com/find?<?php 
            echo http_build_query(['q'=>$query]);
          ?>">IMDB durchsuchen</a></div><?php
        }
        ?>
      </form>
    </div>
    <?php
    //echo '<pre>';print_r($data);echo '</pre>';
  }

}

class ViewLists extends PageContent {
  function __construct(){
    parent::__construct();
    $this->listname = filter_input(INPUT_GET, 'list');
  }
  
  function writeHeadContent(){
    ?><h1>Filmliste - <?php echo $this->listname; ?></h1><?php
  }
  function writeNavigationItems(){
    ?>
      <li><a href="view.php?<?php echo buildQuery([]);?>">Übersicht</a></li>
    <?php
  }
  
  function getItems(&$data, $path){
    //echo ($data===null).'~'.$path[0].'<br>';
    if(!empty($data) && property_exists($data, $path[0])) {
      $prop = $path[0];
      if(count($path)>1){
        return $this->getItems($data->$prop, array_slice($path, 1));
      }else{
        return $data->$prop;
      }
    }
    return false;
  }
  
  function scanFolderWithFilter(...$filters){
    $ext = filter_input(INPUT_GET, 'ext');
    $dir = opendir(getFolderPath());
    $result = array();
    while (false !== ($file = readdir($dir))) {
      $base = basename($file, '.'.($ext?$ext:'mp4'));
      if ($base !== $file &&
          file_exists(getFolderPath().'/'.$base.'.json')) {
        //echo getFolderPath().'/'.$base.'.json'.'<br>';
        $data = json_decode(file_get_contents(getFolderPath().'/'.$base.'.json'));
        foreach($data as $entry){
          $result[$entry->basics->tconst]['file']=$base;
          //$result[$entry->basics->tconst]['basics']=$entry->basics;
          foreach($filters as $filter){
            $items = $this->getItems($entry, explode('/', $filter));
            // echo $entry->basics->tconst.' '.$filter.': '
              // .htmlspecialchars(print_r($items,true)).'<br>';
            if(!empty($items)){
              $result[$entry->basics->tconst][$filter]=$items;
            }
          }
        }
        unset($data);
      }
    }
    return $result;
  }
  
  function writeHeaderContent(){
    parent::writeHeaderContent();
    ?><style>
dl{display:flex;flex-wrap:wrap}
dt{
  display:inline-flex;justify-content:center;align-items:center;
  margin:1px .1em;width:10em;height:3rem;
  line-height:1rem;font-size:.9rem;text-align:center;
  border:1px solid blue;
  box-sizing: border-box;
}
dt+dd{
  display:none;order:1;
}
dt.group{
  order:-2 !important;
  flex-basis: 100%;
  font-size:1.8rem;
  font-weight:bold;
}
dt.group+dd{
  display:block;
  order:-1 !important;
  flex-basis: 100%;
}
    </style><script>
    lastSelectedGroup = null;
    function toggleView(evt){
      if(lastSelectedGroup){
        if(lastSelectedGroup!=evt.target){
          lastSelectedGroup.classList.toggle('group');
        }
      }
      evt.target.classList.toggle('group');
      if(evt.target.classList.contains('group')){
        lastSelectedGroup=evt.target;
      }else{
        lastSelectedGroup=null;
      }
    }
    </script><?php
  }
  
  function writeMainContent(){
    $methodname = 'writeList'.$this->listname;
    if(method_exists($this, $methodname)) {
      call_user_func([$this, $methodname]);
    } else {
      $error = new PageError();
      $error->writeMainContent();
    }
  }
  
  function writeListacting(){
    $this->writePersonsList("crew/actor", "crew/actress");
  }
  function writeListdirecting(){
    $this->writePersonsList("crew/director");
  }
  function writeListproducing(){
    $this->writePersonsList("crew/producer");
  }
  function writeListcomposing(){
    $this->writePersonsList("crew/composer");
  }
  
  function writePersonsList(...$filters){
    $entries = $this->scanFolderWithFilter(...$filters);
    
    $persons=array();
    foreach($entries as $entry){
      $file = $entry['file'];
      foreach($entry as $filter){
        if(gettype($filter)==='array'){
          foreach($filter as $person){
            $persons[$person->primaryName][]=$entry['file'];
          }
        }
      }
    }
    
    uksort($persons, function($a,$b){
      $na = array_pop(explode(' ',$a));
      $nb = array_pop(explode(' ',$b));
      return strcasecmp($na,$nb);
    });
    $this->writeContent($persons);
  }

  function writeListgenre(){
    echo 'list of genres';
    $entries = $this->scanFolderWithFilter("basics/genres");
    $genres = array();
    foreach($entries as $entry){
      foreach(explode(',', $entry["basics/genres"]) as $genre){
        $genres[$genre][]=$entry['file'];
      }
    }
    ksort($genres);
    $this->writeContent($genres);
  }
  
  function writeContent($data){
    echo '<dl class="movies" style="--poster:url(view.jpg)">';
    foreach($data as $caption=>$list) {
      echo '<dt class="" onclick="toggleView(event);" style="order:';
      echo (1000-count($list));
      echo '">'.$caption.'</dt>';
      echo '<dd><ul>';
      foreach($list as $file){
        Overview::writeListItem(getFolderPath(), $file.'.'.($ext?$ext:'mp4'));
      }
      echo '</ul></dd>';
    }
    echo '</dl>';
  }
}

class PageError extends PageContent {
  function writeMainContent() {
    ?>
    <p>
      Es ist ein Fehler aufgetreten.
      Mit den angegebenen Parametern konnte nicht ermittelt werden,
      was genau angezeigt werden sollte.
    </p>
    <p>
      <a href="view.php?<?php echo buildQuery([]);?>">zur Übersicht</a>
    </p>
    <?php
  }
}

if (false === serveStaticFiles()) {
  return false;
}
logRequest();

//if (filter_input(INPUT_SERVER, 'REQUEST_URI') === '/info.php') {
//  phpinfo();
//  return;
//}

if (!empty($file = filter_input(INPUT_POST, 'file'))) {
  uploadPosterImage();
  return;
}

$q=array();
parse_str(filter_input(INPUT_SERVER, 'QUERY_STRING'), $q);
//print_r($q);

$file = filter_input(INPUT_GET, 'file');
$title = filter_input(INPUT_GET, 'title');
//$query = filter_input(INPUT_GET, 'query');
unset($q['ext']);

if (!empty($file)) {
  if (!empty($title)) {
    saveJsonData(getFolderPath(), $file, $title);
    return;
  }
}

if (empty($q)) {
//----- ----- ----- -----  U E B E R S I C H T  ----- ----- ----- -----
  $page = new Overview();
} else if (!empty($file)) {
//----- ----- ----- -----  D E T A I L S  ----- ----- ----- -----
  $page = new Details();
} else if (!empty($title)) {
//----- ----- ----- -----  S U C H E  ----- ----- ----- -----
  $page = new Search();
} else if (!empty(filter_input(INPUT_GET, 'list'))) {
//----- ----- ----- -----  L I S T E N  ----- ----- ----- -----
  $page = new ViewLists();
} else if (!empty(filter_input(INPUT_GET, 'path'))){
  $page = new Overview();
} else {
//----- ----- ----- -----  F E H L E R  ----- ----- ----- -----
  $page = new PageError();
}

//phpinfo();
//return;
function writeCommonCSS() {
  ?>
  <style>
    body{font-family:Segoe UI,sans-serif;}
    footer{margin-top:1rem;border-top:1px solid gray;}
    a{color:inherit;}
    :root{--poster:url(view.jpg);}
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
      margin: .5em;
      padding: .5em;
      background-color: #F0F0F0;
    }
    .movies .missing{color:darksalmon;}
    footer>div{display:inline;}
    footer>div:not(:first-child)::before{content:'-';}
  </style>
  <?php
}
?>
<!DOCTYPE html>
<html>
  <head>
    <title>Local IMDB View</title>
    <?php $page->writeHeaderContent(); ?>    
  </head>
  <body>
    <header>
      <?php $page->writeHeadContent(); ?>
    </header>
    <nav>
      <ul>
        <?php $page->writeNavigationItems(); ?>
      </ul>
    </nav>
    <main>
      <?php $page->writeMainContent(); ?>
    </main>
    <footer>
      <div>
      IMDB&reg; Dataset Viewer
      </div><div>
      &copy; 2021 Jens Hofschröer
      </div><div>
      <a href="https://github.com/nigjo/imdb-datasets"
      >View on Github <img alt="Octocat" src="GitHub-Mark-32px.png" style="height:.9em;"
      ></a>
      </div>
    </footer>
  </body>
</html>