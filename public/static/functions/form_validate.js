/* global gazURL */

document.addEventListener('DOMContentLoaded', function() {
    let url = new gazURL();
    let query = url.query;
    switch (url.path) {
        case "forums":
            if (query['action'] == "new") {
                $("#newthreadform").validate();
            }
            break;
        case "reports":
            if (query['action'] == "report") {
                $("#report_form").validate();
            }
            break;
        case "inbox":
            if (query['action'] == "viewconv" || query['action'] == "compose") {
                $("#messageform").validate();
            }
            break;
        case "user":
            if (query['action'] == "notify") {
                $("#filter_form").validate();
            }
            break;
        case "requests":
            if (query['action'] == "new") {
                $("#request_form").preventDoubleSubmission();
            }
            break;
        case "tools":
            if (query['action'] == "mass_pm") {
                $("#messageform").validate();
            }
            break;
        default:
            break;
    }
});
