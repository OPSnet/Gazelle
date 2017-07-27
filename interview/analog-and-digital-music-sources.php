<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox('Analog and digital music sources','<p>Music uploaded to ' . $SITENAME . ' comes from two main sources: analog media and digital media.</p>

<h3>Analog Music Sources</h3>
An <strong>analog music source</strong> must use an <strong>analog to digital converter</strong> like a sound card to convert physical changes in an analog medium to a digital file that a computer can read. An <strong>analog medium</strong> is an object that stores music by being physically altered.
<h3>Examples</h3>
<ul>
<li>A tape recorder changes the magnetization of magnetic tape in a cassette tape to record sound. Plugging a tape deck into a recording device makes a digital copy of the analog cassette tape.</li>
<li>A record cutter carves grooves in a vinyl record to make a physical representation of the sound. Ripping vinyl through a preamp and into a sound card makes a digital copy of the analog vinyl.</li>
</ul>
Analog recordings can be ripped into digital music files, such as FLAC and MP3. Vinyl records are always approved for uploads, but moderator approval is required for uploads sourced from cassette tapes and other analog media.
<h3>Digital Music Sources</h3>
A <strong>digital music source</strong> has already been encoded into a format that a computer can read, so no conversion is necessary. A <strong>digital medium</strong> is an object that stores music in digital files (a string of binary numbers).
<h3>Examples</h3>
<ul>
<li>CDs</li>
<li>DVDs</li>
<li>Super Audio CDs (SACD)</li>
<li>WEB store downloads (iTunes, Amazon, etc.)</li>
</ul>
Digital music sources can be uploaded to ' . $SITENAME . ' after using spectral analysis to check for lossy transcodes.
<h3>Analog Music Sources vs. Digital Music Sources</h3>
There is still much debate about whether analog and digital sources sound different. Some people prefer the &#8220;feel&#8221; of vinyl and think that music on vinyl records sounds &#8220;warmer&#8221; and &#8220;fuller&#8221;. Others think that digital sources provide an unadulterated and pristine listening experience. ' . $SITENAME . ' allows both, so you can download and make your own judgement!');

newsbot();

	include('publicfooter.php'); 
?>