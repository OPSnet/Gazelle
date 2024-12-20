/*
    TODO: Further optimize serialize function

    UPDATE: We were forced to create an individual XHR for each request
    to avoid race conditions on slower browsers where the request would
    be overwritten before the callback triggered, and leave it hanging.
    This only happened in FF3.0 that we tested.

    Example usage 1:
    ajax.handle = function () {
        $('#preview' + postid).raw().innerHTML = ajax.response;
        $('#editbox' + postid).ghide();
    }
    ajax.post("ajax.php?action=preview","#form-id" + postid);

    Example usage 2:
    ajax.handle = function() {
        $('#quickpost').raw().value = "[quote="+username+"]" + ajax.response + "[/quote]";
    }
    ajax.get("?action=get_post&post=" + postid);

*/
"use strict";
var ajax = {
    get: function (url, callback) {
        var req = new XMLHttpRequest();
        if (callback !== undefined) {
            req.onreadystatechange = function () {
                if (req.readyState !== 4 || req.status !== 200) {
                    return;
                }
                callback(req.responseText);
            };
        }
        req.open("GET", url, true);
        req.send(null);
    },
    post: function (url, data, callback) {
        var req = new XMLHttpRequest();
        var params = ajax.serialize(data);
        if (callback !== undefined) {
            req.onreadystatechange = function () {
                if (req.readyState !== 4 || req.status !== 200) {
                    return;
                }
                callback(req.responseText);
            };
        }
        req.open('POST', url, true);
        req.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        req.send(params);
    },
    serialize: function (data) {
        if (typeof data === 'object' && data.constructor.name != 'Array') {
            return new URLSearchParams(data).toString();
        }
        var query = '';
        if (is_array(data)) {
            for (var key in data) {
                query += key + '=' + encodeURIComponent(data[key]) + '&';
            }
        } else {
            var elements = document.getElementById(data).elements;
            for (var i = 0, il = elements.length; i < il; i++) {
                var element = elements[i];
                if (!isset(element) || element.disabled || element.name === '') {
                    continue;
                }
                switch (element.type) {
                    case 'text':
                    case 'hidden':
                    case 'password':
                    case 'textarea':
                    case 'select-one':
                        query += element.name + '=' + encodeURIComponent(element.value) + '&';
                        break;
                    case 'select-multiple':
                        for (var j = 0, jl = element.options.length; j < jl; j++) {
                            var current = element.options[j];
                            if (current.selected) {
                                query += element.name + '=' + encodeURIComponent(current.value) + '&';
                            }
                        }
                        break;
                    case 'radio':
                        if (element.checked) {
                            query += element.name + '=' + encodeURIComponent(element.value) + '&';
                        }
                        break;
                    case 'checkbox':
                        if (element.checked) {
                            query += element.name + '=' + encodeURIComponent(element.value) + '&';
                        }
                        break;
                }
            }
        }
        return query.substr(0, query.length - 1);
    }
};
//Bookmarks
function Bookmark(type, id, newName) {
    var bmLinks = $('#bookmarklink_' + type + '_' + id + ', .bookmarklink_' + type + '_' + id);
    var oldName = bmLinks.html();
    ajax.get("bookmarks.php?action=add&type=" + type + "&auth=" + authkey + "&id=" + id, function() {
        bmLinks.parent('.remove_bookmark, .add_bookmark').toggleClass('add_bookmark remove_bookmark');
        bmLinks.html(newName).attr('title', 'Remove bookmark').removeAttr('onclick').off('click').click(function() {
            Unbookmark(type, id, oldName);
            return false;
        });
    });
}

function Unbookmark(type, id, newName) {
    if (window.location.pathname.indexOf('bookmarks.php') != -1) {
        ajax.get("bookmarks.php?action=remove&type=" + type + "&auth=" + authkey + "&id=" + id, function() {
            $('#group_' + id).remove();
            $('.groupid_' + id).remove();
            $('.bookmark_' + id).remove();
        });
    } else {
        var bmLinks = $('#bookmarklink_' + type + '_' + id + ', .bookmarklink_' + type + '_' + id);
        var oldName = bmLinks.html();
        ajax.get("bookmarks.php?action=remove&type=" + type + "&auth=" + authkey + "&id=" + id, function() {
            bmLinks.parent('.remove_bookmark, .add_bookmark').toggleClass('add_bookmark remove_bookmark');
            bmLinks.html(newName).attr('title', 'Add bookmark').removeAttr('onclick').off('click').click(function() {
                Bookmark(type, id, oldName);
                return false;
            });
        });
    }
}
