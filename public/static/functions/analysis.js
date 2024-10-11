/* global toggleChecks */

"use strict";

document.addEventListener('DOMContentLoaded', () => {
    // allow error log rows to be bulk-toggled for faster clearing
    document.getElementById('clear-all').addEventListener('click', () => {
        toggleChecks('error-log', false, '.clear-row');
    });
});
