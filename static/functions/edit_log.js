$(document).ready(function() {
	$('form[name="edit_log"] input:checkbox').each(function() {
		$(this).click(function() {
			if ($(this).data('score')) {
				var change = $(this).data('score');
				var field = $('input[name="adjusted_score"]');
				var score = parseInt(field.val());
				if ($(this).is(':checked')) {
					field.val(score - change);
				}
				else {
					field.val(score + change);
				}
			}
			field.val(Math.max(0, field.val()));
		});
	});

	var previous = 0;
	[
		'crc_mismatches',
		'suspicious_positions',
		'timing_problems'
	].forEach(function(value) {
		$('input[name="' + value + '"]').on('focus', function() {
			previous = this.value;
		}).change(function() {
			var value = parseInt(this.value);
			if (value < 0) {
				value = 0;
				this.value = value;
			}
			var change = (value - previous) * $(this).data('score');
			var field = $('input[name="adjusted_score"]');
			var score = parseInt(field.val());
			field.val(score - change);
			field.val(Math.max(0, field.val()));
		});
	});
});