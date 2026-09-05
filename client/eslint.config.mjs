import globals from 'globals'
import stylistic from '@stylistic/eslint-plugin'
import parser from 'vue-eslint-parser'
import pluginVue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'
import playwright from 'eslint-plugin-playwright'
import eslintConfigPrettier from 'eslint-config-prettier/flat'

export default [
  {
    linterOptions: {
      reportUnusedDisableDirectives: 'error'
    }
  },
  {
    ignores: [
      '**/node_modules/*',
      '**/test/_compiled.js',
      '**/.cache/*',
      '**/lib/*',
      'vendor/**',
      'docs/**',
      'public/**',
      'tests/e2e/playwright-report/**',
      'websocket/**',
      'assets/**'
    ]
  },
  {
    plugins: {
      '@stylistic': stylistic
    }
  },
  {
    rules: {
      eqeqeq: ['error', 'always', { null: 'ignore' }],
      camelcase: ['error', {
        properties: 'never',
       ignoreGlobals: true
      }],
      'no-new': 'error',
      'no-new-func': 'error',
      'no-new-object': 'error',
      'no-new-symbol': 'error',
      'no-new-wrappers': 'error',
      // TODO Should be really handled by prettier, but that is a follow-up task
      "eol-last": "error",
    }
  },
  ...pluginVue.configs['flat/vue2-recommended'], // TODO: switch to flat/recommended when using Vue 3
  ...tseslint.configs.recommended,
  {
    files: ['src/**/*.{js,vue}', 'client/**/*.{js,vue}'],
    rules: {
      'comma-dangle': ['error', 'always-multiline'],
      '@stylistic/comma-dangle': ['error', 'always-multiline'],
      '@stylistic/object-curly-spacing': ['error', 'always'],
      'vue/no-reserved-component-names': ['warn'],
      'vue/no-v-html': ['error'],
      'vue/v-on-event-hyphenation': ['error', 'always'],
      'vue/max-attributes-per-line': ['error', {
        singleline: 2,
        multiline: {
          max: 1
        }
      }],

      // Overrides
      'vue/multi-word-component-names': ['off'],
      'vue/require-explicit-emits': ['off'],
      '@stylistic/array-bracket-spacing': ['error', 'never'],
      '@stylistic/arrow-spacing': ['error', { before: true, after: true }],
      '@typescript-eslint/no-unused-vars': 'off',
      'vue/no-required-prop-with-default': 'off', // FIXME: This is ugly, but changing code might be fragile...
    },

    languageOptions: {
      globals: globals.browser,
      parser,
      parserOptions: {
        parser: tseslint.parser,
        requireConfigFile: false
      }
    }
  },
  {
    files: ['tests/e2e/**/*.ts'],
    ...playwright.configs['flat/recommended'],
    ...eslintConfigPrettier,
    rules: {
      ...eslintConfigPrettier.rules,
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/no-unused-vars': 'error'
    }
  }
]
