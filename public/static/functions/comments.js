/* global ajax, BBCode, gazURL, resize */

"use strict";

function QuoteJump(event, post) {
    const button  = event.button;
    let url     = '';
    let pattern = '';
    if (isNaN(post.charAt(0))) {
        switch (post.charAt(0)) {
            case 'a': // artist comment
                url = 'artist';
                break;
            case 't': // torrent comment
                url = 'torrents';
                break;
            case 'c': // collage comment
                url = 'collages';
                break;
            case 'r': // request comment
                url = 'requests';
                break;
            default:
                return;
        }
        pattern = new RegExp(url + '\\.php');
        post = post.substr(1);
        url = 'comments.php?action=jump&postid=' + post;
    } else {
        // forum post
        url = 'forums.php?action=viewthread&postid=' + post;
        pattern = /forums\.php/;
    }
    let hash = "#post" + post;
    if (button == 0) {
        if ($(hash).raw() != null && location.href.match(pattern)) {
            window.location.hash = hash;
        } else {
            window.open(url, '_self');
        }
    } else if (button == 1) {
        window.open(url, '_window');
    }
}

function Quote(post, user, link = false) {
    const username = user;
    const url = new gazURL();

    let target = '';
    let requrl = '';
    let elem = 'post';
    if (url.path == "inbox") {
        requrl = 'inbox.php?action=get_post&post=' + post;
        elem = 'message';
    } else {
        requrl = 'comments.php?action=get&postid=' + post;
    }
    if (link == true) {
        if (url.path == "artist") {
            // artist comment
            target = 'a';
        } else if (url.path == "torrents") {
            // torrent comment
            target = 't';
        } else if (url.path == "collages") {
            // collage comment
            target = 'c';
        } else if (url.path == "requests") {
            // request comment
            target = 'r';
        } else {
            // forum post
            requrl = 'forums.php?action=get_post&post=' + post;
        }
        target += post;
    }

    // if any text inside of a forum post body is selected, use that instead of Ajax result.
    // unfortunately, this will not preserve bbcode in the quote. This is an unfortunate necessity, as
    // doing some sort of weird grepping through the Ajax bbcode for the selected text is overkill.
    if (getSelection().toString() && inPost(getSelection().anchorNode) && inPost(getSelection().focusNode)) {
        insertQuote(getSelection().toString());
    } else {
        ajax.get(requrl, insertQuoteDecoded);
    }

    // DOM element (non-jQuery) -> Bool
    function inPost(elt) {
        return $.contains($('#' + elem + post)[0],elt);
    }

    // Str -> undefined
    function insertQuote(response) {
        if ($('#quickpost').raw().value !== '') {
            $('#quickpost').raw().value += "\n\n";
        }
        $('#quickpost').raw().value = $('#quickpost').raw().value
            + "[quote=" + username + (link == true ? "|" + target : "") + "]"
            + response
            + "[/quote]";
        resize('quickpost');
    }

    // Str -> undefined
    function insertQuoteDecoded(response) {
        if ($('#quickpost').raw().value !== '') {
            $('#quickpost').raw().value += "\n\n";
        }
        $('#quickpost').raw().value = $('#quickpost').raw().value
            + "[quote=" + username + (link == true ? "|" + target : "") + "]"
            + response
            + "[/quote]";
        resize('quickpost');
    }
}

