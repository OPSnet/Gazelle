function ChangeTo(to) {
    if (to == "text") {
        $('#admincommentlinks').ghide();
        $('#admincomment').gshow();
        resize('admincomment');
        var buttons = document.getElementsByName('admincommentbutton');
        for (var i = 0; i < buttons.length; i++) {
            buttons[i].setAttribute('onclick',"ChangeTo('links'); return false;");
        }
    } else if (to == "links") {
        ajax.post("ajax.php?action=preview","form", function(response) {
            $('#admincommentlinks').raw().innerHTML = response;
            $('#admincomment').ghide();
            $('#admincommentlinks').gshow();
            var buttons = document.getElementsByName('admincommentbutton');
            for (var i = 0; i < buttons.length; i++) {
                buttons[i].setAttribute('onclick',"ChangeTo('text'); return false;");
            }
        });
    }
}

function UncheckIfDisabled(checkbox) {
    if (checkbox.disabled) {
        checkbox.checked = false;
    }
}

function AlterParanoia() {
    // Required Ratio is almost deducible from downloaded, the count of seeding and the count of snatched
    // we will "warn" the user by automatically checking the required ratio box when they are
    // revealing that information elsewhere
    if (!$('input[name=p_ratio]').raw()) {
        return;
    }
    var showDownload = $('input[name=p_downloaded]').raw().checked || ($('input[name=p_uploaded]').raw().checked && $('input[name=p_ratio]').raw().checked);
    if (($('input[name=p_c_seeding]').raw().checked) && ($('input[name=p_c_snatched]').raw().checked) && showDownload) {
        $('input[type=checkbox][name=p_requiredratio]').raw().checked = true;
        $('input[type=checkbox][name=p_requiredratio]').raw().disabled = true;
    } else {
        $('input[type=checkbox][name=p_requiredratio]').raw().disabled = false;
    }

    $.each([
        'requestsfilled', 'requestsvoted',
    ], function(i,val) {
        $('input[name=p_list_' + val + ']').raw().disabled = !($('input[name=p_count_' + val + ']').raw().checked && $('input[name=p_bounty_' + val + ']').raw().checked);
        UncheckIfDisabled($('input[name=p_list_' + val + ']').raw());
    });

    $.each([
        'collagecontribs', 'collages', 'leeching', 'torrentcomments', 'perfectflacs', 'seeding', 'snatched', 'uniquegroups', 'uploads',
    ], function(i,val) {
        $('input[name=p_l_' + val + ']').raw().disabled = !$('input[name=p_c_' + val + ']').raw().checked;
        UncheckIfDisabled($('input[name=p_l_' + val + ']').raw());
    });

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
        $('input[name=p_l_uniquegroups]').raw().disabled = true;
        $('input[name=p_c_perfectflacs]').raw().disabled = false;
        $('input[name=p_l_perfectflacs]').raw().disabled = true;
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

function ParanoiaResetOff() {
    ParanoiaReset(true, 0);
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

addDOMLoadEvent(AlterParanoia);

function ToggleWarningAdjust(selector) {
    if (selector.options[selector.selectedIndex].value == '---') {
        $('#ReduceWarningTR').gshow();
        $('#ReduceWarning').raw().disabled = false;
    } else {
        $('#ReduceWarningTR').ghide();
        $('#ReduceWarning').raw().disabled = true;
    }
}

addDOMLoadEvent(ToggleIdenticons);
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

function userform_submit() {
    if ($('#resetpasskey').is(':checked')) {
        if (!confirm('Are you sure you want to reset your passkey?')) {
            return false;
        }
    }
    return formVal();
}

function togglePassKey(key) {
    if ($('#passkey').raw().innerHTML == 'View') {
        $('#passkey').raw().innerHTML = key;
    } else {
        $('#passkey').raw().innerHTML = 'View';
    }

}

function RandomIRCKey() {
    var irckeyChars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
    var randIRCKeyLen = 32;
    var randIRCKey = Array(randIRCKeyLen).fill(irckeyChars).map(function(x) { return x[Math.floor(Math.random() * x.length)]; }).join('');
    irckey.value = randIRCKey;
}

$(document).ready(function() {
    $("#random_password").click(function() {
        var length = 32,
            charset = "abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ0123456789",
            password = "";
        for (var i = 0, n = charset.length; i < length; ++i) {
            password += charset.charAt(Math.floor(Math.random() * n));
        }
        $('#change_password').val(password);
    });
});
