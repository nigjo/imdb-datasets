
export const query = new URLSearchParams(location.search);

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
