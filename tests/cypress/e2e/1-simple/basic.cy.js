describe('page loads as admin', () => {
    /*beforeEach(() => {
        cy.loginAdmin();
    })*/
    let date = new Date();

    [
        "/",
        "/user.php",
        "/forums.php",
        "/upload.php",
        "/collages.php",
        "/top10.php",
        "/rules.php",
        "/wiki.php",
        "/staff.php",
        "/requests.php",
        "/torrents.php"
    ].forEach((url) => {
        beforeEach(() => {
            cy.loginAdmin();
        })
        it(`should have a footer: ${url}`, () => {
            cy.visit(url);
            cy.contains(`Site and design © ${date.getFullYear()} Gazelle`);
        })
    })
})
