<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox('Spectral Analysis', '<strong>Spectral analysis</strong> is a visual way to display the data in a music file. Every music note has a specific <strong>frequency</strong>: lower notes have lower frequencies and higher notes have higher frequencies. All of the frequencies are displayed on a <strong>spectral diagram</strong> (&#8220;<strong>spectral</strong>&#8221; for short), which is a graph of all the frequencies vs. time in a music file. Frequencies are measured in hertz (Hz) and kilohertz (1,000 Hz). Humans have a hearing range from about 20 Hz &#8212; 20kHz (20,000 Hz).
<br /><br />
Since spectrals show all the data in a file, they are helpful tools to use when you&#8217;re trying to decide whether or not a song has been transcoded. Every file has a relatively standard frequency cut-off.
<br /><br />
<strong>Click on any of the spectrals below to view it in a higher resolution.</strong>

<h3>CD / Lossless</h3>
Songs on a retail CD and lossless songs have frequencies that extend all the way to 22 kHz. Since lossless to lossless transcoding preserves all of the data in a music file, the spectral of a lossless song will look the same in FLAC, WAV (PCM), ALAC, etc.

<p style="text-align: center;"><a href="Guide-FLAC.jpg" rel="lightbox[35]"><img class="aligncenter  wp-image-134" title="Spectral (FLAC)" src="Guide-FLAC.jpg" alt="" width="580" height="220" /></a></p>

However, different genres have different-looking spectrals. The example above was a pop song, so most of the frequencies were represented. But look at this classical piano song.

<p style="text-align: center;"><a href="Guide-FLAC-Classical.jpg" rel="lightbox[35]"><img class="aligncenter  wp-image-133" title="Spectral (FLAC — Classical)" src="Guide-FLAC-Classical.jpg" alt="" width="580" height="220" /></a></p>

It looks much different, right? But it&#8217;s still a lossless spectral! Notice how &#8220;white noise&#8221; (the light purple) still extends to 22 kHz, even though those frequencies aren&#8217;t used.
<h3>MP3</h3>
Different types of MP3s have different frequency cut-offs. MP3s also tend to have a &#8220;shelf&#8221; at 16 kHz (you&#8217;ll see it in the spectrals).
<p style="text-align: center;">MP3 320kbps (CBR) has a frequency cut-off at 20.5 kHz.</p>
<p style="text-align: center;"><a href="Guide-MP3-320-CBR.jpg" rel="lightbox[35]"><img class="aligncenter  wp-image-139" title="Spectral (MP3 320)" src="Guide-MP3-320-CBR.jpg" alt="" width="580" height="220" /></a></p>
<p style="text-align: center;">MP3 256kbps (CBR) has a frequency cut-off at 20 kHz.</p>
<p style="text-align: center;"><a href="Guide-MP3-256-CBR.jpg" rel="lightbox[35]"><img class="aligncenter  wp-image-138" title="Spectral (MP3 256)" src="Guide-MP3-256-CBR.jpg" alt="" width="580" height="220" /></a></p>
<p style="text-align: center;">MP3 V0 has a frequency cut-off at 19.5 kHz.</p>
<p style="text-align: center;"><a href="Guide-MP3-V0.jpg" rel="lightbox[35]"><img class="aligncenter  wp-image-140" title="Spectral (MP3 V0)" src="Guide-MP3-V0.jpg" alt="" width="580" height="220" /></a></p>
<p style="text-align: center;">MP3 192kbps (CBR) has a frequency cut-off at 19 kHz.</p>
<p style="text-align: center;"><a href="Guide-MP3-192-CBR.jpg" rel="lightbox[35]"><img class="aligncenter  wp-image-136" title="Spectral (MP3 192)" src="Guide-MP3-192-CBR.jpg" alt="" width="580" height="220" /></a></p>
<p style="text-align: center;">MP3 V2 has a frequency cut-off at 18.5 kHz.</p>
<p style="text-align: center;"><a href="Guide-MP3-V2.jpg" rel="lightbox[35]"><img class="aligncenter  wp-image-141" title="Spectral (MP3 V2)" src="Guide-MP3-V2.jpg" alt="" width="580" height="220" /></a></p>
<p style="text-align: center;">MP3 128kbps (CBR) has a frequency cut-off at 16 kHz.</p>
<p style="text-align: center;"><a href="Guide-MP3-128-CBR.jpg" rel="lightbox[35]"><img class="aligncenter  wp-image-135" title="Spectral (MP3 128)" src="Guide-MP3-128-CBR.jpg" alt="" width="580" height="220" /></a></p>
<h3>Transcodes</h3>
How are spectrals helpful when trying to detect transcodes? Say you download a song in FLAC from a blog. The only way to verify that this song is truly a lossless file and not a transcoded file is by looking at its spectral. (Programs like AudioIdentifier are not reliable at detecting transcodes.)
<br /><br />
For example, the spectral below is of a FLAC file: the file extension is .flac, it is 21.8 MB, and it sounds okay.
<p style="text-align: center;"><a href="Guide-MP3-192-to-FLAC.jpg" rel="lightbox[35]"><img class="aligncenter  wp-image-137" title="Spectral (MP3 192 to FLAC)" src="Guide-MP3-192-to-FLAC.jpg" alt="" width="580" height="220" /></a></p>
But whoa, does that look anything like what a regular FLAC spectral should look like? No! This file was transcoded from MP3 192kbps (CBR) to FLAC. It&#8217;s a lossy to lossless transcode, which is bad.
<h3>Programs</h3>
For spectral analysis, we recommend using either <a title="Adobe Audition" href="http://www.adobe.com/products/audition.html" target="_blank"><strong>Adobe Audition</strong></a> (Windows or Mac OS), <a title="Audacity" href="http://audacity.sourceforge.net/" target="_blank"><strong>Audacity</strong></a> (Windows, Mac OS, Linux), and <a title="SoX" href="http://sox.sourceforge.net/" target="_blank">SoX</a> (Windows, Mac OS, Linux &mdash; command line only). All of the spectrals that appear in this guide were viewed in Adobe Audition CS 6.
Although you should use spectral analysis to determine whether a file is a transcode or not, you will need to use another program to first determine what bitrate or encoding preset the file claims to be. For this purpose, we recommend using <a title="AudioIdentifier" href="http://download.cnet.com/Audio-Identifier/3000-2141_4-10703771.html" target="_blank"><strong>Audio Identifier</strong></a> or <a title="dbPowerAmp" href="http://www.dbpoweramp.com/" target="_blank"><strong>dbPowerAmp</strong></a> on Windows and <a title="dnuos" href="https://bitheap.org/dnuos/" target="_blank"><strong>dnuos</strong></a> or <a title="MediaInfo" href="http://mediainfo.sourceforge.net/en" target="_blank"><strong>MediaInfo</strong></a> on Mac OS.');

newsbot();

	include('publicfooter.php'); 
?>