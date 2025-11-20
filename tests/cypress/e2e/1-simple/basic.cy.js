/* global beforeEach, cy, describe, it */

describe('page loads as admin', () => {
    beforeEach(() => {
        cy.loginAdmin();
    });
    [
        "/",
        "/artist.php",
        "/better.php",
        "/blog.php",
        "/bonus.php",
        "/bookmarks.php",
        "/comments.php",
        "/contest.php",
        "/collages.php",
        "/donate.php",
        "/forums.php",
        "/inbox.php",
        "/index.php",
        "/locked.php",
        "/logchecker.php",
        "/login.php?action=recover",
        "/reports.php",
        "/reportsv2.php",
        "/requests.php",
        "/rules.php",
        "/staff.php",
        "/staffpm.php",
        "/stats.php",
        "/tools.php",
        "/tools.php?action=analysis_list",
        "/tools.php?action=privilege-edit&id=15",
        "/top10.php",
        "/torrents.php",
        "/user.php",
        "/user.php?id=1",
        "/user.php?action=edit&id=1",
        "/user.php?action=invite",
        "/user.php?action=notify",
        "/user.php?action=search&search=aaa",
        "/userhistory.php?action=subscriptions",
        "/userhistory.php?action=posts",
        "/wiki.php",
    ].forEach((url) => {
        it(`should have a footer: ${url}`, () => {
            cy.intercept(url).as('pageLoad' + url)
            cy.visit(url, {failOnStatusCode: false});
            cy.wait('@pageLoad' + url).its('response.statusCode').should('below', 400)
            cy.ensureFooter();
        })
    })
})
