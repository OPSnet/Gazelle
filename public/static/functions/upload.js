/* global showWarningMessage */

let AllowedMediaFormat = {
    WEB:        ['FLAC', 'MP3', 'AAC'],
    CD:         ['FLAC', 'MP3', 'AAC'],
    SACD:       ['FLAC', 'MP3', 'AAC'],
    DVD:        ['FLAC', 'MP3', 'AC3', 'DTS'],
    BD:         ['FLAC', 'MP3', 'AC3', 'DTS'],
    DAT:        ['FLAC', 'MP3', 'AAC'],
    Vinyl:      ['FLAC', 'MP3', 'AAC'],
    Soundboard: ['FLAC', 'MP3', 'AAC'],
    Cassette:   ['FLAC', 'MP3', 'AAC'],
};

let AllowedAudiobookFormat = ['FLAC', 'MP3', 'AAC'];

let AllowedBitrate = {
    FLAC: {
        list: ['24bit Lossless', 'Lossless'],
        rank: [1, 0],
    },
    MP3: {
        list: ['320', 'V0 (VBR)', 'V1 (VBR)', 'V2 (VBR)', '256', '192', '160', '128', '96', '64', 'Other'],
        rank: [0, 1],
    },
    AAC: { list: ['256'], rank: [0] },
    AC3: { list: ['Other'], rank: [0] },
    DTS: { list: ['Other'], rank: [0] },
};

let MAX_EXTRA_FORMATS = 5;
let MAX_RIPLOGS = 400;

let ArtistCount      = 0;
let ArtistJsonCount  = 0;
let ExtraFormatCount = 0;

// the form starts with one logfile field
let LogCount         = 1;

function Categories() {
    let dynamic_form = $('#dynamic_form');
    $.when(
        $.ajax({url: 'ajax.php?action=upload_section&categoryid=' + document.getElementById('categories').value, dataType: 'text'}),
        $.ajax({url: 'ajax.php?action=upload_section&js=1&categoryid=' + document.getElementById('categories').value, dataType: 'text'})
    ).then(
        function (resp1, resp2) {
            if (resp1[1] !== 'success' || resp2[1] !== 'success') {
                console.error('Error fetching upload form');
                return;
            }
            dynamic_form.raw().innerHTML = resp1[0];
            uploadFormInit();
            let script = document.createElement('script', {'type': 'text/javascript'});
            script.innerHTML = resp2[0];
            document.body.append(script);
            dynamic_form.data('loaded', true);
        },
        function (err) {
            console.error(err);
        },
    );
}

function changeMedia() {
    CheckYear();
    setAllowedFormat('#format', '#bitrate');
}

function setAllowedFormat(formatField, bitrateField) {
    let media = $('#media').val();
    let fmt = $(formatField).val();
    $(formatField).empty().append(new Option('---', ''));
    if (document.getElementById('form-music-upload')) {
        if (media === '---') {
            $(bitrateField).empty().append(new Option('---', ''));
            $('#upload_logs').ghide();
            $('#other_bitrate_span').ghide();
            document.getElementById('format_warning').innerHTML = '';
            return;
        }
        $.each(AllowedMediaFormat[media], function(k) {
            $(formatField).append(new Option(AllowedMediaFormat[media][k], AllowedMediaFormat[media][k]));
        });
    } else if (document.getElementById('form-audiobook-upload')) {
        $.each(AllowedAudiobookFormat, function(k) {
            $(formatField).append(new Option(AllowedAudiobookFormat[k], AllowedAudiobookFormat[k]));
        });
    }
    if (fmt === '---' || (media !== undefined && AllowedMediaFormat[media].indexOf(fmt) == -1)) {
        fmt = 'FLAC';
    }
    if (fmt === "---" || (media !== undefined && (['DVD', 'BD'].indexOf(media) == -1 && ['AC3', 'DTS'].indexOf(fmt) > -1))) {
        fmt = 'FLAC';
        $('#bitrate').val(AllowedBitrate['FLAC'].list[AllowedBitrate['FLAC'].rank[0]]);
    }
    $(formatField).val(fmt);
    if ($('#upload_logs').length) {
        if (formatField === '#format' && fmt === 'FLAC' && media === 'CD') {
            $('#upload_logs').gshow();
        }
        else {
            $('#upload_logs').ghide();
        }
        document.getElementById('format_warning').innerHTML = ($(formatField).val() === 'AAC')
            ? 'AAC torrents may only be uploaded if they represent editions unavailable on Orpheus in any other format sourced from the same medium and edition <a href="rules.php?p=upload#r2.1.21" target="_blank">(2.1.21)</a>'
            : '';
    }
    setAllowedBitrate(formatField, bitrateField);
}

