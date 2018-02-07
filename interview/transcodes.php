<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox('Transcodes', '
<strong>Transcoding (verb)</strong> a file means converting from one format to another. A <strong>transcode (noun)</strong> can mean any converted file, but is usually used in a negative context (as in a bad transcode).
<h3>Good Transcodes</h3>
A <strong>good transcode</strong> means that during the transcode process, the file has either never been converted to lossy, or the file has only been converted to lossy once during the last step.
<br />
<p>Examples of good transcodes:</p>
<ul>
<li>uncompressed lossless &gt; compressed lossless</li>
<li>compressed lossless &gt; uncompressed lossless</li>
<li>compressed lossless &gt; compressed lossless</li>
<li>uncompressed lossless &gt; lossy</li>
<li>compressed lossless &gt; lossy</li>
</ul>
<h3>Bad Transcodes</h3>
A <strong>bad transcode</strong> means that during the transcode process, the file has either been converted to a lossy format more than once, or the file has been converted from lossy to lossless. <strong>Bad transcodes are prohibited on ' . $SITENAME . '.</strong>
<p>Examples of bad transcodes:</p>
<ul>
<li>higher lossy bitrate &gt; lower lossy bitrate</li>
<li>same bitrate lossy &gt; same bitrate lossy</li>
<li>lossy &gt; lossless</li>
</ul>');

newsbot();

	include('publicfooter.php'); 
?>