"use strict";

/**
 * Check or uncheck checkboxes in formElem
 * If masterElem is false, toggle each box, otherwise use masterElem's status on all boxes
 * If elemSelector is false, act on all checkboxes in formElem
 */
function toggleChecks(formElem, masterElem, elemSelector) {
    elemSelector = elemSelector || 'input:checkbox';
    if (masterElem) {
        $('#' + formElem + ' ' + elemSelector).prop('checked', masterElem.checked);
    } else {
        $('#' + formElem + ' ' + elemSelector).each(function() {
            this.checked = !this.checked;
        });
    }
}

//Lightbox stuff

/*
 * If loading from a thumbnail, the lightbox is shown first with a "loading" screen
 * while the full size image loads, then the HTML of the lightbox is replaced with the image.
 */

var lightbox = {
    init: function (image, size) {
        if (typeof(image) == 'string') {
            $('#lightbox').gshow().listen('click', lightbox.unbox).raw().innerHTML =
                '<p size="7" style="color: gray; font-size: 50px;">Loading...<p>';
            $('#curtain').gshow().listen('click', lightbox.unbox);
            var src = image;
            image = new Image();
            image.onload = function() {
                lightbox.box_async(image);
            };
            image.src = src;
        }
        if (image.naturalWidth === undefined) {
            var tmp = document.createElement('img');
            tmp.style.visibility = 'hidden';
            tmp.src = image.src;
            image.naturalWidth = tmp.width;
        }
        if (image.naturalWidth > size) {
            lightbox.box(image);
        }
    },
    box: function (image) {
        if (!(image.parentNode != null && image.parentNode.tagName.toUpperCase() == 'A')) {
            $('#lightbox').gshow().listen('click', lightbox.unbox).raw().innerHTML = '<img src="' + image.src + '" alt="" />';
            $('#curtain').gshow().listen('click', lightbox.unbox);
        }
    },
    box_async: function (image) {
        if (!(image.parentNode != null && image.parentNode.tagName.toUpperCase() == 'A')) {
            $('#lightbox').raw().innerHTML = '<img src="' + image.src + '" alt="" />';
        }
    },
    unbox: function (data) {
        $('#curtain').ghide();
        $('#lightbox').ghide().raw().innerHTML = '';
    }
};

function resize(id) {
    var textarea = document.getElementById(id);
    if (textarea.scrollHeight > textarea.clientHeight) {
        textarea.style.height = Math.min(1000, textarea.scrollHeight + textarea.style.fontSize) + 'px';
    }
}

//ZIP downloader stuff
function add_selection() {
    var selected = $('#formats').raw().options[$('#formats').raw().selectedIndex];
    if (selected.disabled === false) {
        var listitem = document.createElement("li");
        listitem.id = 'list' + selected.value;
        listitem.innerHTML = '                        <input type="hidden" name="list[]" value="' + selected.value + '" /> ' +
'                        <span style="float: left;">' + selected.innerHTML + '</span>' +
'                        <a href="#" onclick="remove_selection(\'' + selected.value + '\'); return false;" style="float: right;" class="brackets">X</a>' +
'                        <br style="clear: all;" />';
        $('#list').raw().appendChild(listitem);
        $('#opt' + selected.value).raw().disabled = true;
    }
}

function remove_selection(index) {
    $('#list' + index).remove();
    $('#opt' + index).raw().disabled = '';
}

function toggle_display(selector) {
    let element = document.getElementById(selector);
    if (!element) {
        element = document.getElementsByClassName(selector);
    }
    if (element.style.display === "none" || element.style.display === '') {
        element.style.display = "block";
    } else {
        element.style.display = "none";
    }
}

