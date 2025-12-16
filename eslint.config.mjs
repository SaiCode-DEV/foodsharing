import clientConfig from './client/eslint.config.mjs'

// Re-export the client config so the base path is the repo root while plugins
// and compat still resolve from the client directory.
export default clientConfig
