// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add('login', (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add('drag', { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add('dismiss', { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite('visit', (originalFn, url, options) => { ... })

const date = new Date();
const footer = `Site and design © ${date.getFullYear()} Gazelle`;

Cypress.Commands.add('login', (username, password) => {
    // https://www.cypress.io/blog/2021/08/04/authenticate-faster-in-tests-cy-session-command/
    cy.session([username, password], () => {
        cy.visit('/login.php');
        cy.get('#username').type(username);
        cy.get('#password').type(password);
        cy.get('#loginform').submit();
        cy.url().should('contain', '/index.php');
    })
})

Cypress.Commands.add('loginAdmin', () => {
    cy.login('admin', 'password');
})

Cypress.Commands.add('loginUser', () => {
    cy.login('user', 'password');
})

Cypress.Commands.add('ensureFooter', () => {
    cy.contains(footer);
})

Cypress.Commands.add('logCli', (msg) => {
    // somehow cypress-terminal-report doesn't pick up cy.log()
    Cypress.log({
        name: "logCli",
        message: msg
    });
})
