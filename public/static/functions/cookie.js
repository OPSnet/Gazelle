"use strict";

var cookie = {
    get: function (key_name) {
        const value = document.cookie.match('(^|;)?' + key_name + '=([^;]*)(;|$)');
        return (value) ? value[2] : null;
    },
    set: function (key_name, value, days) {
        if (days === undefined) {
            days = 365;
        }

        let date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = key_name + "=" + value + "; SameSite=Strict; path=/; expires=" + date.toGMTString() + ";";
    },
    del: function (key_name) {
        cookie.set(key_name, '', -1);
    },
    flush: function () {
        document.cookie = '';
    }
};
