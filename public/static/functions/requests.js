function Vote(amount, requestid) {
    if (typeof amount == 'undefined') {
        amount = parseInt($('#amount').raw().value);
    }
    if (amount == 0) {
        amount = 100 * 1024 * 1024;
    }

    var index;
    var votecount;
    if (!requestid) {
        requestid = $('#requestid').raw().value;
        votecount = $('#votecount').raw();
        index = false;
    } else {
        votecount = $('#vote_count_' + requestid).raw();
        bounty = $('#bounty_' + requestid).raw();
        index = true;
    }

    if (amount > 100 * 1024 * 1024) {
        upload = $('#current_uploaded').raw().value;
        download = $('#current_downloaded').raw().value;
        rr = $('#current_rr').raw().value;
        if (amount > 0.3 * (upload - rr * download)) {
            if (!confirm('This vote is more than 30% of your buffer. Please confirm that you wish to place this large of a vote.')) {
                return false;
            }
        }
    }

    ajax.get('requests.php?action=takevote&id=' + requestid + '&auth=' + authkey + '&amount=' + amount, function (response) {
        if (response == 'bankrupt') {
            error_message("You do not have sufficient upload credit to add " + byte_format(amount, 0) + " to this request");
            return;
        } else if (response == 'missing') {
            error_message("Cannot find this request");
            return;
        } else if (response == 'filled') {
            error_message("This request has already been filled");
            return;
        } else if (response == 'success') {
            votecount.innerHTML = (parseInt(votecount.innerHTML)) + 1;
        } else {
            error_message("Error on saving request vote. Please try again later.");
            return;
        }

        if ($('#total_bounty').results() > 0) {
            totalBounty = parseInt($('#total_bounty').raw().value);
            totalBounty += (amount * (1 - $('#request_tax').raw().value));
            var requestTax = $('#request_tax').raw().value;
            $('#total_bounty').raw().value = totalBounty;
            $('#formatted_bounty').raw().innerHTML = byte_format(totalBounty);
            if (requestTax > 0) {
                save_message("Your vote of " + byte_format(amount, 0) + ", adding a " + byte_format(amount * (1 - $('#request_tax').raw().value), 0) + " bounty, has been added");
            } else {
                save_message("Your vote of " + byte_format(amount, 0) + " has been added");
            }
            $('#button').raw().disabled = true;
        } else {
            save_message("Your vote of " + byte_format(amount, 0) + " has been added");
        }
    });
}

function Calculate() {
    var mul = (($('#unit').raw().options[$('#unit').raw().selectedIndex].value == 'mb') ? (1024*1024) : (1024*1024*1024));
    var amt = Math.floor($('#amount_box').raw().value * mul);
    if (amt > $('#current_uploaded').raw().value) {
        $('#new_uploaded').raw().innerHTML = "You can't afford that request!";
        $('#new_bounty').raw().innerHTML = "0 MiB";
        $('#bounty_after_tax').raw().innerHTML = "0 MiB";
        $('#button').raw().disabled = true;
    } else if (isNaN($('#amount_box').raw().value)
            || (window.location.search.indexOf('action=new') != -1 && $('#amount_box').raw().value * mul < 100 * 1024 * 1024)
            || (window.location.search.indexOf('action=view') != -1 && $('#amount_box').raw().value * mul < 100 * 1024 * 1024)) {
        $('#new_uploaded').raw().innerHTML = byte_format(($('#current_uploaded').raw().value), 2);
        $('#new_bounty').raw().innerHTML = "0 MiB";
        $('#bounty_after_tax').raw().innerHTML = "0 MiB";
        $('#button').raw().disabled = true;
    } else {
        $('#button').raw().disabled = false;
        $('#amount').raw().value = amt;
        $('#new_uploaded').raw().innerHTML = byte_format(($('#current_uploaded').raw().value) - amt, 2);
        $('#new_ratio').raw().innerHTML = ratio($('#current_uploaded').raw().value - amt, $('#current_downloaded').raw().value);
        $('#new_bounty').raw().innerHTML = byte_format(mul * $('#amount_box').raw().value, 0);
        $('#bounty_after_tax').raw().innerHTML = byte_format(mul * (1 - $('#request_tax').raw().value) * $('#amount_box').raw().value, 0);
    }
}

