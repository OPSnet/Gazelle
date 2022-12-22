document.addEventListener('DOMContentLoaded', function() {
    $('#password_toggle').click(function () {
        for (name of ['password', 'new_pass_1', 'new_pass_2']) {
            if (document.getElementById(name)) {
                field = document.getElementById(name);
                if (field.getAttribute("type") === "password") {
                    field.setAttribute("type", "text");
                    $('#password_toggle').raw().innerHTML ='&#x1F62E;';
                } else {
                    field.setAttribute("type", "password");
                    $('#password_toggle').raw().innerHTML = '&#x1FAE3;';
                }
            }
        }
    });
});
