document.addEventListener('DOMContentLoaded', function() {
    var url = new gazURL();
    var ARTIST_AUTOCOMPLETE_URL  = 'artist.php?action=autocomplete';
    var COLLAGE_AUTOCOMPLETE_URL = 'collages.php?action=autocomplete';
    var TAGS_AUTOCOMPLETE_URL    = 'torrents.php?action=autocomplete_tags';
    var SELECTOR = '[data-gazelle-autocomplete="true"]';

    $('#artistsearch' + SELECTOR).autocomplete({
        deferRequestBy: 300,
        onSelect : function(suggestion) {
            window.location = 'artist.php?id=' + suggestion['data'];
        },
        serviceUrl: ARTIST_AUTOCOMPLETE_URL,
    });

    $('#collagesearch' + SELECTOR).autocomplete({
        deferRequestBy: 300,
        onSelect : function(suggestion) {
            window.location = 'collages.php?id=' + suggestion['data'];
        },
        serviceUrl: COLLAGE_AUTOCOMPLETE_URL,
    });

    if (url.path == 'torrents' || url.path == 'upload' || url.path == 'artist' || (url.path == 'requests' && url.query['action'] == 'new') || url.path == 'collages') {
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
            serviceUrl: COLLAGE_AUTOCOMPLETE_URL + (url.path == 'artist' ? '&artist=1' : '')
        });
    }
    if (url.path == 'torrents' || url.path == 'upload' || url.path == 'collages' || url.path == 'requests' || url.path == 'top10' || (url.path == 'requests' && url.query['action'] == 'new')) {
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
