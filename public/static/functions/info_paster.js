"use strict";

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('paster').addEventListener('click', () => {
        let paste = '';
        Array.from(document.getElementsByClassName('info-paster')).forEach((e) => {
            paste += e.innerText;
        });
        let reason = document.getElementById('Reason'); 
        reason.value += '\n\n' + paste;
        reason.style.height = '0px';
        reason.style.height = (20 + reason.scrollHeight) + 'px';
    });
});
