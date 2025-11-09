import {text, registerText} from './moviedb_text.js';
import * as dbbasics from './moviedb_basics.js';

const LOGGER = 'SEARCH';

function writeSearchPage(data) {
  document.body.classList.add('loading');
  //console.debug(LOGGER, data);
  const main = document.createDocumentFragment();

  const posterblock = document.createElement('div');
  posterblock.className = 'poster';
  const poster = document.createElement('img');
  poster.alt = 'Filmposter';
  if ('jpg' in data) {
    poster.src = dbbasics.query.has('path')
            ? dbbasics.query.get('path') + '/' + data['jpg']
            : data['jpg'];
  } else {
    poster.src = 'view.jpg';
  }
  posterblock.append(poster);
  main.append(posterblock);

  new Promise((ok, no) => {
    if ('nfo' in data) {
      return fetch(dbbasics.query.get('path') + '/' + data['nfo']).then(r => {
        if (r.ok) {
          return r.text();
        }
        console.warn(data['nfo'], r);
        return null;
      }).then(text => {
        if (text) {
          const parser = new DOMParser();
          return parser.parseFromString(text, "application/xml");
        }
      }).then(ok);
    } else {
      ok(null);
    }
  }).then(nfo => {
    //console.debug(LOGGER, nfo);
    let imdbid = null;
    dbbasics.updatePath(data['/title']);

    let searchTerm = data['/title'];
    if (nfo) {
      let result;
      result = nfo.evaluate('/movie/title', nfo, null,
              XPathResult.STRING_TYPE, null);
      if (result.stringValue) {
        searchTerm = result.stringValue;
      }
      result = nfo.evaluate('/movie/imdbid', nfo, null,
              XPathResult.STRING_TYPE, null);
      if (result.stringValue) {
        imdbid = result.stringValue;
      }
    }
    searchTerm = filterFilename(searchTerm);

    console.debug(LOGGER, 'term', searchTerm);
    console.debug(LOGGER, 'imdbid', imdbid);

    const main = document.querySelector('main');

    const searchBlock = document.createElement('div');
//        <form method="GET">
    const formBlock = document.createElement('form');
    formBlock.onsubmit = (e) => e.preventDefault();
//          <input type="hidden" name="ext" value="<?php echo filter_input(INPUT_GET, 'ext'); ?>">
//          <input type="hidden" name="path" value="<?php echo getRelativePath('.'); ?>">
//          <input type="hidden" name="title" value="<?php echo htmlspecialchars($title); ?>">
//          <label>Suche <input name="query" size="50" value="<?php echo htmlspecialchars($query); ?>"></label>
    const labelBlock = document.createElement('label');
    labelBlock.append('Suche ');
    const searchfield = document.createElement('input');
    searchfield.setAttribute('name', 'query');
    searchfield.setAttribute('size', '50');
    if (imdbid) {
      searchfield.setAttribute('value', imdbid);
    } else {
      searchfield.setAttribute('value', searchTerm);
    }
    labelBlock.append(searchfield);
    formBlock.append(labelBlock);
//          <input type="submit" value="Erneut suchen">
    const button = document.createElement('button');
    button.textContent = 'Suche';
    button.onclick = doSearch;
    formBlock.append(button);
//          <?php
//          if (!$doSearch) {//
//            ?><div id="autosearchtimeout" style="--progress:0%" data-progress=""></div><?php
//          } else {
//            ?><div><a target="imdb" href="https://www.imdb.com/find?<?php
//              echo http_build_query(['q' => $query]);
//              ?>">IMDB durchsuchen</a></div><?php
//            }
//            ?>
//        </form>
    searchBlock.append(formBlock);
    main.append(searchBlock);

    setTimeout(() => searchfield.focus(), 0);
  });

  document.querySelector('main').replaceChildren(main);
  document.querySelector('main').className = 'search';
}

/**
 * 
 * @param {String} filename Dateiname
 * @returns {String} Suchstring
 */
