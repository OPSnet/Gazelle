/* global ajax */

"use strict";

document.addEventListener('DOMContentLoaded', () => {
    let del = document.getElementById('delete-confirm');
    if (del) {
        // Will be null on the principal article, as it cannot be deleted
        del.addEventListener('click', (e) => {
            if (!confirm(
                'Are you sure you want to delete this article?\nYes, DELETE, not as in \'Oh hey, if this is wrong we can get someone to magically undelete it for us later\' it will be GONE.\nGiven this new information, do you still want to DELETE this article and all its revisions and all its aliases and act as if it never existed?'
            )) {
                e.preventDefault();
            }
        });
    }

    document.querySelectorAll('.wiki-remove-alias').forEach((a) => {  
        a.addEventListener('click', (e) => {
            const name = e.target.dataset.name;
            ajax.get(
                'wiki.php?action=delete_alias&auth=' + e.target.dataset.auth
                    + '&alias=' + name,
                () => {
                    document.getElementById('alias_' + name).classList.toggle('hidden');
                }
            );
            e.preventDefault();
        }, false);
    });
});
