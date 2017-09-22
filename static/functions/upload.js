function Categories() {
    ajax.get('ajax.php?action=upload_section&categoryid=' + $('#categories').raw().value, function (response) {
        $('#dynamic_form').raw().innerHTML = response;
        initMultiButtons();
        // Evaluate the code that generates previews.
        eval($('#dynamic_form script.preview_code').html());
    });
}

function Remaster() {
    if ($('#remaster').raw().checked) {
        $('#remaster_true').gshow();
    } else {
        $('#remaster_true').ghide();
    }
}

function Format() {
    var format = $('#format');
    var bitrate = $('#bitrate');
    if (format.raw().options[format.raw().selectedIndex].value === 'FLAC') {
        for (var i = 0; i < bitrate.raw().options.length; i++) {
            if (bitrate.raw().options[i].value === 'Lossless') {
                bitrate.raw()[i].selected = true;
            }
        }
        $('#upload_logs').gshow();
        $('#other_bitrate_span').ghide();
    } else {
        $('#bitrate').raw()[0].selected = true;
        $('#upload_logs').ghide();
    }

    var format_warning = $('#format_warning');
    if (format_warning.raw()) {
		if (format.raw().options[format.raw().selectedIndex].value === 'AAC') {
			format_warning.raw().innerHTML = 'AAC torrents may only be uploaded if they represent editions unavailable on APOLLO in any other format sourced from the same medium and edition <a href="rules.php?p=upload#r2.1.24">(2.1.24)</a>';
		} else {
			format_warning.raw().innerHTML = '';
		}
    }
}

function Bitrate() {
    var bitrate = $('#bitrate');
    $('#other_bitrate').raw().value = '';
    if (bitrate.raw().options[bitrate.raw().selectedIndex].value === 'Other') {
        $('#other_bitrate_span').gshow();
    } else {
        $('#other_bitrate_span').ghide();
    }
}

function AltBitrate() {
    if ($('#other_bitrate').raw().value >= 320) {
        $('#vbr').raw().disabled = true;
        $('#vbr').raw().checked = false;
    } else {
        $('#vbr').raw().disabled = false;
    }
}