function setAllowedBitrate(formatField, bitrateField) {
    let media = $('#media').val();
    let fmt = $(formatField).val();
    let btr = $(bitrateField).val();
    $(bitrateField).empty().append(new Option('---', ''));
    if (media === '---' || fmt === '---') {
        $('#other_bitrate_span').ghide();
        return;
    }
    let allowed = AllowedBitrate[fmt];
    $.each(allowed.list, function(k) {
        if (!(media == 'CD' && allowed.list[k] == '24bit Lossless')) {
            $(bitrateField).append(new Option(allowed.list[k], allowed.list[k]));
        }
    });
    if (btr === '' || allowed.list.indexOf(btr) == -1) {
        btr = allowed.list[allowed.rank[0]];
    }
    $(bitrateField).val(btr);
    if (btr !== 'Other') {
        $('#other_bitrate_span').ghide();
    } else {
        $('#other_bitrate_span').gshow();
    }
}

function AltBitrate() {
    if (document.getElementById('other_bitrate').value >= 320) {
        document.getElementById('vbr').disabled = true;
        document.getElementById('vbr').checked = false;
    } else {
        document.getElementById('vbr').disabled = false;
    }
}

function addFormatRow() {
    if (ExtraFormatCount == MAX_EXTRA_FORMATS) {
        return;
    }
    if (++ExtraFormatCount == MAX_EXTRA_FORMATS) {
        $("#add_format").css('visibility', 'hidden');
    }

    const formatFieldNum = ExtraFormatCount;
    $("#remove_format").show();

    let master = $(document.createElement("tr"))
        .attr({
            id: 'extra_format_row_' + formatFieldNum
        })
        .insertBefore('#extra_format_placeholder');

    $(document.createElement("td"))
        .addClass('label')
        .html("Extra format " + formatFieldNum + ":")
        .appendTo(master);
    let row = $(document.createElement("td"))
        .appendTo(master);
    $(document.createElement("input"))
        .attr({
            id: "extra_file_" + formatFieldNum,
            type: 'file',
            name: "extra_file_" + formatFieldNum,
            size: 30,
            accept: "application/x-bittorrent,.torrent",
        })
        .appendTo(row);
    $(document.createElement("span"))
        .html("&nbsp;&nbsp;&nbsp;&nbsp;Format: ")
        .appendTo(row);

    let formatSelect = $(document.createElement("select"))
        .attr({
            id: "format_" + formatFieldNum,
            name: 'extra_format[]'
        })
        .change(function () {
            setAllowedBitrate('#format_' + formatFieldNum, '#bitrate_' + formatFieldNum);
        });
    let used = getUsedPairs();
    $.each(AllowedBitrate, function(k,v) {
        if (!(k in used) || used[k].length < AllowedBitrate[k].list.length) {
            formatSelect.append(new Option(k, k));
        }
    });
    formatSelect.val('MP3').appendTo(row);

    // bitrates
    $(document.createElement("span")).html("&nbsp;&nbsp;&nbsp;&nbsp;Bitrate: ").appendTo(row);
    let bitrateSelect = $(document.createElement("select"))
        .attr({
            id:"bitrate_" + formatFieldNum,
            name:'extra_bitrate[]'
        })
        .append(new Option('---', ''));
    $.each(AllowedBitrate.MP3.list, function(k, v) {
        if (!(v == 'Other' || (used.MP3 && used.MP3.indexOf(v) !== -1))) {
            bitrateSelect.append(new Option(v, v));
        }
    });
    let nf;
    if (!used.MP3 || used.MP3.indexOf('320') == -1) { nf = 0; }
    else if (used.MP3.indexOf('V0 (VBR)') == -1) { nf = 1; }
    else if (used.MP3.indexOf('V2 (VBR)') == -1) { nf = 2; }
    if (nf !== undefined) {
        bitrateSelect.val(AllowedBitrate.MP3.list[
            AllowedBitrate.MP3.rank[nf]
        ]);
    }
    bitrateSelect.change(function () {
        setAllowedBitrate('#format_' + formatFieldNum, '#bitrate_' + formatFieldNum);
    }).appendTo(row);

    // release description
    let desc_row = $(document.createElement("tr"))
        .attr({ id: "desc_row"})
        .css('cursor', 'pointer')
        .appendTo(row);
    $(document.createElement("a"))
        .html("&nbsp;&nbsp;[Add Release Description]")
        .css('marginLeft', '-5px')
        .click(function () {
            $("#extra_release_desc_" + formatFieldNum)
                .toggle(300);
        })
        .appendTo(desc_row) ;
    $(document.createElement("textarea"))
        .attr({
            id: "extra_release_desc_" + formatFieldNum,
            name: "extra_release_desc[]",
            cols: 60,
            rows: 4,
            style: 'display:none; margin-left: 5px; margin-top: 10px; margin-bottom: 10px;'
        })
        .appendTo(desc_row);
    $("#post").val("Upload torrents");
}

