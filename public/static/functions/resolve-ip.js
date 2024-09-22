/**
 * Function to auto-resolve ip addresses.
 * Elements must be of class resolve-ipv4 and have a data attribute named ip.
 * e.g.: <span class="resolve-ipv4" data-ip="127.0.0.1">Waiting...</span>
 */

"use strict";

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.resolve-ipv4').forEach(async (e) => {
        const response = await fetch(new Request(
            'tools.php?action=get_host&ip=' + e.dataset.ip
        ));
        const data = await response.json();
        document.querySelectorAll('[data-ip="' + data.ip + '"]').forEach((ipaddr) => {
            ipaddr.textContent = data.hostname;
        });
    });
});
