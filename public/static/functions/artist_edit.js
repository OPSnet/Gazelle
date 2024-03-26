"use strict";

(function() {
    function makeEditable(ev, el) {
        ev.preventDefault();
        el.querySelector('.nra-name').classList.add('hidden');
        el.querySelector('.nra-link').classList.add('hidden');
        el.querySelector('.nra-rename-form').classList.remove('hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
        Array.from(document.getElementsByClassName('nra-group')).forEach((el) => {
            el.querySelector('.nra-link')
                .addEventListener("click", (e) => makeEditable(e, el), false);
        });
    });
})();
