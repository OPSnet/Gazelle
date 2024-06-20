"use strict";
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('password_toggle').addEventListener('click', ev => {
        for (const name of ['password', 'new_pass_1', 'new_pass_2']) {
            const field = document.getElementById(name);
            if (field) {
                if (field.getAttribute("type") === "password") {
                    field.setAttribute("type", "text");
                    ev.target.innerHTML = '&#x1F62E;';
                } else {
                    field.setAttribute("type", "password");
                    ev.target.innerHTML = '&#x1FAE3;';
                }
            }
        }
    });
});
