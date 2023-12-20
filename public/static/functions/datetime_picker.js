var dateFormat = 'Y-m-d';
var timeFormat = 'H:i';
var timeStep = 15;
document.addEventListener('DOMContentLoaded', function() {
    // TODO: FIX_STATIC_SERVER
    $.getScript("static/functions/jquery.datetimepicker.js", function() {
        $(".date_picker").datetimepicker({
            timepicker: false,
            format: dateFormat,
            validateOnBlur: false
        });
        $(".datetime_picker").datetimepicker({
            format: dateFormat,
            step: timeStep,
            validateOnBlur: false
        });
        $(".time_picker").datetimepicker({
            format: timeFormat,
            datepicker: false,
            step: timeStep,
            validateOnBlur: false

        });
    });
});
