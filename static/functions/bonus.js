function PreviewTitle(BBCode) {
	$.post('bonus.php?action=title&preview=true', {
		title: $('#title').val(),
		BBCode: BBCode
	}, function(response) {
		$('#preview').html(response);
	});
}

/**
 * @return {boolean}
 */
function ConfirmPurchase(item, next, element) {
	var check = (next) ? next(element) : true;
	if (!check) {
		event.preventDefault();
		return false;
	}
	var check = confirm('Are you sure you want to purchase ' + item + '?');
	if (!check) {
		event.preventDefault();
		return false;
	}
	return true;

}
/**
 * @return {boolean}
 */
function ConfirmOther(Element) {
	var name = prompt('Enter username to give tokens to:');
	if (!name || name === '') {
		return false;
	}
	$(Element).attr('href', $(Element).attr('href') + '&user=' + encodeURIComponent(name));
	return true;
}