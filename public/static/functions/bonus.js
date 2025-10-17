"use strict";

async function previewTitle() {
    const form = new FormData();
    form.append('auth', document.body.dataset.auth);
    form.append('title', document.getElementById('title').value);
    form.append('label', document.forms['custom-title-preview'].elements['label'].value);
    const response = await fetch(
        '?action=prepare', {
            'method': 'POST',
            'body': form,
        }
    );
    const data = await response.json();
    document.getElementById('preview').innerHTML =
        data.status === 'success'
            ? data.response[0]
            : data.status;
}

async function validateBonusUsername() {
    const status   = document.getElementById('bonus-other-status');
    const username = document.getElementById('bonus-user-other').value.trim();
    const purchase = document.getElementById('bonus-other-purchase')
    purchase.disabled = true;
    if (username === '') {
        status.innerHTML = '';
        return;
    }
    const form = new FormData();
    form.append('auth', document.body.dataset.auth);
    form.append('bonus-user-other', username);
    form.append('label', document.forms['bonus-other'].elements['label'].value);
    const response = await fetch(
        '?action=prepare', {
            'method': 'POST',
            'body': form,
        }
    );
    const data = await response.json();
    let message;
    if (data.status !== 'success') {
        message = status;
    } else {
        const info  = data.response;
        const price = new Intl.NumberFormat('en').format(info.price);
        if (!info.found) {
            message = '⛔️ ' + 'user not found';
        } else if (info.id == document.body.dataset.id) {
            message = '⛔️ You cannot gift tokens to yourself';
        } else if (!info.enabled) {
            message = '⛔️ ' + info.username + ' is currently not enabled';
        } else if (!info.accept) {
            message = '🚫 ' + info.username + ' does not wish to receive tokens';
        } else if (info.percent5 >= 100) {
            message = '⛔️ you do not have enough bonus points (' + price + ' needed)';
        } else {
            message = '✅ This will cost ' + price + ' bonus points';
            if (info.percent5 > 0) {
                message = message + ' (which is more than ' + info.percent5 + '% of your balance)';
            }
            document.forms['bonus-other'].elements['user'].value = info.username;
            purchase.disabled = false;
        }
    }
    status.innerHTML = message;
}

document.addEventListener('DOMContentLoaded', () => {
    const button = document.getElementById('preview-title');
    if (button) {
        button.addEventListener('click', () => previewTitle());
    }
    const username = document.getElementById('bonus-user-other');
    if (username) {
        username.addEventListener('change', () => validateBonusUsername());
    }
});
