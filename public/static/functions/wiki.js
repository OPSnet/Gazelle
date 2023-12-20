function Remove_Alias(alias) {
    ajax.get("wiki.php?action=delete_alias&auth=" + authkey + "&alias=" + alias, function(response) {
        $('#alias_' + alias).ghide();
    });
}

document.addEventListener('DOMContentLoaded', function() {
    $("#delete-confirm").click(function() {
        return confirm('Are you sure you want to delete this article?\nYes, DELETE, not as in \'Oh hey, if this is wrong we can get someone to magically undelete it for us later\' it will be GONE.\nGiven this new information, do you still want to DELETE this article and all its revisions and all its aliases and act as if it never existed?');
    });
});
