import globals from 'globals'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'
import { FlatCompat } from '@eslint/eslintrc'
import stylistic from '@stylistic/eslint-plugin'
import parser from 'vue-eslint-parser'
import pluginVue from 'eslint-plugin-vue'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const repoRoot = path.join(__dirname, '..')
const requireFromClient = createRequire(path.join(__dirname, 'package.json'))
const compat = new FlatCompat({
  baseDirectory: repoRoot, // ensure globs like src/**/* resolve from repo root
  resolvePluginsRelativeTo: __dirname // keep plugin resolution in client
})

export default [
  {
    ignores: [
      '**/node_modules/*',
      '**/test/_compiled.js',
      '**/.cache/*',
      '**/lib/*',
      'vendor/**',
      'docs/**',
      'public/**',
      'tests/!(e2e)/**',
      'websocket/**',
      'assets/**'
    ]
  },
  {
    plugins: {
      '@stylistic': stylistic
    }
  },
  ...compat.extends(requireFromClient.resolve('eslint-config-standard')),
  ...pluginVue.configs['flat/vue2-recommended'], // TODO: switch to flat/recommended when using Vue 3
  {
    files: ['src/**/*.{js,vue}', 'client/**/*.{js,vue}'],
    rules: {
      'comma-dangle': ['error', 'always-multiline'],
      '@stylistic/comma-dangle': ['error', 'always-multiline'],
      '@stylistic/object-curly-spacing': ['error', 'always'],
      'vue/no-reserved-component-names': ['warn'],
      'vue/no-v-html': ['error'],
      'vue/custom-event-name-casing': ['error', 'kebab-case', {
        ignores: ['bv::hide::tooltip']
      }],

      'vue/v-on-event-hyphenation': ['error', 'always'],

      'vue/max-attributes-per-line': ['error', {
        singleline: 2,

        multiline: {
          max: 1
        }
      }],

      // Overrides until standard is updated
      'vue/multi-word-component-names': ['off'],
      'vue/require-explicit-emits': ['off'],
      '@stylistic/array-bracket-spacing': ['error', 'never'],
      '@stylistic/arrow-spacing': ['error', { before: true, after: true }]
    },

    languageOptions: {
      globals: {
        ...globals.browser
      },

      parser,

      parserOptions: {
        parser: '@babel/eslint-parser',
        requireConfigFile: false
      }
    }
  },
  {
    files: ['client/**/*.test.js'],

    languageOptions: {
      globals: {
        ...globals.mocha
      }
    }
  }
]
