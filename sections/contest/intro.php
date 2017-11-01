<?php

View::show_header('Apollo Euterpe FLAC Challenge');

?>

<div class="pad">
	<img border="0" src="/static/common/contest-euterpe.png" alt="Apollo Euterpe FLAC Challenge" width="640" height="125" style="display: block; margin-left: auto; margin-right: auto;"/>
</div>

<div class="linkbox">
	<a href="contest.php?action=leaderboard" class="brackets">Leaderboard</a>
	<?=(check_perms('users_mod')) ? '<a href="contest.php?action=admin" class="brackets">Admin</a>' : ''?>
</div>

<div class="thin">
	<h1 id="general">The God of Music's Upload Contest!</h1>
<?php
if (($Contest = Contest::get_current_contest()) === false) {
?>
	<div class="box pad" style="padding: 10px 10px 10px 20px;">
		<p>There is no contest at the moment.</p>
	</div>
<?php
} else {
?>
	<div class="box pad" style="padding: 10px 10px 10px 20px;">
		<p>In celebration of both our amazing community and our continued growth with the
		implementation of the new logchecker, JSON, and SOON™ the Bonus Point system, the
		Apollo Staff is organizing an upload contest!</p>
	</div>

	<div class="box pad" style="padding: 10px 10px 10px 20px;">
		<h2>What's the challenge?</h2>

		<p>Inspired by Euterpe, the Greek Muse of Music, perfect FLAC is the name of the game! Starting now
		and ending on <?=$Contest['DateEnd']?> UTC, your goal is to upload as many perfect FLACs as possible with
		amazing prizes to our top ten uploaders!</p>

		<h2>What counts as a perfect FLAC?</h2>

		<p>A perfect FLAC can be either of the following:</p>
		<ul>
			<li>A FLAC CD rip with log (scoring 100% with the new logchecker), and a .cue file.</li>
			<li>A vinyl rip with lineage, both 16- and 24-bit FLAC uploads count as perfect.</li>
			<li>A WEB download, both 16- and 24-bit FLAC uploads count as perfect.</li>
		</ul>

		<h2>What are the prizes?</h2>

		<div style="padding: 10px 50px;">
			<h3><strong class="important_text">First Place</strong></h3>
			<p><b><i>VIP status</i></b> and 30 Freeleech Tokens</p>

			<h3><strong class="important_text">Second Place</strong></h3>
			<p>20 Freeleech Tokens and a custom title</p>

			<h3><strong class="important_text">Third Place</strong></h3>
			<p>15 Freeleech Tokens and a custom title</p>

			<h3><strong class="important_text">Fourth Place</strong></h3>
			<p>10 Freeleech Tokens and a custom title</p>

			<h3><strong class="important_text">Fifth Place</strong></h3>
			<p>5 Freeleech Tokens</p>

			<h3><strong class="important_text">Sixth to Tenth Place</strong></h3>
			<p>3 Freeleech Tokens</p>
		</div>

		<h3>In addition</h3>

		<ul>
			<li>Our top five uploaders will all get <b>a freeleech pick</b> while sixth to tenth will get a neutral leech pick!</li>
			<li>Instead of keeping the FL tokens or custom title from the prize yourself, you can also choose to give them away to a user (or users) of your choice!</li>
		</ul>

		<h3>Have questions?</h3>

		<p>If you need any assistance with uploading or ripping music, feel free to <a href="/wiki.php">check out our
		wiki</a> that has many helpful articles. In addition, you can also stop by #help in IRC and <a
		href="/forums.php?action=viewforum&forumid=41">the "Help!" subforum</a> for further assistance.</p>

		<h1>Leaderboard</h1>

		<p>Keep an eye on <a href="/contest.php?action=leaderboard">the contest Leaderboard</a> to see where you stand!</p>

	</div>
<?php
}
View::show_footer();
