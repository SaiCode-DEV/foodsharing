const mkdirp = require('mkdirp')
const { merge } = require('webpack-merge')
const webpackBase = require('./webpack.base')
const { existsSync, writeFileSync } = require('fs')
const BundleAnalyzePlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin
const TerserPlugin = require('terser-webpack-plugin')
const CopyWebpackPlugin = require('copy-webpack-plugin')
const path = require('path')
const clientRoot = path.resolve(__dirname)
const { join, dirname } = require('path')
const glob = require('glob')
const { InjectManifest } = require('workbox-webpack-plugin')

const dev = process.env.NODE_ENV !== 'production'

const assetsPath = resolve('../assets')
const modulesJsonPath = join(assetsPath, 'modules.json')

const plugins = []

if (!dev) {
  plugins.push(
    new BundleAnalyzePlugin({
      analyzerMode: 'static',
      reportFilename: 'bundlesize.html',
      defaultSizes: 'gzip',
      openAnalyzer: false,
      logLevel: 'info',
    }),
  )
}

plugins.push(
  {
    // Writes modules.json which is then loaded by the php app (see src/Utility/WebpackHelper.php).
    // This is how the php app will know if it is a webpack-enabled module or not.
    apply (compiler) {
      compiler.hooks.emit.tapPromise('write-modules', compiler => {
        const stats = compiler.getStats().toJson()
        const data = {}
        for (const [entryName, { assets }] of Object.entries(stats.entrypoints)) {
          data[entryName] = assets.map(asset => join(stats.publicPath, asset.name))
        }
        // We do not emit the data like a proper plugin as we want to create the file when running the dev server too
        const json = `${JSON.stringify(data, null, 2)}`

        if (!existsSync(assetsPath)) {
          mkdirp.sync(assetsPath)
        }
        writeFileSync(modulesJsonPath, json)
        return Promise.resolve()
      })
    },
  },
)

plugins.push(
  new CopyWebpackPlugin({
    patterns: [
      { from: 'node_modules/emoji-picker-element-data/de/cldr/data.json', to: './emoji-picker-element-data/de/data.json' },
    ],
  }),
)

if (!dev) {
  plugins.push(
    new InjectManifest({
      swSrc: './src/serviceWorker.js',
      swDest: path.join(assetsPath, 'sw.js'),
      maximumFileSizeToCacheInBytes: 25 * 1024 * 1024, // 25 MB
    }),
  )
}

module.exports = merge(webpackBase, {
  entry: moduleEntries(),
  mode: dev ? 'development' : 'production',
  devtool: dev ? 'eval-cheap-module-source-map' : 'source-map',
  stats: 'minimal',
  output: {
    path: assetsPath,
    ...(dev
      ? {
          filename: 'js/[name].js',
          chunkFilename: 'js/[chunkhash].js',
        }
      : {
          filename: 'js/[name].[fullhash].js',
          chunkFilename: 'js/[id].[chunkhash].js',
        }),
    publicPath: '/assets/',
  },
  module: {
    rules: [
      {
        enforce: 'pre',
        test: /\.(js|vue)$/,
        exclude: [
          /node_modules/,
          resolve('lib'),
        ],
        loader: 'eslint-loader',
        options: {
          configFile: resolve('package.json'),
        },
      },
      {
        test: /\.(png|jpe?g|gif|svg|mp4|webm)(\?.*)?$/,
        loader: 'url-loader',
        options: {
          limit: 10000,
          name: dev ? 'img/[name].[ext]' : 'img/[name].[contenthash:7].[ext]',
        },
      },
      {
        test: /\.(woff2?|eot|ttf|otf)(\?.*)?$/,
        loader: 'url-loader',
        options: {
          limit: 10000,
          name: dev ? 'fonts/[name].[ext]' : 'fonts/[name].[contenthash:7].[ext]',
        },
      },
    ],
  },
  plugins,
  optimization: {
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          sourceMap: true,
        },
      }),
    ],
    runtimeChunk: 'multiple',
    splitChunks: {
      chunks: 'async',
      name: dev
        ? (module, chunks, cacheGroupKey) => {
            return chunks.map(chunk => chunk.name).join('~')
          }
        : false,
      maxInitialRequests: 6,
    },
  },
})

function resolve (dir) {
  return path.join(clientRoot, dir)
}

function moduleEntries () {
  const basedir = join(__dirname, '../src/Modules')
  return uniq(glob.sync(join(basedir, '*/*.js')).map(filename => {
    return dirname(filename).substring(basedir.length + 1)
  })).reduce((entries, name) => {
    entries[`Modules/${name}`] = join(basedir, name, `${name}.js`)
    return entries
  }, {})
}

function uniq (items) {
  return [...new Set(items)]
}
