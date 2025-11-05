import {text, registerText} from './moviedb_text.js';
import * as dbbasics from './moviedb_basics.js';

registerText({
  'de': {
    ov_has_imdb: 'IMDB Daten vorhanden',
    ov_no_imdb: 'keine IMDB Daten',
    ov_has_nfo: 'Jellyfin Daten vorhanden',
    ov_no_nfo: 'keine Jellyfin Daten',
    ov_poster: 'Filmposter'
  }
});

function writeOverview(data) {
  console.log('OVERVIEW', data);
  let num = data['*filecount'];
  const main = document.createDocumentFragment();
  var sorted = [];
  for (var i = 0; i < num; i++) {
    const fileinfo = data[i];
    const removals = ['the', 'a', 'der', 'die', 'das', 'ein'];
    const toremove = new RegExp('^(' + removals.join('|') + ') ');
    var sortkey = fileinfo['/title'].toLowerCase();
    sortkey = sortkey.replace(toremove, '');
    sortkey = 'm_' + sortkey.replace(/[^\p{Lowercase}\p{digit}]/gv, '');
    sorted[sortkey] = fileinfo;
    sorted[i] = sortkey;
  }
  //console.log(sorted.join('#'));
  sorted.sort();

  for (var i = 0; i < num; i++) {
    const files = sorted[sorted[i]];
    if (!files) {
      console.warn(i, sorted[i], files);
      continue;
    }
    const block = document.createElement('div');
    block.className = 'movie';
    const posterblock = document.createElement('div');
    posterblock.className = 'poster';
    const poster = document.createElement('img');
    poster.alt = text('ov_poster');
    if (num > 20) {
      poster.setAttribute('loading', 'lazy');
    }
    if ('jpg' in files) {
      poster.src = dbbasics.query.has('path')
              ? dbbasics.query.get('path') + '/' + files['jpg']
              : files['jpg'];
    } else {
      poster.src = 'view.jpg';
    }
    posterblock.append(poster);
    block.append(posterblock);

    const name = document.createElement('div');
    name.className = 'movietitle';
    let title = ('/title' in files) ? files['/title'] : files[files['/movie']];
    name.textContent = title;
    block.append(name);

    let infoblock = document.createElement('div');
    infoblock.className = 'infos';
    block.append(infoblock);
    let div;
    div = document.createElement('div');
    div.dataset.src = 'db';
    div.title = text('ov_has_imdb');
    if (!('json' in files)) {
      block.classList.add('nodbdata');
      div.textContent = '\u24BE'; // (I) = "IMDB"
      div.title = text('ov_no_imdb');
      //TODO: do search
      posterblock.onclick = () => {
        alert('search not implemented, yet');
      };
    } else {
      div.textContent = '\uD83C\uDD58'; // (I) = "IMDB"
      posterblock.onclick = () => {
        const target = new URLSearchParams(dbbasics.query);
        target.set('file', files[files['/movie']]);
        //writeDetails(files);
        location.href = '?' + target;
      };
    }
    infoblock.append(div);
    div = document.createElement('div');
    div.dataset.src = 'nfo';
    div.title = text('ov_has_nfo');
    if (!('nfo' in files)) {
      block.classList.add('nonfo');
      div.textContent = '\u24BF'; // (J) = "Jellyfin"
      div.title = text('ov_no_nfo');
    } else {
      div.textContent = '\uD83C\uDD59'; // (J) = "Jellyfin"
    }
    infoblock.append(div);
    div = document.createElement('div');
    div.dataset.src = 'file';
    switch (files['/movie']) {
      case 'avi':
        div.textContent = '\uD83C\uDD50'; // (A) = "AVI"
        break;
      case 'mkv':
        div.textContent = '\uD83C\uDD5C'; // (M) = "MKV"
        break;
      case 'mp4':
        div.textContent = '\u278D'; // (4) = "MP4"
        break;
      default:
        div.textContent = '\u229D'; // (-) = "Unknown/Dash"
        break;
    }
    div.title = files['/kind'];
    infoblock.append(div);
    if ('avi' in files) {
      block.classList.add('legacyfmt');
    }

    main.append(block);
  }
  document.querySelector('main').replaceChildren(main);
  document.querySelector('main').className = 'overview';
}

export default writeOverview;
