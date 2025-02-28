/* global toggleChecks */

import { toggleChecks } from "global.js";

document.addEventListener('DOMContentLoaded', function () {
    $('#clear-all').click(function () {
        toggleChecks('error-log', false, '.clear-row');
    });
});
