"use strict";
document.addEventListener('DOMContentLoaded', function () {
    const elemIdMap = [
        ['debug-view-cache',     'debug_cache'],
        ['debug-view-del-cache', 'debug_cache_del'],
        ['debug-view-error',     'debug_error'],
        ['debug-view-flag',      'debug_flag'],
        ['debug-view-ocelot',    'debug_ocelot'],
        ['debug-view-perf',      'debug_perf'],
        ['debug-view-query-my',  'debug-query-my'],
        ['debug-view-query-pg',  'debug-query-pg'],
        ['debug-view-sphinxql',  'debug_sphinxql'],
    ];
    elemIdMap.forEach(val => {
        const src    = document.getElementById(val[0]);
        const target = document.getElementById(val[1]);
        // NB: error, ocelot, sphinxql are not always present on a given page
        if (src && target) {
            src.addEventListener('click', e => {
                target.classList.toggle('hidden');
                e.preventDefault();
            });
        }
    });
});
