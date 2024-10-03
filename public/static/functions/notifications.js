"use strict";

async function clearItem(tid) {
    await fetch(new Request(
        "?action=notify_clear_item&torrentid=" + tid
        + "&auth=" + document.body.dataset.auth
    ));
    document.getElementById('torrent' + tid).remove();
}

async function clearSelected(filterId) {
    const checkBoxes = filterId
        ? $('.notify_box_' + filterId, $('#notificationform_' + filterId))
        : $('.notify_box');
    let checkedBoxes = [];
    for (let i = checkBoxes.length - 1; i >= 0; i--) {
        if (checkBoxes[i].checked) {
            checkedBoxes.push(checkBoxes[i].value);
        }
    }
    await fetch(new Request(
        "?action=notify_clear_items&torrentids=" + checkedBoxes.join(',')
        + "&auth=" + document.body.dataset.auth
    ));
    for (let i = checkedBoxes.length - 1; i >= 0; i--) {
        document.getElementById('torrent' + checkedBoxes[i]).remove();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    let notifyBoxes = $('.notify_box');
    notifyBoxes.keydown(function(e) {
        let nextBox = false;
        let index = notifyBoxes.index($(this));
        if (index > 0 && e.which === 75) { // K
            nextBox = notifyBoxes.get(index - 1);
        } else if (index < (notifyBoxes.length - 1) && e.which === 74) { // J
            nextBox = notifyBoxes.get(index + 1);
        } else if (e.which === 88) { // X
            $(this).prop('checked', !$(this).prop('checked'));
        }
        if (nextBox) {
            nextBox.focus();
            $(window).scrollTop(
                $(nextBox).position()['top'] = $(window).height() / 4
            );
        }
    });
});
