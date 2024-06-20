"use strict";
document.addEventListener('DOMContentLoaded', function () {
    function get_debug_toggle(other_id) {
        return ev => {
            let el = ev.target;
            while ((el = el.parentNode) && el !== document) {
                if (el.matches('.layout')) {
                    const nextEl = el.nextElementSibling;
                    if (nextEl && nextEl.id === other_id) {
                        nextEl.classList.toggle('hidden');
                        return false;
                    }
                }
            }
            return false;
        };
    }
    const elemIdMap = [
        ['debug-view-cache', 'debug_cache'],
        ['debug-view-del-cache', 'debug_cache'],
        ['debug-view-class', 'debug_class'],
        ['debug-view-error', 'debug_error'],
        ['debug-view-extension', 'debug_extension'],
        ['debug-view-flag', 'debug_flag'],
        ['debug-view-include', 'debug_include'],
        ['debug-view-ocelot', 'debug_ocelot'],
        ['debug-view-perf', 'debug_perf'],
        ['debug-view-query', 'debug_query'],
        ['debug-view-sphinxql', 'debug_sphinxql'],
        ['debug-view-task', 'debug_task'],
        ['debug-view-var', 'debug_var'],
    ];
    elemIdMap.forEach(val => {
        document.getElementById(val[0])?.addEventListener('click', get_debug_toggle(val[1]));
    });
});
