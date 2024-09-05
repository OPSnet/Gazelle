/**
 * Function to auto-resolve ip addresses.
 * Elements must be of class resolve-ipv4 and have a data attribute named ip.
 * e.g.: <span class="resolve-ipv4" data-ip="127.0.0.1">Waiting...</span>
 */

/* global ajax */

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.resolve-ipv4').forEach(function(element) {
        ajax.get("tools.php?action=get_host&ip=" + element.dataset.ip, function(response) {
            response = JSON.parse(response);
            document.querySelectorAll('[data-ip="' + response.ip + '"]').forEach(function(ipaddr) {
                ipaddr.textContent = response.hostname;
            });
        });
    });
});
