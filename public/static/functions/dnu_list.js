document.addEventListener('DOMContentLoaded', () => {
    // Reorder table rows through drag'n'drop
    $('#dnu tbody').sortable({
        cancel: 'input, .colhead, .rowa',
        helper: function (_unused, elements) {
            // Iterate through each table cell and correct width
            elements.children().each(function () {
                $(this).width($(this).width());
            });
            return elements;
        },
        update: function () {
            let request = $.ajax({
                url: 'tools.php',
                type: "post",
                data: 'action=dnu_alter&auth=' + document.body.dataset.auth + '&submit=Reorder&' + $(this).sortable('serialize'),
            });
            request.done(function () {
            });
        }
    });
});
