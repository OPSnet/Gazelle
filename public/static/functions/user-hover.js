"use strict";

document.addEventListener('DOMContentLoaded', () => {
    function bgcolor(id, color) {
        document.querySelectorAll('[data-id="' + id + '"]').forEach(function(user) {
            user.style.backgroundColor = color;
        });
    }

    const user = document.querySelectorAll('.user-hover');
    user.forEach((u) => {
        u.addEventListener('mouseenter', (e) => {
            bgcolor(e.target.dataset.id, "#008000");
        });
        u.addEventListener('mouseleave', (e) => {
            bgcolor(e.target.dataset.id, "");
        });
    });
});
