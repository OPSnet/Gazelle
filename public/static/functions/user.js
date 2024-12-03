"use strict";

function init_invitetree_toggle(toggle) {
    let invitetree = document.getElementById('invitetree');
    toggle.addEventListener('click', async (e) => {
        e.preventDefault();
        if (!invitetree.classList.contains('hidden')) {
            invitetree.classList.add('hidden');
            toggle.innerHTML = 'View';
        } else if (invitetree.innerHTML.trim() !== "") {
            invitetree.classList.remove('hidden');
            toggle.innerHTML = 'Hide';
        } else {
            invitetree.innerHTML = "Loading...";
            invitetree.classList.remove('hidden');
            let form = new FormData();
            form.append('id', toggle.dataset.id);
            form.append('auth', document.body.dataset.auth);
            const response = await fetch(
                '?action=load-invitetree', {
                    'method': "POST",
                    'body': form,
                },
            );
            invitetree.innerHTML = await response.json();
            toggle.innerHTML = 'Hide';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    async function toggle_editor() {
        let button = document.getElementById('admincommentbutton');
        let edit   = document.getElementById('admincomment');
        let view   = document.getElementById('admincommentlinks');
        if (edit.classList.contains('hidden')) {
            button.innerHTML = 'View';
            view.classList.add('hidden');
            edit.classList.remove('hidden');
        } else if (view.classList.contains('hidden')) {
            button.innerHTML = 'Edit';
            let form = new FormData();
            form.append('admincomment', edit.value);
            const response = await fetch(
                'ajax.php?action=preview', {
                    'method': "POST",
                    'body': form,
                }
            );
            view.innerHTML = await response.text();
            edit.classList.add('hidden');
            view.classList.remove('hidden');
        }
    }

    document.getElementById('admincommentbutton')?.addEventListener('click', (e) => {
        toggle_editor();
        e.preventDefault();
    });

    let passkey = document.getElementById('passkey');
    passkey.addEventListener('click', (e) => {
        passkey.innerHTML = (passkey.innerHTML == 'View')
            ? passkey.dataset.key
            : 'View';
        e.preventDefault();
    });

    document.getElementById('gen-password').addEventListener('click', () => {
        document.getElementById('change_password').value = Array(32)
            .fill('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz')
            .map((str) => { return str[Math.floor(Math.random() * str.length)]; })
            .join('');
    });

    function download_warning() {
        return confirm('If you no longer have the content, your ratio WILL be affected; be sure to check the cumulative size of all torrents before redownloading!');
    }

    document.getElementById('collect-upload')?.addEventListener('click', () => { return download_warning(); });
    document.getElementById('collect-snatch')?.addEventListener('click', () => { return download_warning(); });
    document.getElementById('collect-seeding')?.addEventListener('click', () => { return download_warning(); });

    let adjuster = document.getElementById('warning-adjust');
    if (adjuster) {
        adjuster.addEventListener('click', () => {
            if (adjuster.options[adjuster.selectedIndex].value == '---') {
                document.getElementById('ReduceWarningTR').classList.remove('hidden');
                document.getElementsByName('ReduceWarning')[0].disabled = false;
            } else {
                document.getElementById('ReduceWarningTR').classList.add('hidden');
                document.getElementsByName('ReduceWarning')[0].disabled = true;
            }
        });
    }

    let invitetree_toggle = document.querySelector('.user-invite-tree');
    if (invitetree_toggle) {
        init_invitetree_toggle(invitetree_toggle);
     }
});
