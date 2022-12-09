const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    watchForFileChanges: false,
    baseUrl: 'http://localhost/',
    downloadsFolder: 'tests/cypress/downloads',
    fixturesFolder: 'tests/cypress/fixtures',
    screenshotsFolder: 'tests/cypress/screenshots',
    videosFolder: 'tests/cypress/videos',
    supportFile: 'tests/cypress/support/e2e.{js,jsx,ts,tsx}',
    specPattern: 'tests/cypress/e2e/**/*.cy.{js,jsx,ts,tsx}',
    video: false,
    screenshotOnRunFailure: false
  },
});
