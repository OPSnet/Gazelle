/* global beforeEach, Cypress, cy, describe, expect, it */

describe('artist rich autocomplete', () => {
    const extendedTestingIt = Cypress.env('RICH_AC_EXTENDED_TESTING') ? it : it.skip;
    const queryText = 'te';
    const artistId = 424242;
    const artistName = 'Autocomplete Test Artist';
    const artistUrl = `/artist.php?id=${artistId}`;
    const absoluteArtistUrl = new URL(artistUrl, Cypress.config('baseUrl')).href;
    const redirectArtistId = 424247;
    const redirectArtistName = 'Redirect Alias Typo';
    const redirectPrimaryArtistName = 'Redirect Alias Canonical Artist';
    const discogsPath = '/rules.php?p=upload';
    const absoluteDiscogsUrl = new URL(discogsPath, Cypress.config('baseUrl')).href;
    const inlineImage = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';

    const buildSuggestionHtml = ({
        id,
        name,
        akaPrimaryName = null,
        imageUrl = null,
        tags = [],
        discogsHref = null,
        showAdvancedSnatches = true,
        bio = '',
    }) => `
<div class="collage-artist-entry box2 ${imageUrl ? 'cae-has-image' : 'cae-no-image'} ${(tags.length > 0 || discogsHref) ? 'cae-has-meta' : 'cae-no-meta'} ${bio ? 'cae-has-bio' : 'cae-no-bio'}">
    <a class="cae-row-link" href="/artist.php?id=${id}" aria-label="${name}"></a>
${imageUrl ? `
    <div class="cae-img">
        <img src="${imageUrl}" alt="${name}" width="150" height="150">
    </div>` : ''}
    <div class="cae-copy">
        <div class="cae-name">${name}</div>
${akaPrimaryName ? `
        <div class="cae-aka">aka ${akaPrimaryName}</div>` : ''}
${discogsHref || showAdvancedSnatches ? `
        <span class="cae-advanced-meta">
${discogsHref ? `
            <a class="cae-advanced-item cae-advanced-item-discogs ac-discogs-link external" aria-label="Discogs" title="Discogs" target="_blank" rel="noreferrer noopener" href="${discogsHref}">
                <span class="cae-advanced-label">Discogs</span>
            </a>` : ''}
${showAdvancedSnatches ? `
            <span class="cae-advanced-item cae-advanced-item-snatches">
                <span class="cae-advanced-label">Snatches</span>
                <span class="cae-advanced-value">345</span>
            </span>` : ''}
        </span>` : ''}
${tags.length > 0 ? `
        <div class="cae-meta cae-has-tags ${discogsHref ? 'cae-has-discogs' : 'cae-no-discogs'}">
            <div class="cae-tag tags">
${tags.map((tag) => `                <span class="cae-tag-entry">${tag}</span>`).join('\n')}
            </div>
        </div>` : ''}
${bio ? `
        <div class="cae-bio">
            <div class="cae-bio-content">${bio}</div>
        </div>` : ''}
    </div>
</div>`;

    const mainSuggestion = {
        data: artistId,
        value: artistName,
        html: buildSuggestionHtml({
            id: artistId,
            name: artistName,
            imageUrl: inlineImage,
            tags: ['dream pop', 'shoegaze'],
            discogsHref: discogsPath,
            bio: 'Clicking the row should select the artist while embedded links remain independent.',
        }),
    };

    const redirectSuggestion = {
        data: redirectArtistId,
        value: redirectArtistName,
        html: buildSuggestionHtml({
            id: redirectArtistId,
            name: redirectArtistName,
            akaPrimaryName: redirectPrimaryArtistName,
            tags: ['redirect'],
            bio: 'Redirect alias disambiguation remains visible.',
        }),
    };

    const filteredBioSuggestion = {
        data: 424249,
        value: 'Filtered Bio Artist',
        html: buildSuggestionHtml({
            id: 424249,
            name: 'Filtered Bio Artist',
            tags: ['bio'],
            bio: [
                'Kept opening line.',
                '',
                '<a href="https://www.discogs.com/artist/123-filtered">Discogs</a>',
                '[url=https://example.com/artist]Example[/url]',
                '<p>[url=https://example.com/paragraph]Paragraph URL[/url]</p>',
                '<p>Paragraph [b]bold[/b] text.</p>',
                'Words [b]foo[/b] bar.',
                'Singer [artist]Pink[/artist].',
                'He lives in [url=maine.gov]Maine[/url].',
                'Style [i]lean[/i], [u]underlined[/u], and [color=red]red[/color].',
                '==Compact Header==',
                '== Spaced Header ==',
                '===Mid Header===',
                '====Small Header====',
                '<strong>Rendered</strong> markup with <a href="https://example.com/rendered">rendered link</a>.',
                'Kept closing line with <a href="https://example.com/context">context link</a>.',
            ].join('<br>'),
        }),
    };

    const orderedSuggestions = [
        { data: 424301, value: 'Ranked Beta' },
        { data: 424302, value: 'Ranked Alpha' },
        { data: 424303, value: 'Ranked Gamma' },
    ].map((suggestion) => ({
        ...suggestion,
        html: buildSuggestionHtml({
            id: suggestion.data,
            name: suggestion.value,
            tags: ['ordered'],
            bio: `${suggestion.value} fixture row.`,
        }),
    }));

    const keyboardSuggestions = [
        { data: 111001, value: 'Terra One' },
        { data: 111002, value: 'Terra Two' },
        { data: 111003, value: 'Terra Three' },
    ].map((suggestion) => ({
        ...suggestion,
        html: buildSuggestionHtml({
            id: suggestion.data,
            name: suggestion.value,
            tags: ['keyboard'],
            bio: `${suggestion.value} keyboard fixture row.`,
        }),
    }));

    const manySuggestions = Array.from({ length: 20 }, (_, index) => ({
        data: 550000 + index,
        value: `Telemetry Close Test ${index + 1}`,
        html: buildSuggestionHtml({
            id: 550000 + index,
            name: `Telemetry Close Test ${index + 1}`,
            tags: ['close'],
            bio: `Close regression fixture row ${index + 1}.`,
        }),
    }));

    const stubArtistAutocomplete = (suggestionsFactory = null) => {
        cy.intercept('GET', '**/artist.php?action=autocomplete*', (req) => {
            const raw = req.query.query ?? '';
            const query = Array.isArray(raw) ? raw[0] : String(raw);
            const suggestions = suggestionsFactory
                ? suggestionsFactory(query)
                : (query.length >= 2 ? [mainSuggestion] : []);

            req.reply({
                statusCode: 200,
                body: { query, suggestions },
            });
        }).as('artistAutocomplete');
    };

    const stubArtistPageNavigation = () => {
        cy.intercept('GET', `**/artist.php?id=${artistId}`, {
            statusCode: 200,
            headers: { 'content-type': 'text/html; charset=UTF-8' },
            body: '<!doctype html><title>Artist navigation target</title>',
        }).as('artistPageNavigation');
    };

    const waitForRichAutocompleteReady = ({ timeout = 10000, requireVisible = true } = {}) => {
        const inputSelector = '#artistsearch[data-gazelle-autocomplete="true"]';
        if (requireVisible) {
            cy.get(inputSelector, { timeout }).then(($input) => {
                if (!$input.is(':visible')) {
                    cy.get('#header')
                        .trigger('mouseenter', { force: true })
                        .trigger('mouseover', { force: true });
                }
            });
        }

        let chain = cy.get(inputSelector, { timeout });
        if (requireVisible) {
            chain = chain.should('be.visible');
        }
        chain
            .should('have.attr', 'role', 'combobox')
            .should('have.attr', 'aria-controls');
    };

    const visitIndexAtViewport = (width, height, options = {}) => {
        cy.viewport(width, height);
        cy.visit('/index.php');
        waitForRichAutocompleteReady(options);
    };

    const openArtistAutocomplete = (lookup = queryText, expectedLabel = artistName, options = {}) => {
        const {
            inputOptions = { force: true },
            requireVisibleMenu = true,
            requireVisibleLabel = requireVisibleMenu,
        } = options;

        cy.get('#artistsearch[data-gazelle-autocomplete="true"]')
            .scrollIntoView()
            .clear(inputOptions)
            .type(lookup, inputOptions);
        cy.wait('@artistAutocomplete');

        let menu = cy.get('ul.autocomplete-menu.show');
        menu = requireVisibleMenu ? menu.should('be.visible') : menu.should('exist');
        menu.should('have.class', 'ac-rich');

        const label = cy.contains('ul.autocomplete-menu .dropdown-item', expectedLabel);
        if (requireVisibleLabel) {
            label.should('be.visible');
        } else {
            label.should('exist');
        }
    };

    const rowFor = (artistLabel) => cy.contains('ul.autocomplete-menu .dropdown-item', artistLabel)
        .closest('.dropdown-item')
        .find('.collage-artist-entry');

    const stubBackgroundAnchorClicks = () => cy.window().then((win) => {
        win.__acBackgroundClicks = [];
        cy.stub(win.HTMLAnchorElement.prototype, 'click').callsFake(function() {
            win.__acBackgroundClicks.push({
                href: this.href,
                rel: this.getAttribute('rel') || this.rel,
                target: this.target,
            });
        });
    });

    beforeEach(() => {
        cy.loginAdmin();
        cy.visit('/index.php');
        waitForRichAutocompleteReady();
    });

    it('routes row clicks to the artist URL without hijacking Discogs links', () => {
        stubArtistAutocomplete();
        stubArtistPageNavigation();
        openArtistAutocomplete();

        cy.get('ul.autocomplete-menu .dropdown-item a.ac-discogs-link')
            .should('have.attr', 'href', discogsPath)
            .and('have.attr', 'target', '_blank')
            .and('have.attr', 'rel')
            .and('include', 'noreferrer')
            .and('include', 'noopener');
        cy.get('ul.autocomplete-menu .dropdown-item a.ac-discogs-link').click({ force: true });
        cy.location('pathname').should('eq', '/index.php');

        cy.get('ul.autocomplete-menu .dropdown-item .cae-row-link').click({ force: true });
        cy.wait('@artistPageNavigation');
        cy.location('pathname').should('eq', '/artist.php');
        cy.location('search').should('include', `id=${artistId}`);
    });

    extendedTestingIt('fully loads the artist page from a row click', () => {
        stubArtistAutocomplete();
        openArtistAutocomplete();

        cy.get('ul.autocomplete-menu .dropdown-item .cae-row-link').click({ force: true });
        cy.location('pathname').should('eq', '/artist.php');
        cy.location('search').should('include', `id=${artistId}`);
    });

    it('background-opens artist rows and advanced Discogs links without leaving the page', () => {
        stubArtistAutocomplete();
        stubBackgroundAnchorClicks();
        openArtistAutocomplete();

        cy.get('ul.autocomplete-menu .dropdown-item .cae-row-link').click({ metaKey: true, force: true });
        cy.window().its('__acBackgroundClicks').should((clicks) => {
            expect(clicks[0]).to.deep.include({
                href: absoluteArtistUrl,
                target: '_blank',
                rel: 'noopener',
            });
        });
        cy.location('pathname').should('eq', '/index.php');

        cy.window().then((win) => {
            win.__acBackgroundClicks = [];
        });
        cy.get('ul.autocomplete-menu .dropdown-item a.ac-discogs-link').click({ metaKey: true, force: true });
        cy.window().its('__acBackgroundClicks').should((clicks) => {
            expect(clicks[0]).to.deep.include({
                href: absoluteDiscogsUrl,
                target: '_blank',
            });
            expect(clicks[0].rel.split(/\s+/)).to.include.members(['noopener', 'noreferrer']);
        });
        cy.location('pathname').should('eq', '/index.php');
        cy.get('ul.autocomplete-menu.show').should('be.visible');
    });

    it('renders core rich rows, redirect aliases, and sanitized bios', () => {
        stubArtistAutocomplete((query) => (query.length >= 2
            ? [mainSuggestion, redirectSuggestion, filteredBioSuggestion]
            : []));
        openArtistAutocomplete();

        rowFor(artistName).within(() => {
            cy.get('.cae-name').should('contain.text', artistName);
            cy.get('.cae-row-link').should('have.attr', 'href', artistUrl);
            cy.get('.cae-img img').should('have.attr', 'src', inlineImage);
            cy.get('.cae-tag').should('contain.text', 'dream pop');
            cy.get('.cae-advanced-meta').should('exist');
            cy.get('.cae-bio-content').should('contain.text', 'Clicking the row should select the artist');
        });

        rowFor(redirectArtistName).within(() => {
            cy.get('.cae-row-link').should('have.attr', 'href', `/artist.php?id=${redirectArtistId}`);
            cy.get('.cae-name').should('contain.text', redirectArtistName);
            cy.get('.cae-aka').should('have.text', `aka ${redirectPrimaryArtistName}`);
        });

        rowFor('Filtered Bio Artist').within(() => {
            cy.get('.cae-bio-content').should(($bio) => {
                const text = $bio.text();
                expect(text).to.include('Kept opening line.');
                expect(text).to.include('Paragraph bold text.');
                expect(text).to.include('Words foo bar.');
                expect(text).to.include('Singer Pink.');
                expect(text).to.include('He lives in Maine.');
                expect(text).to.include('Style lean, underlined, and red.');
                expect(text).to.include('Compact Header');
                expect(text).to.include('Spaced Header');
                expect(text).to.include('Mid Header');
                expect(text).to.include('Small Header');
                expect(text).to.include('Rendered markup with rendered link.');
                expect(text).to.include('Kept closing line with context link.');
                expect(text).to.not.include('Discogs');
                expect(text).to.not.include('Example');
                expect(text).to.not.include('Paragraph URL');
                expect(text).to.not.include('[url=');
                expect(text).to.not.include('[/');
                expect(text).to.not.include('==');
            });
            cy.get('.cae-bio-content p').should('not.exist');
            cy.get('.cae-bio-content a').should('not.exist');
            cy.get('.cae-bio-content strong, .cae-bio-content span').should('not.exist');
        });
    });

    it('preserves server order in the rendered menu', () => {
        stubArtistAutocomplete((query) => (query.length >= 2 ? orderedSuggestions : []));
        openArtistAutocomplete(queryText, 'Ranked Beta');

        cy.get('ul.autocomplete-menu.show .collage-artist-entry .cae-name').then(($names) => {
            expect($names.toArray().map((el) => el.textContent.trim())).to.deep.equal([
                'Ranked Beta',
                'Ranked Alpha',
                'Ranked Gamma',
            ]);
        });
    });

    it('submits the header artist search when enter is pressed without a highlighted suggestion', () => {
        stubArtistAutocomplete();
        openArtistAutocomplete();
        cy.get('ul.autocomplete-menu li.ac-row-chosen').should('not.exist');

        cy.get('#artistsearch[data-gazelle-autocomplete="true"]').type('{enter}');

        cy.location().should((location) => {
            const params = new URLSearchParams(location.search);
            expect(location.pathname).to.eq('/torrents.php');
            if (params.get('action') === 'advanced') {
                expect(params.get('artistname')).to.eq(queryText);
            } else {
                expect(params.get('searchstr')).to.eq(queryText);
            }
        });
    });

    it('keeps keyboard selection and pointer selection aligned', () => {
        stubArtistAutocomplete((query) => (query.length >= 2 ? keyboardSuggestions : []));
        openArtistAutocomplete(queryText, 'Terra One');

        cy.get('#artistsearch').type('{downarrow}{downarrow}');
        cy.get('ul.autocomplete-menu.show').should('have.class', 'ac-mode-keyboard');
        cy.get('ul.autocomplete-menu li.ac-row.ac-row-chosen')
            .should('have.length', 1)
            .and('contain.text', 'Terra Two');

        cy.contains('ul.autocomplete-menu .dropdown-item', 'Terra Three')
            .trigger('mousemove', { force: true });
        cy.get('ul.autocomplete-menu.show')
            .should('have.class', 'ac-mode-pointer')
            .and('not.have.class', 'ac-mode-keyboard');
        cy.get('ul.autocomplete-menu li.ac-row.ac-row-chosen')
            .should('have.length', 1)
            .and('contain.text', 'Terra Three');
    });

    it('clears rich rows and resets scroll when escape closes the desktop menu', () => {
        stubArtistAutocomplete((query) => (query.length >= 2 ? manySuggestions : []));
        openArtistAutocomplete(queryText, 'Telemetry Close Test 1');

        cy.get('ul.autocomplete-menu.show').then(($menu) => {
            const menu = $menu[0];
            menu.scrollTop = menu.scrollHeight;
            expect(menu.scrollTop, 'menu was scrolled before dismissal').to.be.greaterThan(0);
        });
        cy.get('#artistsearch[data-gazelle-autocomplete="true"]').type('{esc}');

        cy.get('ul.autocomplete-menu.show').should('not.exist');
        cy.get('#artistsearch[data-gazelle-autocomplete="true"]').should('have.attr', 'aria-expanded', 'false');
        cy.get('body').should('not.have.class', 'ac-mobile-rich-open');
        cy.get('#artistsearch[data-gazelle-autocomplete="true"]').then(($input) => {
            expect($input[0].getAttribute('aria-activedescendant') || '').to.eq('');
            const menuId = $input[0].getAttribute('aria-controls');
            expect(menuId, 'artist autocomplete menu id').to.be.a('string').and.not.be.empty;
            cy.get(`#${menuId}`).find('li').should('have.length', 0);
        });

        openArtistAutocomplete('xy', 'Telemetry Close Test 1');
        cy.get('ul.autocomplete-menu.show').should(($menu) => {
            expect($menu[0].scrollTop, 'reopened menu scroll position').to.eq(0);
        });
    });

    it('uses the mobile takeover with compact rows at phone widths', () => {
        stubArtistAutocomplete((query) => (query.length >= 2
            ? [mainSuggestion, redirectSuggestion]
            : []));

        visitIndexAtViewport(390, 844, { requireVisible: false });
        openArtistAutocomplete(queryText, artistName, {
            requireVisibleMenu: false,
            requireVisibleLabel: false,
        });

        cy.get('body').should('have.class', 'ac-mobile-rich-open');
        cy.get('ul.autocomplete-menu.show')
            .should('have.class', 'ac-mobile-rich-menu')
            .and(($menu) => {
                const rect = $menu[0].getBoundingClientRect();
                expect(rect.left, 'menu left').to.be.at.least(0);
                expect(rect.right, 'menu right').to.be.at.most(391);
                expect($menu[0].scrollWidth, 'menu scroll width').to.be.at.most($menu[0].clientWidth + 1);
            });

        rowFor(artistName).within(() => {
            cy.get('.cae-name').should('contain.text', artistName);
            cy.get('.cae-meta, .cae-bio, .cae-advanced-meta').should('not.be.visible');
        });
        rowFor(redirectArtistName).within(() => {
            cy.get('.cae-name').should('contain.text', redirectArtistName);
            cy.get('.cae-aka').should('contain.text', redirectPrimaryArtistName);
        });
    });
});
