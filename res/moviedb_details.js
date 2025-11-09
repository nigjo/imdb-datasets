import {text, registerText} from './moviedb_text.js';
import * as dbbasics from './moviedb_basics.js';

registerText({
  'de': {
    'details.nav_overview': 'Übersicht',
    'details.term_year': 'Erscheinungsjahr',
    'details.term_length': 'Laufzeit',
    'details.term_genre': 'Genre',
    'details.term_databaseid': 'Datenbank ID',
    'details.term_director': 'Regie',
    'details.term_writer': 'Drehbuch',
    'details.term_imdbid': 'IMDB-ID',
    'details.term_mpaa': 'Altersfreigabe',
    'details.term_quality': 'Videoauflösung',
    'details.term_kodi_name': 'Kodi Bezeichner'
  },
  'en': {
    'details.term_kodi_name': 'Kodi Identifier'
  }
});

function writeDetails(data) {
  document.body.classList.add('loading');
  console.debug('DETAILS', data);
  
  dbbasics.addNav([
    {
      '&': text('details.nav_overview'),
      path: dbbasics.query.get('path')
    }
  ]);
  
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

  const detailsblock = document.createElement('div');
  detailsblock.className = 'moviedetails';
  main.append(detailsblock);
  Promise.all([
    new Promise((ok, no) => {
      if ('json' in data) {
        return fetch(dbbasics.query.get('path') + '/' + data['json']).then(r => {
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
      addDetail(list, text('details.term_year'), imdb['basics']['startYear']);
      addDetail(list, text('details.term_length'), imdb['basics']['runtimeMinutes'] + ' min');
      addDetail(list, text('details.term_genre'), imdb['basics']['genres']);
      addDetail(list, text('details.term_databaseid'), imdb['basics']['tconst']);

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
      dbbasics.updatePath(title);

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
        addListOfNames(list, text('details.term_director'), imdb['directors'], imdb['crew']['director']);
      } else {
        addListOfNames(list, text('details.term_director'), imdb['directors']);
      }
    }
    if ('writers' in imdb) {
      if ('crew' in imdb && 'writer' in imdb['crew']) {
        addListOfNames(list, text('details.term_writer'), imdb['writers'], imdb['crew']['writer']);
      } else {
        addListOfNames(list, text('details.term_writer'), imdb['writers']);
      }
    }

    if (nfo) {
      let result;
      result = nfo.evaluate('/movie/imdbid', nfo, null,
              XPathResult.STRING_TYPE, null);
      if (result.stringValue) {
        addDetail(list, text('details.term_imdbid'), result.stringValue);
      }
      result = nfo.evaluate('/movie/mpaa', nfo, null,
              XPathResult.STRING_TYPE, null);
      if (result.stringValue) {
        addDetail(list, text('details.term_mpaa'), result.stringValue);
      }
      result = nfo.evaluate('/movie/fileinfo/streamdetails/video/height', nfo, null,
              XPathResult.STRING_TYPE, null);
      if (result.stringValue) {
        addDetail(list, text('details.term_quality'), result.stringValue + 'p');
        kodiFilename += ' - ' + result.stringValue + 'p';
      }
    }

    kodiFilename += '.' + data['/movie'];
    // Remove any runs of periods (thanks falstro!)
    kodiFilename = kodiFilename.replace(/([\.]{2,})/g, '');
    addDetail(list, text('details.term_kodi_name'), kodiFilename);
  });

  document.querySelector('main').replaceChildren(main);
  document.querySelector('main').className = 'details';
}

export default writeDetails;
