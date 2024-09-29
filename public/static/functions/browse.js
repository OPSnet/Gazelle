/* global ajax */

function show_downloads_load (torrentid, page) {
    $('#downloads_' + torrentid).gshow().raw().innerHTML = '<h4>Loading...</h4>';
    $.ajax({
        url: 'torrents.php?action=downloadlist&page=' + page + '&torrentid=' + torrentid,
        success: function(json) {
            let response = JSON.parse(json);
            $('#downloads_' + torrentid).gshow().raw().innerHTML = response.html;
            for (let p = 1; p <= response.pages; ++p) {
                if (p != response.page) {
                    $('.pager-' + p).attr(
                        'onclick',
                        "show_downloads(" + torrentid + ", " + $('.pager-' + p).raw().innerHTML + ")"
                    );
                }
            }
        }
    });
}

function show_snatches_load (torrentid, page) {
    $('#snatches_' + torrentid).gshow().raw().innerHTML = '<h4>Loading...</h4>';
    $.ajax({
        url: 'torrents.php?action=snatchlist&page=' + page + '&torrentid=' + torrentid,
        success: function(json) {
            let response = JSON.parse(json);
            $('#snatches_' + torrentid).gshow().raw().innerHTML = response.html;
            for (let p = 1; p <= response.pages; ++p) {
                if (p != response.page) {
                    $('.pager-' + p).attr(
                        'onclick',
                        "show_snatches(" + torrentid + ", " + $('.pager-' + p).raw().innerHTML + ")"
                    );
                }
            }
        }
    });
}

function show_seeders_load (torrentid, page) {
    $('#peers_' + torrentid).gshow().raw().innerHTML = '<h4>Loading...</h4>';
    $.ajax({
        url: 'torrents.php?action=peerlist&page=' + page + '&torrentid=' + torrentid,
        success: function(json) {
            let response = JSON.parse(json);
            $('#peers_' + torrentid).gshow().raw().innerHTML = response.html;
            for (let p = 1; p <= response.pages; ++p) {
                if (p != response.page) {
                    $('.pager-' + p).attr(
                        'onclick',
                        "show_seeders(" + torrentid + ", " + $('.pager-' + p).raw().innerHTML + ")"
                    );
                }
            }
        }
    });
}

function show_downloads (TorrentID, Page) {
    if (Page > 0) {
        show_downloads_load(TorrentID, Page);
    } else {
        if ($('#downloads_' + TorrentID).raw().innerHTML === '') {
            $('#downloads_' + TorrentID).gshow().raw().innerHTML = '<h4>Loading...</h4>';
            show_downloads_load(TorrentID, 1);
        } else {
            $('#downloads_' + TorrentID).gtoggle();
        }
    }
    $('#viewlog_' + TorrentID).ghide();
    $('#peers_' + TorrentID).ghide();
    $('#snatches_' + TorrentID).ghide();
    $('#files_' + TorrentID).ghide();
    $('#reported_' + TorrentID).ghide();
}

function show_snatches (TorrentID, Page) {
    if (Page > 0) {
        show_snatches_load(TorrentID, Page);
    } else {
        if ($('#snatches_' + TorrentID).raw().innerHTML === '') {
            $('#snatches_' + TorrentID).gshow().raw().innerHTML = '<h4>Loading...</h4>';
            show_snatches_load(TorrentID, 1);
        } else {
            $('#snatches_' + TorrentID).gtoggle();
        }
    }
    $('#viewlog_' + TorrentID).ghide();
    $('#peers_' + TorrentID).ghide();
    $('#downloads_' + TorrentID).ghide();
    $('#files_' + TorrentID).ghide();
    $('#reported_' + TorrentID).ghide();
}

function show_seeders (TorrentID, Page) {
    if (Page > 0) {
        show_seeders_load(TorrentID, Page);
    } else {
        if ($('#peers_' + TorrentID).raw().innerHTML === '') {
            $('#peers_' + TorrentID).gshow().raw().innerHTML = '<h4>Loading...</h4>';
            show_seeders_load(TorrentID, 1);
        } else {
            $('#peers_' + TorrentID).gtoggle();
        }
    }
    $('#viewlog_' + TorrentID).ghide();
    $('#snatches_' + TorrentID).ghide();
    $('#downloads_' + TorrentID).ghide();
    $('#files_' + TorrentID).ghide();
    $('#reported_' + TorrentID).ghide();
}

