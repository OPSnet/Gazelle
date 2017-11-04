function PreviewTitle(BBCode) {
	$.post('bonus.php?action=title&preview=true', {
		title: $('#title').val(),
		BBCode: BBCode
	}, function(response) {
		$('#preview').html(response);
	});
}

function ConfirmOther(Element) {
	var name = prompt('Enter username to give tokens to:');
	if (name === null || name === '') {
		event.preventDefault();
		return false;
	}
	$(Element).attr('href', $(Element).attr('href') + '&user=' + encodeURIComponent(name));
	return true;
}