function toggleNotes(id) {
    var style = $('#notes_div_' + id).raw().style.display;
    if (style == "none") {
        $('#notes_div_' + id).raw().style.display = "block";
    }
    else {
        $('#notes_div_' + id).raw().style.display = "none";
    }
}

function saveNotes(id) {
    var notes = $('#notes_' + id).raw().value;
    notes = notes.replace(/(\r\n|\n\r|\r|\n)/g, '<br />');
    var post = new Array();
    post['id'] = id;
    post['notes'] = notes;
    ajax.post('reports.php?action=add_notes', post, function (response) {
        if (JSON.parse(response)['status'] != 'success') {
            alert("Error, could not save notes");
        }
    });

}

function claim(id) {
    var post = new Array();
    post['id'] = id;
    ajax.post('reports.php?action=claim', post, function (response) {
        var json = JSON.parse(response);
        if (json['status'] == 'failure') {
            alert("Error, could not claim.");
        }
        if (json['status'] == 'dupe') {
            alert("Oops, this report has already been claimed.");
        }
        if (json['status'] == 'success') {
            var username = json['username'];
            $('#claim_' + id).raw().innerHTML = '<a href="#" onclick="return false;">Claimed by ' + username + '</a>';
        }
    });
}

function unClaim(id) {
    const post = {
        id: id,
        remove: 1,
    };
    ajax.post('reports.php?action=unclaim', post, function (response) {
        var json = JSON.parse(response);
        if (json['status'] == 'success') {
            document.getElementById('claimed_' + id).innerHTML = '<a href="#" id="claim_' + id + '" onclick="claim(' + id + '); return false;" class="brackets">Claim</a>';
        }
    });
}

function resolve(id, claimer) {
    let answer = true;
    if (!claimer) {
        if (document.getElementById('claimed_' + id)) {
            answer = confirm("This is a claimed report. Are you sure you want to resolve it?");
        }
    }
    if (answer) {
        ajax.post('reports.php?action=resolve', 'report_form_' + id, function (response) {
                var json = JSON.parse(response);
                if (json['status'] == 'success') {
                    $('#report_' + id).remove();
                } else {
                    alert(json['status']);
                }
            }
        );
    }
    return false;
}

document.addEventListener('DOMContentLoaded', () => {
    let reports = document.querySelectorAll(".user-report-reason");
    reports.forEach((report) => {  
        report.addEventListener("click", () => {
            report.classList.toggle("user-report-reason");
        }, false);
    });
});
