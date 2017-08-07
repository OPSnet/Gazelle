<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox('After the interview', '<strong>*POSTING THE CONTENTS OF THIS INTERVIEW ANYWHERE OR SHOWING IT TO ANYONE WILL COST YOU YOUR ACCOUNT OR FUTURE INTERVIEWS!*</strong>
<h2>You Passed!</h2>
Hooray! Nice job answering all of those questions. After you log in, make sure you re-read and follow the Golden Rules. It is helpful to download the ' . $SITENAME . ' Toolbox (which is freeleech) for programs that most of us use. Then get going! Post in the forums, upload your own CD rips, chat with us in IRC, download music&#8230;
<h2>You Failed!</h2>
That stinks. Don&#8217;t worry, the interview is meant to be educational, which is why you have a total of three tries to pass the interview. Study up on this site and try again after 48 hours (2 days) have passed.<br /><br />
If you have failed three times, you may not interview again. However, you may still be invited by a friend!');

newsbot();

	include('publicfooter.php'); 
?>