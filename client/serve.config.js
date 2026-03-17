const webpackConfig = require('./webpack.config')

const host = process.env.HOST || 'localhost'

module.exports = {
  ...webpackConfig,
  devServer: {
    host,
    port: 8082,
    hot: true,
    devMiddleware: {
      index: 'index.html',
      publicPath: '/assets/',
      writeToDisk: true,
    },
    static: false,
    allowedHosts: 'all',
    client: {
      overlay: {
        warnings: true,
        errors: true,
      },
      webSocketURL: {
        hostname: host,
        port: 18090, // see docker/docker-compose.dev.yml
      },
    },
  },
}
