/* jshint esversion: 9 */

function sseInit(url, cb, id = 'default') {
  window.sseClient = window.sseClient || {};
  window.sseClient[id] = new EventSource(url, { withCredentials: true });
  window.sseClient[id].addEventListener('message', (e) => {
      if (e.data === "[CLOSE]") {
          window.sseClient[id].close();
      } else {
          cb(JSON.parse(e.data));
      }
  });
  window.sseClient[id].addEventListener('error', (e) => {
      console.error(e);
  });
}

/* usage
sseInit('URL', (data) => {
  console.log(data);
}, 'NAME (optional)';
*/