/*
 *  TODO: This is probably obsolete, replace it with something modern.
 *
 *  UPDATE: We were forced to create an individual XHR for each request
 *  to avoid race conditions on slower browsers where the request would
 *  be overwritten before the callback triggered, and leave it hanging.
 *  This only happened in FF3.0 that we tested.
 *
 *  Example usage 1:
 *  ajax.handle = function () {
 *      $('#preview' + postid).raw().innerHTML = ajax.response;
 *      $('#editbox' + postid).ghide();
 *  }
 *  ajax.post("ajax.php?action=preview","#form-id" + postid);
 *
 *  Example usage 2:
 *  ajax.handle = function() {
 *      $('#quickpost').raw().value = "[quote="+username+"]" + ajax.response + "[/quote]";
 *  }
 *  ajax.get("?action=get_post&post=" + postid);
 *
 */

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
        if (typeof data === 'object' && data instanceof Array) {
            for (var key in data) {
                query += key + '=' + encodeURIComponent(data[key]) + '&';
            }
        } else {
            var elements = document.getElementById(data).elements;
            for (var i = 0, il = elements.length; i < il; i++) {
                var element = elements[i];
                if (element.disabled || element.name === '') {
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
    ajax.get("bookmarks.php?action=add&type=" + type + "&auth=" + document.body.dataset.auth + "&id=" + id, function() {
        bmLinks.parent('.remove_bookmark, .add_bookmark').toggleClass('add_bookmark remove_bookmark');
        bmLinks.html(newName).attr('title', 'Remove bookmark').removeAttr('onclick').off('click').click(function() {
            Unbookmark(type, id, oldName);
            return false;
        });
    });
}

function Unbookmark(type, id, newName) {
    if (window.location.pathname.indexOf('bookmarks.php') != -1) {
        ajax.get("bookmarks.php?action=remove&type=" + type + "&auth=" + document.body.dataset.auth + "&id=" + id, function() {
            $('#group_' + id).remove();
            $('.groupid_' + id).remove();
            $('.bookmark_' + id).remove();
        });
    } else {
        var bmLinks = $('#bookmarklink_' + type + '_' + id + ', .bookmarklink_' + type + '_' + id);
        var oldName = bmLinks.html();
        ajax.get("bookmarks.php?action=remove&type=" + type + "&auth=" + document.body.dataset.auth + "&id=" + id, function() {
            bmLinks.parent('.remove_bookmark, .add_bookmark').toggleClass('add_bookmark remove_bookmark');
            bmLinks.html(newName).attr('title', 'Add bookmark').removeAttr('onclick').off('click').click(function() {
                Bookmark(type, id, oldName);
                return false;
            });
        });
    }
}

/* Site wide functions */

function byte_format(size, precision) {
    if (precision === undefined) {
        precision = 2;
    }
    let steps = 0;
    while (steps < 8 && size >= 1024) {
        steps++;
        size = size / 1024;
    }
    let ext;
    switch (steps) {
        case 1: ext = ' KiB';
                break;
        case 2: ext = ' MiB';
                break;
        case 3: ext = ' GiB';
                break;
        case 4: ext = ' TiB';
                break;
        case 5: ext = ' PiB';
                break;
        case 6: ext = ' EiB';
                break;
        case 7: ext = ' ZiB';
                break;
        case 8: ext = ' YiB';
                break;
        default: ext = ' B';
                break;
    }
    return (size.toFixed(precision) + ext);
}

function ratio_css(ratio) {
    if (ratio < 0.1) { return 'r00'; }
    if (ratio < 0.2) { return 'r01'; }
    if (ratio < 0.3) { return 'r02'; }
    if (ratio < 0.4) { return 'r03'; }
    if (ratio < 0.5) { return 'r04'; }
    if (ratio < 0.6) { return 'r05'; }
    if (ratio < 0.7) { return 'r06'; }
    if (ratio < 0.8) { return 'r07'; }
    if (ratio < 0.9) { return 'r08'; }
    if (ratio < 1) { return 'r09'; }
    if (ratio < 2) { return 'r10'; }
    if (ratio < 5) { return 'r20'; }
    return 'r50';
}

function ratio(dividend, divisor, color) {
    if (!color) {
        color = true;
    }
    if (divisor == 0 && dividend == 0) {
        return '--';
    } else if (divisor == 0) {
        return '<span class="r99">∞</span>';
    } else if (dividend == 0 && divisor > 0) {
        return '<span class="r00">-∞</span>';
    }
    var rat = ((dividend / divisor) - 0.005).toFixed(2); //Subtract .005 to floor to 2 decimals
    if (color) {
        var col = ratio_css(rat);
        if (col) {
            rat = '<span class="' + col + '">' + rat + '</span>';
        }
    }
    return rat;
}

function save_message(message) {
    var messageDiv = document.createElement("div");
    messageDiv.className = "save_message";
    messageDiv.innerHTML = message;
    $("#content").raw().insertBefore(messageDiv,$("#content").raw().firstChild);
}

function error_message(message) {
    var messageDiv = document.createElement("div");
    messageDiv.className = "error_message";
    messageDiv.innerHTML = message;
    $("#content").raw().insertBefore(messageDiv,$("#content").raw().firstChild);
}

// returns key if true, and false if false. better than the PHP funciton
// TODO: nuke this nonsense
function in_array(needle, haystack, strict) {
    if (strict === undefined) {
        strict = false;
    }
    for (var key in haystack) {
        if ((haystack[key] == needle && strict === false) || haystack[key] === needle) {
            return true;
        }
    }
    return false;
}

function array_search(needle, haystack, strict) {
    if (strict === undefined) {
        strict = false;
    }
    for (var key in haystack) {
        if ((strict === false && haystack[key] == needle) || haystack[key] === needle) {
            return key;
        }
    }
    return false;
}

var util = function (selector, context) {
    return new util.fn.init(selector, context);
};

function gazURL() {
    var path = window.location.pathname.split('/');
    path = path[path.length - 1].split(".")[0];
    var splitted = window.location.search.substr(1).split("&");
    var query = {};
    var length = 0;
    for (var i = 0; i < splitted.length; i++) {
        var q = splitted[i].split("=");
        if (q != "") {
            query[q[0]] = q[1];
            length++;
        }
    }
    query['length'] = length;
    var response = [];
    response['path'] = path;
    response['query'] = query;
    return response;
}

function showWarningMessage(content, button_cb=null) {
    let el = document.querySelector('.warning-message');
    if (!el) {
        el = document.createElement('div');
        el.classList.add('warning-message');

        const button = document.createElement('button');
        button.classList.add('warning-message-confirm');
        button.type = 'button';
        button.innerText = 'Ok';
        button.addEventListener('click', () => {
            if (button_cb) {
                button_cb();
            }
            el.remove();
        });

        el.appendChild(button);
        document.body.append(el);
    }
    el.prepend(content);
}

function clearWarningMessage() {
    document.querySelector('.warning-message')?.remove();
}

$.fn.extend({
    results: function () {
        return this.length;
    },
    gshow: function () {
        return this.remove_class('hidden');
    },
    ghide: function (force) {
        return this.add_class('hidden', force);
    },
    gtoggle: function (force) {
        //Should we interate and invert all entries, or just go by the first?
        if (!in_array('hidden', this[0].className.split(' '))) {
            this.add_class('hidden', force);
        } else {
            this.remove_class('hidden');
        }
        return this;
    },
    listen: function (event, callback) {
        for (var i = 0, il = this.length; i < il; i++) {
            var object = this[i];
            if (document.addEventListener) {
                object.addEventListener(event, callback, false);
            } else {
                object.attachEvent('on' + event, callback);
            }
        }
        return this;
    },
    add_class: function (class_name, force) {
        for (var i = 0, il = this.length; i < il; i++) {
            var object = this[i];
            if (object.className === '') {
                object.className = class_name;
            } else if (force || !in_array(class_name, object.className.split(' '))) {
                object.className = object.className + ' ' + class_name;
            }
        }
        return this;
    },
    remove_class: function (class_name) {
        for (var i = 0, il = this.length; i < il; i++) {
            var object = this[i];
            var classes = object.className.split(' ');
            var result = array_search(class_name, classes);
            if (result !== false) {
                classes.splice(result, 1);
                object.className = classes.join(' ');
            }
        }
        return this;
    },
    has_class: function(class_name) {
        for (var i = 0, il = this.length; i < il; i++) {
            var object = this[i];
            var classes = object.className.split(' ');
            if (array_search(class_name, classes)) {
                return true;
            }
        }
        return false;
    },
    toggle_class: function(class_name) {
        for (var i = 0, il = this.length; i < il; i++) {
            var object = this[i];
            var classes = object.className.split(' ');
            var result = array_search(class_name, classes);
            if (result !== false) {
                classes.splice(result, 1);
                object.className = classes.join(' ');
            } else {
                if (object.className === '') {
                    object.className = class_name;
                } else {
                    object.className = object.className + ' ' + class_name;
                }
            }
        }
        return this;
    },
    disable : function () {
        $(this).prop('disabled', true);
        return this;
    },
    enable : function () {
        $(this).prop('disabled', false);
        return this;
    },
    raw: function (number) {
        if (typeof number == 'undefined') {
            number = 0;
        }
        return $(this).get(number);
    },
    nextElementSibling: function () {
        var here = this[0];
        if (here.nextElementSibling) {
            return $(here.nextElementSibling);
        }
        do {
            here = here.nextSibling;
        } while (here.nodeType != 1);
        return $(here);
    },
    previousElementSibling: function () {
        var here = this[0];
        if (here.previousElementSibling) {
            return $(here.previousElementSibling);
        }
        do {
            here = here.nextSibling;
        } while (here.nodeType != 1);
        return $(here);
    },
    updateTooltip: function(tooltip) {
        if ($.fn.tooltipster) {
            $(this).tooltipster('update', tooltip);
        } else {
            $(this).attr('title', tooltip);
        }
        return this;
    },

    // Disable unset form elements to allow search URLs cleanups
    disableUnset: function() {
        $('input, select', this).filter(function() {
            return $(this).val() === "";
        }).disable();
        return this;
    },

    // Prevent double submission of forms
    preventDoubleSubmission: function() {
        $(this).submit(function(e) {
            var $form = $(this);
            if ($form.data('submitted') === true) {
                // Previously submitted - don't submit again
                e.preventDefault();
            } else {
                // Mark it so that the next submit can be ignored
                $form.data('submitted', true);
            }
        });
        // Keep chainability
        return this;
    }
});

document.addEventListener('DOMContentLoaded', () => {
    // autocomplete
    var url = new gazURL();
    var ARTIST_AUTOCOMPLETE_URL  = 'artist.php?action=autocomplete';
    var COLLAGE_AUTOCOMPLETE_URL = 'collages.php?action=autocomplete';
    var TAGS_AUTOCOMPLETE_URL    = 'torrents.php?action=autocomplete_tags';
    var SELECTOR = '[data-gazelle-autocomplete="true"]';

    $('#artistsearch' + SELECTOR).autocomplete({
        deferRequestBy: 300,
        onSelect : function(suggestion) {
            window.location = 'artist.php?id=' + suggestion['data'];
        },
        serviceUrl: ARTIST_AUTOCOMPLETE_URL,
    });

    $('#collagesearch' + SELECTOR).autocomplete({
        deferRequestBy: 300,
        onSelect : function(suggestion) {
            window.location = 'collages.php?id=' + suggestion['data'];
        },
        serviceUrl: COLLAGE_AUTOCOMPLETE_URL,
    });

    if (url.path == 'torrents' || url.path == 'upload' || url.path == 'artist' || (url.path == 'requests' && url.query['action'] == 'new') || url.path == 'collages') {
        $("#artist_0" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: ARTIST_AUTOCOMPLETE_URL
        });
        $("#artist" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: ARTIST_AUTOCOMPLETE_URL
        });
        $("#artistsimilar" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: ARTIST_AUTOCOMPLETE_URL
        });
        $("#collage_ref" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: COLLAGE_AUTOCOMPLETE_URL + (url.path == 'artist' ? '&artist=1' : '')
        });
    }
    if (url.path == 'torrents' || url.path == 'upload' || url.path == 'collages' || url.path == 'requests' || url.path == 'top10' || (url.path == 'requests' && url.query['action'] == 'new')) {
        $("#tags" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            delimiter: ',',
            serviceUrl: TAGS_AUTOCOMPLETE_URL
        });
        $("#tagname" + SELECTOR).autocomplete({
            deferRequestBy: 300,
            serviceUrl: TAGS_AUTOCOMPLETE_URL,
        });
    }

    // header search bar
    $('#torrentssearch').focus(function () { if (this.value == 'Torrents') { this.value = ''; }});
    $('#torrentssearch').blur(function () { if (this.value == '') { this.value = 'Torrents'; }});

    $('#artistsearch').focus(function () { if (this.value == 'Artists') { this.value = ''; }});
    $('#artistsearch').blur(function () { if (this.value == '') { this.value = 'Artists'; }});

    $('#collagesearch').focus(function () { if (this.value == 'Collages') { this.value = ''; }});
    $('#collagesearch').blur(function () { if (this.value == '') { this.value = 'Collages'; }});

    $('#requestssearch').focus(function () { if (this.value == 'Requests') { this.value = ''; }});
    $('#requestssearch').blur(function () { if (this.value == '') { this.value = 'Requests'; }});

    $('#forumssearch').focus(function () { if (this.value == 'Forums') { this.value = ''; }});
    $('#forumssearch').blur(function () { if (this.value == '') { this.value = 'Forums'; }});

    $('#logsearch').focus(function () { if (this.value == 'Log') { this.value = ''; }});
    $('#logsearch').blur(function () { if (this.value == '') { this.value = 'Log'; }});

    $('#userssearch').focus(function () { if (this.value == 'Users') { this.value = ''; }});
    $('#userssearch').blur(function () { if (this.value == '') { this.value = 'Users'; }});
});