function removeFormatRow() {
    if (ExtraFormatCount == 0) {
        return;
    }
    if (ExtraFormatCount-- > 0) {
        $("#extra_format_placeholder").prev().remove();
        if (ExtraFormatCount == 0) {
            $("#remove_format").hide();
        }
        $("#add_format").css('visibility', 'visible');
        $("#post").val("Upload torrents");
    }
}

function getUsedPairs() {
    // fetch the format/bitrate combinations already used in the form
    let fmt = $('#format').val();
    let btr = $('#bitrate').val();
    let used = {};
    if (fmt !== '' && btr !== '' && AllowedBitrate[fmt].list.indexOf(btr) != -1) {
        used[fmt] = [btr];
    }
    for (let e = 1; e < ExtraFormatCount; e++) {
        fmt = $("#format_" + e).val();
        btr = $("#bitrate_" + e).val();
        if (fmt !== '' && btr !== '' && AllowedBitrate[fmt].list.indexOf(btr) != -1) {
            if (fmt in used) {
                used[fmt].push(btr);
            }
            else {
                used[fmt] = [btr];
            }
        }
    }
    return used;
}

function add_tag() {
    const tagEl = document.getElementById('tags');
    const genreTagEl = document.getElementById('genre_tags');
    if (tagEl.value === "") {
        tagEl.value = genreTagEl.options[genreTagEl.selectedIndex].value;
    } else if (!(genreTagEl.options[genreTagEl.selectedIndex].value === '---')) {
        tagEl.value = tagEl.value + ', ' + genreTagEl.options[genreTagEl.selectedIndex].value;
    }
}

function AddLogField(acceptTypes) {
    if (LogCount > MAX_RIPLOGS) {
        return;
    }
    LogCount++;
    let LogField = document.createElement("input");
    LogField.type = "file";
    LogField.id = "logfile_" + LogCount;
    LogField.name = "logfiles[]";
    LogField.accept = acceptTypes;
    LogField.multiple = true;
    LogField.size = 50;
    let x = document.getElementById('logfields');
    x.appendChild(document.createElement("br"));
    x.appendChild(LogField);
}

function RemoveLogField() {
    if (LogCount === 1) {
        return;
    }
    let x = document.getElementById('logfields');
    for (let i = 0; i < 2; i++) {
        x.removeChild(x.lastChild);
    }
    LogCount--;
}

