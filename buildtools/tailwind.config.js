/** @type {import('tailwindcss').Config} */
// const defaultTheme = require('tailwindcss/defaultTheme')

module.exports = {
  // darkMode: 'class',
  theme: {
    extend: {
      animation: {
        // Bounces 5 times 1s equals 5 seconds
        'bounce-short': 'bounce .8s ease-in-out 4.4'
      },
      fontFamily: {
        // sans: ['San Francisco', ...defaultTheme.fontFamily.sans],
      },
      aspectRatio: {
        '2/1': '2 / 1',
        '16/10': '16 / 10',
      },
      colors: {
      },
      transitionProperty: {
        width: 'width',
        height: 'height',
      },
    },
  },
  plugins: [
    // require('@tailwindcss/forms'),
    // require('tailwindcss-textshadow'),
    // require('@tailwindcss/container-queries'),
    // require('preline/plugin'),
  ],
  corePlugins: {
    preflight: true,
  },
}
