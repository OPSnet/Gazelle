$(document).ready(function() {
    $("#perm-defaults").click(function() {
        for (i = 0; i < $('#permissionsform').raw().elements.length; i++) {
            element = $('#permissionsform').raw().elements[i];
            if (element.id.substr(0, 8) == 'default_') {
                $('#' + element.id.substr(8)).raw().checked = element.checked;
            }
        }
        return false;
    });
});

