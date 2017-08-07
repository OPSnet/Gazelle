<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox($SITENAME .' rules','<p>The Golden Rules on ' . $SITENAME . ' are as follows:</p>
<ul>
<li><strong>All staff decisions must be respected.</strong> If you take issue with a decision, you must do so privately with the staff member who issued the decision or with an administrator of the site. Complaining about staff decisions in public or otherwise disrespecting staff members will not be taken lightly.</li>
<li><strong>Access to this website is a privilege, not a right, and it can be taken away from you for any reason.</strong></li>
<li><strong>One account per person per lifetime. Anyone creating additional accounts will be banned.</strong></li>
<li>Avatars must not exceed 256 kB or be vertically longer than 400 pixels. Avatars must be safe for work, be entirely unoffensive, and cannot contain any nudity or religious imagery. Use common sense.</li>
<li>Do not post our .torrent files on other sites. Every .torrent file has your personal passkey embedded in it. The tracker will automatically disable your account if you share your torrent files with others. You will not get your account back. This doesn&#8217;t prohibit you from sharing the content on other sites, but does prohibit you from sharing the .torrent file.</li>
<li>Any torrent you are seeding to this tracker must only have our tracker&#8217;s URL in it. Adding another tracker&#8217;s URL will cause incorrect data to be sent to our tracker, and will lead to your getting disabled for cheating. Similarly, your client must have DHT and PEX disabled for all ' . $SITENAME . ' torrents.</li>
<li>This is a torrent site which promotes sharing amongst the community. If you are not willing to give back to the community what you take from it, this site is not for you. In other words, we expect you to have an acceptable share ratio. If you download a torrent, please, seed the copy you have until there are sufficient people seeding the torrent data before you stop.</li>
<li><strong>Do not browse the site using proxies or TOR. The site will automatically alert us. This includes VPNs with dynamic IP addresses. Dedicated IP addresses for VPNs are OK.</strong></li>
<li>Asking for invites to any site is not allowed anywhere on ' . $SITENAME . ' or our IRC network. Invites may be offered in the Invites forum, and nowhere else.</li>
<li><strong>Trading and selling invites is strictly prohibited, as is offering them in public</strong> &#8211; this includes on any forum which is not a class-restricted section on an invitation-only torrent site.</li>
<li><strong>Trading, selling, sharing, or giving away your account is prohibited. </strong>PM a mod to disable your account if you no longer want it.</li>
<li>You&#8217;re completely responsible for the people you invite. If your invitees are caught cheating or trading/selling invites, not only will they be banned, so will you. Be careful who you invite. Invites are a precious commodity.</li>
<li>Be careful when sharing an IP or a computer with a friend if they have (or have had) an account. From then on your accounts will be inherently linked and if one of you violates the rules, both accounts will be disabled along with any other accounts linked by IP. This rule applies to logging into the site.</li>
<li><strong>Attempting to find or exploit a bug in the site code is the worst possible offense you can commit.</strong> We have automatic systems in place for monitoring these activities, and committing them will result in the banning of you, your inviter, and your inviter&#8217;s entire invite tree.</li>
<li>We&#8217;re a community. Working together is what makes this place what it is. There are well over a thousand new torrents uploaded every day and sadly the staff aren&#8217;t psychic. If you come across something that violates a rule, report it and help us better organize the site for you.</li>
<li>We respect the wishes of other sites here, as we wish for them to do the same. Please refrain from posting links to or full names for sites that do not want to be mentioned.</li>
</ul>
<h3>Dupes</h3>
A <strong>dupe</strong> is a torrent that is a duplicate of another torrent that already exists on the site. ' . $SITENAME . ' allows many different pressings of the same CD to coexist, as long the the CDs have different content.<br /><br />
For example, International Versions (especially Japanese releases) often have bonus tracks that are not present in the original release or the US release. Uploading a release with bonus tracks when the original release has already been uploaded is not considered a dupe because there is different content on both CDs.
<ul>
<li>Torrents that have the same bitrates, formats, and comparable or identical sampling rates for the same music release are duplicates. If a torrent is already present on the site in the format and bitrate you wanted to upload, you are not allowed to upload it.</li>
<li>Scene and non-scene torrents for the same release, in the same bitrate and format, are dupes.</li>
<li>Rip log information (table of contents, peak levels, and pre-gaps), tracklist, and running order determine distinct editions, not catalog information<strong>.</strong> Merely having different catalog numbers or CD packaging is not enough to justify a new, distinct edition, though differences in year and label (imprint) do determine distinct releases.</li>
<li>Torrents that have been inactive (not seeded) for two weeks may be trumped by the identical torrent (reseeded) or by a brand new rip or encode of the album. If you have the original torrent files for the inactive torrent, you should reseed those original files instead of uploading a new torrent.</li>
</ul>
<h3>Trumps</h3>
The process of replacing a torrent that does not follow the rules with a torrent that does follow the rules is called <strong>trumping</strong>.<br />The most common trumps are format trumps, tag trumps, and folder trumps.

<h3>Format Trumps</h3>
The following chart shows the hierarchy of format trumps.
<p style="text-align: center;"><a href="trumpchart.png" rel="lightbox[39]"><img title="Trump Chart" alt="" src="trumpchart.png" width="500" height="250" /></a></p>
At the top of each column in a green box are formats that can never be trumped. We recommend that you only upload in these formats in order to prevent your torrents from being trumped by other users.<br />
<strong>Lossy Format Trump Rules</strong>
<ul>
<li>If there is no existing torrent of the album in the allowed format you&#8217;ve chosen, you may upload it in any bitrate that averages at least 192 kbps.</li>
<li>You may always upload MP3 V0 , MP3 V2, or MP3 320kbps (CBR) as long as another rip with the same bitrate and format doesn&#8217;t already exist.</li>
<li>Higher bitrate CBR (Constant Bitrate) and ABR (Average Bitrate) torrents replace lower ones. Once a CBR rip has been uploaded, no CBR rips of that bitrate or lower can be uploaded. In the same manner, once an ABR rip has been uploaded, no ABR rips of that bitrate or lower can be uploaded.</li>
<li>AAC encodes can be trumped by any allowed MP3 format of the same edition and media. (This does not apply to AAC torrents with files bought from the iTunes Store that contain iTunes Exclusive tracks.)</li>
<li>Lossy format torrents with .log files, .cue files, .m3u files, and album artwork do not replace equivalent existing torrents.</li>
</ul>
<strong>Lossless Format Trump Rules</strong>
<ul>
<li>Rips must be taken from commercially pressed or official (artist- or label-approved) CD sources. They may not come from CD-R copies of the same pressed CDs (unless the release was only distributed on CD-R by the artist or label).</li>
<li>A FLAC torrent without a log (or with a log from a non-EAC or non-XLD ripping tool like dBpoweramp or Rubyripper) may be trumped by a FLAC torrent with a log from an approved ripping tool with any score.</li>
<li>A FLAC upload with an EAC or XLD log that scores 100% on the log checker replaces one with a lower score. However, no log scoring less than 100% can trump an already existing one that scores under 100% (for example, a rip with a 99% log cannot replace a rip with an 80% log).</li>
<li>A 100% log rip without a cue sheet can be replaced by a 100% log rip with a noncompliant cue sheet ONLY when the included cue sheet is materially different from &#8220;a cue generated from the ripping log.&#8221; Examples of a material difference include additional or correct indices, properly detected pre-gap lengths, and pre-emphasis flags.</li>
</ul>
<h3>Tag Trumps</h3>
<strong>Tag trumps</strong> happen when the original torrent either doesn&#8217;t have the required tag fields or the information in one of the tag fields is completely wrong or misspelled. In the case of misspelled words, the spelling must be entirely off in order for the tag trump to be considered (for example, missing prepositions like &#8220;the&#8221; or &#8220;a&#8221;, or a couple letters being in the wrong order like &#8220;lvoe&#8221; instead of &#8220;love&#8221; is not enough for a tag trump).
<ul>
<li>The required tag fields are: title, album, artist, track number.</li>
<li>Torrent album titles must accurately reflect the actual album titles. Use proper capitalization when naming your albums. Typing the album titles in all lowercase letters or all capital letters is unacceptable and makes the torrent trumpable.</li>
<li>Newly re-tagged torrents trumping badly tagged torrents must reflect a substantial improvement over the previous tags. Small changes that include replacing ASCII characters with proper foreign language characters with diacritical marks (<em>&#225;, &#233;, &#237;, &#243;, &#250;</em>, etc.), fixing slight misspellings, or missing an alternate spelling of an artist (excluding &#8220;The&#8221; before a band name) are not enough for replacing other torrents.</li>
</ul>
<h3>Folder Trumps</h3>
<strong>Folder trumps</strong> happen when the original torrent&#8217;s folder is not named like it should be. Folders should at the very least include the album name, but should hopefully also include the year released and music format. Nested folders are also not allowed.
<ul>
<li>Music releases must be in a directory that contains the music. This includes single track releases, which must be enclosed in a torrent folder even if there is only one file in the torrent. No music may be compressed in an archive (.rar, .zip, .tar, .iso).</li>
<li>Name your directories with meaningful titles, such as &#8220;Artist &#8211; Album (Year) &#8211; Format.&#8221; The minimum acceptable is &#8220;Album&#8221; although you should include more information.</li>
<li>Avoid creating unnecessary nested folders (such as an extra folder for the actual album) inside your properly named directory.</li>
<li>File names must accurately reflect the song titles. You may not have file names like 01track.mp3, 02track.mp3, etc. Torrents containing files that are named with incorrect song titles can be trumped by properly labeled torrents.</li>
<li>Multiple-disc torrents cannot have tracks with the same numbers in one directory. You may place all the tracks for Disc One in one directory and all the tracks for Disc Two in another directory.</li>
</ul>
');

newsbot();

	include('publicfooter.php'); 
?>