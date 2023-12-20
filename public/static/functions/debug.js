document.addEventListener('DOMContentLoaded', function () {
    $("#debug-view-cache").click(function() { $(this).parents('.layout').next('#debug_cache').gtoggle(); return false;});
    $("#debug-view-del-cache").click(function() { $(this).parents('.layout').next('#debug_cache').gtoggle(); return false;});
    $("#debug-view-class").click(function() { $(this).parents('.layout').next('#debug_class').gtoggle(); return false;});
    $("#debug-view-error").click(function() { $(this).parents('.layout').next('#debug_error').gtoggle(); return false;});
    $("#debug-view-extension").click(function() { $(this).parents('.layout').next('#debug_extension').gtoggle(); return false;});
    $("#debug-view-flag").click(function() { $(this).parents('.layout').next('#debug_flag').gtoggle(); return false;});
    $("#debug-view-include").click(function() { $(this).parents('.layout').next('#debug_include').gtoggle(); return false;});
    $("#debug-view-ocelot").click(function() { $(this).parents('.layout').next('#debug_ocelot').gtoggle(); return false;});
    $("#debug-view-perf").click(function() { $(this).parents('.layout').next('#debug_perf').gtoggle(); return false;});
    $("#debug-view-query").click(function() { $(this).parents('.layout').next('#debug_query').gtoggle(); return false;});
    $("#debug-view-sphinxql").click(function() { $(this).parents('.layout').next('#debug_sphinxql').gtoggle(); return false;});
    $("#debug-view-task").click(function() { $(this).parents('.layout').next('#debug_task').gtoggle(); return false;});
    $("#debug-view-var").click(function() { $(this).parents('.layout').next('#debug_var').gtoggle(); return false;});
});
