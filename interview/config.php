<?

$SITENAME = "Orpheus";
$interviewchan = "#recruitment";

function newstop() { 
	print("<div class=\"main_column\">"); 
}

function newsbox($heading = '', $text = '') {
	print("<div class=\"box news_post\"><div class=\"head\"><strong>" . ($heading ? "$heading" : "") . "</strong></div><div class=\"pad\">" . ($text ? "$text" : "") . "</div></div>\n");
}

function newsbot() { 
	print("</div>"); 
}

?>