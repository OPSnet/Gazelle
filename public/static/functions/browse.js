"use strict";

async function show_filelist_load(div) {
    div.innerHTML = '<h4>Loading...</h4>';
    div.classList.remove('hidden');
    const response = await fetch(
        'torrents.php?action=filelist&id=' + div.id.replace('files_', '')
    );
    div.innerHTML = await response.json();
}

async function show_and_load_page(thing, tid, page_num) {
    const thing_action_map = new Map([
        ['peers', 'peerlist'], ['downloads', 'downloadlist'], ['snatches', 'snatchlist']
    ]);
    const action = thing_action_map.get(thing);
    const e = document.getElementById(thing + '_' + tid);
    e.innerHTML = '<h4>Loading...</h4>';
    e.classList.remove('hidden');
    const response = await fetch(
        `torrents.php?action=${action}&page=${page_num}&torrentid=${tid}`,
    );
    const data = await response.json();
    e.innerHTML = data.html;
    if (data.pages > 1) {
        for (const p of e.getElementsByClassName('pager-link')) {
            const page = p.innerText;
            if (page !== data.page) {
                p.addEventListener('click', async () => await show_thing(thing, tid, page));
            }
        }
    }
}

function hide_dynamic_content(tid, exclude = []) {
    const prefixes = ['downloads', 'files', 'peers', 'reported', 'snatches', 'viewlog'];
    for (const prefix of prefixes) {
        if (!exclude.includes(prefix)) {
            document.getElementById(prefix + '_' + tid)?.classList.add('hidden');
        }
    }
}

async function show_thing(thing, tid, page) {
    if (page > 0) {
        await show_and_load_page(thing, tid, page);
    } else {
        const e = document.getElementById(thing + '_' + tid);
        if (e.innerHTML === '') {
            await show_and_load_page(thing, tid, 1);
        } else {
            e.classList.toggle('hidden');
        }
    }
    hide_dynamic_content(tid, [thing]);
}

async function show_downloads(tid, page) {
    await show_thing('downloads', tid, page);
}

async function show_snatches(tid, page) {
    await show_thing('snatches', tid, page);
}

async function show_peers(tid, page) {
    await show_thing('peers', tid, page);
}

async function show_logs(tid) {
    const e = document.getElementById('viewlog_' + tid);
    if (e.innerHTML === '') {
        const response = await fetch(
            'torrents.php?action=viewlog&torrentid=' + tid
        );
        e.innerHTML = await response.text();
    }
    e.classList.toggle('hidden');
    hide_dynamic_content(tid, ['viewlog']);
}

async function show_filelist(tid) {
    const div = document.getElementById('files_' + tid);
    if (div.innerHTML === '') {
        await show_filelist_load(div);
    } else {
        div.classList.toggle('hidden');
    }
    hide_dynamic_content(tid, ['files']);
}

function show_reported(tid) {
    hide_dynamic_content(tid, ['reported']);
    document.getElementById('reported_' + tid)?.classList.toggle('hidden');
}

function add_tag(tag) {
    if ($('#tags').raw().value == "") {
        $('#tags').raw().value = tag;
    } else {
        $('#tags').raw().value = $('#tags').raw().value + ", " + tag;
    }
}

/**
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
            if (!showing) {
                row.ghide();
            } else {
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
    let role = document.createElement("select");
    role.name = "importance[]";
    role.innerHTML = '<option value="1">Main</option><option value="2">Guest</option><option value="4">Composer</option><option value="5">Conductor</option><option value="6">DJ / Compiler</option><option value="3">Remixer</option><option value="7">Producer</option><option value="8">Arranger</option>';
    role.selectedIndex = selected;
    x.appendChild(role);
    if ($("#artist").data("gazelle-autocomplete")) {
        $(ArtistField).live('focus', () => {
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

async function add_to_collage() {
    let field = document.forms['add-to-collage'].elements;
    let form  = new FormData();
    form.append('auth', document.body.dataset.auth);
    form.append('name', field['collage_ref'].value);
    form.append('entry_id', Number(field['entryid'].value));
    form.append('collage_id', field['collage-select'] === undefined
        ? 0
        : Number(field['collage-select'].value));
    let add = document.getElementById('add-result');
    add.innerHTML = '...';
    const response = await fetch(
        'collages.php?action=ajax_add', {
            'method': "POST",
            'body': form,
        }
    );
    const data = await response.json();
    add.innerHTML = (data.status === 'success')
        ? 'Added to <b>' + data.response.link + '</b>'
        : 'Failed to add! (' + data.error + ')';
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('collage-add')?.addEventListener('click', add_to_collage);

    for (const reseed of document.querySelectorAll('.request-reseed')) {
        reseed.addEventListener('click', (e) => {
            if (!confirm(
                'Are you sure you want to request a re-seed of this torrent?'
            )) {
                e.preventDefault();
            }
        });
    }

    for (const view of document.querySelectorAll('.view-filelist')) {
        view.addEventListener('click', async (e) => {
            e.preventDefault();
            await show_filelist(view.parentNode.dataset.id);
        });
    }
    for (const view of document.querySelectorAll('.view-riplog')) {
        view.addEventListener('click', async (e) => {
            e.preventDefault();
            await show_logs(view.parentNode.dataset.id);
        });
    }
    for (const view of document.querySelectorAll('.view-download')) {
        view.addEventListener('click', async (e) => {
            e.preventDefault();
            await show_downloads(view.parentNode.dataset.id);
        });
    }
    for (const view of document.querySelectorAll('.view-report')) {
        view.addEventListener('click', (e) => {
            e.preventDefault();
            show_reported(view.parentNode.dataset.id);
        });
    }
    for (const view of document.querySelectorAll('.view-peer')) {
        view.addEventListener('click', async (e) => {
            e.preventDefault();
            await show_peers(view.parentNode.dataset.id);
        });
    }
    for (const view of document.querySelectorAll('.view-snatch')) {
        view.addEventListener('click', async (e) => {
            e.preventDefault();
            await show_snatches(view.parentNode.dataset.id);
        });
    }
});
