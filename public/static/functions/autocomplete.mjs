/**
 * Rich autocomplete runtime.
 *
 * Layout telemetry toggle:
 * - `window.GAZELLE_AC_DEBUG = true` enables console telemetry for the current page
 * - `localStorage.gazelle_ac_debug = "1"` persists console telemetry across reloads
 */
import { default as RichArtistAutocomplete } from './autocomplete-rich-artist.mjs';

function isBackgroundOpenEvent(event) {
    return !!event && (event.button === 1 || event.metaKey || event.ctrlKey);
}

function dispatchBackgroundAnchor(url) {
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.style.display = 'none';
    document.body.append(link);
    link.click();
    link.remove();
}

function selectArtist({data}, event = null) {
    const url = 'artist.php?id=' + data;
    if (isBackgroundOpenEvent(event)) {
        dispatchBackgroundAnchor(url);
        return;
    }
    window.location = url;
}

function addAutocomplete(id, autocomp_url, options = {}) {
    RichArtistAutocomplete.init(id, {
        server: autocomp_url,
        serverDataKey: 'suggestions',
        valueField: 'data',
        labelField: 'value',
        onRenderItem: (item) => item.html,
        onSelectItem: (suggestion, _inst, event = null) => selectArtist(suggestion, event),
        liveServer: true,
        noCache: false,
        showAllSuggestions: true,
        suggestionsThreshold: 2,
        autoselectFirst: false,
        ignoreEnter: true,
        ...options,
    });
}

document.addEventListener('DOMContentLoaded', () => {
    // autocomplete
    const url = new gazURL();
    const ARTIST_AUTOCOMPLETE_URL  = 'artist.php?action=autocomplete';
    const COLLAGE_AUTOCOMPLETE_URL = 'collages.php?action=autocomplete';
    const TAGS_AUTOCOMPLETE_URL    = 'torrents.php?action=autocomplete_tags';
    const SELECTOR = '[data-gazelle-autocomplete="true"]';

    addAutocomplete('#artistsearch' + SELECTOR, ARTIST_AUTOCOMPLETE_URL, {
        menuClass: 'ac-rich ac-rich-simple ac-flag-reserve-no-image-slot',
        // Fixed positioning lets the menu size from its rendered top, which avoids
        // per-theme desktop offsets when the search bar sits at different heights.
        fixed: true,
    });

    $('#collagesearch' + SELECTOR).autocomplete({
        deferRequestBy: 300,
        onSelect : function(suggestion) {
            window.location = 'collages.php?id=' + suggestion['data'];
        },
        serviceUrl: COLLAGE_AUTOCOMPLETE_URL,
    });

    if (['torrents', 'upload', 'collages', 'artist'].includes(url.path) || (url.path === 'requests' && url.query['action'] === 'new')) {
        $("#artist_0" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: ARTIST_AUTOCOMPLETE_URL
        });
        $("#artist" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: ARTIST_AUTOCOMPLETE_URL
        });
        $("#artistsimilar" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: ARTIST_AUTOCOMPLETE_URL
        });
        $("#collage_ref" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: COLLAGE_AUTOCOMPLETE_URL + (url.path === 'artist' ? '&artist=1' : '')
        });
    }

    if (['torrents', 'upload', 'collages', 'requests', 'top10'].includes(url.path)) {
        $("#tags" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            delimiter: ',',
            serviceUrl: TAGS_AUTOCOMPLETE_URL
        });
        $("#tagname" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: TAGS_AUTOCOMPLETE_URL,
        });
    }
});
