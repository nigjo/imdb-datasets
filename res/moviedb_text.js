const DEFAULT_LOCALE = 'de';
let current_locale = DEFAULT_LOCALE;
let knownLocales = {};

export function text(key) {
  if (current_locale in knownLocales && key in knownLocales[current_locale]) {
    return knownLocales[current_locale][key];
  }
  if (DEFAULT_LOCALE in knownLocales && key in knownLocales[DEFAULT_LOCALE]) {
    return knownLocales[DEFAULT_LOCALE][key];
  }
  return key;
}

export function registerText(localeData) {
  for (let locale of Object.keys(localeData)) {
    for (let key of Object.keys(localeData[locale])) {
      knownLocales[locale] = knownLocales[locale] || {};
      if (!(key in knownLocales[locale])) {
        knownLocales[locale][key] = localeData[locale][key];
      }
    }
  }
}