function add_tag() {
    if ($('#tags').raw().value == "") {
        $('#tags').raw().value = $('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value;
    } else if ($('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value === '---') {
    } else {
        $('#tags').raw().value = $('#tags').raw().value + ', ' + $('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value;
    }
}

var LogCount = 1;

function AddLogField() {
    if (LogCount >= 200) {
        return;
    }
    var LogField = document.createElement("input");
    LogField.type = "file";
    LogField.id = "file";
    LogField.name = "logfiles[]";
    LogField.size = 50;
    var x = $('#logfields').raw();
    x.appendChild(document.createElement("br"));
    x.appendChild(LogField);
    LogCount++;
}

function RemoveLogField() {
    if (LogCount == 1) {
        return;
    }
    var x = $('#logfields').raw();
    for (i = 0; i < 2; i++) {
        x.removeChild(x.lastChild);
    }
    LogCount--;
}

var ExtraLogCount = 1;

function AddExtraLogField(id) {
    if (LogCount >= 200) {
        return;
    }
    var LogField = document.createElement("input");
    LogField.type = "file";
    LogField.id = "file_" + id;
    LogField.name = "logfiles_" + id + "[]";
    LogField.size = 50;
    var x = $('#logfields_' + id).raw();
    x.appendChild(document.createElement("br"));
    x.appendChild(LogField);
    LogCount++;
}

function RemoveLogField() {
    if (LogCount == 1) {
        return;
    }
    var x = $('#logfields').raw();
    for (i = 0; i < 2; i++) {
        x.removeChild(x.lastChild);
    }
    LogCount--;
}

var FormatCount = 0;

function AddFormat() {
    if (FormatCount >= 10) {
        return;
    }
    FormatCount++;
    $('#extras').raw().value = FormatCount;

    var NewRow = document.createElement("tr");
    NewRow.id = "new_torrent_row"+FormatCount;
    NewRow.setAttribute("style","border-top-width: 5px; border-left-width: 5px; border-right-width: 5px;");

    var NewCell1 = document.createElement("td");
    NewCell1.setAttribute("class","label");
    NewCell1.innerHTML = "Extra Torrent File";

    var NewCell2 = document.createElement("td");
    var TorrentField = document.createElement("input");
    TorrentField.type = "file";
    TorrentField.id = "extra_torrent_file"+FormatCount;
    TorrentField.name = "extra_torrent_files[]";
    TorrentField.size = 50;
    NewCell2.appendChild(TorrentField);

    NewRow.appendChild(NewCell1);
    NewRow.appendChild(NewCell2);

    NewRow = document.createElement("tr");
    NewRow.id = "new_format_row"+FormatCount;
    NewRow.setAttribute("style","border-left-width: 5px; border-right-width: 5px;");
    NewCell1 = document.createElement("td");
    NewCell1.setAttribute("class","label");
    NewCell1.innerHTML = "Extra Format / Bitrate";

    NewCell2 = document.createElement("td");
    tmp = '<select id="releasetype" name="extra_formats[]"><option value="">---</option>';
    var formats=["Saab","Volvo","BMW"];
    for (var i in formats) {
        tmp += '<option value="'+formats[i]+'">'+formats[i]+"</option>\n";
    }
    tmp += "</select>";
    var bitrates=["1","2","3"];
    tmp += '<select id="releasetype" name="extra_bitrates[]"><option value="">---</option>';
    for (var i in bitrates) {
        tmp += '<option value="'+bitrates[i]+'">'+bitrates[i]+"</option>\n";
    }
    tmp += "</select>";

    NewCell2.innerHTML = tmp;
    NewRow.appendChild(NewCell1);
    NewRow.appendChild(NewCell2);


    NewRow = document.createElement("tr");
    NewRow.id = "new_description_row"+FormatCount;
    NewRow.setAttribute("style","border-bottom-width: 5px; border-left-width: 5px; border-right-width: 5px;");
    NewCell1 = document.createElement("td");
    NewCell1.setAttribute("class","label");
    NewCell1.innerHTML = "Extra Release Description";

    NewCell2 = document.createElement("td");
    NewCell2.innerHTML = '<textarea name="extra_release_desc[]" id="release_desc" cols="60" rows="4"></textarea>';

    NewRow.appendChild(NewCell1);
    NewRow.appendChild(NewCell2);
}

function RemoveFormat() {
    if (FormatCount == 0) {
        return;
    }
    $('#extras').raw().value = FormatCount;

    var x = $('#new_torrent_row'+FormatCount).raw();
    x.parentNode.removeChild(x);

    x = $('#new_format_row'+FormatCount).raw();
    x.parentNode.removeChild(x);

    x = $('#new_description_row'+FormatCount).raw();
    x.parentNode.removeChild(x);

    FormatCount--;
}


var ArtistCount = 1;

function AddArtistField() {
    if (ArtistCount >= 200) {
        return;
    }
    var ArtistField = document.createElement("input");
    ArtistField.type = "text";
    ArtistField.id = "artist_" + ArtistCount;
    ArtistField.name = "artists[]";
    ArtistField.size = 45;
    ArtistField.onblur = CheckVA;

    var ImportanceField = document.createElement("select");
    ImportanceField.id = "importance_" + ArtistCount;
    ImportanceField.name = "importance[]";
    ImportanceField.options[0] = new Option("Main", "1");
    ImportanceField.options[1] = new Option("Guest", "2");
    ImportanceField.options[2] = new Option("Composer	", "4");
    ImportanceField.options[3] = new Option("Conductor", "5");
    ImportanceField.options[4] = new Option("DJ / Compiler", "6");
    ImportanceField.options[5] = new Option("Remixer", "3");
    ImportanceField.options[6] = new Option("Producer", "7");

    var x = $('#artistfields').raw();
    x.appendChild(document.createElement("br"));
    x.appendChild(ArtistField);
    x.appendChild(document.createTextNode('\n'));
    x.appendChild(ImportanceField);

    if ($("#artist_0").data("gazelle-autocomplete")) {
        $(ArtistField).live('focus', function() {
            $(ArtistField).autocomplete({
                serviceUrl : 'artist.php?action=autocomplete'
            });
        });
    }

    ArtistCount++;
}

function RemoveArtistField() {
    if (ArtistCount === 1) {
        return;
    }
    var x = $('#artistfields').raw();
    for (i = 0; i < 4; i++) {
        x.removeChild(x.lastChild);
    }
    ArtistCount--;
}


function CheckVA () {
	var shown = false;
	for (var i = 0; i < ArtistCount; i++) {
        var artistId = "#artist_" + i;
        if ($(artistId).raw().value.toLowerCase().trim().match(/^(va|various(\sa|a)rtis(t|ts)|various)$/)) {
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
    var media = $('#media').raw().options[$('#media').raw().selectedIndex].text;
    if (media == "---" || media == "Vinyl" || media == "Soundboard" || media == "Cassette") {
        media = "old";
    }
    var year = $('#year').val();
    var unknown = $('#unknown').prop('checked');
    if (year < 1982 && year != '' && media != "old" && !unknown) {
        $('#yearwarning').gshow();
        $('#remaster').raw().checked = true;
        $('#remaster_true').gshow();
    } else if (unknown) {
        $('#remaster').raw().checked = true;
        $('#yearwarning').ghide();
        $('#remaster_true').gshow();
    } else {
        $('#yearwarning').ghide();
    }
}

function ToggleUnknown() {
    if ($('#unknown').raw().checked) {
        $('#remaster_year').raw().value = "";
        $('#remaster_title').raw().value = "";
        $('#remaster_record_label').raw().value = "";
        $('#remaster_catalogue_number').raw().value = "";

        if ($('#groupremasters').raw()) {
            $('#groupremasters').raw().selectedIndex = 0;
            $('#groupremasters').raw().disabled = true;
        }

        $('#remaster_year').raw().disabled = true;
        $('#remaster_title').raw().disabled = true;
        $('#remaster_record_label').raw().disabled = true;
        $('#remaster_catalogue_number').raw().disabled = true;
    } else {
        $('#remaster_year').raw().disabled = false;
        $('#remaster_title').raw().disabled = false;
        $('#remaster_record_label').raw().disabled = false;
        $('#remaster_catalogue_number').raw().disabled = false;

        if ($('#groupremasters').raw()) {
            $('#groupremasters').raw().disabled = false;
        }
    }
}

function GroupRemaster() {
    var remasters = json.decode($('#json_remasters').raw().value);
    var index = $('#groupremasters').raw().options[$('#groupremasters').raw().selectedIndex].value;
    if (index != "") {
        $('#remaster_year').raw().value = remasters[index][1];
        $('#remaster_title').raw().value = remasters[index][2];
        $('#remaster_record_label').raw().value = remasters[index][3];
        $('#remaster_catalogue_number').raw().value = remasters[index][4];
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
function FillInFields(mapping, data) {
    for (var prop in mapping) {
        if (!mapping.hasOwnProperty(prop)) {
            continue;
        }
        if (data[mapping[prop]] && data[mapping[prop]] !== '') {
            $('#' + prop).val(data[mapping[prop]]).trigger('change');
        }
    }
}

function AddArtist(array, importance, cnt) {
    for (var i = 0; i < array.length; i++) {
        var artist_id = (cnt > 0) ? 'artist_' + cnt : 'artist';
        var importance_id = (cnt > 0) ? 'importance_' + cnt : 'importance';
        if (array[i]['name']) {
			$('#' + artist_id).val(array[i]['name']);
			$('#' + importance_id).val(importance);
			AddArtistField();
			cnt++;
        }
    }
    return cnt;
}

function ParseEncoding(encoding) {
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

function ParseMusicJson(group, torrent) {
    var cnt = 0;

	// JSON property to HTML value for importance
	var mapping = {
		artists: 1,
		with: 2,
		composers: 4,
		conductor: 5,
		dj: 6,
		remixedBy: 3,
		producer: 7
	};

    if (group['musicInfo']) {
		for (var prop in group['musicInfo']) {
		    if (!group['musicInfo'].hasOwnProperty(prop)) {
		        continue;
            }
            cnt = AddArtist(group['musicInfo'][prop], mapping[prop], cnt);
		}
    }

    // HTML ID to JSON key for group data
    mapping = {
		record_label: 'recordLabel',
		releasetype: 'releaseType',
		catalogue_number: 'catalogueNumber'
	};

	FillInFields(mapping, group);

	// HTML ID to JSON key for torrent data
	mapping = {
		format: 'format',
		media: 'media'
    };
	FillInFields(mapping, torrent);

	ParseEncoding(torrent['encoding']);

    if (torrent['scene']) {
        $('#scene').prop('checked', torrent['scene']);
    }

    if (torrent['remastered'] === true) {
        $('#remaster').prop('checked', true);
        mapping = {
            remaster_year: 'remasterYear',
			remaster_title: 'remasterTitle',
			remaster_record_label: 'remasterRecordLabel',
			remaster_catalogue_number: 'remasterCatalogueNumber'
        };
        FillInFields(mapping, torrent);
    }
}

function ParseForm(group, torrent) {
	var mapping = {
		title: 'name',
		year: 'year',
		image: 'wikiImage'
	};
	FillInFields(mapping, group);

	if (!group['categoryName'] || group['categoryName'] === 'Music') {
		ParseMusicJson(group, torrent);
	}
	else if (group['categoryName'] === 'Comedy') {
        mapping = {
            format: 'format'
        };
	    FillInFields(mapping, torrent);
        ParseEncoding(torrent['encoding']);
	}

	// special columns
	if (group['tags']) {
		$('#tags').val(group['tags'].join(','));
	}

	if (group['wikiBody']) {
		$.post('upload.php?action=parse_html',
			{
				'html': group['wikiBody']
			},
			function(response) {
				$('#album_desc').val(response);
				$('#desc').val(response);
			}
		);
	}

	if (torrent['description']) {
		$.post('upload.php?action=parse_html',
			{'html': torrent['description']},
			function(response) {
				$('#release_desc').val(response);
			}
		);
	}

	// reset the file input
	var el = $('#torrent-json-file');
	el.wrap('<form>').closest('form').get(0).reset();
	el.unwrap();
}

function ParseUploadJson() {
    var reader = new FileReader();

    reader.addEventListener('load', function() {
        try {
			var data = JSON.parse(reader.result.toString());
			var group = data['response']['group'];
			var torrent = data['response']['torrent'];

			var categories_mapping = {
			    'Music': 0,
                'Applications': 1,
			    'E-Books': 2,
                'Audiobooks': 3,
                'E-Learning Videos': 4,
                'Comedy': 5,
                'Comics': 6
            };

			var categories = $('#categories');
			categories.val((categories.val() + 1) % 7).trigger('change');
			setTimeout(function() {
				if (!group['categoryName']) {
					group['categoryName'] = 'Music';
				}
				categories.val(categories_mapping[group['categoryName']]).trigger('change');
				// delay for the form to change before filling it
				setTimeout(function() { ParseForm(group, torrent); }, 100);
			});
        }
        catch (e) {
            alert("Could not read file. Please try again.");
            console.log(e);
        }
    }, false);

    var file = $('#torrent-json-file')[0].files[0];
    if (file) {
        reader.readAsText(file);
    }
}