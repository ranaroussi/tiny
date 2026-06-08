/* jshint esversion: 9 */

if (window.navigator.standalone) {
  document.querySelector('html').classList.add('pwa');
}
if ((window.navigator.userAgentData?.platform) == 'macOS' || (window.navigator.platform || '') == 'MacIntel') {
  document.querySelector('html').classList.add('mac');
}
if (window.navigator.userAgentData?.mobile) {
  document.querySelector('html').classList.add('mobile');
}

(function (theme, undefined) {
  theme.getSetting = () => localStorage.getItem('theme') || 'system';
  theme.get = () => localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');

  theme.settings = () => {
    return localStorage.getItem('theme') || 'system';
  };

  theme.force = () => {
    init();
  };

  theme.set = (theme) => {
    const html = document.querySelector('html');
    if (theme === 'dark') {
      localStorage.setItem('theme', 'dark');
      document.querySelector('html').classList.remove('light');
      document.querySelector('html').classList.add('dark');
    } else  if (theme === 'light') {
      localStorage.setItem('theme', 'light');
      document.querySelector('html').classList.remove('dark');
      document.querySelector('html').classList.add('light');
    } else {
      localStorage.setItem('theme', 'system');
      const prefersDarkColorScheme = window && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
      if (prefersDarkColorScheme) {
        html.classList.remove('light');
        html.classList.add('dark');
      } else {
        html.classList.remove('dark');
        html.classList.add('light');
      }
    }

    // init();
  };

  function init() {
    theme.set(localStorage.getItem('theme') || 'system');
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', ({ matches }) => {
      theme.set(localStorage.getItem('theme') || 'system');
    });

    try {
      charting.recolorAll();
    } catch (e) {}
  }

  init();

}((window.theme = window.theme || {})));
