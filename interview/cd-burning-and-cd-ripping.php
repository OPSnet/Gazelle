<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox('CD bburning and CD ripping','<strong>CD ripping</strong> is a way to extract the music files from a CD. <strong>CD burning</strong> is a way to make a CD from music files.
<h3>Log Files</h3>
A <strong>log file</strong> is a text file with the file extension &#8220;.log&#8221;. Like its name suggests, it acts as a log of the entire ripping process and it records any errors that may have occurred. You may not, for any reason, modify a log file. It is strictly against the rules and you will be warned for a very long time. Click <a title="Sample Log File" href="log-file.php" target="_blank">here</a> to see an example of a log file.
<h3>Cue Files</h3>
A <strong>cue file</strong> is a text file with the file extension &#8220;.cue&#8221;. Cue files act as a catalog or a table of contents of a CD and allow you to burn a CD identical to an original CD. Click <a title="Sample Cue File" href="cue-file.php" target="_blank">here</a> to see an example of a cue file.
<h3>Suggested CD Ripping Programs</h3>
' . $SITENAME . ' suggests using <strong>Exact Audio Copy (EAC)</strong> on Windows or Linux (with Wine), and <strong>XLD</strong> on Mac OS. Both EAC and XLD produce high quality rips with sufficient logs to prove that the files are up to standard.');

newsbot();

	include('publicfooter.php'); 
?>