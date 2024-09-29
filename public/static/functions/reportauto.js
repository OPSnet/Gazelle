"use strict";

(function () {
function submitComment(event) {
    event.preventDefault();

    const URL = "report_auto.php?action=add_comment";
    const target = event.target;
    const FD = new FormData(target);
    const XHR = new XMLHttpRequest();

    Array.from(target.elements).forEach(e => e.disabled = true);

    function error(err) {
        alert("error submitting comment");
        console.log("error", err);
        Array.from(target.elements).forEach(e => e.disabled = false);
    }

    XHR.addEventListener("load", (e) => {
        if (e.target.status !== 200) {
            error("bad status: " + e.target.status + " " + e.target.responseText);
        } else {
            target.reset();
            Array.from(target.elements).forEach(e => e.disabled = false);
        }
    });

    XHR.addEventListener("error", (e) => {
        error(e);
    });

    XHR.open("POST", URL);
    XHR.send(FD);
}

function submitAction(event) {
    const target = event.target;
    const ACTION = target.dataset.action;
    function on_success() {
        const newAction = ACTION.startsWith('un') ? ACTION.slice(2) : 'un' + ACTION;
        target.textContent = newAction.charAt(0).toUpperCase() + newAction.slice(1);
        target.dataset.action = newAction;
        target.disabled = false;
    }
    doAction(event, on_success);
}

function submitAllAction(event) {
    function on_success() {
        event.target.style.textDecoration = 'line-through';
    }
    doAction(event, on_success);
}

function doAction(event, success_cb) {
    event.preventDefault();

    const target = event.target;
    const ACTION = target.dataset.action;
    const ACTION_ID = target.dataset.id;
    let URL = "report_auto.php?action=" + ACTION + "&id=" + ACTION_ID + "&auth=" + document.body.dataset.auth;
    if ("typeid" in target.dataset) {
        URL += "&type=" + target.dataset.typeid;
    }
    const XHR = new XMLHttpRequest();

    target.disabled = true;

    function error(err) {
        alert("error");
        console.log("error", err);
        target.disabled = false;
    }

    XHR.addEventListener("load", (e) => {
        if (e.target.status !== 200) {
            error("bad status: " + e.target.status + " " + e.target.responseText);
        } else {
            success_cb();
        }
    });

    XHR.addEventListener("error", (e) => {
        error(e);
    });

    XHR.open("GET", URL);
    XHR.send();
}

document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.auto_reports_list form.add_comment').
        forEach(x => x.addEventListener("submit", submitComment));
    document.querySelectorAll('.auto_reports_list button.action_button').
        forEach(x => x.addEventListener("click", submitAction));
    document.querySelectorAll('.auto_reports .all_buttons button.all_button').
        forEach(x => x.addEventListener("click", submitAllAction));
});

})();
