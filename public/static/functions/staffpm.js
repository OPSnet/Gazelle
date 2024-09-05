/* global ajax, resize */

function SetMessage() {
    const id = document.getElementById('common_answers_select').value;

    ajax.get("?action=get_response&plain=1&id=" + id, function (data) {
        $('#quickpost').raw().value = data;
        $('#common_answers').ghide();
    });
}

function UpdateMessage() {
    const id = document.getElementById('common_answers_select').value;

    ajax.get("?action=get_response&plain=0&id=" + id, function (data) {
        $('#common_answers_body').raw().innerHTML = data;
        $('#first_common_response').remove();
    });
}

function SaveMessage(id) {
    const ajax_message = 'ajax_message_' + id;
    let ToPost = [];

    ToPost['id'] = id;
    ToPost['name'] = document.getElementById('response_name_' + id).value;
    ToPost['message'] = document.getElementById('response_message_' + id).value;

    ajax.post("?action=edit_response", ToPost, function (data) {
            if (data == '1') {
                document.getElementById(ajax_message).textContent = 'Response successfully created.';
            } else if (data == '2') {
                document.getElementById(ajax_message).textContent = 'Response successfully edited.';
            } else {
                document.getElementById(ajax_message).textContent = 'Something went wrong.';
            }
            $('#' + ajax_message).gshow();
            setTimeout("$('#" + ajax_message + "').ghide()", 2000);
        }
    );
}

function DeleteMessage(id, auth) {
    ajax.post("?action=delete_response", {'id': id, 'auth': auth}, function (data) {
        document.getElementById('response_' + id).classList.add('hidden');
        let ajax_message = document.getElementById('ajax_message_' + id);
        ajax_message.textContent = (data == '1') 
            ? 'Response successfully deleted.'
            : 'Something went wrong.';
        ajax_message.classList.remove('hidden');
        setTimeout(() => { ajax_message.classList.add('hidden'); }, 2000);
    });
}

function Assign() {
    let ToPost = [];
    ToPost['assign'] = document.getElementById('assign_to').value;
    ToPost['convid'] = document.getElementById('convid').value;

    ajax.post("?action=assign", ToPost, function (data) {
        if (data == '1') {
            document.getElementById('ajax_message').textContent = 'Conversation successfully assigned.';
        } else {
            document.getElementById('ajax_message').textContent = 'Something went wrong.';
        }
        $('#ajax_message').gshow();
        setTimeout("$('#ajax_message').ghide()", 2000);
    });
}

function PreviewResponse(id) {
    const div = '#response_div_'+id;
    if ($(div).has_class('hidden')) {
        let ToPost = [];
        ToPost['message'] = document.getElementById('response_message_'+id).value;
        ajax.post('?action=preview', ToPost, function (data) {
            document.getElementById('response_div_'+id).innerHTML = data;
            $(div).gtoggle();
            $('#response_message_'+id).gtoggle();
        });
    } else {
        $(div).gtoggle();
        $('#response_message_'+id).gtoggle();
    }
}

function PreviewMessage() {
    if ($('#preview').has_class('hidden')) {
        let ToPost = [];
        ToPost['message'] = document.getElementById('quickpost').value;
        ajax.post('?action=preview', ToPost, function (data) {
            document.getElementById('preview').innerHTML = data;
            $('#preview').gtoggle();
            $('#quickpost').gtoggle();
            $('#previewbtn').raw().value = "Edit";
        });
    } else {
        $('#preview').gtoggle();
        $('#quickpost').gtoggle();
        $('#previewbtn').raw().value = "Preview";
    }
}

function Quote(postid, username) {
    ajax.get("?action=get_post&post=" + postid, function(response) {
        if ($('#quickpost').raw().value !== '') {
            $('#quickpost').raw().value = $('#quickpost').raw().value + "\n\n";
        }
        $('#quickpost').raw().value = $('#quickpost').raw().value + "[quote=" + username + "]" +
            //response.replace(/(img|aud)(\]|=)/ig,'url$2').replace(/\[url\=(https?:\/\/[^\s\[\]<>"\'()]+?)\]\[url\](.+?)\[\/url\]\[\/url\]/gi, "[url]$1[/url]")
            response
        + "[/quote]";
        resize('quickpost');
    });
}
