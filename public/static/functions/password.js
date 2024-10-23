"use strict";

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('password_toggle')?.addEventListener('click', (e) => {
        for (const name of ['password', 'new_pass_1', 'new_pass_2']) {
            let field = document.getElementById(name);
            if (field) {
                if (field.getAttribute("type") === "password") {
                    field.setAttribute("type", "text");
                    e.target.innerHTML = '😮';
                } else {
                    field.setAttribute("type", "password");
                    e.target.innerHTML = '🫣';
                }
            }
        }
    });
});
