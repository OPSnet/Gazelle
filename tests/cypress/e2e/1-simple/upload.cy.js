describe('uploading torrent', () => {
    beforeEach(() => {
        cy.loginUser();
    })

    it('upload music torrent', () => {
        cy.visit('/upload.php');
        cy.ensureFooter();
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
        cy.ensureFooter();
    })

    it('upload torrent to existing group', () => {
        cy.visit('/upload.php');
        cy.ensureFooter();
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
        cy.ensureFooter();
    })

    it('re-attach log to upload', () => {
        // find torrent id
        cy.visit('/torrents.php?type=uploaded');
        cy.ensureFooter();

        // bunch of callbacks have been flattened below
        cy.get('.group_info').contains('Log (100%)').first()
            .find('a[href*="torrents.php?"][href*="torrentid="]').first()
            .invoke('attr', 'href').then((torrent_url) => {
        let torrent_id = torrent_url.match(/[&?]torrentid=([0-9]+)/)[1];
        cy.visit(torrent_url);
        cy.ensureFooter();
        cy.get(`#torrent_${torrent_id}`).contains('View log').click();
        cy.get(`a[href*="view.php?type=riplog&id=${torrent_id}."]`).first()
            .invoke('attr', 'href').then((log_url) => {
        let log_id = log_url.match(/&id=[0-9]+\.([0-9]+)/)[1];

        // delete existing log
        cy.loginAdmin();
        cy.visit('/');
        cy.window()
            .then((window) => {
                cy.window().should('have.property', 'authkey');
                cy.visit('/torrents.php', {
                    qs: {
                        action: 'deletelog',
                        torrentid: torrent_id,
                        logid: log_id,
                        auth: window.authkey
                    }
                });
            });

        // add log via torrent edit page
        cy.loginUser();
        // verify log was correctly removed
        cy.visit(torrent_url);
        cy.ensureFooter();
        cy.contains('Log (100%)').should('not.exist');
        cy.visit(`/torrents.php?action=edit&id=${torrent_id}`);
        cy.ensureFooter();
        cy.get('#logfile_1').selectFile('tests/cypress/files/valid_log_eac.log');
        cy.get('input[value="Edit torrent"]').click();

        // verify
        cy.visit(torrent_url);
        cy.ensureFooter();
        cy.contains('Log (100%)');
        }); // end log_url

        cy.visit(torrent_url);
        cy.ensureFooter();
        cy.get(`#torrent_${torrent_id}`).contains('View log').click();
        cy.get(`a[href*="view.php?type=riplog&id=${torrent_id}."]`).first()
            .invoke('attr', 'href').then((log_url) => {
        let log_id = log_url.match(/&id=[0-9]+\.([0-9]+)/)[1];

        // delete existing log
        cy.loginAdmin();
        cy.visit('/');
        cy.window()
            .then((window) => {
            cy.window().should('have.property', 'authkey');
            cy.visit('/torrents.php', {qs: {
                    action: 'deletelog',
                    torrentid: torrent_id,
                    logid: log_id,
                    auth: window.authkey
            }});
        });

        // add log via ajax.php?action=add_log
        cy.loginUser();
        // verify log was correctly removed
        cy.visit(torrent_url);
        cy.ensureFooter();
        cy.contains('Log (100%)').should('not.exist');
        // set up api token
        cy.visit('/user.php?action=token&do=generate',
            {method: 'POST', body: {token_name: 'test_reattach_log'}});
        cy.ensureFooter();

        // upload log
        cy.get('.box2 > .pad > strong').invoke('text').then((api_key) => {
        cy.fixture('../files/valid_log_eac.log', 'binary').then( (log_bin) => {
            // File in binary format gets converted to blob so it can be sent as Form data
            const blob = Cypress.Blob.binaryStringToBlob(log_bin, 'application/octet-stream');
            const formData = new FormData();
            formData.append('logfiles[]', blob, 'eac.log');
            cy.request({
                url: '/ajax.php',
                qs: {action: 'add_log', id: torrent_id},
                method: 'POST',
                headers: {authorization: `token ${api_key}`,
                          'content-type': 'multipart/form-data'},
                body: formData
            }).then( (response) => {
                // it's an ArrayBuffer but for some reason instanceof ArrayBuffer is false
                // and its stringifyed version is "{}"
                let body = typeof response.body === 'object' ?
                    new TextDecoder().decode(response.body) : response.body;
                try {
                    body = JSON.parse(body);
                } catch (e) {
                    cy.logCli("bad response body: " + body);
                }
                expect(body).to.have.property('status', 'success');
            });
        })
        // verify
        cy.visit(torrent_url);
        cy.ensureFooter();
        cy.contains('Log (100%)');
        })})});
    })
})