function show_logs (TorrentID, HasLogDB, LogScore) {
    if (HasLogDB === 1) {
        if ($('#viewlog_' + TorrentID).raw().innerHTML === '') {
            $('#viewlog_' + TorrentID).gshow().raw().innerHTML = '<h4>Loading...</h4>';
            ajax.get('torrents.php?action=viewlog&logscore=' + LogScore + '&torrentid=' + TorrentID, function(response) {
                $('#viewlog_' + TorrentID).gshow().raw().innerHTML = response;
            });
        } else {
            $('#viewlog_' + TorrentID).gtoggle();
        }
    }
    $('#peers_' + TorrentID).ghide();
    $('#snatches_' + TorrentID).ghide();
    $('#downloads_' + TorrentID).ghide();
    $('#files_' + TorrentID).ghide();
    $('#reported_' + TorrentID).ghide();
}

function show_files(TorrentID) {
    $('#files_' + TorrentID).gtoggle();
    $('#viewlog_' + TorrentID).ghide();
    $('#peers_' + TorrentID).ghide();
    $('#snatches_' + TorrentID).ghide();
    $('#downloads_' + TorrentID).ghide();
    $('#reported_' + TorrentID).ghide();
}

function show_reported(TorrentID) {
    $('#files_' + TorrentID).ghide();
    $('#viewlog_' + TorrentID).ghide();
    $('#peers_' + TorrentID).ghide();
    $('#snatches_' + TorrentID).ghide();
    $('#downloads_' + TorrentID).ghide();
    $('#reported_' + TorrentID).gtoggle();
}

function add_tag(tag) {
    if ($('#tags').raw().value == "") {
        $('#tags').raw().value = tag;
    } else {
        $('#tags').raw().value = $('#tags').raw().value + ", " + tag;
    }
}

/**
 *
 * @param {Event} event
 */
function openAll(event) {
    // we check individual keyCodes for dealing with macOS X weirdness
    // otherwise, can just check for ctrlKey or metaKey
    // http://stackoverflow.com/a/3922353
    return (
        event.keyCode == 91 // WebKit (left apple)
        || event.keyCode == 93 // WebKit (right apple)
        || event.keyCode == 224 // Firefox
        || event.keyCode == 17 // Opera
    ) || (event.ctrlKey || event.metaKey);
}

function toggle_group(groupid, link, event) {
    let showRow = true;
    let clickedRow = link;
    while (clickedRow.nodeName != 'TR') {
        clickedRow = clickedRow.parentNode;
    }
    let group_rows = clickedRow.parentNode.children;
    let showing = link.parentNode.classList.contains('show_torrents');

    const allGroups = openAll(event);

    let releaseType = null;
    if (allGroups) {
        for (const className of clickedRow.classList) {
            if (className.startsWith('releases_')) {
                releaseType = className;
                break;
            }
        }
    }

    for (let i = 0; i < group_rows.length; i++) {
        let row = $(group_rows[i]);
        if (row.has_class('colhead_dark')) {
            continue;
        }
        if (row.has_class('colhead')) {
            continue;
        }
        if (row.has_class('torrent')) {
            continue; // Prevents non-grouped torrents from disappearing when collapsing all groups
        }

        // we have groupid_#_header so as to not break the toggle_edition logic
        if (!(
            (allGroups && (releaseType === null || row[0].classList.contains(releaseType)))
            || (row.has_class('groupid_' + groupid) || row.has_class('groupid_' + groupid + '_header'))
        )) {
            continue;
        }

        if (row.has_class('group')) {
            let section = (location.pathname.search('/artist.php$') !== -1) 
                ? 'in this release type.'
                : 'on this page.';
            let tooltip = showing
                ? 'Collapse this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all groups '+section
                : 'Expand this group. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to expand all groups '+section;
            $('a.show_torrents_link', row).updateTooltip(tooltip);
            $('a.show_torrents_link', row).raw().parentNode.className = (showing) ? 'hide_torrents' : 'show_torrents';
        } else {
            if (showing) {
                // show the row depending on whether the edition it's in is collapsed or not
                if (row.has_class('edition')) {
                    row.gshow();
                    showRow = ($('a', row.raw()).raw().innerHTML != '+');
                } else {
                    if (showRow) {
                        row.gshow();
                    } else {
                        row.ghide();
                    }
                }
            } else {
                row.ghide();
            }
        }
    }

    event.preventDefault();
}

function toggle_edition(groupid, editionid, lnk, event) {
    let clickedRow = lnk;
    while (clickedRow.nodeName != 'TR') {
        clickedRow = clickedRow.parentNode;
    }
    let showing = $(clickedRow).nextElementSibling().has_class('hidden');

    const allEditions = openAll(event);

    let group_rows = $('tr.groupid_' + groupid);
    for (let i = 0; i < group_rows.results(); i++) {
        let row = $(group_rows.raw(i));
        if (row.has_class('edition') && (allEditions || row.raw(0) == clickedRow)) {
            let tooltip = showing
                ? 'Collapse this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to collapse all editions in this torrent group.'
                : 'Expand this edition. Hold [Command] <em>(Mac)</em> or [Ctrl] <em>(PC)</em> while clicking to expand all editions in this torrent group.';
            $('a', row).raw().innerHTML = (showing) ? '&minus;' : '+';
            $('a', row).updateTooltip(tooltip);
            continue;
        }
        if (allEditions || row.has_class('edition_' + editionid)) {
            if (showing && !row.has_class('torrentdetails')) {
                row.gshow();
            } else {
                row.ghide();
            }
        }
    }

    event.preventDefault();
}

