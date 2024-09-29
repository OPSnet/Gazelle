document.addEventListener('DOMContentLoaded', function() {
    var field = $('input[name="adjusted_score"]');
    $('form[name="edit_log"] input:checkbox').each(function() {
        if ($(this).data('score')) {
            if ($(this).is(':checked')) {
                field.data('actual', field.data('actual') - $(this).data('score'));
            }
        }
        $(this).click(function() {
            if ($(this).data('score')) {
                var change = $(this).data('score');
                if ($(this).is(':checked')) {
                    field.data('actual', field.data('actual') - change);
                }
                else {
                    field.data('actual', field.data('actual') + change);
                }
            }
            field.val(Math.max(0, field.data('actual')));
        });
    });

    var previous = 0;
    [
        'crc_mismatches',
        'suspicious_positions',
        'timing_problems'
    ].forEach(function(value) {
        var input = $('input[name="' + value + '"]');
        field.data('actual', field.data('actual') - (parseInt(input.val()) * input.data('score')));
        field.val(Math.max(0, field.data('actual')));
        input.on('focus', function() {
            previous = this.value;
        }).change(function() {
            var value = parseInt(this.value);
            if (isNaN(value) || value < 0) {
                value = 0;
                this.value = value;
            }
            var change = (value - previous) * $(this).data('score');
            field.data('actual', field.data('actual') - change);
            field.val(Math.max(0, field.data('actual')));
        });
    });
});
