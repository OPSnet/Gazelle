"use strict";

async function handleLinkAction(action, gid) {
    const response = await fetch(new Request(
        'ajax.php?action=votefavorite&do=vote&vote=' + action
            + '&groupid=' + gid
            + '&auth=' + document.body.dataset.auth
    ));
    const info = await response.json();
    if (info.status != 'success') {
        return;
    }

    let percent = document.getElementById('votepercent');
    if (percent === null) {
        // on a list page
        document.getElementById('votescore-' + gid).innerHTML
            = (info.response.score * 100).toFixed(1);
        const data_id = '[data-id="' + gid + '"]';
        if (action == 'clear') {
            document.querySelector('.small_clearvote' + data_id).classList.add('hidden');
            document.querySelector('.small_upvote' + data_id).classList.remove('hidden');
            document.querySelector('.small_downvote' + data_id).classList.remove('hidden');
            document.getElementById('voted_up_' + gid).classList.add('hidden');
            document.getElementById('voted_down_' + gid).classList.add('hidden');
        } else {
            document.querySelector('.small_clearvote' + data_id).classList.remove('hidden');
            document.querySelector('.small_upvote' + data_id).classList.add('hidden');
            document.querySelector('.small_downvote' + data_id).classList.add('hidden');
            document.getElementById('voted_' + action + '_' + gid).classList.remove('hidden');
        }
    } else {
        // on a torrent group page
        percent.innerHTML = (info.response.total == 0)
            ? '—'
            : ((info.response.up / info.response.total) * 100).toFixed(1) + '%';
        document.getElementById('votescore').innerHTML
            = (info.response.score * 100).toFixed(1);
        document.getElementById('upvotes').innerHTML = info.response.up;
        document.getElementById('downvotes').innerHTML = info.response.down;
        document.getElementById('totalvotes').innerHTML = info.response.total;
        if (action == 'clear') {
            document.getElementById('vote_message').classList.remove('hidden');
            document.getElementById('unvote_message').classList.add('hidden');
            document.getElementById('upvoted').classList.add('hidden');
            document.getElementById('downvoted').classList.add('hidden');
        } else {
            document.getElementById('vote-clear').classList.remove('hidden');
            document.getElementById('vote_message').classList.add('hidden');
            document.getElementById('unvote_message').classList.remove('hidden');
            if (action == 'up') {
                document.getElementById('upvoted').classList.remove('hidden');
                document.getElementById('downvoted').classList.add('hidden');
            } else {
                document.getElementById('upvoted').classList.add('hidden');
                document.getElementById('downvoted').classList.remove('hidden');
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    Array.from(document.querySelectorAll('.small_upvote, #vote-up')).forEach(function(a) {
        a.addEventListener('click', (e) => {
            handleLinkAction('up', a.dataset.id);
            e.preventDefault();
        });
    });

    Array.from(document.querySelectorAll('.small_downvote, #vote-down')).forEach(function(a) {
        a.addEventListener('click', (e) => {
            handleLinkAction('down', a.dataset.id);
            e.preventDefault();
        });
    });

    Array.from(document.querySelectorAll('.small_clearvote, #vote-clear')).forEach(function(a) {
        a.addEventListener('click', (e) => {
            handleLinkAction('clear', a.dataset.id);
            e.preventDefault();
        });
    });
});