function toggleTorrentSearch(mode) {
    if (mode == 0) {
        let link = $('#ft_toggle').raw();
        $('#ft_container').gtoggle();
        if (link.textContent.substr(0, 4) == 'Hide') {
            link.innerHTML = 'Show search form';
            $('#ft_type').ghide();
        } else {
            link.innerHTML = 'Hide search form';
            $('#ft_type').gshow();
        }
    }
    if (mode == 'basic') {
        $('.fti_advanced').disable();
        $('.fti_basic').enable();
        $('.ftr_advanced').ghide(true);
        $('.ftr_basic').gshow();
        $('#ft_type').attr('onclick', "return toggleTorrentSearch('advanced')");
        $('#ft_type').raw().innerHTML = 'Switch to advanced';
        $('#ft_action').attr('value', mode);
    } else if (mode == 'advanced') {
        $('.fti_advanced').enable();
        $('.fti_basic').disable();
        $('.ftr_advanced').gshow();
        $('.ftr_basic').ghide();
        $('#ft_type').attr('onclick', "return toggleTorrentSearch('basic')");
        $('#ft_type').raw().innerHTML = 'Switch to basic';
        $('#ft_action').attr('value', mode);
    }
    return false;
}

let ArtistFieldCount = 1;

function AddArtistField() {
    if (ArtistFieldCount >= 100) {
        return;
    }
    let mapping = {
        1: 0,
        2: 1,
        4: 2,
        5: 3,
        6: 4,
        3: 5,
        7: 6,
        8: 7,
    };
    let selected = mapping[$("#AddArtists select:last-child").val()];
    let x = $('#AddArtists').raw();
    x.appendChild(document.createElement("br"));
    let ArtistField = document.createElement("input");
    ArtistField.type = "text";
    ArtistField.name = "aliasname[]";
    ArtistField.size = "17";
    x.appendChild(ArtistField);
    x.appendChild(document.createTextNode(' '));
    let Importance = document.createElement("select");
    Importance.name = "importance[]";
    Importance.innerHTML = '<option value="1">Main</option><option value="2">Guest</option><option value="4">Composer</option><option value="5">Conductor</option><option value="6">DJ / Compiler</option><option value="3">Remixer</option><option value="7">Producer</option><option value="8">Arranger</option>';
    Importance.selectedIndex = selected;
    x.appendChild(Importance);
    if ($("#artist").data("gazelle-autocomplete")) {
        $(ArtistField).live('focus', function() {
            $(ArtistField).autocomplete({
                serviceUrl : 'artist.php?action=autocomplete'
            });
        });
    }
    ArtistFieldCount++;
}

let coverFieldCount = 0;
let hasCoverAddButton = false;

function addCoverField() {
    if (coverFieldCount >= 100) {
        return;
    }
    let x = $('#add_cover').raw();
    x.appendChild(document.createElement("br"));
    let field = document.createElement("input");
    field.type = "text";
    field.name = "image[]";
    field.placeholder = "URL";
    x.appendChild(field);
    x.appendChild(document.createTextNode(' '));
    let summary = document.createElement("input");
    summary.type = "text";
    summary.name = "summary[]";
    summary.placeholder = "Summary";
    x.appendChild(summary);
    coverFieldCount++;

    if (!hasCoverAddButton) {
        x = $('#add_covers_form').raw();
        field = document.createElement("input");
        field.type = "submit";
        field.value = "Add";
        x.appendChild(field);
        hasCoverAddButton = true;
    }
}

function ToggleEditionRows() {
    $('#edition_title').gtoggle();
    $('#edition_label').gtoggle();
    $('#edition_catalogue').gtoggle();
}

function add_to_collage() {
    let field = document.forms['add-to-collage'].elements;
    let post = {
        'auth':       document.body.dataset.auth,
        'name':       field['collage_ref'].value,
        'entry_id':   Number(field['entryid'].value),
        'collage_id': field['collage-select'] === undefined ? 0 : Number(field['collage-select'].value),
    };
    $('#add-result').raw().innerHTML = '...';
    ajax.post('collages.php?action=ajax_add', post, function (response) {
        let result = JSON.parse(response);
        $('#add-result').raw().innerHTML = (result['status'] == 'success')
            ? 'Added to <b>' + result['response']['link'] + '</b>'
            : 'Failed to add! (' + result['error'] + ')';
    });
}
