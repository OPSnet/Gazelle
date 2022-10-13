/* allow error log rows to be bulk-toggled for faster clearing */

$(document).ready(function () {
    $('#clear-all').click(function () {
        toggleChecks('error-log', false, '.clear-row');
    });
});
