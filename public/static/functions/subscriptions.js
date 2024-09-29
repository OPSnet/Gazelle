/* global ajax */

function Subscribe(threadid) {
    ajax.get("userhistory.php?action=thread_subscribe&threadid=" + threadid + "&auth=" + document.body.dataset.auth, function() {
        var subscribeLink = $("#subscribelink" + threadid).raw();
        if (subscribeLink) {
            if (subscribeLink.firstChild.nodeValue.charAt(0) == '[') {
                subscribeLink.firstChild.nodeValue = subscribeLink.firstChild.nodeValue.charAt(1) == 'U'
                    ? '[Subscribe]'
                    : '[Unsubscribe]';
            } else {
                subscribeLink.firstChild.nodeValue = subscribeLink.firstChild.nodeValue.charAt(0) == 'U'
                    ? "Subscribe"
                    : "Unsubscribe";
            }
        }
    });
}

function SubscribeComments(page, pageid) {
    ajax.get('userhistory.php?action=comments_subscribe&page=' + page + '&pageid=' + pageid + '&auth=' + document.body.dataset.auth, function() {
        var subscribeLink = $("#subscribelink_" + page + pageid).raw();
        if (subscribeLink) {
            subscribeLink.firstChild.nodeValue = subscribeLink.firstChild.nodeValue.charAt(0) == 'U'
                ? "Subscribe"
                : "Unsubscribe";
        }
    });
}

function Collapse() {
    var collapseLink = $('#collapselink').raw();
    var hide = (collapseLink.innerHTML.substr(0,1) == 'H' ? 1 : 0);
    if ($('.row').results() > 0) {
        $('.row').gtoggle();
    }
    if (hide) {
        collapseLink.innerHTML = 'Show post bodies';
    } else {
        collapseLink.innerHTML = 'Hide post bodies';
    }
}

function autosub(forumid) {
    var post = new Array();
    post['auth']   = document.body.dataset.auth;
    post['id']     = forumid;
    post['active'] = document.getElementById("autosub").text.charAt(0) == 'A' ? 1 : 0;
    ajax.post("forums.php?id=" + forumid + "&action=autosub&auth=" + document.body.dataset.auth, post, function(response) {
        var result = JSON.parse(response);
        if (result['status'] == 'success') {
            document.getElementById("autosub").innerHTML = result['response']['autosub'] ? "Cancel autosubscribe" : "Auto subscribe";
        }
    });
}
