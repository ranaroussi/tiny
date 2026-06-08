/* eslint-disable no-empty */
/* eslint-disable no-unused-vars */
/* jshint esversion: 9 */

function $(selector) {
  if (selector.startsWith('#') && selector.indexOf(' ') === -1) {
    return document.querySelector(selector);
  }
  return document.querySelectorAll(selector);
}

function onDocReady(callback, timeout = 0) {
  if (/complete|interactive|loaded/.test(document.readyState)) {
    setTimeout(callback, timeout);
  } else {
    document.addEventListener('DOMContentLoaded', () => {
      setTimeout(callback, timeout);
    });
  }
}

function getCSRFToken() {
  return $('input[name="csrf_token"]')[0]?.value;
}

function withCSRFToken(data) {
  return { ...data, csrf_token: getCSRFToken() };
}
