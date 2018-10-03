<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox('Prepare for the Interview','<p><strong>You are responsible for knowing all of the information on this site.</strong></p>
In order to receive an invite to ' . $SITENAME . ', you must pass our interview. Though every interview is different, the information you must learn in order to pass each interview is the same:
<ol>
<li><a title="Analog and Digital Music Sources" href="analog-and-digital-music-sources.php" target="_blank"><strong>Analog and Digital Music Sources</strong></a> &mdash; What&#8217;s the difference between vinyl and CDs?</li>
<li><a title="Audio Formats" href="audio-formats.php" target="_blank"><strong>Audio Formats</strong></a> &mdash; What are the different types of music formats, and which are allowed on ' . $SITENAME . '.</li>
<li><a title="MP3" href="mp3.php" target="_blank"><strong>MP3</strong></a> &mdash; Everything you need to know about LAME and LAME Presets.</li>
<li><a title="Transcodes" href="transcodes.php" target="_blank"><strong>Transcodes</strong></a> &mdash; How you can figure out if a transcode is good or bad.</li>
<li><a title="Torrenting" href="torrenting.php" target="_blank"><strong>Torrenting</strong></a> &mdash; BitTorrent vocabulary, ratio, and more!</li>
<li><a title="Spectral Analysis" href="spectral-analysis.php" target="_blank"><strong>Spectral Analysis</strong></a> &mdash; The best way to determine the bitrate of an unknown music file. (Pretty pictures!)</li>
<li><a title="CD Burning and CD Ripping" href="cd-burning-and-cd-ripping.php" target="_blank"><strong>CD Burning and CD Ripping</strong> </a> &mdash; How to make the best possible CD rip.</li>
<li><a title="' . $SITENAME . ' Rules" href="orpheus-rules.php" target="_blank"><strong>' . $SITENAME . ' Rules</strong></a> &mdash; Break them and perish.</li>
</ol>
Luckily, everything you need to know is right on this site! Make sure you carefully read through all of the pages under the Knowledge menu before you even think of queuing in ' . $interviewchan . '. Take notes if you wish, however, you may <strong>NOT</strong> use these notes while taking an interview.');

newsbot();

	include('publicfooter.php'); 
?>