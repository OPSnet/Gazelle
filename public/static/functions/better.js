"use strict";

document.addEventListener('DOMContentLoaded', () => {
    const e = document.getElementById('collector-list');
    if (e) {
        e.addEventListener('click', (e) => {
            const token_count = e.dataset.n;
            if (!confirm(
                (e.dataset.seed > 0
                    ? 'Use '
                    : 'Warning! This torrent is not seeded at the moment, are you sure you want to use '
                )
                + token_count + ' token' + (token_count > 1 ? 's' : '') 
                + ' here?'
            )) {
                e.preventDefault();
            }
        });
    }
});
