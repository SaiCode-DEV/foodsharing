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
          data[entryName] = assets
            .filter(asset => !/\.hot-update\.js(on)?$/i.test(asset.name))
            .map(asset => join(stats.publicPath, asset.name))
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
      swDest: 'sw.js',
      maximumFileSizeToCacheInBytes: 25 * 1024 * 1024, // 25 MB
    }),
  )
}

module.exports = merge(webpackBase, {
  entry: moduleEntries(),
  mode: dev ? 'development' : 'production',
  devtool: dev ? 'eval-cheap-module-source-map' : 'source-map',
  stats: 'minimal',
  watchOptions: {
    ignored: '**/node_modules',
  },
  output: {
    path: assetsPath,
    ...(dev
      ? {
          filename: 'js/[name].[contenthash].js',
          // chunkFilename: 'js/[chunkhash].js', // will be derived from filename
          hotUpdateMainFilename: 'hot/[runtime].[fullhash].hot-update.json',
          hotUpdateChunkFilename: 'hot/[id].[fullhash].hot-update.js',
        }
      : {
          filename: 'js/[name].[fullhash].js',
          // chunkFilename: 'js/[id].[chunkhash].js', // will be derived from filename
        }),
    publicPath: '/assets/',
    globalObject: 'self',
  },
  performance: dev
    ? {
        // Disable performance hints in development
        hints: false,
      }
    : {
        maxEntrypointSize: 512000,
        maxAssetSize: 512000,
        hints: 'warning',
        assetFilter: function (assetFilename) {
          return !assetFilename.endsWith('.map') && !assetFilename.match(/\.(png|jpg|gif|svg)$/)
        },
      },
  cache: {
    type: 'filesystem',
    buildDependencies: {
      config: [__filename],
    },
    compression: 'gzip',
    maxAge: 172800000, // 2 days
  },
  module: {
    rules: [
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
        parallel: true,
        terserOptions: {
          sourceMap: true,
          compress: {
            drop_console: !dev,
            drop_debugger: !dev,
          },
        },
      }),
    ],
    moduleIds: 'deterministic',
    // Avoid a single shared runtime chunk in development —
    // it can cause HMR runtime mismatches when multiple updates
    // are written/read from disk. Use a shared runtime in production.
    runtimeChunk: dev ? false : 'single',
    splitChunks: {
      chunks: 'all',
      maxInitialRequests: 10,
      minSize: 20000,
      maxSize: 250000,
      cacheGroups: {
        vendor: {
          test: /[\\/]node_modules[\\/]/,
          name (module) {
            const packageName = module.context.match(/[\\/]node_modules[\\/](.*?)([\\/]|$)/)[1]
            return `vendor.${packageName.replace('@', '')}`
          },
          priority: -10,
          chunks: 'all',
        },
        common: {
          name: 'common',
          minChunks: 2,
          priority: -20,
          chunks: 'all',
          reuseExistingChunk: true,
        },
      },
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
