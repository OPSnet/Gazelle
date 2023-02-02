describe('uploading torrent', () => {
    let date = new Date();
    let footer = `Site and design © ${date.getFullYear()} Gazelle`;

    beforeEach(() => {
        cy.loginUser();
    })

    it('upload music torrent', () => {
        cy.visit('/upload.php');
        cy.get('#file').selectFile('tests/cypress/files/valid_torrent.torrent')
        cy.get("#categories").select('Music');
        cy.get("#releasetype").select('Album');
        cy.get("#image").type('https://coolimagehost.example.com/a/b/c/image.jpg');
        cy.get('#artistfields a[href="#"]:first').click();
        cy.get('#artist_0').type('Some Artist');
        cy.get('#importance_0').select('Main');
        cy.get('#artist_1').type('Bananarama');
        cy.get('#importance_1').select('Guest');
        cy.get('#title').type('Some Album');
        cy.get('#year').type('2022');
        cy.get('#remaster').click();
        cy.get('#remaster_year').type('2023');
        cy.get('#remaster_title').type('test edition');
        cy.get('#remaster_record_label').type('Cool Test Label!');
        cy.get('#remaster_catalogue_number').type('TEST123');
        cy.get('#media').select('CD');
        cy.get('#format').select('FLAC');
        cy.get('#bitrate').select('Lossless');
        cy.get('#logfile_1').selectFile('tests/cypress/files/valid_log_eac.log')
        cy.get('#tags').type('test, rock, some.stuff');
        cy.get('#album_desc').type('test album description with some text to not make upload.php sad');
        cy.get('#release_desc').type('test release description');
        cy.get('#post').click();

        // check content before url because cy.location() does not trigger our fail event handler
        cy.contains('[CD / FLAC / Lossless / Log (100%)]');
        cy.location('pathname').should('eq', '/torrents.php');
        cy.location('search').should('match', /\?id=[0-9]+/);
        cy.get('#torrent_details').find('a').its('length').should('be.gte', 5);
        cy.contains('Some Artist');
        cy.contains('2022');
        cy.contains('Cool Test Label!');
        cy.contains(footer);
    })

    it('upload torrent to existing group', () => {
        cy.visit('/upload.php');
        cy.get('#file').selectFile('tests/cypress/files/valid_torrent_2.torrent')
        cy.get("#categories").select('Music');
        cy.get("#releasetype").select('Album');
        cy.get('#artistfields a[href="#"]:first').click();
        cy.get('#artist_0').type('Some Artist');
        cy.get('#importance_0').select('Main');
        cy.get('#title').type('Some Album');
        cy.get('#year').type('2022');
        cy.get('#remaster').click();
        cy.get('#remaster_year').type('2023');
        cy.get('#remaster_title').type('test edition');
        cy.get('#remaster_record_label').type('Cool Test Label!');
        cy.get('#remaster_catalogue_number').type('TEST123');
        cy.get('#media').select('CD');
        cy.get('#format').select('MP3');
        cy.get('#bitrate').select('320');
        cy.get('#tags').type('test, rock, some.stuff');
        cy.get('#album_desc').type('320 test album description with some text to not make upload.php sad');
        cy.get('#release_desc').type('320 test release description');
        cy.get('#post').click();

        // check content before url because cy.location() does not trigger our fail event handler
        cy.contains('[CD / MP3 / 320]');
        cy.location('pathname').should('eq', '/torrents.php');
        cy.location('search').should('match', /\?id=[0-9]+/);
        cy.get('#torrent_details').find('a').its('length').should('be.gte', 5);
        cy.contains('Some Artist');
        cy.contains('2022');
        cy.contains('Cool Test Label!');
        cy.contains('[CD / FLAC / Lossless / Log (100%)]');
        cy.contains(footer);
    })
})
