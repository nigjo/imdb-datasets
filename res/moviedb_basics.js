export const query = new URLSearchParams(location.search);

const LOGGER = 'BASICS';

export function updatePath(suffix = null) {
  let path = '/';
  if (query.has('path')) {
    path += query.get('path');
  }
  if (suffix) {
    path += ' - ' + suffix;
  }
  document.getElementById('currentpath').textContent = path;
}

export function writeError(message) {
  let info = document.createElement('p');
  info.className = 'error';
  info.textContent = message;
  document.querySelector('main').replaceChildren(info);
}

export function addNav(navdata) {
  let root = document.createElement('ul');
  //console.log(LOGGER, 'nav', navdata);

  for (var entry of navdata) {
    console.log(LOGGER, 'nav', entry);
    let item = document.createElement('li');
    let link = document.createElement('a');
    let target = new URLSearchParams();
    let caption = 'Item';
    for (let k of Object.keys(entry)) {
      //console.log(LOGGER, 'nav', k, entry[k]);
      if (k === '&')
        caption = entry[k];
      else
        target.set(k, entry[k]);
    }
    console.log(LOGGER, 'caption', caption);
    link.href = '?' + target;
    link.textContent = caption;
    //console.log(LOGGER, 'link', link);
    item.append(link);
    root.append(item);
  }

  document.querySelector('nav').append(root);
}
