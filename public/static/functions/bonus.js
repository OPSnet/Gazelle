function PreviewTitle(BBCode) {
    $.post('bonus.php?action=title&preview=true', {
        title: $('#title').val(),
        BBCode: BBCode
    }, function(response) {
        $('#preview').html(response);
    });
}

function NoOp(event, item, next, element) {
    return next && next(event, element);
}

/**
 * @param {Object} event
 * @param {String} item
 * @param {Function} next
 * @param {Object} element
 * @return {boolean}
 */
function ConfirmPurchase(event, item, next, element) {
    var check = (next) ? next(event, element) : true;
    if (!check) {
        event.preventDefault();
        return false;
    }
    check = confirm('Are you sure you want to purchase ' + item + '?');
    if (!check) {
        event.preventDefault();
        return false;
    }
    return true;

}
