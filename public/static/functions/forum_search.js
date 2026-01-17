document.addEventListener('DOMContentLoaded', function() {
    $(".forum_category").click(function(e) {
        const id = this.id;
        const is_checked = $(this).text() != "Check all";
        $(this).text(is_checked ? "Check all" : "Uncheck all");
        $("input[data-category='" + id + "']").prop("checked", !is_checked);
        e.preventDefault();
    });

    $("#type_body").click(function() {
        $("#post_created_row").gshow();
    });
    $("#type_title").click(function() {
        $("#post_created_row").ghide();
    });
});