function AddArtistField() {
    if (ArtistCount >= 200) {
        return;
    }
    ArtistCount++;
    let ArtistField = document.createElement("input");
    ArtistField.type = "text";
    ArtistField.id = "artist_" + ArtistCount;
    ArtistField.name = "artists[]";
    ArtistField.size = 45;
    ArtistField.onblur = CheckVA;

    let RoleField = document.createElement("select");
    RoleField.id = "importance_" + ArtistCount;
    RoleField.name = "importance[]";
    RoleField.options[0] = new Option("Main", "1");
    RoleField.options[1] = new Option("Guest", "2");
    RoleField.options[2] = new Option("Composer", "4");
    RoleField.options[3] = new Option("Conductor", "5");
    RoleField.options[4] = new Option("DJ / Compiler", "6");
    RoleField.options[5] = new Option("Remixer", "3");
    RoleField.options[6] = new Option("Producer", "7");
    RoleField.options[7] = new Option("Arranger", "8");
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
    RoleField.selectedIndex = mapping[document.getElementById("importance_" + (ArtistCount - 1)).value];

    const x = document.getElementById('artistfields');
    const div = document.createElement("div");
    div.id = "artist_field_" + ArtistCount;
    div.style.display = "inline";
    div.appendChild(document.createElement("br"));
    div.appendChild(ArtistField);
    div.append(' ');
    div.appendChild(RoleField);
    x.appendChild(div);

    if (document.getElementById('artist_0').dataset.gazelleAutocomplete) {
        ArtistField.addEventListener('focus', () => {
            $(ArtistField).autocomplete({
                serviceUrl : 'artist.php?action=autocomplete'
            });
        });
    }
}

function RemoveArtistField() {
    if (ArtistCount === 0) {
        return;
    }
    document.getElementById("artist_field_" + ArtistCount).remove();
    ArtistCount--;
}


function CheckVA() {
    let shown = false;
    for (let i = 0; i < ArtistCount; i++) {
        if (document.getElementById('artist_' + i).value.toLowerCase().trim().match(/^(va|various(\sa|a)rtis(t|ts)|various)$/)) {
            $('#vawarning').gshow();
            shown = true;
            break;
        }
    }
    if (!shown) {
        $('#vawarning').ghide();
    }
}

function CheckYear() {
    const isUnknownEdition = document.getElementById('unknown')?.checked;
    const media = document.getElementById('media').value;
    const isOldMedia = ["---", "Vinyl", "Soundboard", "Cassette"].includes(media);
    const year = document.getElementById('remaster_year').value;
    if (year !== '' && year < 1982 && !isOldMedia && !isUnknownEdition) {
        $('#yearwarning').gshow();
    } else {
        $('#yearwarning').ghide();
    }
}

function ToggleUnknown() {
    if (document.getElementById('unknown').checked) {
        document.getElementById('remaster_year').value = "";
        document.getElementById('remaster_title').value = "";
        document.getElementById('remaster_record_label').value = "";
        document.getElementById('remaster_catalogue_number').value = "";

        if (document.getElementById('groupremasters')) {
            document.getElementById('groupremasters').selectedIndex = 0;
            document.getElementById('groupremasters').disabled = true;
        }

        document.getElementById('remaster_year').disabled = true;
        document.getElementById('remaster_title').disabled = true;
        document.getElementById('remaster_record_label').disabled = true;
        document.getElementById('remaster_catalogue_number').disabled = true;
    } else {
        document.getElementById('remaster_year').disabled = false;
        document.getElementById('remaster_title').disabled = false;
        document.getElementById('remaster_record_label').disabled = false;
        document.getElementById('remaster_catalogue_number').disabled = false;

        if (document.getElementById('groupremasters')) {
            document.getElementById('groupremasters').disabled = false;
        }
    }
}

function GroupRemaster() {
    const remasters = JSON.parse(document.getElementById('json_remasters').value);
    const index = document.getElementById('groupremasters').options[document.getElementById('groupremasters').selectedIndex].value;
    if (index !== "") {
        document.getElementById('remaster_year').value = remasters[index][1];
        document.getElementById('remaster_title').value = remasters[index][2];
        document.getElementById('remaster_record_label').value = remasters[index][3];
        document.getElementById('remaster_catalogue_number').value = remasters[index][4];
    }
}

