const query = new URLSearchParams(location.search);
Promise.all([
  fetch('fileinfo.php?' + query).then(r => {
    if (r.ok) {
      return r.json();
    }
    throw r;
  }),
  new Promise((ok, no) => {
    if (document.readyState !== 'loading')
      ok();
    else {
      document.addEventListener('DOMContentLoaded', ok);
    }
  })
]).then(mix => {
  return mix[0];
}).then(response => {

  updatePath();

  if ('ok' !== response.status) {
    writeError(response.message);
    return;
  }
  if ('/' in response.data) {
    response.data['/'].sort(
            (a, b) => a.localeCompare(b, 'de', {'sensitivity': 'base'})
    );
    writeNav(response.data['/']);
    delete response.data['/'];
  } else {
    writeNav([]);
  }

  if (query.has('file')) {
    const fileid = query.get('file');
    let found = false;
    for (var i = 0, max = response.data['*filecount']; i < max; i++) {
      const info = response.data[i];
      const mfile = info[info['/movie']];
      if (mfile === fileid) {
        found = true;
        writeDetails(info);
        break;
      }
    }
    if (!found) {
      writeError("Keine Daten zu '" + query.get('file') + "' gefunden.");
    }
  } else {
    writeOverview(response.data);
  }
});

function updatePath(suffix = null) {
  let path = '/';
  if (query.has('path')) {
    path += query.get('path');
  }
  if (suffix) {
    path += ' - ' + suffix;
  }
  document.getElementById('currentpath').textContent = path;
}

function writeError(message) {
  let info = document.createElement('p');
  info.className = 'error';
  info.textContent = message;
  document.querySelector('main').replaceChildren(info);
}

