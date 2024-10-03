/* global resize */

"use strict";

document.addEventListener('DOMContentLoaded', () => {
    // used on templates/staffpm/common-response.twig
    // remove a canned response
    Array.from(document.querySelectorAll('.common-ans-del')).forEach((button) => {
        button.addEventListener('click', async (e) => {
            const id = e.target.dataset.id;
            let form = new FormData();
            form.append('id', id);
            form.append('auth', e.target.dataset.auth);
            const response = await fetch(new Request(
                '?action=delete_response', {
                    'method': "POST",
                    'body': form,
                },
            ));
            const data  = await response.text();
            let message = document.getElementById('ajax_message_' + id);
            document.getElementById('response_' + id).classList.add('hidden');
            message.textContent = (data == '1') 
                ? 'Answer successfully deleted.'
                : 'Something went wrong.';
            message.classList.remove('hidden');
            setTimeout(() => { message.classList.add('hidden'); }, 5000);
        });
    });

    // used on templates/staffpm/common-response.twig
    // save a canned response
    Array.from(document.querySelectorAll('.common-ans-save')).forEach((button) => {
        button.addEventListener('click', async (e) => {
            const id = e.target.dataset.id;
            let form = new FormData();
            form.append('id', id);
            form.append('message', document.getElementById('answer-' + id).value);
            form.append('name', document.getElementById('name-' + id).value);
            const response = await fetch(new Request(
                '?action=edit_response', {
                    'method': "POST",
                    'body': form,
                },
            ));
            let message = document.getElementById('ajax_message_' + id);
            const data  = await response.text();
            if (data == '1') {
                message.textContent = 'Answer successfully created.';
            } else if (data == '2') {
                message.textContent = 'Answer successfully edited.';
            } else {
                message.textContent = 'Something went wrong.';
            }
            message.classList.remove('hidden');
            setTimeout(() => { message.classList.add('hidden'); }, 5000);
        });
    });

    // used on templates/staffpm/message.twig
    // preview this canned response
    const answer_select = document.getElementById('common_answers_select');
    if (answer_select) {
        answer_select.addEventListener('change', async () => {
            const response = await fetch(new Request(
                '?action=get_response&plain=0&id=' + answer_select.value
            ));
            let preview = document.getElementById('common_answers_body');
            if (preview) {
                preview.innerHTML = await response.text();
            }
        });
    }

    // used on templates/staffpm/message.twig
    // update the staffpm reply with this canned response
    const answer_set = document.getElementById('common-ans-set');
    if (answer_set) {
        answer_set.addEventListener('click', async () => {
            const response = await fetch(new Request(
                '?action=get_response&plain=1&id='
                + document.getElementById('common_answers_select').value
            ));
            let quickpost = document.getElementById('quickpost');
            if (quickpost) {
                quickpost.value = quickpost.value
                    + (quickpost.value !== '' ? "\n\n" : '')
                    + await response.text();
            }
        });
    }

    // used on templates/staffpm/message.twig
    // assign the current staffpm to someone
    const spm_assign = document.getElementById('assign');
    if (spm_assign) {
        spm_assign.addEventListener('click', async () => {
            let form = new FormData();
            form.append('assign', document.getElementById('assign_to').value);
            form.append('convid', document.getElementById('convid').value);
            const response = await fetch(new Request(
                '?action=assign', {
                    'method': "POST",
                    'body': form,
                },
            ))
            const data = await response.text();
            let message = document.getElementById('ajax_message');
            message.textContent = (data == '1')
                ? 'Conversation successfully assigned.'
                : 'Something went wrong.';
            message.classList.remove('hidden');
            setTimeout(() => { message.classList.add('hidden'); }, 5000);
        });
    }

    // used on templates/staffpm/message.twig
    // reveal/hide list of common answers
    const common = document.getElementById('common');
    if (common) {
        common.addEventListener('click', () => {
            let pane = document.getElementById('common_answers');
            if (pane.classList.contains('hidden')) {
                pane.classList.remove('hidden');
            } else {
                pane.classList.add('hidden');
            }
        });
    }

    // used on templates/staffpm/message.twig
    // quote the post being replied to
    Array.from(document.querySelectorAll('.quote-action')).forEach((quote) => {
        quote.addEventListener('click', async (e) => {
            const id       = e.target.dataset.id;
            const response = await fetch(new Request(
                '?action=get_post&post=' + id
            ));
            const data    = await response.json();
            let quickpost = document.getElementById('quickpost');
            quickpost.value = quickpost.value
                + (quickpost.value !== '' ? "\n\n" : '')
                + '[quote=' + data.username + ']' + data.body + '[/quote]';
            resize('quickpost');
        });
    });
});
