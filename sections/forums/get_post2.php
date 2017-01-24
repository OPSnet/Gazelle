<?
//TODO: make this use the cache version of the thread, save the db query
/*********************************************************************\
//--------------Get Post--------------------------------------------//

This gets the raw BBCode of a post. It's used for editing and
quoting posts.

It gets called if $_GET['action'] == 'get_post'. It requires
$_GET['post'], which is the ID of the post.

\*********************************************************************/

// Quick SQL injection check
if (!$_GET['post'] || !is_number($_GET['post'])) {
    error(0);
}

// Variables for database input
$PostID = $_GET['post'];

// Mainly
$DB->query("
	SELECT
		p.Body,
		t.ForumID
	FROM forums_posts AS p
		JOIN forums_topics AS t ON p.TopicID = t.ID
	WHERE p.ID = '44107'");
list($Body, $ForumID) = $DB->next_record(MYSQLI_NUM);
var_dump($Body);

$DB->query("
	SELECT
		p.Body,
		t.ForumID
	FROM forums_posts AS p
		JOIN forums_topics AS t ON p.TopicID = t.ID
	WHERE p.ID = '44107'");
list($Body, $ForumID) = $DB->next_record(MYSQLI_NUM, false);
var_dump($Body);
var_dump(Format::make_utf8($Body));
var_dump(Format::is_utf8($Body));
$encoding = mb_detect_encoding($Body, 'UTF-8, ISO-8859-1');
var_dump($encoding);
if ($encoding != 'UTF-8') {
    $Body = @mb_convert_encoding($Str, 'UTF-8', $Encoding);
    var_dump("NOT UTF-8");
    var_dump($Body);
}
var_dump(mb_convert_encoding($Body, 'HTML-ENTITIES', 'UTF-8'));

$Body = preg_replace("/&(?![A-Za-z]{0,4}\w{2,3};|#[0-9]{2,5};)/m", '&amp;', $Body);
var_dump($Body);

$Replace = array(
    "'",'"',"<",">",
    '&#128;','&#130;','&#131;','&#132;','&#133;','&#134;','&#135;','&#136;',
    '&#137;','&#138;','&#139;','&#140;','&#142;','&#145;','&#146;','&#147;',
    '&#148;','&#149;','&#150;','&#151;','&#152;','&#153;','&#154;','&#155;',
    '&#156;','&#158;','&#159;'
);

$With = array(
    '&#39;','&quot;','&lt;','&gt;',
    '&#8364;','&#8218;','&#402;','&#8222;','&#8230;','&#8224;','&#8225;','&#710;',
    '&#8240;','&#352;','&#8249;','&#338;','&#381;','&#8216;','&#8217;','&#8220;',
    '&#8221;','&#8226;','&#8211;','&#8212;','&#732;','&#8482;','&#353;','&#8250;',
    '&#339;','&#382;','&#376;'
);

$Body = str_replace($Replace, $With, $Body);
var_dump($Body);

// Is the user allowed to view the post?
if (!Forums::check_forumperm($ForumID)) {
    error(0);
}

// This gets sent to the browser, which echoes it wherever
var_dump(trim($Body));
echo trim($Body);