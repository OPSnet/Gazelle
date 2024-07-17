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
        ajax.post("ajax.php?action=preview", "form", function(response) {
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

function ToggleWarningAdjust(selector) {
    if (selector.options[selector.selectedIndex].value == '---') {
        $('#ReduceWarningTR').gshow();
        $('#ReduceWarning').raw().disabled = false;
    } else {
        $('#ReduceWarningTR').ghide();
        $('#ReduceWarning').raw().disabled = true;
    }
}

function userform_submit() {
    if ($('#resetpasskey').is(':checked')) {
        if (!confirm('Are you sure you want to reset your passkey?')) {
            return false;
        }
    }
    return true;
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

function download_warning() {
    return confirm('If you no longer have the content, your ratio WILL be affected; be sure to check the cumulative size of all torrents before redownloading!');
}

document.addEventListener('DOMContentLoaded', function() {
    $("#random_password").click(function() {
        var length = 32,
            charset = "abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ0123456789",
            password = "";
        for (var i = 0, n = charset.length; i < length; ++i) {
            password += charset.charAt(Math.floor(Math.random() * n));
        }
        $('#change_password').val(password);
    });

    $("#collect-upload").click(function() { return download_warning(); });
    $("#collect-snatch").click(function() { return download_warning(); });
    $("#collect-seeding").click(function() { return download_warning(); });
    $("#gen-irc-key").click(function() { RandomIRCKey(); });
});
