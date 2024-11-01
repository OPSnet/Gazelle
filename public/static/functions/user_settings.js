var PUSHOVER = 5;
var TOASTY = 4;
var PUSHBULLET = 6;

const PARANOIA_STATS   = 3;
const userFormSelector = '#userform table.user_options';
const searchSelector   = userFormSelector + ' > tbody > tr';

function uncheck_if_disabled(checkbox) {
    if (checkbox.disabled) {
        checkbox.checked = false;
    }
}

function refresh_paranoia() {
    // Required Ratio is almost deducible from downloaded, the count of seeding and the count of snatched
    // we will "warn" the user by automatically checking the required ratio box when they are
    // revealing that information elsewhere
    if (!document.querySelector('input[name=p_ratio]')) {
        return;
    }

    [ 'requestsfilled', 'requestsvoted'].forEach((val) => {
        document.querySelector('input[name=p_list_' + val + ']').disabled
            = !(
                document.querySelector('input[name=p_count_' + val + ']').checked
                && document.querySelector('input[name=p_bounty_' + val + ']').checked
            );
    });

    [
        'collagecontribs', 'collages', 'leeching', 'torrentcomments',
        'perfectflacs', 'seeding', 'snatched', 'uniquegroups', 'uploads',
    ].forEach((val) => {
        document.querySelector('input[name=p_l_' + val + ']').disabled
            = !document.querySelector('input[name=p_c_' + val + ']').checked;
        uncheck_if_disabled(document.querySelector('input[name=p_l_' + val + ']'));
    });

    if (document.querySelector('input[name=p_c_seeding]').checked
        && document.querySelector('input[name=p_c_snatched]').checked
        && (
            document.querySelector('input[name=p_downloaded]').checked
            ||
            (document.querySelector('input[name=p_uploaded]').checked
                && document.querySelector('input[name=p_ratio]').checked
            )
        )
    ) {
        document.querySelector('input[type=checkbox][name=p_requiredratio]').checked = true;
    } else {
        document.querySelector('input[type=checkbox][name=p_requiredratio]').disabled = false;
    }

    // unique groups, "Perfect" FLACs and artists added are deducible from the list of uploads
    if (document.querySelector('input[name=p_l_uploads]').checked) {
        [
            'input[name=p_c_perfectflacs]', 'input[name=p_l_perfectflacs]',
            'input[name=p_c_uniquegroups]', 'input[name=p_l_uniquegroups]',
            'input[type=checkbox][name=p_artistsadded]',
        ].forEach((val) => {
            document.querySelector(val).checked = true;
            document.querySelector(val).disabled = true;
        });
    } else {
        document.querySelector('input[name=p_c_uniquegroups]').disabled = false;
        document.querySelector('input[name=p_c_perfectflacs]').disabled = false;
        document.querySelector('input[type=checkbox][name=p_artistsadded]').disabled = false;
    }

    if (!document.querySelector('input[name=p_l_collagecontribs]').checked) {
        document.querySelector('input[name=p_l_collages]').checked = false;
    }
    uncheck_if_disabled(document.querySelector('input[name=p_l_collages]'));
}

function paranoia_configure(setting) {
    Array.from(document.querySelectorAll('input[type="checkbox"]'))
        .filter((cb) => { return cb.name.match(/^p_(?!lastseen)/)})
        .forEach((cb) => {
            cb.checked = (setting == PARANOIA_STATS) ? !cb.name.match(/^p_l(?:ist)?_/) : setting;
        });
    refresh_paranoia();
}

function paranoia_stats() {
    paranoia_configure(PARANOIA_STATS);
    document.querySelector('input[name=p_l_collages]').checked = false;
}

function paranoia_all() {
    paranoia_configure(false);
    document.querySelector('input[name=p_c_collages]').checked = false;
    document.querySelector('input[name=p_l_collages]').checked = false;
}

function paranoia_none() {
    paranoia_configure(true);
}

function toggle_identicons() {
    let disable_avatars = document.getElementById('disableavatars');
    if (['2', '3'].includes(disable_avatars.value)) {
        disable_avatars.classList.remove('hidden');
    } else {
        disable_avatars.classList.add('hidden');
    }
}

/**
 * Gets device IDs from the pushbullet API
 *
 * @return array of dictionaries with devices
 */
function fetchPushbulletDevices(apikey) {
    $.ajax({
        url: 'ajax.php',
        data: {
          "action": 'pushbullet_devices',
          "apikey": apikey
        },
        type: 'GET',
        success: function(raw_data, textStatus, xhr) {
            var data = JSON.parse(raw_data);
            var field = $('#pushdevice');
            var value = field.val();
            if (data.error || textStatus !== 'success' ) {
                if (data.error) {
                    field.html('<option>' + data.error.message + '</option>');
                } else {
                    $('#pushdevice').html('<option>No devices fetched</option>');
                }
            } else {
                if(data['devices'].length > 0) {
                    field.html('');
                }
                for (var i = 0; i < data['devices'].length; i++) {
                    var model = data['devices'][i]['extras']['model'];
                    var nickname = data['devices'][i]['extras']['nickname'];
                    var name = nickname !== undefined ? nickname : model;
                    var option = new Option(name, data['devices'][i]['iden']);

                    option.selected = (option.value == value);
                    field[0].add(option);
                }
            }
        },
        error: function(data,textStatus,xhr) {
            $('#pushdevice').html('<option>' + textStatus + '</option>');
        }
    });
}

