"use strict";

function init_pitch(sel) {
    const input = document.getElementById('pitch');
    sel.addEventListener('change', () => {
        input.value = (sel.value === '0')
            ? ''
            : sel.options[sel.selectedIndex].text;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const selector = document.getElementById('news-pitch');
    if (selector) {
        init_pitch(selector);
    }
});
