function Ban(ip, elemID) {
    var notes = prompt("Enter notes for this ban");
    if (notes != null && notes.length > 0) {
        var xmlhttp;
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange=function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                document.getElementById(elemID).innerHTML = "<strong>[Banned]</strong>";
            }
        }
        xmlhttp.open("GET", "tools.php?action=quick_ban&perform=create&ip=" + ip + "&notes=" + notes, true);
        xmlhttp.send();
    }
}

function UnBan(ip, id, elemID) {
    var xmlhttp;
    if (window.XMLHttpRequest) {
        xmlhttp = new XMLHttpRequest();
    } else {
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
            document.getElementById(elemID).innerHTML = "Ban";
            document.getElementById(elemID).onclick = function() { Ban(ip, elemID); return false; };
        }
    }
    xmlhttp.open("GET","tools.php?action=quick_ban&perform=delete&id=" + id + "&ip=" + ip, true);
    xmlhttp.send();
}
