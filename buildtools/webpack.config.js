const path = require('path');

module.exports = {
  entry: '../app/src/app.js',
  output: {
    path: path.resolve(__dirname, '../html/static/app'),
    filename: 'app.js',
    chunkFilename: (pathData) => {
      // Convert chunk names to lowercase and replace slashes with dashes
      const name = pathData.chunk.name || pathData.chunk.id;
      const lowerName = name.toString().toLowerCase().replace(/\//g, '-');
      return `${lowerName}.[contenthash].js`;
    },
    publicPath: '/static/app/',
    clean: true // Clean the output directory before emit
  },
  resolve: {
    extensions: ['.js', '.jsx'],
    alias: {
      '@': path.resolve(__dirname, '../app/src'),
    }
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-react']
          }
        }
      },
    ]
  }
};