/**
 * Accepts a mapping which is an object where each prop is the id of
 * an html element and the value corresponds to a key in the data object
 * which we want to put as the value of the html element.
 *
 * @param mapping
 * @param data
 */
function jsonFill(source, mapping) {
    for (let prop in mapping) {
        // skip releasetype, it is a special case
        if (prop === 'releasetype' || !Object.prototype.hasOwnProperty.call(mapping, prop)) {
            continue;
        }
        if (source[mapping[prop]] && source[mapping[prop]] !== '') {
            $('#' + prop).val(source[mapping[prop]]).trigger('change');
        }
    }
}

function fillEncodingField(encoding) {
    if ($("#bitrate option[value='" + encoding + "']").length > 0) {
        $('#bitrate').val(encoding);
    }
    else {
        $('#bitrate').val('Other').trigger('change');
        setTimeout(function() {
            if (encoding.length > 5 && encoding.substr(-5) === '(VBR)') {
                encoding = encoding.substr(0, encoding.length - 6);
                $('#vbr').prop('checked', true);
            }
            $('#other_bitrate').val(encoding);
        }, 50);
    }
}

function fillArtist(artistlist, role) {
    for (let i = 0; i < artistlist.length; i++) {
        if (artistlist[i]['name']) {
            if (ArtistJsonCount++ > 0) {
                AddArtistField();
            }
            $('#artist_' + ArtistCount).val(artistlist[i]['name']);
            $('#importance_' + ArtistCount).val(role);
        }
    }
}

function fillMusicForm(group, torrent, source) {
    if (group['musicInfo']) {
        // JSON property to HTML value for artist role
        let mapping = {
            artists: 1,
            with: 2,
            composers: 4,
            conductor: 5,
            dj: 6,
            remixedBy: 3,
            producer: 7,
            arranger: 8,
        };
        for (let prop in group['musicInfo']) {
            if (Object.prototype.hasOwnProperty.call(group['musicInfo'], prop)) {
                fillArtist(group['musicInfo'][prop], mapping[prop]);
            }
        }
    }

    // fill out group info
    jsonFill(group, {
        record_label: 'recordLabel',
        catalogue_number: 'catalogueNumber',
        album_desc: 'wikiBBcode',
    });

    // ideally, the torrent json contains a releaseName field that is human readable,
    // and through which we can correspond it to our site's dropdown. Otherwise,
    // we assume they are using the original WCD category IDs, which is augmented
    // for specific well-known sources.
    let releaseTypeName = group['releaseTypeName'] || group['releaseName'];
    if (!releaseTypeName) {
        const releaseTypes = {
            1: 'Album',
            3: 'Soundtrack',
            5: 'EP',
            6: 'Anthology',
            7: 'Compilation',
            9: 'Single',
            11: 'Live album',
            13: 'Remix',
            14: 'Bootleg',
            15: 'Interview',
            16: 'Mixtape',
            21: 'Unknown',
        };
        if (source === 'red') {
            Object.assign(releaseTypes, {
                17: 'Demo',
                18: 'Concert Recording',
                19: 'DJ Mix',
            });
        }

        releaseTypeName = releaseTypes[group['releaseType']];
    }

    if (releaseTypeName) {
        $("#releasetype option")
            .filter(function() { return $(this).text().toLowerCase() === releaseTypeName.toLowerCase(); })
            .prop('selected', true)
            .trigger('change');
    } else {
        $("#releasetype").val($("#releasetype option:first").val());
    }


    // fill out torrent info
    jsonFill(torrent, {
        media: 'media',
    });

    setAllowedFormat('#format', '#bitrate');

    jsonFill(torrent, {
        format: 'format',
    });

    setAllowedBitrate('#format', '#bitrate');

    jsonFill(torrent, {
        bitrate: 'bitrate',
    });

    // deal with potential "string" bools
    if (torrent['scene'] && torrent['scene'] !== 'false') {
        $('#scene').prop('checked', torrent['scene']);
    }

    if (torrent['remastered'] && torrent['remastered'] !== 'false') {
        $('#remaster').prop('checked', true).triggerHandler('click');
        jsonFill(torrent, {
            remaster_year: 'remasterYear',
            remaster_title: 'remasterTitle',
            remaster_record_label: 'remasterRecordLabel',
            remaster_catalogue_number: 'remasterCatalogueNumber',
        });
    }
}

