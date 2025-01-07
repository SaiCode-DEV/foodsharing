const path = require('path')
const clientRoot = path.resolve(__dirname)
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const VueLoaderPlugin = require('vue-loader/lib/plugin')

const plugins = [
  new VueLoaderPlugin(),
]

const production = process.env.NODE_ENV === 'production'

if (production) {
  plugins.push(
    new MiniCssExtractPlugin({
      filename: 'css/[id].[contenthash].css',
      chunkFilename: 'css/[id].[contenthash].css',
    }),
  )
}

module.exports = {
  resolve: {
    extensions: ['.js', '.vue'],
    modules: [
      resolve('node_modules'),
    ],
    alias: {
      fonts: resolve('../fonts'),
      img: resolve('../img'),
      css: resolve('../css'),
      js: resolve('lib'),
      '@': resolve('src'),
      '@php': resolve('../src'),
      '>': resolve('test'),
      '@translations': resolve('../translations'),
    },
    cache: true,
    symlinks: false,
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: [
          /(node_modules)/,
          resolve('lib'), // ignore the old lib/**.js files
        ],
        use: [
          'cache-loader',
          {
            loader: 'thread-loader',
            options: {
              workers: 2,
              workerParallelJobs: 25,
              poolTimeout: 2000,
            },
          },
          {
            loader: 'babel-loader',
            options: {
              presets: [
                [
                  '@babel/preset-env',
                ],
              ],
              cacheDirectory: true,
            },
          },
        ],
      },
      {
        test: /\.vue$/,
        exclude: /(node_modules)/,
        use: [
          'cache-loader',
          'thread-loader',
          'vue-loader',
        ],
      },
      {
        test: /\.css$/,
        use: [
          production ? MiniCssExtractPlugin.loader : 'style-loader',
          {
            loader: 'css-loader',
          },
        ],
      },
      {
        test: /\.scss$/,
        use: [
          production ? MiniCssExtractPlugin.loader : 'style-loader',
          'cache-loader',
          'css-loader',
          'thread-loader',
          'sass-loader',
        ],
      },
      {
        test: /\.yml$/,
        exclude: [
          /(node_modules)/,
        ],
        use: [
          'json-loader',
          'yaml-loader',
        ],
      },
      {
        test: /\.mjs$/,
        include: /node_modules/,
        type: 'javascript/auto',
      },
    ],
  },
  plugins,
}

function resolve (dir) {
  return path.join(clientRoot, dir)
}
