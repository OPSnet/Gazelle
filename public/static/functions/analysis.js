/* allow error log rows to be bulk-toggled for faster clearing */

/* global toggleChecks */

document.addEventListener('DOMContentLoaded', function () {
    $('#clear-all').click(function () {
        toggleChecks('error-log', false, '.clear-row');
    });
});