function fillForm(group, torrent, source) {
    jsonFill(group, {
        title: 'name',
        year: 'year',
        image: 'wikiImage',
    });

    if (!group['categoryName'] || group['categoryName'] === 'Music') {
        fillMusicForm(group, torrent, source);
    }
    else if (group['categoryName'] === 'Comedy') {
        jsonFill(torrent, {
            format: 'format',
        });
    }

    // other columns
    fillEncodingField(torrent['encoding']);
    if (group['tags']) {
        $('#tags').val(Object.values(group['tags']).filter(f => f != "").join(', '));
    }
    if (torrent['description']) {
        // This does not get converted to HTML in the ajax endpoint
        $('#release_desc').val(torrent['description']);
    }

    // reset the file input
    let el = $('#torrent-json-file');
    el.wrap('<form>').closest('form').get(0).reset();
    el.unwrap();
}

function WaitForCategory(callback) {
    setTimeout(function() {
        let dynamic_form = $('#dynamic_form');
        if (dynamic_form.data('loaded') === true) {
            dynamic_form.data('loaded', false);
            callback();
        }
        else {
            setTimeout(function(){WaitForCategory(callback);}, 400);
        }
    }, 100);
}

function insertParsedJson(data, source) {
    const group = data['response']['group'];
    let torrent = data['response']['torrent'];
    if (Array.isArray(torrent)) {
        torrent = torrent[0];
    }

    const categories_mapping = {
        'Music': 0,
        'Applications': 1,
        'E-Books': 2,
        'Audiobooks': 3,
        'E-Learning Videos': 4,
        'Comedy': 5,
        'Comics': 6
    };

    const categories = $('#categories');
    if (!group['categoryName']) {
        group['categoryName'] = 'Music';
    }
    categories.val(categories_mapping[group['categoryName']]).triggerHandler('change');

    if (torrent['media'] == 'Blu-Ray') {
        torrent['media'] = 'BD';
    }

    function completeFill() {
        // delay for the form to change before filling it
        WaitForCategory(function() {
            fillForm(group, torrent, source);
        });
    }

    // ideally we are getting the JSON file from an endpoint that does not make use reverse
    // HTML back into BBCode. We use `wikiBBcode` here, RED uses `bbBody`.
    if (group['wikiBBcode'] === undefined) {
        if (group['bbBody'] !== undefined) {
            group['wikiBBcode'] = group['bbBody'];
            completeFill();
        } else if (group['wikiBody']) {
            // worst case, we only have some rendered html code, ask the server to convert it back to bbcode
            $.post('upload.php?action=parse_html',
                {'html': group['wikiBody']}
            ).done((response) => {
                group['wikiBBcode'] = response.responseText;
            }).fail((xhr) => {
                alert("Error parsing the torrent group description.");
                console.error(xhr);
                group['wikiBBcode'] = '';
            }).always(() => {
                completeFill();
            });
        } else {
            group['wikiBBcode'] = '';
            completeFill();
        }
    } else {
        completeFill();
    }
}

function ParseUploadJson() {
    const reader = new FileReader();
    let source;
    reader.addEventListener('load', function() {
        try {
            let data = JSON.parse(reader.result.toString());
            data = unescapeStrings(data); // deal with legacy JS files that have html entities
            insertParsedJson(data, source);
        }
        catch (e) {
            alert("Failed to parse JSON file.");
            console.error(e);
        }
    }, false);

    const file = document.getElementById('torrent-json-file').files[0];
    if (file) {
        if (file.name.endsWith('[redacted.sh].json')) {
            source = 'red';
        }
        reader.readAsText(file);
    }
}

