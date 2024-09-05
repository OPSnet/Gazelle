/* global ajax, byte_format, error_message, ratio, save_message */

function Vote(requestid, amount, votecount, upload, download, rr) {
    if (amount > 100 * 1024 * 1024 && amount > 0.3 * (upload - rr * download)) {
        if (!confirm('This vote is more than 30% of your buffer. Please confirm that you wish to place this large of a vote.')) {
            return false;
        }
    }

    ajax.get('requests.php?action=takevote&id=' + requestid + '&auth=' + authkey + '&amount=' + amount, function (response) {
        response = JSON.parse(response);
        if (response.status == 'success') {
            let vote_count = document.getElementById('vote_count_' + response.id);
            if (!vote_count) {
                // we are an individual request page
                vote_count = document.getElementById('votecount');
            }
            vote_count.textContent = response.total.toLocaleString('en-US');
            let vote_link = document.querySelectorAll('[data-id="' + response.id + '"]');
            if (vote_link) {
                vote_link[0].onClick = null;
                vote_link[0].innerHTML = "&check;";
                vote_link[0].classList.remove('brackets');
            }
        } else if (response.status == 'bankrupt') {
            error_message("You do not have sufficient upload credit to add " + byte_format(amount, 0) + " to this request");
            return;
        } else if (response.status == 'missing') {
            error_message("Cannot find this request");
            return;
        } else if (response.status == 'filled') {
            error_message("This request has already been filled");
            return;
        } else {
            error_message("Error on saving request vote. Please try again later.");
            return;
        }

        if ($('#total_bounty').results() > 0) {
            let totalBounty = parseInt($('#total_bounty').raw().value);
            totalBounty += (amount * (1 - $('#request_tax').raw().value));
            let requestTax = $('#request_tax').raw().value;
            $('#total_bounty').raw().value = totalBounty;
            $('#formatted_bounty').raw().innerHTML = byte_format(totalBounty);
            if (requestTax > 0) {
                save_message("Your vote of " + byte_format(amount, 0) + ", adding a " + byte_format(amount * (1 - requestTax), 0) + " bounty, has been added");
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
    const box_val = document.getElementById('amount_box').value;
    const unit    = document.getElementById("unit");
    const mul     = unit.options[unit.selectedIndex].value == 'mb' ? 1024 ** 2 : 1024 ** 3;
    const amt     = Math.floor(box_val * mul);

    const current_upload_val = document.getElementById('current_uploaded').value;
    let bounty_after_tax     = document.getElementById('bounty_after_tax');
    let new_bounty           = document.getElementById('new_bounty');
    let new_uploaded         = document.getElementById('new_uploaded');
    let button               = document.getElementById('button');

    if (amt > current_upload_val) {
        new_uploaded.innerHTML     = "You can't afford that request!";
        new_bounty.innerHTML       = "0 MiB";
        bounty_after_tax.innerHTML = "0 MiB";
        button.disabled            = true;
    } else if (isNaN(box_val
        || (window.location.search.indexOf('action=new')  != -1 && amt < 100 * 1024 ** 2)
        || (window.location.search.indexOf('action=view') != -1 && amt < 100 * 1024 ** 2)
    )) {
        new_uploaded.innerHTML     = byte_format(current_upload_val, 2);
        new_bounty.innerHTML       = "0 MiB";
        bounty_after_tax.innerHTML = "0 MiB";
        button.disabled            = true;
    } else {
        new_uploaded.innerHTML     = byte_format(current_upload_val - amt, 2);
        new_bounty.innerHTML       = byte_format(amt, 2);
        bounty_after_tax.innerHTML = byte_format(
            amt * (1 - document.getElementById("request_tax").value),
            4
        );
        document.getElementById('amount').value = amt;
        document.getElementById('new_ratio').innerHTML = ratio(
            current_upload_val - amt,
            document.getElementById('current_downloaded').value
        );
        button.disabled = false;
    }
}

function AddArtistField() {
    let ArtistCount = document.getElementsByName("artists[]").length;
    if (ArtistCount >= 200) {
        return;
    }
    let ArtistField = document.createElement("input");
    ArtistField.type = "text";
    ArtistField.id = "artist_" + ArtistCount;
    ArtistField.name = "artists[]";
    ArtistField.size = 45;
    ArtistField.onblur = CheckVA;

    let roleField = document.createElement("select");
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

    let x = $('#artistfields').raw();
    x.appendChild(document.createElement("br"));
    x.appendChild(ArtistField);
    x.appendChild(document.createTextNode('\n'));
    x.appendChild(roleField);

    if ($("#artist_0").data("gazelle-autocomplete")) {
        $(ArtistField).live('focus', () => {
            $(ArtistField).autocomplete({
                serviceUrl : 'artist.php?action=autocomplete'
            });
        });
    }
}

function CheckVA () {
    let ArtistCount = document.getElementsByName("artists[]").length;
    let shown = false;
    for (let i = 0; i < ArtistCount; i++) {
        let artistId = "#artist_" + i;
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
    let ArtistCount = document.getElementsByName("artists[]").length;
    if (ArtistCount === 1) {
        return;
    }
    let x = $('#artistfields').raw();

    while (x.lastChild.tagName !== "INPUT") {
        x.removeChild(x.lastChild);
    }
    x.removeChild(x.lastChild);
    x.removeChild(x.lastChild); //Remove trailing new line.
    ArtistCount--;
}

function Categories() {
    let cat = $('#categories').raw().options[$('#categories').raw().selectedIndex].value;
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
    } else if (!($('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value == "---")) {
        $('#tags').raw().value = $('#tags').raw().value + ", " + $('#genre_tags').raw().options[$('#genre_tags').raw().selectedIndex].value;
    }
}

function Toggle(id, disable) {
    let arr = document.getElementsByName(id + '[]');
    let master = $('#toggle_' + id).raw().checked;
    for (let x in arr) {
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
    let formats = document.getElementsByName('formats[]');
    let media   = document.getElementsByName('media[]');
    let flac    = formats[1].checked;
    let cd      = media[0].checked;

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

document.addEventListener('DOMContentLoaded', () => {
    const amountBox = document.getElementById('amount_box');
    if (amountBox) {
        amountBox.addEventListener('input', () => {
            Calculate();
        });
        Calculate();
    }

    // from an individual request page
    const button = document.getElementById('button');
    if (button) {
        button.addEventListener('click', () => {
            const requestId = document.getElementById('requestid');
            if (requestId != null) {
                Vote(
                    parseInt(requestId.value),
                    parseFloat(document.getElementById('amount').value),
                    parseInt(document.getElementById('votecount').textContent),
                    parseInt(document.getElementById('current_uploaded').value),
                    parseInt(document.getElementById('current_downloaded').value),
                    parseFloat(document.getElementById('current_rr').value),
                );
            }
        });
    }

    // from a page that lists requests
    const voter = document.querySelectorAll('.request-vote');
    voter.forEach(function(span) {
        span.addEventListener('click', (e) => {
            Vote(
                parseInt(e.target.dataset.id),
                parseFloat(e.target.dataset.bounty),
                parseInt(e.target.dataset.n),
                parseInt(document.getElementById('current_uploaded').value),
                parseInt(document.getElementById('current_downloaded').value),
                parseFloat(document.getElementById('current_rr').value),
            );
        });
    });
});
