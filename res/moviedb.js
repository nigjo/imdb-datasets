import * as dbbasics from './moviedb_basics.js';
import {text, registerText} from './moviedb_text.js';

const LOGGER = 'MOVIEDB';

function loadPage(pagename, pagedata) {
  import('./moviedb_' + pagename + '.js').then(page => {
    console.debug('DB', 'loaded', page);
    page.default(pagedata);
  });
}

Promise.all([
  fetch('fileinfo.php?' + dbbasics.query).then(r => {
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

  dbbasics.updatePath();

  if ('ok' !== response.status) {
    dbbasics.writeError(response.message);
    return;
  }

  if (dbbasics.query.has('file')) {
    const fileid = dbbasics.query.get('file');
    let found = false;
    for (var i = 0, max = response.data['*filecount']; i < max; i++) {
      const info = response.data[i];
      const mfile = info[info['/movie']];
      if (mfile === fileid) {
        found = true;
        if ('json' in info)
          loadPage('details', info);
        else
          loadPage('search', info);
        break;
      }
    }
    if (!found) {
      dbbasics.writeError("Keine Daten zu '" + dbbasics.query.get('file') + "' gefunden.");
    }
  } else {
    loadPage('overview', response.data);
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

});

registerText({
  'de': {
    nav_folder_up: 'Elternverzeichnis'
  }
});


function writeNav(navdata) {

  if (dbbasics.query.has('path')) {
    let path = dbbasics.query.get('path');
    console.debug(LOGGER, 'path', path);
    if (path !== '') {
      let parts = path.split('/');
      parts.pop();
      console.debug(LOGGER, 'parts', parts);
      const parentLink = {'&': text('nav_folder_up')};
      if (parts.length > 0) {
        parentLink.path = parts.join('/');
      }
      dbbasics.addNav([parentLink]);
    }
  }

  const subs = [];
  for (var entry of navdata) {
    const link = {};
    link.path = dbbasics.query.has('path')
            ? dbbasics.query.get('path') + '/' + entry
            : entry;
    link['&'] = entry;
    subs.push(link);
  }

  if (subs.length > 0) {
    dbbasics.addNav(subs);
  }
}