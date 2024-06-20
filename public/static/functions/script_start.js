"use strict";

/* Site wide functions */

// http://www.thefutureoftheweb.com/blog/adddomloadevent
// retrieved 2010-08-12
var addDOMLoadEvent = (
    function() {
        var e = [], t, s, n, i, o, d = document, w = window, r = 'readyState', c = 'onreadystatechange',
            x = function() {
                    n = 1;
                    clearInterval(t);
                    while (i = e.shift()) {
                        i();
                    }
                    if (s) {
                        s[c] = ''
                    }
                };
        return function(f) {
                if (n) {
                    return f();
                }
                if (!e[0]) {
                    d.addEventListener && d.addEventListener("DOMContentLoaded", x, false);
                    /*@cc_on@*//*@if(@_win32)d.write("<script id=__ie_onload defer src=//0><\/scr"+"ipt>");s=d.getElementById("__ie_onload");s[c]=function(){s[r]=="complete"&&x()};/*@end@*/
                    if (/WebKit/i.test(navigator.userAgent))
                        t = setInterval(function() {
                                /loaded|complete/.test(d[r]) && x()
                                }, 10);
                        o = w.onload;
                        w.onload = function() {
                                x();
                                o && o()
                                }
                }
                e.push(f)
                }
    }
)();

//PHP ports
function isset(variable) {
    return typeof (variable) !== 'undefined';
}

function is_array(input) {
    return typeof(input) === 'object' && input instanceof Array;
}

function byte_format(size, precision) {
    if (precision === undefined) {
        precision = 2;
    }
    var steps = 0;
    while (steps < 8 && size >= 1024) {
        steps++;
        size = size / 1024;
    }
    var ext;
    switch (steps) {
        case 0: ext = ' B';
                break;
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

//returns key if true, and false if false. better than the PHP funciton
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
}

function gazURL() {
    var path = window.location.pathname.split('/');
    var path = path[path.length - 1].split(".")[0];
    var splitted = window.location.search.substr(1).split("&");
    var query = {};
    var length = 0;
    for (var i = 0; i < splitted.length; i++) {
        var q = splitted[i].split("=");
        if (q != "") {
            query[q[0]] = q[1];
            length++;
        }
    };
    query['length'] = length;
    var response = new Array();
    response['path'] = path;
    response['query'] = query;
    return response;
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

document.addEventListener('DOMContentLoaded', function () {
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