function createUploadWarningElement(warnings) {
    const text = "Your torrent was successfully uploaded. However, " +
        "there were some problems:";
    const el = document.createElement('div');
    el.classList.add('upload-warnings');
    const textbox = document.createElement('span');
    textbox.innerText = text;
    const warnList = document.createElement('ul');
    for (const warning of warnings) {
        const li = document.createElement('li');
        li.innerHTML = warning;
        warnList.appendChild(li);
    }
    el.append(textbox, warnList);
    return el;
}

/**
 * Recursively iterate over the whole parsed JSON object and remove all html escapes
 * in strings (excluding object keys).
 */
function unescapeStrings(json_obj) {
    if (!json_obj) {
        return json_obj;
    } else if (Array.isArray(json_obj)) {
        json_obj.forEach((e, i) => {
            json_obj[i] = unescapeStrings(e);
        });
    } else if (typeof json_obj === 'object') {
        for (const [key, value] of Object.entries(json_obj)) {
            // wikiBody is supposed to be html, wikiBBcode was generated by our gazelle fork
            // and should not contain html entities (unless the uploader made an error)
            if (key !== 'wikiBody' && key !== 'wikiBBcode') {
                json_obj[key] = unescapeStrings(value);
            }
        }
    } else if (typeof json_obj === 'string') {
        return htmlDecode(json_obj);
    }
    return json_obj;
}

/**
 * convert html entities to utf-8 characters
 */
function htmlDecode(string) {
    const doc = new DOMParser().parseFromString(string, 'text/html');
    return doc.documentElement.textContent;
}

function checkFields() {
    let error = 0;
    // When your DOM is historically fucked up...
    const is_new_grp = document.getElementById('torrent-new')?.value === "1";
    const is_music = document.getElementById('categories')?.value  === "0";
    const is_upload = document.querySelector('input[type="hidden"][value="takeedit"]') === null;

    const checkEl = document.getElementById('check');
    checkEl.replaceChildren();

    if (is_upload) {
        if (!document.getElementById('file').value) {
            ++error;
            checkEl.innerHTML += '<li>No torrent file specified, no-one will be able to download from you.</li>';
        }
        if (is_new_grp) {
            if (!document.getElementById('title').value) {
                ++error;
                checkEl.innerHTML += '<li>No title specified.</li>';
            }
            if (document.getElementById('tags').value === '') {
                ++error;
                checkEl.innerHTML += '<li>You must add at least one tag that describes this release.</li>';
            }
        }
    }

    if (is_music) {
        if (is_new_grp) {  // implies is_upload
            if (!document.getElementById('artist_0').value) {
                ++error;
                checkEl.innerHTML += '<li>No artist specified. There must be at least one Main artist.</li>';
            }
            if (document.getElementById('releasetype').value === '---') {
                ++error;
                checkEl.innerHTML += '<li>Release type (Album, EP, ...) not specified.</li>';
            }
            if (document.getElementById('year').value === '') {
                ++error;
                checkEl.innerHTML += '<li>No year specified. When was this released?</li>';
            }
            if (document.getElementById('album_desc').value === '') {
                ++error;
                checkEl.innerHTML += '<li>You need to add a description to this release (which also improves its chance of being snatched).</li>';
            }
        }
        if (is_upload) {
            const remYearEl = document.getElementById('remaster_year');
            if (!remYearEl.disabled && remYearEl.value === '') {
                ++error;
                checkEl.innerHTML += '<li>You must specify at minimum the edition year. Label and catalogue number are highly encouraged.</li>';
            }
        }
        if (document.getElementById('media').value === '---') {
            ++error;
            checkEl.innerHTML += '<li>Media type (CD, WEB, ...) not specified.</li>';
        }
        if (document.getElementById('format').value === '---') {
            ++error;
            checkEl.innerHTML += '<li>Format (FLAC, MP3, ...) not specified.</li>';
        }
        if (document.getElementById('bitrate').value === '---') {
            ++error;
            checkEl.innerHTML += '<li>Bitrate (Lossless, 320, V0 (VBR), ...) not specified.</li>';
        }
        if (LogCount > 1 && !document.getElementById('upload_logs').classList.contains('hidden')) {
            let missing = 0;
            for (let i = 1; i <= LogCount; i++) {
                if (!document.getElementById('logfile_' + i)?.value) {
                    ++missing;
                }
            }
            if (missing) {
                ++error;
                checkEl.innerHTML += '<li>You specified that there was a logfile for this rip but did not add it '
                    + `(Expected: ${LogCount}, missing: ${missing}).</li>`;
            }
        }
    }

    if (error) {
        if (is_music) {
            checkEl.innerHTML += '<li>Using <b>YADG</b> solves many such problems automatically, check it out in the forums.</li>';
        }
        checkEl.innerHTML += '<li><strong class="important_text">You must correct the above problems.</strong></li>';
        checkEl.style.display = '';
    } else {
        checkEl.style.display = 'none';
    }

    return error === 0;
}