async function edit_post(id) {
    const dataset  = document.getElementById('edit-' + id).dataset;
    const is_forum = location.href.match(/forums\.php/);

    // If no edit is already underway or a previous edit was finished, make the necessary dom changes.
    if (!$('#editbox' + id).results() || $('#editbox' + id + '.hidden').results()) {
        $('#reply_box').ghide();
        const boxWidth = location.href.match(/(artist|torrents)\.php/) ? "50" : "80";
        const inputname = is_forum ? "post" : "postid";
        const pmbox = (dataset.author != document.body.dataset.id)
            ? '<span id="pmbox' + id + '"><label>PM user on edit? <input type="checkbox" name="pm" value="1" /></label></span>'
            : '';
        $('#bar' + id).raw().cancel = $('#content' + id).raw().innerHTML;
        $('#bar' + id).raw().oldbar = $('#bar' + id).raw().innerHTML;
        $('#content' + id).raw().innerHTML = "<div id=\"preview" + id
            + "\"></div><form id=\"form" + id + "\" method=\"post\" action=\"\">"
            + pmbox + "<input type=\"hidden\" name=\"auth\" value=\""
            + document.body.dataset.auth + "\" />&nbsp;<input type=\"hidden\" name=\"key\" value=\""
            + dataset.key + "\" />&nbsp;<input type=\"hidden\" name=\""
            + inputname + "\" value=\"" + id + "\" /><textarea id=\"editbox"
            + id + "\" onkeyup=\"resize('editbox"
            + id + "');\" name=\"body\" cols=\"" + boxWidth + "\" rows=\"10\"></textarea></form>";
        $('#bar' + id).raw().innerHTML = '<input type="button" value="Preview" onclick="Preview_Edit('
            + id + ');" />&nbsp;<input type="button" value="Post" onclick="Save_Edit('
            + id + ')" />&nbsp;<input type="button" value="Cancel" onclick="Cancel_Edit('
            + id + ');" />';
        $('#postcontrol-' + id).ghide();
    }

    /* If it's the initial edit, fetch the post content to be edited.
     * If editing is already underway and edit is pressed again, reset the post
     * (keeps current functionality, move into brackets to stop from happening).
     */
    let response = await fetch(new Request(
        (is_forum ? "?action=get_post&post=" : "comments.php?action=get&postid=") + id,
    ));
    const box = 'editbox' + id;
    document.getElementById(box).value = await response.text();
    resize(box);
}

function Cancel_Edit(postid) {
    if (confirm("Are you sure you want to cancel?")) {
        $('#reply_box').gshow();
        $('#bar' + postid).raw().innerHTML = $('#bar' + postid).raw().oldbar;
        $('#content' + postid).raw().innerHTML = $('#bar' + postid).raw().cancel;
        $('#postcontrol-' + postid).gshow();
    }
}

function Preview_Edit(postid) {
    $('#bar' + postid).raw().innerHTML = "<input type=\"button\" value=\"Editor\" onclick=\"Cancel_Preview(" + postid + ");\" />&nbsp;<input type=\"button\" value=\"Post\" onclick=\"Save_Edit(" + postid + ")\" />&nbsp;<input type=\"button\" value=\"Cancel\" onclick=\"Cancel_Edit(" + postid + ");\" />";
    ajax.post("ajax.php?action=preview","form" + postid, function(response) {
        $('#preview' + postid).raw().innerHTML = response;
        $('#editbox' + postid).ghide();
        BBCode.run_renderer($('#preview' + postid));
    });
}

function Cancel_Preview(postid) {
    $('#bar' + postid).raw().innerHTML = "<input type=\"button\" value=\"Preview\" onclick=\"Preview_Edit(" + postid + ");\" />&nbsp;<input type=\"button\" value=\"Post\" onclick=\"Save_Edit(" + postid + ")\" />&nbsp;<input type=\"button\" value=\"Cancel\" onclick=\"Cancel_Edit(" + postid + ");\" />";
    $('#preview' + postid).raw().innerHTML = "";
    $('#editbox' + postid).gshow();
}

function Save_Edit(postid) {
    $('#reply_box').gshow();
    if (location.href.match(/forums\.php/)) {
        ajax.post("forums.php?action=takeedit","form" + postid, function (response) {
            $('#bar' + postid).raw().innerHTML = "<a href=\"reports.php?action=report&amp;type=post&amp;id="+postid+"\" class=\"brackets\">Report</a>&nbsp;<a href=\"#\">↑</a>";
            $('#content' + postid).raw().innerHTML = response;
            $('#editbox' + postid).ghide();
            $('#pmbox' + postid).ghide();
            $('#postcontrol-' + postid).gshow();
            BBCode.run_renderer($('#content' + postid));
        });
    } else {
        ajax.post("comments.php?action=take_edit","form" + postid, function (response) {
            $('#bar' + postid).raw().innerHTML = "";
            $('#content' + postid).raw().innerHTML = response;
            $('#editbox' + postid).ghide();
            $('#pmbox' + postid).ghide();
            $('#postcontrol-' + postid).gshow();
            BBCode.run_renderer($('#content' + postid));
        });
    }
}