function filterFilename(filename) {
  console.debug(LOGGER, 'filter', filename);
  if (filename.includes('_TVOON_DE')) {
    let found = filename.match(/(\w+?)_(\d{2,}(\.\d{2,})+)_.*_(.+)_(\d+)(_TVOON_DE)/);
    if (found) {
      return found[1].replaceAll('_', ' ');
    }
  }
  if (filename.includes('min)')) {
    filename = filename.replace(/ \(.*?min\)$/, '');
  }

  return filename;
}

let searchAllowed = true;
let foundTcons = [];

function doSearch(evt) {
  console.debug(LOGGER, 'suche', evt);
  /** @type String */
  const term = event.target.form.query.value;
  console.debug(LOGGER, term);
//  event.target.textContent = 'stop';
//  event.target.onclick = () => searchAllowed = false;

  //Reset der Ergebnisse
  foundTcons = [];

  let oldResult = document.getElementById('results');
  let resultDiv = document.createElement('div');
  resultDiv.id = 'results';
  if (oldResult)
    oldResult.replaceWith(resultDiv);
  else
    document.querySelector('main').append(resultDiv);

  if (term.match(/^tt\d+$/)) {
    // Direkt die ID suchen
    searchFor("Datenbank ID", 'imdbid', term);
  } else {
    searchFor("Titel", 'title', term).then(() =>
      searchFor("Alternativtitel", 'aka', term)
    ).then(() =>
      searchFor("Im Titel", 'likeTitle', term)
    ).then(() =>
      searchFor("Im Alternativtitel", 'likeAka', term)
    );
  }
}

function searchFor(caption, mode, term) {
  let data = new FormData();
  data.set('mode', mode);
  data.set('query', term);
  const header = document.createElement('h2');
  header.textContent = caption;
  document.getElementById('results').append(header);

  document.getElementById('results').append('Suche läuft...');

  return fetch('./moviedb-query.php', {
    method: 'POST',
    body: data
  }).then(r =>
    r.ok ? r.json() : {}
  ).then(data =>
    showResult(data)
  ).catch(e =>
    document.getElementById('results')
            .lastChild.replaceWith('FEHLER: ' + e)
  );
}

function showResult(data) {
  console.log(LOGGER, 'result', data);

  let resultsDiv = document.getElementById('results');
  if ('results' in data && data.results.length !== 0) {
    const list = document.createElement('dl');
    for (var item of data.results) {

      if (foundTcons.includes(item.tconst)) {
        continue;
      }

      foundTcons.push(item.tconst);

      const term = document.createElement('dt');
      term.textContent = item.primaryTitle;
      if (item.primaryTitle !== item.originalTitle) {
        term.textContent += ' / "' + item.originalTitle + '"';
      }
      if ("akaTitle" in item) {
        term.textContent += ' / "' + item.akaTitle + '"';
      }
      list.append(term);
      const value = document.createElement('dd');

      let defs = [];

      if (item.runtimeMinutes !== '\\N')
        defs.push(item.runtimeMinutes + 'min');
      if (item.startYear !== '\\N')
        defs.push(item.startYear);
      if (item.genres !== '\\N')
        defs.push(item.genres);

//      value.textContent =
//              item.runtimeMinutes + 'min, '
//              + item.startYear
//              + ' -  ' + item.genres;
      const imdblink = document.createElement('a');
      imdblink.href = 'https://www.imdb.com/title/' + item.tconst;
      imdblink.textContent = 'IMDB';
      imdblink.target = 'IMDB';
      defs.push(imdblink);
      
      console.debug(LOGGER, defs);
      const mapped = defs.map((element, idx) => idx > 0 ? [' - ', element] : element);
      console.debug(LOGGER, mapped);
      value.append(...mapped.flat());
      list.append(value);
    }
    resultsDiv.lastChild.replaceWith(list);
  } else {
    const noitems = document.createElement('p');
    noitems.textContent = 'Keine Ergebnisse';
    resultsDiv.lastChild.replaceWith(noitems);
  }
}

export default writeSearchPage;