function musicFormInit() {
    $('#torrent-json-file').change(function () {
        ParseUploadJson();
    });
    $('#media').change(function () {
        changeMedia();
    });
    $('#format').change(function () {
        setAllowedFormat('#format', '#bitrate');
    });
    $('#bitrate').change(function () {
        setAllowedBitrate('#format', '#bitrate');
    });
    $('#add_format').click(function () {
        addFormatRow();
    });
    $('#remove_format').click(function () {
        removeFormatRow();
    });
    $('#other_bitrate_span').click(function () {
        AltBitrate();
    });
    ArtistCount      = 0;
    ArtistJsonCount  = 0;
    ExtraFormatCount = 0;

    // the form starts with one logfile field
    LogCount         = 1;
}

function audiobookFormInit() {
    $('#format').change(function () {
        setAllowedFormat('#format', '#bitrate');
    });
    $('#bitrate').change(function () {
        setAllowedBitrate('#format', '#bitrate');
    });
}

function uploadFormInit(firstInit = false) {
    if (document.getElementById('form-music-upload')) {
        musicFormInit();
    } else if (document.getElementById('form-audiobook-upload')) {
        audiobookFormInit();
    }

    if (firstInit) {
        // create a handler to submit upload form data to ajax.php?action=upload
        // will display errors or redirect to the uploaded torrent, or filled request, on success
        document.getElementById('upload_table').addEventListener('submit', (ev) => {
            if (!checkFields()) {
                ev.preventDefault();
                return;
            }
            const target = ev.target;
            const submit_btn = document.getElementById('post');
            submit_btn.disabled = true;
            if (target.classList.contains('edit_form')) {
                // edit form, not upload
                return;
            }
            ev.preventDefault();

            const uploadForm = new FormData(target);
            const request = new XMLHttpRequest();

            request.addEventListener('loadend', (ev) => {
                const response = ev.target.response;
                if (!response || response['status'] !== "success") {  // error
                    const errorElem = document.getElementById('check');
                    const li = document.createElement("li");
                    if (response && response['error']) {
                        li.innerHTML = response['error'];
                    } else {
                        console.log("server response", ev.target);
                        li.innerText = "There was an error uploading your torrent. Please try again.";
                    }
                    errorElem.replaceChildren(li);
                    errorElem.style.display = 'inherit';
                    submit_btn.disabled = false;
                } else {  // success
                    const resp = response['response'];
                    let url;
                    if (resp['fillRequest']) {
                        url = 'requests.php?action=view&id=' + resp['fillRequest']['requestId'];
                    } else {
                        const gid = resp['groupId'];
                        const tid = resp['torrentId'];
                        url = `torrents.php?id=${gid}&torrentid=${tid}#torrent${tid}`;
                    }
                    if (resp['warnings']?.length) {  // show warning popup
                        const callback = () => {
                            window.location.href = url;
                        };
                        showWarningMessage(createUploadWarningElement(resp['warnings']), callback);
                    } else {  // no problems, redirect to newly uploaded torrent or filled request
                        window.location.href = url;
                    }
                }
            });

            request.open('POST', 'ajax.php?action=upload');
            request.responseType = 'json';
            request.send(uploadForm);
        });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    uploadFormInit(true);
});
