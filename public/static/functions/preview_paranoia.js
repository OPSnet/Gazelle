(function() {
    // Used to get user ID from URL.
    function getURLParameter(name) {
        return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(location.search)||[null,""])[1].replace(/\+/g, '%20'))||null;
    }
    // Attach click event on document ready.
    $(function(){
        $('a#preview_paranoia').click(function(event) {
            event.preventDefault();
            // Collect the names of the checked "p_*" boxes the same way the
            // browser would on a real submit of the settings form. The
            // server-side Gazelle\Util\Paranoia::calculate() does the actual
            // reduction into a consistent set of paranoia settings, the same
            // as it does for the edit form's $_POST.
            var form = this.closest('form');
            var checked = Array.from(new FormData(form).keys())
                .filter(function(name) { return name.indexOf('p_') === 0; });
            var paranoiaString = checked.join(',');
            // Get user ID from URL parameter.
            var userId = getURLParameter("id");
            // Open a new tab with specified paranoia settings.
            window.open('user.php?id=' + encodeURIComponent(userId) + '&preview=1&paranoia=' + encodeURIComponent(paranoiaString), '_blank');
        });
    });
})();
