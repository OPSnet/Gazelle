function ChangeReportType() {
    $.post("reportsv2.php?action=ajax_report", $('#reportform').serialize(), function(response) {
        $('#dynamic_form').html(response);
    });
}

function ChangeResolve(reportid) {
    const url = 'reportsv2.php?action=ajax_get_resolve_options&type='
        + document.getElementById('resolve_type' + reportid).value;
    $.getJSON(url, function(x) {
        $('#delete' + reportid).prop('checked', x[0]);
        if ($('#uploaderid' + reportid).val() === $('#reporterid' + reportid).val()) {
            $('#upload' + reportid).prop('checked', false);
            $('#warning' + reportid).val('0');
        } else {
            $('#upload' + reportid).prop('checked', x[1]);
            $('#warning' + reportid).val(x[2]);
        }
        $('#update_resolve' + reportid).enable();
    });
}

function Load(reportid) {
    document.getElementById('resolve_type' + reportid).value = document.getElementById('type' + reportid).value;
    ChangeResolve(reportid);
}

function ErrorBox(reportid, message) {
    $('#all_reports').prepend('<div class="box pad center" id="error_box">Message from report ' + reportid + ': ' + message + '\n<input type="button" value="Hide Errors" onclick="HideErrors();" /></div>');
}

function HideErrors() {
    document.getElementById('error_box')?.remove();
}

function TakeResolve(reportid) {
    $('#submit_' + reportid).disable();
    $.post("reportsv2.php?action=takeresolve", $('#reportform_' + reportid).serialize(), function(response) {
        if (response) {
            ErrorBox(reportid, response);
        } else {
            if ($('#from_delete' + reportid).length) {
                window.location.search = '?id=' + $('#from_delete' + reportid).val();
            } else {
                $('#report' + reportid).remove();
                if ($('#dynamic').prop('checked')) {
                    NewReport(1);
                }
            }
        }
    });
}

function NewReport(q, view, id) {
    var url = 'reportsv2.php?action=ajax_new_report&uniqurl=' + q;
    if (view) {
        url += '&view=' + view;
    }
    if (id) {
        url += '&id=' + id;
    }
    $.get(url, function (response) {
        if (response) {
            var div = $(response);
            var id = div.data("reportid");
            if (!$('#report'+id).length) {
                $('#all_reports').append(div);
                $('#no_reports').remove();
                if ($('#type', div).length) {
                    Load(id);
                }
            }
        } else {
            // No new reports at this time
            if (!$('.report').length && !$('#no_reports') == 0) {
                $('#all_reports').append($('<div id="no_reports" class="box pad center"><strong>No new reports! \\o/</strong></div>'));
            }
        }
        if (--q > 0) {
            // Recursion to avoid grabbing the same report multiple times
            NewReport(q, view, id);
        }
    });
}

function AddMore(view, id) {
    // Function will add the amount of reports in the input box unless that will take it over 50
    var num = parseInt($('#repop_amount').val()) || 10;
    var curCount = $('.report').length;
    if (curCount < 50) {
        NewReport(Math.min(num, 50 - curCount), view, id);
    }
}

function SendPM(reportid) {
    $.post('reportsv2.php?action=ajax_take_pm', $('#reportform_' + reportid).serialize(), function(response) {
        $('#uploader_pm' + reportid).val(response);
    });
}

function UpdateComment(reportid) {
    $.post('reportsv2.php?action=ajax_update_comment', $('#reportform_' + reportid).serialize(), function(response) {
        if (response) {
            alert(response);
        }
    });
}

function GiveBack(reportid) {
    if (reportid) {
        $.get("reportsv2.php?action=ajax_unclaim&id=" + reportid, function(response) {
            if (response) {
                alert(response);
            }
        });
        $('#report' + reportid).remove();
    } else {
        $('#all_reports input[name="reportid"]').each(function() {
            $.get("reportsv2.php?action=ajax_unclaim&id=" + this.value, function(response) {
                if (response) {
                    alert(response);
                }
            });
            $('#report' + this.value).remove();
        });
    }
}

function ManualResolve(reportid) {
    $('#resolve_type' + reportid).append('<option value="manual">Manual Resolve</option>').val('manual');
    TakeResolve(reportid);
}

function Dismiss(reportid) {
    $('#resolve_type' + reportid).append('<option value="dismiss">Invalid Report</option>').val('dismiss');
    TakeResolve(reportid);
}

function ClearReport(reportid) {
    $('#report' + reportid).remove();
}

function Grab(reportid) {
    if (reportid) {
        $.get("reportsv2.php?action=ajax_claim&id=" + reportid, function(response) {
            if (response == 1) {
                $('#grab' + reportid).disable();
            } else {
                alert('Grab failed for some reason :/');
            }
        });
    } else {
        $('#all_reports input[name="reportid"]').each(function() {
            var reportid = this.value;
            $.get("reportsv2.php?action=ajax_claim&id=" + reportid, function(response) {
                if (response == 1) {
                    $('#grab' + reportid).disable();
                } else {
                    alert("One of those grabs failed, sorry I can't be more useful :P");
                }
            });
        });
    }
}

function MultiResolve() {
    $('input[name="multi"]:checked').each(function() {
        TakeResolve(this.id.substr(5));
    });
}

function UpdateResolve(reportid) {
    var url = 'reportsv2.php?action=ajax_update_resolve&reportid=' + reportid
        + "&auth=" + $('input[name="auth"]').val()
        + "&newresolve=" + $('#resolve_type' + reportid).val()
        + "&categoryid=" + $('#categoryid' + reportid).val();
    $.get(url, function(response) {
        $('#update_resolve' + reportid).disable();
    });
}


function Switch(reportid, otherid) {
    // We want to switch positions of the reported torrent
    // This entails invalidating the current report and creating a new with the correct preset.
    Dismiss(reportid);

    var report = {
        auth: authkey,
        reportid: reportid,
        otherid: otherid,
    };

    $.post('reportsv2.php?action=ajax_switch', report, function(response) {
        //Returns new report ID.
        if (isNaN(response)) {
            alert(response);
        } else {
            window.location = 'reportsv2.php?view=report&id=' + response;
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const reportType = document.getElementById('type');
    if (reportType) {
        reportType.addEventListener('change', function() {
            ChangeReportType();
        });
        ChangeReportType();
    }
});
