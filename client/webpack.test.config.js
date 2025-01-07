const glob = require('glob')
const { merge } = require('webpack-merge')
const webpack = require('webpack')

const path = require('path')
const clientRoot = path.resolve(__dirname)

const webpackBase = require('./webpack.base')

module.exports = merge(webpackBase, {
  entry: [
    ...glob.sync(resolve('src/**/*.test.js')),
    ...glob.sync(resolve('../src/**/*.test.js')),
  ],
  mode: 'development',
  devtool: 'inline-cheap-module-source-map',
  output: {
    path: resolve('test'),
    filename: '_compiled.js',
  },
  resolve: {
    alias: {
      sinon: path.resolve(__dirname, 'node_modules/sinon/pkg/sinon-esm.js'),
    },
  },
  plugins: [
    new webpack.ProvidePlugin({
      // In webpack 5 automatic node.js polyfills are removed
      // -> see https://stackoverflow.com/a/65018686/14239942
      process: 'process/browser.js',
    }),
  ],
  module: {
    rules: [
      {
        test: /\.(png|jpe?g|gif|svg)(\?.*)?$/,
        use: 'null-loader',
      },
      {
        test: /\.(woff2?|eot|ttf|otf)(\?.*)?$/,
        use: 'null-loader',
      },
    ],
  },
})

function resolve (dir) {
  return path.join(clientRoot, dir)
}
