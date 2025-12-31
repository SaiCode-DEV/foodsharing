/* eslint-env node */
module.exports = {
  extends: [
    'eslint:recommended',
    'plugin:@typescript-eslint/strict',
    'plugin:playwright/recommended',
    'prettier'
  ],
  plugins: ['@typescript-eslint'],
  parser: '@typescript-eslint/parser',
  root: true
}
