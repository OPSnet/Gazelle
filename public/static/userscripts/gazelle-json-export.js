// ==UserScript==
// @name         Gazelle - Torrentpage JSON export
// @namespace    http://tampermonkey.net/
// @version      0.7.1
// @description  Add JSON export buttons to torrents
// @author       Flacstradamus@notwhat
// @author       itismadness@orpheus
// @include      http*://redacted.sh/torrents.php?id=*
// @include      http*://redacted.sh/artist.php?id=*
// @include      http*://hydra.zone/torrents.php?id=*
// @include      http*://hydra.zone/artist.php?id=*
// @include      http*://libble.me/torrents.php?id=*
// @include      http*://libble.me/artist.php?id=*
// @include      http*://lztr.me/torrents.php?id=*
// @include      http*://lztr.me/artist.php?id=*
// ==/UserScript==

(function() {
    'use strict';
    // only add one link, can get duplicates if using forward/back buttons in browser
    if (document.querySelectorAll('a[href*="ajax.php?action=torrent"]').length > 0) {
        return;
    }
    var downloadlinkElms = document.querySelectorAll('a[href*="torrents.php"]');
    for(var i=0,link, l=downloadlinkElms.length;i<l;i++) {
        if(downloadlinkElms[i].href.indexOf('action=download') != -1 && downloadlinkElms[i].href.indexOf('usetoken=') == -1) {
            link = document.createElement('a');
            link.textContent = 'JS';
            var txtNode = document.createTextNode(' | ');
            var torrentId = downloadlinkElms[i].href.replace(/^.*?id=(\d+)&.*?$/,'$1');
            link.href= 'ajax.php?action=torrent&id=' + torrentId;
            link.download = document.querySelector('h2').textContent + ' [' + torrentId + '] ['+ location.host + '].json';
            downloadlinkElms[i].parentElement.lastElementChild.after(txtNode);
            txtNode.after(link);
        }
    }
})();
