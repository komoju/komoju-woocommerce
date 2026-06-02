const { defineConfig } = require('cypress')
const path = require('path')

// Load cypress.env.json if it exists (for local dev)
let localEnv = {}
try {
  localEnv = require(path.resolve(__dirname, 'cypress.env.json'))
} catch (e) {
  // File doesn't exist, rely on process.env
}

module.exports = defineConfig({
  chromeWebSecurity: false,
  video: false,
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./cypress/plugins/index.js')(on, config)
    },
    baseUrl: 'http://localhost:8000',
    defaultCommandTimeout: 6000
  },
  env: {
    KOMOJU_SECRET_KEY: process.env.KOMOJU_SECRET_KEY || localEnv.KOMOJU_SECRET_KEY || '',
    KOMOJU_PUBLISHABLE_KEY: process.env.KOMOJU_PUBLISHABLE_KEY || localEnv.KOMOJU_PUBLISHABLE_KEY || '',
  },
  retries: {
    runMode: 2,
    openMode: 0,
  }
})
