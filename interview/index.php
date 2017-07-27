<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox('Welcome!','Hello, and welcome to the ' . $SITENAME . ' Interview Preparation website! This site was written as a guide for potential users to learn about music formats, transcodes, torrenting, and burning and ripping &mdash; everything you need to know in order to pass the ' . $SITENAME . ' interview.');

newsbox('What is ' . $SITENAME . '?','Founded on November 19, 2016, ' . $SITENAME . ' is a private BitTorrent site with a large selection of music, comic books, software, audiobooks, and eBooks. With fast download speeds, well-seeded torrents, and a wide selection of music files encoded in a variety of formats, ' . $SITENAME . ' is a paradise for music lovers.');

newsbox('Are you ready to join ' . $SITENAME . '?', $SITENAME .' is an invite-only site. If you do not know any current members, however, you may receive an invite by passing a ' . $SITENAME . ' interview. By opening invites to anyone who can pass our interview, we hope to find members who are active in the community, interested in music, and willing to share with others by seeding and uploading music from their own collections.<br /><br /><em><span>Note: If you have previously had an account on ' . $SITENAME . ', go to #disabled on IRC and follow the directions in the topic in order to talk to a moderator about getting your account re-enabled. DO NOT try to interview, or your accounts will be permanently banned.</span></em>');

newsbot();

	include('publicfooter.php'); 
?>