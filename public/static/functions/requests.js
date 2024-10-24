/* global byte_format, error_message, ratio, save_message */

"use strict";

(function() {
    const DETAIL_PAGE = 1;
    const VOTE_PAGE   = 2;

    async function add_bounty(source, requestid, amount, votecount, upload, download, rr) {
        if (amount > 100 * 1024 * 1024 && amount > 0.3 * (upload - rr * download)) {
            if (!confirm(
                'This vote is more than 30% of your buffer. Please confirm that you wish to place this large of a vote.'
            )) {
                return false;
            }
        }

        let response = await fetch('requests.php?action=takevote&id=' + requestid
            + '&auth=' + document.body.dataset.auth + '&amount=' + amount
            );
        let data = await response.json();
        if (data.status === 'bankrupt') {
            error_message("You do not have sufficient upload credit to add "
                + byte_format(amount, 0) + " to this request"
            );
            return;
        }
        if (data.status === 'missing') {
            error_message("Cannot find this request");
            return;
        }
        if (data.status === 'filled') {
            error_message("This request has already been filled");
            return;
        } else if (data.status !== 'success') {
            error_message("Error on saving request vote. Please try again later.");
            return;
        }

        let vote_id = false;
        if (source === DETAIL_PAGE) {
            document.getElementById('button').disabled = true;
            document.getElementById('formatted_bounty').innerHTML = byte_format(data.bounty);
            document.getElementById('new_bounty').innerHTML = byte_format(amount, 0);
            const tax_rate = document.getElementById('request_tax').value;
            const final    = amount * (1 - tax_rate);
            save_message(
                "Your vote of " + byte_format(amount, 0)
                + (tax_rate > 0
                    ?  ", adding a " + byte_format(final, 0) + " bounty,"
                    :  ''
                )
                + ' has been added'
            );
            vote_id = 'votecount';
        } else {
            let vote_link = document.querySelector('[data-id="' + data.id + '"]');
            vote_link.onClick = null;
            vote_link.innerHTML = '✓';
            vote_link.classList.remove('brackets');
            vote_id = 'vote_count_' + data.id;
        }
        document.getElementById(vote_id).textContent = data.total.toLocaleString('en-US');
    }

    function recalculate_bounty() {
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
            return;
        }

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

    function artist_add() {
        const total = document.getElementsByName('artists[]').length;
        if (total >= 200) {
            return;
        }

        let artistList = document.getElementById('artistfields');
        artistList.appendChild(document.createElement('br'));

        let field    = document.createElement('input');
        field.type   = 'text';
        field.id     = 'artist_' + total;
        field.name   = 'artists[]';
        field.size   = 45;
        field.onblur = check_various_artists;
        artistList.appendChild(field);
        if (document.getElementById('artist_0').dataset.gazelleAutocomplete) {
            field.setAttribute('data-gazelle-autocomplete', 'true');
            $("#artist_" + total).autocomplete({
                deferRequestBy: 300,
                serviceUrl : 'artist.php?action=autocomplete'
            });
        }

        artistList.appendChild(document.createTextNode('\n'));
        let role   = document.createElement('select');
        role.options[0] = new Option('Main', '1');
        role.options[1] = new Option('Guest', '2');
        role.options[2] = new Option('Composer', '4');
        role.options[3] = new Option('Conductor', '5');
        role.options[4] = new Option('DJ / Compiler', '6');
        role.options[5] = new Option('Remixer', '3');
        role.options[6] = new Option('Producer', '7');
        role.options[7] = new Option('Arranger', '8');
        role.id    = 'importance_' + total;
        role.name  = 'importance[]';
        role.value = document.getElementById('importance_' + (total - 1)).value;
        artistList.appendChild(role);
    }

    function artist_remove() {
        if (document.getElementsByName('artists[]').length === 1) {
            return;
        }
        let artistList = document.getElementById('artistfields');
        while (artistList.lastChild.tagName !== "INPUT") { // <select>
            artistList.removeChild(artistList.lastChild);
        }
        artistList.removeChild(artistList.lastChild); // <input>
        artistList.removeChild(artistList.lastChild); // <br>
    }

    function check_various_artists() {
        let shown = false;
        Array.from(document.getElementsByName('artists[]')).forEach((input) => {
            if (input.value.toLowerCase().trim().match(/^va(rious(\s?a(rtists?)))?$/)) {
                document.getElementById('vawarning').classList.remove('hidden');
                shown = true;
                return;
            }
        });
        if (!shown) {
            document.getElementById('vawarning').classList.add('hidden');
        }
    }

    function configure_category_form() {
        const cat = document.getElementById('categories').selectedOptions[0].value;
        if (cat == "Music") {
            ['artist', 'bitrates', 'cataloguenumber', 'formats', 'media', 'oclc',
                'recordlabel', 'releasetypes', 'year'
            ].forEach((input) => {
                document.getElementById(input + '_tr').classList.remove('hidden');
            });
            toggle_log_cue();
        } else if (["Audiobooks", "Comedy"].includes(cat)) {
            ['artist', 'bitrates', 'cataloguenumber', 'formats', 'logcue', 'media',
                'oclc', 'recordlabel', 'releasetypes'
            ].forEach((input) => {
                document.getElementById(input + '_tr').classList.add('hidden');
            });
            document.getElementById('year_tr').classList.remove('hidden');
        } else {
            ['artist', 'bitrates', 'cataloguenumber', 'formats', 'logcue', 'media',
                'oclc', 'recordlabel', 'releasetypes', 'year'
            ].forEach((input) => {
                document.getElementById(input + '_tr').classList.add('hidden');
            });
        }
    }

    function add_tag() {
        let tags     = document.getElementById('tags');
        const name   = tags.value;
        const chosen = document.getElementById('genre_tags').selectedOptions[0].value;
        if (name == '') {
            tags.value = chosen;
        } else if (chosen != '---') {
            tags.value = tags.value + ', ' + chosen;
        }
    }

    function toggle_group(group, disable) {
        const all_check = document.getElementById('toggle_' + group).checked;
        Array.from(document.getElementsByName(group + '[]')).forEach((cb) => {
            cb.checked = all_check;
            if (disable) {
                cb.disabled = all_check;
            }
        });

        if (["formats", "media"].includes(group)) {
            toggle_log_cue();
        }
    }

    function toggle_log_cue() {
        let logcue_tr = document.getElementById('logcue_tr');
        if (
            document.getElementsByName('formats[]')[1].checked // FLAC
            &&
            document.getElementsByName('media[]')[0].checked   // CD
        ) {
            logcue_tr.classList.remove('hidden');
        } else {
            logcue_tr.classList.add('hidden');
        }
        toggle_log_score();
    }

    function toggle_log_score() {
        if (document.getElementById('needlog').checked) {
            document.getElementById('minlogscore_span').classList.remove('hidden');
        } else {
            document.getElementById('minlogscore_span').classList.add('hidden');
        }
    }

    function init_input() {
        document.getElementById('artist-add').addEventListener('click', (e) => {
            artist_add();
            e.preventDefault();
        });
        document.getElementById('artist-remove').addEventListener('click', (e) => {
            artist_remove();
            e.preventDefault();
        });
        document.getElementById('artist_0').addEventListener('blur', () => {
            check_various_artists();
        });

        document.getElementById('categories').addEventListener('change', () => {
            configure_category_form();
        });
        document.getElementById('genre_tags').addEventListener('change', () => {
            add_tag();
        });
        document.getElementById('needlog').addEventListener('click', () => {
            toggle_log_score();
        });
        Array.from(document.getElementsByName('format[]')).forEach((format) => {
            format.addEventListener('change', () => {
                toggle_log_cue();
            });
        });
        Array.from(document.getElementsByName('media[]')).forEach((media) => {
            media.addEventListener('change', () => {
                toggle_log_cue();
            });
        });

        document.getElementById('toggle_formats').addEventListener('change', () => {
            toggle_group('formats', 1);
        });
        document.getElementById('toggle_bitrates').addEventListener('change', () => {
            toggle_group('bitrates', 1);
        });
        document.getElementById('toggle_media').addEventListener('change', () => {
            toggle_group('media', 1);
        });

        toggle_log_cue();
        configure_category_form();
    }

    function init_vote() {
        document.getElementById('amount_box').addEventListener('input', () => {
            recalculate_bounty();
        });
        document.getElementById('unit').addEventListener('input', () => {
            recalculate_bounty();
        });
        document.getElementById('button').addEventListener('click', () => {
            const requestId = document.getElementById('requestid');
            if (requestId != null) {
                add_bounty(
                    DETAIL_PAGE,
                    parseInt(requestId.value),
                    parseFloat(document.getElementById('amount').value),
                    parseInt(document.getElementById('votecount').textContent),
                    parseInt(document.getElementById('current_uploaded').value),
                    parseInt(document.getElementById('current_downloaded').value),
                    parseFloat(document.getElementById('current_rr').value),
                );
            }
        });

        recalculate_bounty();
    }

    function init_request_page(action) {
        if (['edit', 'new'].includes(action)) {
            init_input();
        }

        if (['view', 'new'].includes(action)) {
            init_vote();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const page = window.location.href.match(/\brequests\.php.*?action=(edit|new|view)/);
        if (page) {
            init_request_page(page[1]);
        } else {
            Array.from(document.querySelectorAll('.request-vote')).forEach((span) => {
                span.addEventListener('click', (e) => {
                    add_bounty(
                        VOTE_PAGE,
                        parseInt(e.target.dataset.id),
                        parseFloat(e.target.dataset.bounty),
                        parseInt(e.target.dataset.n),
                        parseInt(document.getElementById('current_uploaded').value),
                        parseInt(document.getElementById('current_downloaded').value),
                        parseFloat(document.getElementById('current_rr').value),
                    );
                });
            });
        }
    });
}());
