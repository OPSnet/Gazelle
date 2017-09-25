// ==UserScript==
// @name         Gazelle - Torrentpage JSON export
// @namespace    http://tampermonkey.net/
// @version      0.6
// @description  Add JSON export buttons to torrents
// @author       Flacstradamus@notwhat
// @include      http*://redacted.ch/torrents.php?id=*
// @include      http*://redacted.ch/artist.php?id=*
// @include      http*://hydra.zone/torrents.php?id=*
// @include      http*://hydra.zone/artist.php?id=*
// @include      http*://libble.me/torrents.php?id=*
// @include      http*://libble.me/artist.php?id=*
// @include      http*://lztr.me/torrents.php?id=*
// @include      http*://lztr.me/artist.php?id=*
// ==/UserScript==

(function() {
	'use strict';

	var downloadlinkElms = document.querySelectorAll('a[href*="torrents.php"]');
	for(var i=0,link, l=downloadlinkElms.length;i<l;i++) {
		if(downloadlinkElms[i].href.indexOf('action=download') != -1 && downloadlinkElms[i].href.indexOf('usetoken=') == -1) {
			link = document.createElement('a');
			link.textContent = 'JS';
			var txtNode = document.createTextNode(' | ');
			link.href= 'ajax.php?action=torrent&id=' + downloadlinkElms[i].href.replace(/^.*?id=(\d+)&.*?$/,'$1');
			link.download = document.querySelector('h2').textContent + ' ['+ location.host + '].json';
			downloadlinkElms[i].parentElement.lastElementChild.after(txtNode);
			txtNode.after(link);
		}
	}
})();