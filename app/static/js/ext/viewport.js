/* jshint esversion: 9 */
window.appViewport = {
    screens: {
        xs: 480,
        sm: 640,
        md: 768,
        lg: 1024,
        xl: 1280,
        '2xl': 1536,
        '3xl': 1920,
        '4xl': 2560,
        '5xl': 3200,
        '6xl': 3840,
    },

    init: () => {
        window.viewPort = {
            width: (window.innerWidth > 0) ? window.innerWidth : appViewport.width,
            height: (window.innerHeight > 0) ? window.innerHeight : appViewport.height,
        };
    },

    get: () => {
        for (const [label, size] of Object.entries(appViewport.screens)) {
            if (window.viewPort.width <= size) return label;
        }
    },

    is: (target) => {
        for (const [label, size] of Object.entries(appViewport.screens)) {
            if (window.viewPort.width <= size && target == label) return true;
        }
        return false;
    },

    matches: (target) => {
        let keys = Object.keys(appViewport.screens);
        for (let i = 0; i < keys.length; i++) {
            if (window.viewPort.width >= appViewport.screens[keys[i]] && keys.slice(i).indexOf(target) < 1) {
                return true;
            }
        }
        return false;
    }
};
window.appViewport.init();
window.onresize = () => {
    setTimeout(appViewport.init, 100);
};
