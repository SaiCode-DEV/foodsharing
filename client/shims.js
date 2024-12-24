Object.assign(module.exports, convert({

  leaflet: {
    dependencies: [
      'leaflet/dist/leaflet.css',
    ],
  },

  'leaflet.awesome-markers': {
    resolve: require.resolve('leaflet.awesome-markers/dist/leaflet.awesome-markers.js'),
    imports: {
      L: 'leaflet',
    },
    dependencies: [
      require.resolve('leaflet.awesome-markers/dist/leaflet.awesome-markers.css'),
    ],
  },

  'leaflet.markercluster': {
    imports: {
      L: 'leaflet',
    },
    dependencies: [
      require.resolve('leaflet.markercluster/dist/MarkerCluster.css'),
      require.resolve('leaflet.markercluster/dist/MarkerCluster.Default.css'),
    ],
  },
}))

function convert (entries) {
  if (!global._counter) global._counter = 0
  const rules = []
  const aliases = {}

  for (const [name, options] of Object.entries(entries)) {
    const importsLoaderOptions = []

    const {
      resolve,
      disableAMD = false,
      imports = {},
      dependencies = [],
      exports,
    } = options

    const test = resolve || require.resolve(name)

    if (resolve) {
      aliases[name] = resolve
    }

    if (disableAMD) {
      importsLoaderOptions.push('define=>false')
    }

    for (const [k, v] of Object.entries(imports)) {
      importsLoaderOptions.push(`${k}=${v}`)
    }

    for (const dependency of dependencies) {
      importsLoaderOptions.push(`_${global._counter++}=${dependency}`)
    }

    if (exports) {
      rules.push({
        test,
        use: `exports-loader?${exports}`,
      })
    }

    rules.push({
      test,
      use: {
        loader: 'imports-loader',
        options: importsLoaderOptions.join(','),
      },
    })
  }

  return { rules, alias: aliases }
}