function writeNav(navdata) {
  let root = document.createElement('ul');

  if (query.has('path')) {
    let item = document.createElement('li');
    let link = document.createElement('a');
    let path = query.get('path');
    let parts = path.split('/');
    parts.pop();
    let target = new URLSearchParams();
    if (parts.length > 0) {
      target.set('path', parts.join('/'));
    }
    link.href = '?' + target;
    link.textContent = 'zurück';
    item.append(link);
    root.append(item);
  }

  for (var entry of navdata) {
    let item = document.createElement('li');
    let link = document.createElement('a');
    let target = new URLSearchParams();
    target.set('path', query.has('path')
            ? query.get('path') + '/' + entry
            : entry
            );
    link.href = '?' + target;
    link.textContent = entry;
    item.append(link);
    root.append(item);
  }
  document.querySelector('nav').replaceChildren(root);
}

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
    poster.alt = 'Filmposter';
    if (num > 20) {
      poster.setAttribute('loading', 'lazy');
    }
    if ('jpg' in files) {
      poster.src = query.has('path')
              ? query.get('path') + '/' + files['jpg']
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
    div.title = "IMDB Data";
    if (!('json' in files)) {
      block.classList.add('nodbdata');
      div.textContent = '\u24BE'; // (I) = "IMDB"
      div.title += " missing";
      //TODO: do search
      posterblock.onclick = () => {
        alert('search not implemented, yet');
      };
    } else {
      div.textContent = '\uD83C\uDD58'; // (I) = "IMDB"
      posterblock.onclick = () => {
        const target = new URLSearchParams(query);
        target.set('file', files[files['/movie']]);
        //writeDetails(files);
        location.href = '?' + target;
      };
    }
    infoblock.append(div);
    div = document.createElement('div');
    div.dataset.src = 'nfo';
    div.title = "Jellyfin Data";
    if (!('nfo' in files)) {
      block.classList.add('nonfo');
      div.textContent = '\u24BF'; // (J) = "Jellyfin"
      div.title += " missing";
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

function writeDetails(data) {
  document.body.classList.add('loading');
  console.debug('DETAILS', data);
  const main = document.createDocumentFragment();

  const posterblock = document.createElement('div');
  posterblock.className = 'poster';
  const poster = document.createElement('img');
  poster.alt = 'Filmposter';
  if ('jpg' in data) {
    poster.src = query.has('path')
            ? query.get('path') + '/' + data['jpg']
            : data['jpg'];
  } else {
    poster.src = 'view.jpg';
  }
  posterblock.append(poster);
  main.append(posterblock);

  const detailsblock = document.createElement('div');
  detailsblock.className = 'moviedetails';
  main.append(detailsblock);
  Promise.all([
    new Promise((ok, no) => {
      if ('json' in data) {
        return fetch(query.get('path') + '/' + data['json']).then(r => {
          if (r.ok)
            return r.json();
          console.warn(data['json'], r);
          return {};
        }).then(loaded => {
          if (Array.isArray(loaded)) {
            return loaded[0];
          }
          if ('version' in loaded) {
            if (loaded['version'] !== 2) {
              return {};
            }
            return loaded['imdb'][0];
          }
        }).then(ok);
      } else {
        ok({});
      }
    }),
    new Promise((ok, no) => {
      if ('nfo' in data) {
        return fetch(query.get('path') + '/' + data['nfo']).then(r => {
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
//        }).then(loaded => {
//          if (loaded) {
//            let imdb = loaded.evaluate('/movie/imdbid', loaded, null,
//                    XPathResult.STRING_TYPE, null);
//            console.debug('XML', loaded, imdb);
//            if (imdb && imdb.stringValue)
//            {
//              return loaded;
//            }
//          } else {
//            console.debug('xml', loaded);
//          }
//          return null;
        }).then(ok);
      } else {
        ok(null);
      }
    })
  ]).then(infos => {
    document.body.classList.remove('loading');
    console.debug('INFOS', infos);

    const imdb = infos[0];
    const nfo = infos[1];

    const filterKodiName = (filename) => {
      let kodiFilename = filename;
      kodiFilename = kodiFilename.replace(': ', ' - ');
      kodiFilename = kodiFilename.replace(':', '-');
      kodiFilename = kodiFilename.replace(/([^\w\s\d\-_~,;\[\]\(\).])/gu, '');
      return kodiFilename;
    };

    let kodiFilename = filterKodiName(data['/title']);

    const addDetail = (list, term, detail) => {
      console.debug('DETAIL', term, detail);
      const defterm = document.createElement('dt');
      defterm.textContent = term;
      list.append(defterm);
      const defdesc = document.createElement('dd');
      if (Array.isArray(detail)) {
        defdesc.replaceChildren(...detail
                .map((item, idx) => {
                  if (Array.isArray(item)) {
                    let span = document.createElement('span');
                    span.className = 'job';
                    span.textContent = ' (' + item[1] + ')';
                    if (idx > 0) {
                      return [
                        document.createTextNode(', '),
                        document.createTextNode(item[0]),
                        span];
                    } else {
                      return [document.createTextNode(item[0]), span];
                    }
                  } else {
                    if (idx > 0) {
                      return [
                        document.createTextNode(', '),
                        document.createTextNode(item)];
                    } else {
                      return document.createTextNode(item);
                    }
                  }
                })
                .flat()
                );
      } else {
        defdesc.textContent = detail;
      }
      list.append(defdesc);
    };
    const list = document.createElement('dl');
    detailsblock.append(list);
    if ('basics' in imdb) {
      addDetail(list, 'Erscheinungsjahr', imdb['basics']['startYear']);
      addDetail(list, 'Laufzeit', imdb['basics']['runtimeMinutes'] + ' min');
      addDetail(list, 'Genre', imdb['basics']['genres']);
      addDetail(list, 'Identifier', imdb['basics']['tconst']);

      let title = imdb['basics']['primaryTitle'];
      let deFound = false;
      let someFound = false;
      for (let aka of imdb['aka']) {
        if (aka['region'] === 'DE') {
          if (aka['types'] === 'imdbDisplay') {
            title = aka['title'];
            break;
          }
          if (aka['types'] === 'short title' || aka['types'] === 'promotional title') {
            if (!deFound) {
              title = aka['title'];
            }
          } else {
            title = aka['title'];
          }
          deFound = true;
          someFound = true;
        } else if (aka['region'] === 'XWG') {
          if (!deFound) {
            title = aka['title'];
            someFound = true;
          }
        } else if (aka['region'] === 'AT') {
          if (!deFound) {
            title = aka['title'];
            someFound = true;
          }
        } else if (aka['region'] === 'XWW') {
          if (!someFound) {
            title = aka['title'];
          }
        }
      }
      updatePath(title);

      kodiFilename = filterKodiName(title);
      kodiFilename += ' (' + imdb['basics']['startYear'] + ')';
      kodiFilename += ' [imdbid-' + imdb['basics']['tconst'] + ']';
    }

    const addListOfNames = (list, term, data, jobs = []) => {
      const idJobs = new Map(jobs
              .filter(i => i['job'] !== '\\N')
              .map(i => [i['nconst'], i['job']])
              );
      console.debug('JOBS', jobs, idJobs);
      const names = data
              .map(i => {
                if (idJobs.has(i['nconst'])) {
                  return [i['primaryName'], idJobs.get(i['nconst'])];
                } else {
                  return i['primaryName'];
                }
              });
      addDetail(list, term, names);
    };

    if ('directors' in imdb) {
      if ('crew' in imdb && 'director' in imdb['crew']) {
        addListOfNames(list, 'Regie', imdb['directors'], imdb['crew']['director']);
      } else {
        addListOfNames(list, 'Regie', imdb['directors']);
      }
    }
    if ('writers' in imdb) {
      if ('crew' in imdb && 'writer' in imdb['crew']) {
        addListOfNames(list, 'Drehbuch', imdb['writers'], imdb['crew']['writer']);
      } else {
        addListOfNames(list, 'Drehbuch', imdb['writers']);
      }
    }

    if (nfo) {
      let result;
      result = nfo.evaluate('/movie/imdbid', nfo, null,
              XPathResult.STRING_TYPE, null);
      if (result.stringValue) {
        addDetail(list, "IMDB-ID", result.stringValue);
      }
      result = nfo.evaluate('/movie/mpaa', nfo, null,
              XPathResult.STRING_TYPE, null);
      if (result.stringValue) {
        addDetail(list, "Freigabe", result.stringValue);
      }
      result = nfo.evaluate('/movie/fileinfo/streamdetails/video/height', nfo, null,
              XPathResult.STRING_TYPE, null);
      if (result.stringValue) {
        addDetail(list, "Auflösung", result.stringValue + 'p');
        kodiFilename += ' - ' + result.stringValue + 'p';
      }
    }

    kodiFilename += '.' + data['/movie'];
    // Remove any runs of periods (thanks falstro!)
    kodiFilename = kodiFilename.replace(/([\.]{2,})/g, '');
    addDetail(list, 'Kodi Bezeichner', kodiFilename);
  });

  document.querySelector('main').replaceChildren(main);
  document.querySelector('main').className = 'details';
}