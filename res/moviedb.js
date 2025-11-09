import * as dbbasics from './moviedb_basics.js';
import {text, registerText} from './moviedb_text.js';

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
  if ('/' in response.data) {
    response.data['/'].sort(
            (a, b) => a.localeCompare(b, 'de', {'sensitivity': 'base'})
    );
    writeNav(response.data['/']);
    delete response.data['/'];
  } else {
    writeNav([]);
  }

  if (dbbasics.query.has('file')) {
    const fileid = dbbasics.query.get('file');
    let found = false;
    for (var i = 0, max = response.data['*filecount']; i < max; i++) {
      const info = response.data[i];
      const mfile = info[info['/movie']];
      if (mfile === fileid) {
        found = true;
        if('json' in info)
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
});

registerText({
  'de': {
    nav_folder_up: 'Elternverzeichnis'
  }
});


function writeNav(navdata) {
  let root = document.createElement('ul');

  if (dbbasics.query.has('path')) {
    let item = document.createElement('li');
    let link = document.createElement('a');
    let path = dbbasics.query.get('path');
    let parts = path.split('/');
    parts.pop();
    let target = new URLSearchParams();
    if (parts.length > 0) {
      target.set('path', parts.join('/'));
    }
    link.href = '?' + target;
    link.textContent = text('nav_folder_up');
    item.append(link);
    root.append(item);
  }

  for (var entry of navdata) {
    let item = document.createElement('li');
    let link = document.createElement('a');
    let target = new URLSearchParams();
    target.set('path', dbbasics.query.has('path')
            ? dbbasics.query.get('path') + '/' + entry
            : entry
            );
    link.href = '?' + target;
    link.textContent = entry;
    item.append(link);
    root.append(item);
  }
  document.querySelector('nav').replaceChildren(root);
}
