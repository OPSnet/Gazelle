jQuery(document).ready(function ($) {
    // Helper function to preserve table cell dimentions
    var fixDimentions = function (unused, elements) {
        // Iterate through each table cell and correct width
        elements.children().each(function () {
            $(this).width($(this).width());
        });
        return elements;
    };

    // Make table sortable
    $('#dnu tbody').sortable({
        helper: fixDimentions,
        cancel: 'input, .colhead, .rowa',
        update: function (event, ui) {
            request = $.ajax({
                url: 'tools.php',
                type: "post",
                data: 'action=dnu_alter&auth=' + authkey + '&' + $(this).sortable('serialize') + '&submit=Reorder'
            });
            request.done(function (response, textStatus, jqXHR) {
            });
        }
    });
});
