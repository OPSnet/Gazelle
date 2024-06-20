/**
*
* Validates passwords to make sure they are powerful
**/

(function() {
var CLEAR = 0;
var WEAK = 1;
var STRONG = 3;
var SHORT = 4;
var MATCH_IRCKEY = 5;
var MATCH_USERNAME = 6;
var COMMON = 7;
var MATCH_OLD_PASSWORD = 8;

var USER_PATH = "/user.php";

document.addEventListener('DOMContentLoaded', function() {
    const new1 = document.getElementById('new_pass_1');

    if (!new1) {
        return;
    }

    const new2 = document.getElementById('new_pass_2');

    let old = new1.value.length;
    new1.addEventListener('keyup', () => {
        const password1 = new1.value;
        if (password1.length !== old) {
            disableSubmit();
            calculateComplexity(password1);
            old = password1.length;
        }
    });

    new1.addEventListener('change', async () => {
        const password1 = new1.value;
        const password2 = new2.value;
        if (password1.length === 0 && password2.length === 0) {
            enableSubmit();
        } else if (getStrong() === true) {
            await validatePassword(password1);
        }
    });

    new1.addEventListener('focus', () => {
        const password1 = new1.value;
        const password2 = new2.value;
        if (password1.length > 0) {
            checkMatching(password1, password2);
        }
    });

    new1.addEventListener('blur', () => {
        const password1 = new1.value;
        const password2 = new2.value;
        if (password1.length === 0 && password2.length === 0) {
            enableSubmit();
        }
    });

    new2.addEventListener('keyup', () => {
        const password1 = new1.value;
        const password2 = new2.value;
        checkMatching(password1, password2);
    });
});

async function validatePassword(password) {
    if (isUserPage()) {
        const resp = await fetch('ajax.php?action=password_validate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'password=' + password
        });
        if (await resp.json() === false) {
            setStatus(COMMON);
        }
    }
}

function calculateComplexity(password) {
    const length = password.length;
    let username, oldPassword, irckey;

    if (isUserPage()) {
        username = document.getElementsByClassName("username")[0].innerText;
        irckey = document.getElementById('irckey').value;
        oldPassword = document.getElementById('password').value;
    } else {
        username = document.getElementById('username')?.value;
    }

    if (length === 0) {
        setStatus(CLEAR);
    } else if (length < 8) {
        setStatus(SHORT);
    } else if (username && password.toLowerCase() === username.toLowerCase()) {
        setStatus(MATCH_USERNAME);
    } else if (irckey && password.toLowerCase() === irckey.toLowerCase()) {
        setStatus(MATCH_IRCKEY);
    } else if (oldPassword && password === oldPassword) {
        setStatus(MATCH_OLD_PASSWORD);
    } else if (isStrongPassword(password) || length >= 20) {
        setStatus(STRONG);
    } else {
        setStatus(WEAK);
    }
}

function isStrongPassword(password) {
    return /(?=^.{8,}$)(?=.*[^a-zA-Z])(?=.*[A-Z])(?=.*[a-z]).*$/.test(password);
}

function checkMatching(password1, password2) {
    const el = document.getElementById('pass_match');
    if (password2.length > 0) {
        if (password1 === password2 && getStrong() === true) {
            el.textContent = "Passwords match";
            el.style.color = "green";
            enableSubmit();
        } else if (getStrong() === true) {
            el.textContent = "Passwords do not match";
            el.style.color = "red";
            disableSubmit();
        } else {
            el.textContent = "Password isn't strong";
            el.style.color = "red";
            disableSubmit();
        }
    } else {
        el.textContent = "";
    }
}

function getStrong() {
    return document.getElementById('pass_strength').textContent === "Strong";
}

function setStatus(strength) {
    const el = document.getElementById('pass_strength');
    if (strength === WEAK) {
        disableSubmit();
        el.textContent = "Weak";
        el.style.color = "red";
    } else if (strength === STRONG) {
        disableSubmit();
        el.textContent = "Strong";
        el.style.color = "green";
    } else if (strength === SHORT) {
        disableSubmit();
        el.textContent = "Too Short";
        el.style.color = "red";
    } else if (strength === MATCH_IRCKEY) {
        disableSubmit();
        el.textContent = "Password cannot match IRC Key";
        el.style.color = "red";
    } else if (strength === MATCH_USERNAME) {
        disableSubmit();
        el.textContent = "Password cannot match Username";
        el.style.color = "red";
    } else if (strength === COMMON) {
        disableSubmit();
        el.textContent = "Password is too common";
        el.style.color = "red";
    } else if (strength === MATCH_OLD_PASSWORD) {
        disableSubmit();
        el.textContent = "New password cannot match old password";
        el.style.color = "red";
    } else if (strength === CLEAR) {
        el.textContent = "";
    }
}

function disableSubmit() {
    document.querySelector('input[type="submit"]').disabled = true;
}

function enableSubmit() {
    document.querySelector('input[type="submit"]').disabled = false;
}

function isUserPage() {
    return window.location.pathname.indexOf(USER_PATH) !== -1;
}

})();