function init_css_gallery() {
    let gallery = Array.from(
        document.querySelectorAll('input[name="stylesheet_gallery"]')
    );

    let stylesheet = document.querySelector('select#stylesheet');
    function update_radio() {
        let radio = gallery[
            gallery.findIndex((g) => { return g.value == stylesheet.value; })
        ];
        radio.click();
        radio.parentNode.parentNode.classList.add('selected');
    }

    stylesheet.addEventListener('change', () => {
        // select the appropriate gallery item, clear the external url
        update_radio();
        external_url.value = '';
    });

    // If no custom stylesheet, select the current gallery
    let external_url = document.querySelector('input#styleurl');
    if (external_url.value === '') {
        update_radio();
    }
    external_url.addEventListener('keydown', () => {
        // If the custom CSS field is changed, clear radio buttons
        gallery.forEach((g) => {
            g.checked = false;
        });
    });
    external_url.addEventListener('keyup', (e) => {
        // If the field is empty, select appropriate gallery item again by the drop-down
        if (e.target.value === "") {
            update_radio();
        }
    });

    // When a gallery radio is clicked, update the drop-down
    gallery.forEach((g) => {
        g.addEventListener('change', () => {
            stylesheet.value = g.value;
            external_url.value = '';
        });
    });

    // gallery visibility can be toggled
    let toggle = document.getElementById('toggle_css_gallery');
    toggle.addEventListener('click', (e) => {
        if (toggle.innerHTML === 'Hide gallery') {
            toggle.innerHTML = 'Show gallery';
            document.getElementById('css_gallery').style.display = 'none';
        } else {
            toggle.innerHTML = 'Hide gallery';
            document.getElementById('css_gallery').style.display = 'block';
        }
        e.preventDefault();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    Array.from(document.querySelectorAll("#settings_sections li"))
        .filter((li) => { return li.dataset.gazelleSectionId != 'live_search'; })
        .forEach((li) => {
            li.addEventListener('click', (e) => {
                const id = li.dataset.gazelleSectionId;
                if (id === 'all_settings') {
                    document.querySelectorAll(userFormSelector).forEach((s) => {
                        s.style.display = '';
                    });
                } else {
                    document.querySelectorAll(userFormSelector).forEach((s) => {
                        s.style.display = 'none';
                    });
                    document.getElementById(id).style.display = '';
                }
                e.preventDefault();
            });
        });

    function fuzzyMatch(str, pattern) {
        if (!str.length || !pattern.length) {
            return true;
        }
        pattern = pattern.split("").reduce((a,b) => { return a + ".*" + b; });
        return new RegExp(pattern).test(str);
    }

    let s = document.getElementById('settings_search');
    s.addEventListener('keyup', () => {
        const search = s.value.toLowerCase().trim();
        if (search.length == 0) {
            document.querySelector(searchSelector).style.display = '';
        } else {
            Array.from(document.querySelectorAll(searchSelector))
                .filter((div) => { return !div.classList.contains('colhead_dark'); })
                .forEach((e) => {
                    const text = e.querySelector('td').textContent.trim().toLowerCase();
                    e.style.display = (fuzzyMatch(text, search)) ? '' : 'none';
                });
        }
    });

    if ($("#pushservice").val() > 0) {
        $('.pushdeviceid').hide();
        $('#pushsettings').show();
        if ($('#pushservice').val() == PUSHBULLET) {
            fetchPushbulletDevices($('#pushkey').val());
            $('.pushdeviceid').show();
        }
    }

    $("#pushservice").change(function() {
        if ($(this).val() > 0) {
            $('#pushsettings').show(500);

            if ($(this).val() == TOASTY) {
                $('#pushservice_title').text("Device ID");
            } else if ($(this).val() == PUSHOVER) {
                $('#pushservice_title').text("User Key");
            } else {
                $('#pushservice_title').text("API Key");
            }
        } else {
            $('#pushsettings').hide(500);
        }

        if ($(this).val() == PUSHBULLET) {
            fetchPushbulletDevices($('#pushkey').val());
            $('.pushdeviceid').show(500);
        } else {
            $('.pushdeviceid').hide(500);
        }
    });

    $("#pushkey").blur(function() {
        if($("#pushservice").val() == PUSHBULLET) {
            fetchPushbulletDevices($(this).val());
        }
    });

    document.getElementById('disableavatars').addEventListener('change', () => {
        toggle_identicons();
    });

    document.getElementById('notifications_Inbox_traditional').addEventListener('click', () => {
        document.getElementById('notifications_Inbox_popup').checked = false;
    });
    document.getElementById('notifications_Inbox_popup').addEventListener('click', () => {
        document.getElementById('notifications_Inbox_traditional').checked = false;
    });
    document.getElementById('notifications_Torrent_traditional').addEventListener('click', () => {
        document.getElementById('notifications_Torrent_popup').checked = false;
    });
    document.getElementById('notifications_Torrent_popup').addEventListener('click', () => {
        document.getElementById('notifications_Torrent_traditional').checked = false;
    });

    document.getElementById('gen-irc-key').addEventListener('click', () => {
        document.getElementById('irckey').value = Array(32)
            .fill('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz')
            .map((str) => { return str[Math.floor(Math.random() * str.length)]; })
            .join('');
    });

    document.getElementById('paranoid-none').addEventListener('click', () => { paranoia_none(); });
    document.getElementById('paranoid-stats').addEventListener('click', () => { paranoia_stats(); });
    document.getElementById('paranoid-all').addEventListener('click', () => { paranoia_all(); });

    Array.from(document.getElementsByClassName('paranoia-setting')).forEach((e) => {
        e.addEventListener("change", () => {
            refresh_paranoia();
        });
    });

    document.getElementById('save-profile').addEventListener('click', (e) => {
        if (document.getElementById('resetpasskey').checked) {
            if (!confirm('Are you sure you want to reset your passkey?')) {
                e.preventDefault();
            }
        }
    });

    refresh_paranoia();
    toggle_identicons();
    init_css_gallery();
});
