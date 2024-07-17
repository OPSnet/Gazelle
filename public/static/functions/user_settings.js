var PUSHOVER = 5;
var TOASTY = 4;
var PUSHBULLET = 6;

const userFormSelector = '#userform table.user_options';
const searchSelector = userFormSelector + ' > tbody > tr';

function fuzzyMatch(str, pattern) {
    pattern = pattern.split("").reduce(function(a,b){ return a+".*"+b; });
    return new RegExp(pattern).test(str);
}

function AlterParanoia() {
    // Required Ratio is almost deducible from downloaded, the count of seeding and the count of snatched
    // we will "warn" the user by automatically checking the required ratio box when they are
    // revealing that information elsewhere
    if (!$('input[name=p_ratio]').raw()) {
        return;
    }

    $.each([
        'requestsfilled', 'requestsvoted',
    ], function(i,val) {
        $('input[name=p_list_' + val + ']').raw().disabled = !($('input[name=p_count_' + val + ']').raw().checked && $('input[name=p_bounty_' + val + ']').raw().checked);
    });

    $.each([
        'collagecontribs', 'collages', 'leeching', 'torrentcomments', 'perfectflacs', 'seeding', 'snatched', 'uniquegroups', 'uploads',
    ], function(i,val) {
        $('input[name=p_l_' + val + ']').raw().disabled = !$('input[name=p_c_' + val + ']').raw().checked;
        UncheckIfDisabled($('input[name=p_l_' + val + ']').raw());
    });

    if ($('input[name=p_c_seeding]').raw().checked
        && $('input[name=p_c_snatched]').raw().checked
        && ($('input[name=p_downloaded]').raw().checked || ($('input[name=p_uploaded]').raw().checked && $('input[name=p_ratio]').raw().checked))
    ) {
        $('input[type=checkbox][name=p_requiredratio]').raw().checked = true;
    } else {
        $('input[type=checkbox][name=p_requiredratio]').raw().disabled = false;
    }

    // unique groups, "Perfect" FLACs and artists added are deducible from the list of uploads
    if ($('input[name=p_l_uploads]').raw().checked) {
        $('input[name=p_c_uniquegroups]').raw().checked = true;
        $('input[name=p_c_uniquegroups]').raw().disabled = true;
        $('input[name=p_l_uniquegroups]').raw().checked = true;
        $('input[name=p_l_uniquegroups]').raw().disabled = true;
        $('input[name=p_c_perfectflacs]').raw().checked = true;
        $('input[name=p_c_perfectflacs]').raw().disabled = true;
        $('input[name=p_l_perfectflacs]').raw().checked = true;
        $('input[name=p_l_perfectflacs]').raw().disabled = true;
        $('input[type=checkbox][name=p_artistsadded]').raw().checked = true;
        $('input[type=checkbox][name=p_artistsadded]').raw().disabled = true;
    } else {
        $('input[name=p_c_uniquegroups]').raw().disabled = false;
        $('input[name=p_c_perfectflacs]').raw().disabled = false;
        $('input[type=checkbox][name=p_artistsadded]').raw().disabled = false;
    }

    if (!$('input[name=p_l_collagecontribs]').raw().checked) {
        $('input[name=p_l_collages]').raw().checked = false;
    }
    UncheckIfDisabled($('input[name=p_l_collages]').raw());
}

function ParanoiaReset(checkbox, drops) {
    var selects = $('select');
    for (var i = 0; i < selects.results(); i++) {
        if (selects.raw(i).name.match(/^p_/)) {
            if (drops == 0) {
                selects.raw(i).selectedIndex = 0;
            } else if (drops == 1) {
                selects.raw(i).selectedIndex = selects.raw(i).options.length - 2;
            } else if (drops == 2) {
                selects.raw(i).selectedIndex = selects.raw(i).options.length - 1;
            }
            AlterParanoia();
        }
    }
    var checkboxes = $(':checkbox');
    for (var i = 0; i < checkboxes.results(); i++) {
        if (checkboxes.raw(i).name.match(/^p_/) && (checkboxes.raw(i).name != 'p_lastseen')) {
            if (checkbox == 3) {
                checkboxes.raw(i).checked = !(checkboxes.raw(i).name.match(/^p_list_/) || checkboxes.raw(i).name.match(/^p_l_/));
            } else {
                checkboxes.raw(i).checked = checkbox;
            }
            AlterParanoia();
        }
    }
}

function ParanoiaResetStats() {
    ParanoiaReset(3, 0);
    $('input[name=p_l_collages]').raw().checked = false;
}

function ParanoiaResetOn() {
    ParanoiaReset(false, 0);
    $('input[name=p_c_collages]').raw().checked = false;
    $('input[name=p_l_collages]').raw().checked = false;
}

function ParanoiaResetOff() {
    ParanoiaReset(true, 0);
}

function ToggleIdenticons() {
    var disableAvatars = $('#disableavatars');
    if (disableAvatars.length) {
        var selected = disableAvatars[0].selectedIndex;
        if (selected == 2 || selected == 3) {
            $('#identicons').gshow();
        } else {
            $('#identicons').ghide();
        }
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
        success: function(data, textStatus, xhr) {
            var data = jQuery.parseJSON(data);
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

document.addEventListener('DOMContentLoaded', function () {
    var top = $('#settings_sections').offset().top - parseFloat($('#settings_sections').css('marginTop').replace(/auto/, 0));
    $(window).scroll(function (event) {
        var y = $(this).scrollTop();
        if (y >= top) {
            $('#settings_sections').addClass('fixed');
        } else {
            $('#settings_sections').removeClass('fixed');
        }
    });

    $("#settings_sections li").each(function(index) {
        $(this).click(function(e) {
            var id = $(this).data("gazelle-section-id");
            if (id) {
                e.preventDefault();
                if (id == "all_settings" || id == "live_search") {
                    $(userFormSelector).show();
                } else {
                    $(userFormSelector).hide();
                    $("#" + id).show();
                }
            }
        });
    });

    $("#settings_search").on("keyup", function() {
        var search = $(this).val().toLowerCase();
        if ($.trim(search).length > 0) {
            $(searchSelector).not(".colhead_dark").each(function(index) {
                var text = $(this).find("td:first").text().toLowerCase();
                if (text.length > 0 && search.length > 0 && fuzzyMatch(text, search)) {
                    $(this).show();
                }
                else {
                    $(this).hide();
                }
            });
        } else {
            $(searchSelector).show();
        }
    });

    $("#disableavatars").change(function() {
        ToggleIdenticons();
    });

    // I'm sure there is a better way to do this but this will do for now.
    $("#notifications_Inbox_traditional").click(function() {
        $("#notifications_Inbox_popup").prop('checked', false);
    });
    $("#notifications_Inbox_popup").click(function() {
        $("#notifications_Inbox_traditional").prop('checked', false);
    });
    $("#notifications_Torrents_traditional").click(function() {
        $("#notifications_Torrents_popup").prop('checked', false);
    });
    $("#notifications_Torrents_popup").click(function() {
        $("#notifications_Torrents_traditional").prop('checked', false);
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

    document.getElementById('paranoid-none').addEventListener('click', () => { ParanoiaResetOff(); });
    document.getElementById('paranoid-stats').addEventListener('click', () => { ParanoiaResetStats(); });
    document.getElementById('paranoid-all').addEventListener('click', () => { ParanoiaResetOn(); });

    Array.from(document.getElementsByClassName('paranoia-setting')).forEach((el) => {
        el.addEventListener("change", () => { AlterParanoia() });
    });

    AlterParanoia();
    ToggleIdenticons();
});
