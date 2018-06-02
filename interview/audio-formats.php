<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox('Audio Formats','An <strong>audio format</strong> is a type of computer file that stores music. Music formats are either uncompressed lossless, compressed lossless, or lossy.
<h2>Bitrates</h2>
A <strong>bitrate</strong> is the number of bits conveyed or transferred in a unit of time. When talking about music formats, bitrate is used in <strong>kilobits per second (kbps)</strong>. When comparing files with different bitrates (of the same song), the file with the higher bitrate has the higher quality.
For example, an MP3 320kbps (CBR) file transfers 320 kilobits per second.
<h2>Uncompressed Lossless</h2>
<strong>Uncompressed lossless formats</strong> store all of the original recorded data. Since silence is given the same number of bits per second as sound is, uncompressed lossless files are huge. The main uncompressed lossless format is <strong>pulse-code modulation (PCM)</strong>.
<h3>Examples</h3>
<ul>
<li>WAV (PCM) (used on Windows)</li>
<li>AIFF (PCM) (used on Mac OS)</li>
</ul>
<h2>Compressed Lossless</h2>
<strong>Compressed lossless formats</strong> store all of the original recorded data in less space than uncompressed lossless formats by compressing the data. By giving silence almost no bits per second and compressing sound, a compressed lossless file is usually half as big as the same song stored in an uncompressed lossless file.
Since both uncompressed lossless formats and compressed lossless formats retain all the data from the original recording, they can be transcoded between each other without a loss in quality.
<h3>Examples</h3>
<ul>
<li>Free Lossless Audio Codec (FLAC)</li>
<li>Apple Lossless Audio Codec (ALAC)</li>
<li>Monkey&#8217;s Audio (APE)</li>
</ul>
<h2>Lossy</h2>
<strong>Lossy formats</strong> are always compressed. Lossy formats have smaller file sizes than both uncompressed lossless formats and compressed lossless formats because they remove some of the original data. Usually the removed data is in the higher frequencies that humans can&#8217;t hear, however, there can be obvious audible differences between lossy formats and lossless formats.
Because lossy formats remove data during compression (and thus lose quality), lossy formats <strong>CANNOT</strong> be transcoded to lossless formats or other lossy formats without losing more quality.
<h3>Examples</h3>
<ul>
<li>MPEG Layer 3 Audio (MP3)</li>
<li>Advanced Audio Encoding (AAC)</li>
<li>Windows Media Audio (WMA)</li>
<li>Dolby Digital Audio Codec 3 (AC3)</li>
<li>DTS Coherent Acoustics Codec (DTS)</li>
</ul>
<h2>File Size</h2>
Here&#8217;s an example of how the file size of the same song varies depending on whether the song&#8217;s format is uncompressed lossless, compressed lossless, or lossy. Let&#8217;s take the classic pop song, Sk8er Boi by Avril Lavigne. For reference, the song is 3 minutes, 24 seconds long.<br /><br />
<strong>Uncompressed Lossless &mdash; WAV (PCM):</strong> 34.3 MB<br />
<strong>Compressed Lossless &mdash; FLAC:</strong> 25.75 MB (25% compressed)<br />
<strong>Lossy &mdash; MP3 320 (CBR):</strong> 7.78 MB (78% compressed)<br />
<h2>Transparency</h2>
<strong>Transparency</strong> is a term used to describe the audible quality of a lossy music file. A lossy file is considered transparent if the average human cannot tell the difference between the lossy file and a lossless file of the same song by just listening to both without knowing which file is which.
For most people, MP3 192kbps (CBR) is considered transparent.
<h2>Allowed Formats</h2>
While there are several types of lossless and lossy music formats, only a few are allowed to be uploaded to ' . $SITENAME . '.
<h3>Allowed Lossless Formats</h3>
<ul>
<li>FLAC</li>
</ul>
Because lossless formats can be transcoded between each other without a loss in quality, the only allowed lossless format on ' . $SITENAME . ' is FLAC. However, you can download the FLAC and convert to ALAC (for iTunes) or whatever lossless or lossy format you prefer.
<h3>Allowed Lossy Formats</h3>
<ul>
<li>MP3 (the minimum bitrate for MP3 is 192kbps (CBR))</li>
<li>AAC (can be trumped by any MP3 torrent unless it was bought from the iTunes store and includes iTunes Exclusive tracks)</li>
<li>AC3 (usually found in DVDs)</li>
<li>DTS (usually found in DVDs)</li>
</ul>
MP3 is the most popular lossy format on ' . $SITENAME . '. We allow AAC files bought from the iTunes store because there are often iTunes-specific bonus tracks, and since AAC is lossy it cannot be converted to other formats without a loss in quality. Similarly, AC3 and DTS are music formats often found on DVDs and since they are lossy, they cannot be converted to other formats without a loss in quality.');

newsbot();

	include('publicfooter.php'); 
?>