async function delete_post(id) {
    if (confirm('Are you sure you wish to delete this post?') == true) {
        await fetch(new Request(
            (location.href.match(/forums\.php/)
                ? "forums.php?action=delete&auth="
                : "comments.php?action=take_delete&auth=" 
            ) + document.body.dataset.auth + "&postid=" + id
        ));
        document.getElementById('post' + id).classList.add('hidden');
    }
}

async function LoadEdit(type, post, depth) {
    let response = await fetch(new Request(
        "ajax.php?action=post_edit&postid=" + post + "&depth=" + depth + "&type=" + type
    ));
    document.getElementById('content' + post).innerHTML = await response.text();
}

function AddPollOption(id) {
    let form    = document.createElement("form");
    form.method = "POST";

    let auth   = document.createElement("input");
    auth.type  = "hidden";
    auth.name  = "auth";
    auth.value = document.body.dataset.auth;
    form.appendChild(auth);

    let action   = document.createElement("input");
    action.type  = "hidden";
    action.name  = "action";
    action.value = "add_poll_option";
    form.appendChild(action);

    let threadid   = document.createElement("input");
    threadid.type  = "hidden";
    threadid.name  = "threadid";
    threadid.value = id;
    form.appendChild(threadid);

    let input  = document.createElement("input");
    input.type = "text";
    input.name = "new_option";
    input.size = "50";
    form.appendChild(input);

    let submit   = document.createElement("input");
    submit.type  = "submit";
    submit.id    = "new_submit";
    submit.value = "Add";
    form.appendChild(submit);

    let item = document.createElement("li");
    item.appendChild(form);

    let list = $('#poll_options').raw();
    list.appendChild(item);
}

/**
 * HTML5-compatible storage system
 * Tries to use 'oninput' event to detect text changes and sessionStorage to save it.
 *
 * new StoreText('some_textarea_id', 'some_form_id', 'some_topic_id')
 * The form is required to remove the stored text once it is submitted.
 *
 * Topic ID is required to retrieve the right text on the right topic
 **/
function StoreText (field, form, topic) {
    this.field = document.getElementById(field);
    this.form = document.getElementById(form);
    this.key = 'auto_save_temp';
    this.keyID = 'auto_save_temp_id';
    this.topic = +topic;
    this.load();
}
StoreText.prototype = {
    constructor : StoreText,
    load : function () {
        if (this.enabled() && this.valid()) {
            this.retrieve();
            this.autosave();
            this.clearForm();
        }
    },
    valid : function () {
        return this.field && this.form && !isNaN(this.topic);
    },
    enabled : function () {
        return window.sessionStorage && typeof window.sessionStorage === 'object';
    },
    retrieve : function () {
        const r = sessionStorage.getItem(this.key);
        if (this.topic === +sessionStorage.getItem(this.keyID) && r) {
            this.field.value = r;
        }
    },
    remove : function () {
        sessionStorage.removeItem(this.keyID);
        sessionStorage.removeItem(this.key);
    },
    save : function () {
        sessionStorage.setItem(this.keyID, this.topic);
        sessionStorage.setItem(this.key, this.field.value);
    },
    autosave : function () {
        $(this.field).on(this.getInputEvent(), $.proxy(this.save, this));
    },
    getInputEvent : function () {
        let e = '';
        if ('oninput' in this.field) {
            e = 'input';
        } else if (document.body.addEventListener) {
            e = 'change keyup paste cut';
        } else {
            e = 'propertychange';
        }
        return e;
    },
    clearForm : function () {
        $(this.form).submit($.proxy(this.remove, this));
    }
};

document.addEventListener('DOMContentLoaded', () => {
    Array.from(document.getElementsByClassName('delete-post')).forEach((del) => {
        del.addEventListener('click', () => {
            delete_post(del.dataset.id);
        });
    });

    Array.from(document.getElementsByClassName('edit-post')).forEach((edit) => {
        edit.addEventListener('click', () => {
            edit_post(edit.id.substr(edit.id.indexOf('edit-') + 5)); // edit-123 => 123
        });
    });
});
