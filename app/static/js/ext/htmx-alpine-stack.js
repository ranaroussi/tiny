/* ---------------------------------------------------------------------------- */
/* --- we need this to make htmx + alpine work together (back button issue) --- */
window.appScriptWaitInterval = [];
window.$stack = window.$stack || {
    get: (el) => {
        if (typeof el === 'string') {
            if (document.getElementById(el)?._x_dataStack) {
            return document.getElementById(el)?._x_dataStack[0];
            }
            return {};
        }
        return el?._x_dataStack[0];
    },

    markAsUnloaded: (el) => {
        const stack = window.$stack.get(el);
        stack.loaded = false;
    },

    markAsLoaded: (el) => {
        const stack = window.$stack.get(el);
        stack.loaded = true;
    },

    waitUntilLoaded: (func, component, triesLeft = 100) => {
        window.$stack.markAsUnloaded(component);
        window.appScriptWaitInterval[func] = setInterval(() => {
            if (typeof window[func] == 'function') {
                clearInterval(window.appScriptWaitInterval[func]);
                window.$stack.markAsLoaded(component);
            }
            triesLeft--;
            if (triesLeft <= 1) {
                clearInterval(window.appScriptWaitInterval[func]);
            }
        }, 10);
    },

    handleFrom: (form, stack, func = 'submit') => {
        onDocReady(() => {
            if (typeof form == 'string') {
                form = document.querySelector(form);
            }
            stack = window.$stack.get(stack);
            form.addEventListener('submit', function(e) {
                stack[func](e);
            });
            form.addEventListener('htmx:beforeRequest', function(e) {
                stack[func](e);
            });
        });
    },

    init: () => {
        document.addEventListener('alpine:init', () => {
            document.querySelector('template').content.firstElementChild.setAttribute('x-role', 'template');
            onDocReady(() => {
                window.$stack.markAsLoaded('content-wrapper');
            });
        });
        window.addEventListener('htmx:beforeRequest', (req) => {
            const noswap = req.detail.requestConfig.headers['HX-Swap'] == 'none';
            if (noswap) { return; }
            document.querySelectorAll('[x-role="template"]:not([hx-noswap])').forEach((el) => {
                el.remove();
            });
        });

        window.addEventListener('htmx:afterSwap', () => {
            onDocReady(() => {
                const component = $('#content-wrapper');
                if (component) {
                    const func = component.querySelector('template').content.querySelector('[x-data]').getAttribute('x-data');
                    window.$stack.waitUntilLoaded(func, 'content-wrapper', 100);
                }
            });
        });
    }
};
/* ---------------------------------------------------------------------------- */
window.$stack.init();