function AddArtistField() {
    var ArtistCount = document.getElementsByName("artists[]").length;
    if (ArtistCount >= 200) {
        return;
    }
    var ArtistField = document.createElement("input");
    ArtistField.type = "text";
    ArtistField.id = "artist_" + ArtistCount;
    ArtistField.name = "artists[]";
    ArtistField.size = 45;
    ArtistField.onblur = CheckVA;

    var roleField = document.createElement("select");
    roleField.id = "importance";
    roleField.name = "importance[]";
    roleField.options[0] = new Option("Main", "1");
    roleField.options[1] = new Option("Guest", "2");
    roleField.options[2] = new Option("Composer", "4");
    roleField.options[3] = new Option("Conductor", "5");
    roleField.options[4] = new Option("DJ / Compiler", "6");
    roleField.options[5] = new Option("Remixer", "3");
    roleField.options[6] = new Option("Producer", "7");
    roleField.options[7] = new Option("Arranger", "8");

    var x = $('#artistfields').raw();
    x.appendChild(document.createElement("br"));
    x.appendChild(ArtistField);
    x.appendChild(document.createTextNode('\n'));
    x.appendChild(roleField);

    if ($("#artist_0").data("gazelle-autocomplete")) {
        $(ArtistField).live('focus', function() {
            $(ArtistField).autocomplete({
                serviceUrl : 'artist.php?action=autocomplete'
            });
        });
    }
}

function CheckVA () {
    var ArtistCount = document.getElementsByName("artists[]").length;
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

function RemoveArtistField() {
    var ArtistCount = document.getElementsByName("artists[]").length;
    if (ArtistCount === 1) {
        return;
    }
    var x = $('#artistfields').raw();

    while (x.lastChild.tagName !== "INPUT") {
        x.removeChild(x.lastChild);
    }
    x.removeChild(x.lastChild);
    x.removeChild(x.lastChild); //Remove trailing new line.
    ArtistCount--;
}

function Categories() {
    var cat = $('#categories').raw().options[$('#categories').raw().selectedIndex].value;
    if (cat == "Music") {
        $('#artist_tr').gshow();
        $('#releasetypes_tr').gshow();
        $('#formats_tr').gshow();
        $('#bitrates_tr').gshow();
        $('#media_tr').gshow();
        ToggleLogCue();
        $('#year_tr').gshow();
        $('#cataloguenumber_tr').gshow();
        $('#recordlabel_tr').gshow();
        $('#oclc_tr').gshow();
    } else if (cat == "Audiobooks" || cat == "Comedy") {
        $('#year_tr').gshow();
        $('#artist_tr').ghide();
        $('#releasetypes_tr').ghide();
        $('#formats_tr').ghide();
        $('#bitrates_tr').ghide();
        $('#media_tr').ghide();
        $('#logcue_tr').ghide();
        $('#cataloguenumber_tr').ghide();
        $('#recordlabel_tr').ghide();
        $('#oclc_tr').ghide();
    } else {
        $('#artist_tr').ghide();
        $('#releasetypes_tr').ghide();
        $('#formats_tr').ghide();
        $('#bitrates_tr').ghide();
        $('#media_tr').ghide();
        $('#logcue_tr').ghide();
        $('#year_tr').ghide();
        $('#cataloguenumber_tr').ghide();
        $('#recordlabel_tr').ghide();
        $('#oclc_tr').ghide();
    }
}

function add_tag() {
    if ($('#tags').raw().value == "") {
        $('#tags').raw().value = $('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value;
    } else if ($('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value == "---") {
    } else {
        $('#tags').raw().value = $('#tags').raw().value + ", " + $('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value;
    }
}

function Toggle(id, disable) {
    var arr = document.getElementsByName(id + '[]');
    var master = $('#toggle_' + id).raw().checked;
    for (var x in arr) {
        arr[x].checked = master;
        if (disable == 1) {
            arr[x].disabled = master;
        }
    }

    if (id === "formats" || id === "media") {
        ToggleLogCue();
    }
}

function ToggleLogCue() {
    var formats = document.getElementsByName('formats[]');
    var media   = document.getElementsByName('media[]');
    var flac    = formats[1].checked;
    var cd      = media[0].checked;

    if (flac && cd) {
        $('#logcue_tr').gshow();
    } else {
        $('#logcue_tr').ghide();
    }
    ToggleLogScore();
}

function ToggleLogScore() {
    if ($('#needlog').raw().checked) {
        $('#minlogscore_span').gshow();
    } else {
        $('#minlogscore_span').ghide();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const amountBox = document.getElementById('amount_box');
    if (amountBox) {
        amountBox.addEventListener('input', function() {
            Calculate();
        });
        Calculate();
    }
});
