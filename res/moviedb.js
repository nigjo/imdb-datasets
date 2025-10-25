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

  if(query.has('file')){
    writeDetails(response.data[query.get('file')]);
  }else{
    writeOverview(response.data);
  }
});

function updatePath() {
  if (query.has('path')) {
    document.getElementById('currentpath').textContent = '/' + query.get('path');
  } else {
    document.getElementById('currentpath').textContent = '/';
  }
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
  console.log(data);
  let num = data['*filecount'];
  const main = document.createDocumentFragment();
  var sorted = [];
  for (var i = 0; i < num; i++) {
    const fileinfo = data[i];
    const removals = ['the','a','der','die','das','ein'];
    const toremove = new RegExp('^('+removals.join('|')+') ');
    var sortkey = fileinfo['/title'].toLowerCase();
    sortkey = sortkey.replace(toremove,'');
    sortkey = 'm_'+sortkey.replace(/[^\p{Lowercase}\p{digit}]/gv,'');
    sorted[sortkey] = fileinfo;
    sorted[i] = sortkey;
  }
  //console.log(sorted.join('#'));
  sorted.sort();

  for (var i = 0; i < num; i++) {
    const files = sorted[sorted[i]];
    if(!files){
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
    div.dataset.src='db';
    div.title = "IMDB Data";
    if (!('json' in files)){
      block.classList.add('nodbdata');
      div.textContent = '\u24BE'; // (I) = "IMDB"
      div.title += " missing";
    }else{
      div.textContent = '\uD83C\uDD58'; // (I) = "IMDB"
    }
    infoblock.append(div);
    div = document.createElement('div');
    div.dataset.src='nfo';
    div.title = "Jellyfin Data";
    if (!('nfo' in files)){
      block.classList.add('nonfo');
      div.textContent = '\u24BF'; // (J) = "Jellyfin"
      div.title += " missing";
    }else{
      div.textContent = '\uD83C\uDD59'; // (J) = "Jellyfin"
    }
    infoblock.append(div);
    div = document.createElement('div');
    div.dataset.src='file';
    switch(files['/movie']){
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
    if ('avi' in files){
      block.classList.add('legacyfmt');
    }

    main.append(block);
  }
  document.querySelector('main').replaceChildren(main);
  document.querySelector('main').className = 'overview';
}