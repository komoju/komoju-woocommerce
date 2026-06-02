const { defineConfig } = require('cypress')

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
    KOMOJU_SECRET_KEY: process.env.KOMOJU_SECRET_KEY || '',
    KOMOJU_PUBLISHABLE_KEY: process.env.KOMOJU_PUBLISHABLE_KEY || '',
  },
  retries: {
    runMode: 2,
    openMode: 0,
  }
})
