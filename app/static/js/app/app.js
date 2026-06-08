// // modifier keys
// window.modifierKeys = [];
// window.addEventListener('keydown', (e) => {
//   if (['meta', 'control', 'c', 'enter', 'escape', 'shift', 'alt'].includes(e.key.toLowerCase())) {
//     let key = e.key.toLowerCase();

//     // "super" key
//     if (navigator.userAgent.toLowerCase().indexOf('mac') !== -1) {
//       key = key.replace('meta', 'super');
//     } else if (['win', 'windows', 'linux', 'x11'].includes(navigator.userAgent.toLowerCase())) {
//       key = key.replace('control', 'super');
//     }

//     window.modifierKeys.push(key);
//     setTimeout(() => {
//       window.modifierKeys = window.modifierKeys.filter((item) => item !== key);
//     }, 500);
//   }
// });


// ------------------------------------------------------------
// alpine.js utils
// ------------------------------------------------------------
window.waitInterval = undefined;
async function waitUntil(callback, ms, triesLeft = 100) {
  return new Promise((resolve, reject) => {
    window.waitInterval = setInterval(async () => {
      if (await callback()) {
        clearInterval(window.waitInterval);
        resolve();
      } else if (triesLeft <= 1) {
        clearInterval(window.waitInterval);
        reject();
      }
      triesLeft--;
    }, ms);
  });
}

function dispatchOnNextPage(item, data) {
  localStorage.dispatch = JSON.stringify({
    item,
    data,
  });
}

function getStack(el) {
  if (typeof el === 'string') {
    if (document.getElementById(el)?._x_dataStack) {
      return document.getElementById(el)?._x_dataStack[0];
    }
    return {};
  }
  return el?._x_dataStack[0];
}

// ------------------------------------------------------------

function core() {
  return {
    // darkmode: window.theme && theme.get() == 'dark' || false,
    dispatch: localStorage.dispatch ? JSON.parse(localStorage.dispatch) : null,

    init() {
      if (!isEmpty(this.dispatch)) {
        onDocReady(() => {
          this.$dispatch(this.dispatch.item, this.dispatch.data);
          this.dispatch = null;
        }, 750);
        delete localStorage.dispatch;
      }
    },
  };
}


function get(url, targetAndSelect, select) {
  targetAndSelect = targetAndSelect || '#main';
  select = select || targetAndSelect;
  htmx.ajax('GET', url, {
    swap: 'outerHTML',
    target: targetAndSelect,
    select,
  })
}


/*
document.addEventListener('alpine:init', () => {
  if ('tiny' in window && 'user' in window.tiny) {
    Alpine.store('user', {
      _exists: true,
      ...window.tiny.user,
      can(permissions) {
        if (Array.isArray(permissions)) {
          return permissions.some((permission) => this.org.role === permission);
        } else {
          if (this.org.role === permissions) {
            return true;
          }
        }
        return false;
      },
    });
  } else {
    Alpine.store('user', { _exists: false });
  }
});
*/
