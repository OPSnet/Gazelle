let voteLock = false;

function handleBoxAction(info, direction) {
console.log(info);
    if (info.status != 'success') {
        return;
    }
    $('#upvotes').raw().innerHTML = info.response.up;
    $('#downvotes').raw().innerHTML = info.response.down;
    $('#totalvotes').raw().innerHTML = info.response.total;
    $('#votescore').raw().innerHTML = (info.response.score * 100).toFixed(1);
    if (info.response.total == 0) {
        $('#votepercent').raw().innerHTML = '&mdash;';
    } else {
        $('#votepercent').raw().innerHTML = ((info.response.up / info.response.total) * 100).toFixed(1) + '%';
    }
    if (direction == 0) {
        $('#vote_message').gshow();
        $('#unvote_message').ghide();
        $('#upvoted').ghide();
        $('#downvoted').ghide();
    } else {
        $('#vote_message').ghide();
        $('#unvote_message').gshow();
        if (direction == 1) {
            $('#upvoted').gshow();
            $('#downvoted').ghide();
        } else {
            $('#upvoted').ghide();
            $('#downvoted').gshow();
        }
    }
}

function handleLinkAction(info, direction) {
    if (info.status != 'success') {
        return;
    }
    const groupid = info.response.id;
    $('#votescore-' + groupid).raw().innerHTML = (info.response.score * 100).toFixed(1);
    if (direction == 0) {
        $('#vote_clear_' + groupid).ghide();
        $('#vote_up_' + groupid).gshow();
        $('#vote_down_' + groupid).gshow();
        $('#voted_up_' + groupid).ghide();
        $('#voted_down_' + groupid).ghide();
    } else {
        $('#vote_clear_' + groupid).gshow();
        $('#vote_up_' + groupid).ghide();
        $('#vote_down_' + groupid).ghide();
        if (direction == 1) {
            $('#voted_up_' + groupid).gshow();
        } else {
            $('#voted_down_' + groupid).gshow();
        }
    }
}

function DownVoteBox(groupid, authkey) {
    if (!voteLock) {
        voteLock = true;
        ajax.get('ajax.php?action=votefavorite&do=vote&groupid=' + groupid + '&vote=down' + '&auth=' + authkey, function (response) {
            handleBoxAction(JSON.parse(response), -1);
        });
        voteLock = false;
    }
}

function UpVoteBox(groupid, authkey) {
    if (!voteLock) {
        voteLock = true;
        ajax.get('ajax.php?action=votefavorite&do=vote&groupid=' + groupid + '&vote=up' + '&auth=' + authkey, function (response) {
            handleBoxAction(JSON.parse(response), +1);
        });
        voteLock = false;
    }
}

function UnvoteBox(groupid, authkey) {
    if (!voteLock) {
        voteLock = true;
        ajax.get('ajax.php?action=votefavorite&do=unvote&groupid=' + groupid + '&auth=' + authkey, function (response) {
                handleBoxAction(JSON.parse(response), 0);
        });
        voteLock = false;
    }
}

function DownVoteGroup(groupid, authkey) {
    if (voteLock) {
        return;
    }
    voteLock = true;
    ajax.get('ajax.php?action=votefavorite&do=vote&groupid=' + groupid + '&vote=down' + '&auth=' + authkey, function (response) {
        handleLinkAction(JSON.parse(response), -1);
    });
    voteLock = false;
}

function UpVoteGroup(groupid, authkey) {
    if (voteLock) {
        return;
    }
    voteLock = true;
    ajax.get('ajax.php?action=votefavorite&do=vote&groupid=' + groupid + '&vote=up' + '&auth=' + authkey, function (response) {
        handleLinkAction(JSON.parse(response), +1);
    });
    voteLock = false;
}

function UnvoteGroup(groupid, authkey) {
    if (voteLock) {
        return;
    }
    voteLock = true;
        ajax.get('ajax.php?action=votefavorite&do=unvote&groupid=' + groupid + '&auth=' + authkey, function (response) {
        handleLinkAction(JSON.parse(response), 0);
    });
    voteLock = false;
}
