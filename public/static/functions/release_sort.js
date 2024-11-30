/* global sortable_list_default */

document.addEventListener('DOMContentLoaded', function() {
    let serialize = function () {
        let a = [];
        $('#sortable input').each(function () {
            a.push(this.id);
        });
        $('#sorthide').val(JSON.stringify(a));
    };

    serialize();

    // Couldn't use an associative array because JavaScript sorting is stupid
    // https://dev-answers.blogspot.com/2012/03/javascript-object-keys-being-sorted-in.html
    $('#sortable')
        .on('click', 'input', function () {
            // the + converts the boolean to either 1 or 0
            this.id = this.id.slice(0, -1) + +this.checked;
            serialize();
        })
        .sortable({
            placeholder: 'ui-state-highlight',
            update: serialize
        });

    $('#toggle_sortable').click(function (e) {
        e.preventDefault();
        $('#sortable_container').slideToggle(function () {
            $('#toggle_sortable').text($(this).is(':visible') ? 'Collapse' : 'Expand');
        });
    });

    $('#reset_sortable').click(function (e) {
        e.preventDefault();
        $('#sortable').html(sortable_list_default); // var sortable_list_default is found on edit.php
        serialize();
    });
});
