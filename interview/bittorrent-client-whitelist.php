<? 
	include('publicheader.php');
	include('sidebar.php');

newstop();

newsbox('Bittorrent client whitelist', '
<p>The following BitTorrent clients are on the ' . $SITENAME . ' whitelist:</p>
<ul>
<li>btgdaemon 0.9.x</li>
<li>btgdaemon 1.0.x</li>
<li>btpd 0.13</li>
<li>btpd 0.15</li>
<li>btpd 0.16</li>
<li>Deluge 1.2.1 / libtorrent (Rasterbar) 0.14.9</li>
<li>Deluge 1.2.2</li>
<li>Deluge 1.2.3</li>
<li>Deluge 1.3.x</li>
<li>Enhanced CTorrent (dnh3.2)</li>
<li>Enhanced CTorrent (dnh3.3)</li>
<li>KTorrent 2.1.x</li>
<li>KTorrent 2.2.x</li>
<li>KTorrent 3.0.x</li>
<li>KTorrent 3.1.x</li>
<li>KTorrent 3.2.x</li>
<li>KTorrent 3.3.x</li>
<li>KTorrent 4.0.x</li>
<li>KTorrent 4.1.x</li>
<li>KTorrent 4.2.x</li>
<li>KTorrent 4.3.x</li>
<li>leechcraft</li>
<li>leechcraft 0.5.xx</li>
<li>qBittorrent 2.3.x</li>
<li>qBittorrent 2.4.x</li>
<li>qBittorrent 2.5.x</li>
<li>qBittorrent 2.6.x</li>
<li>qBittorrent 2.7.x</li>
<li>qBittorrent 2.8.x</li>
<li>qBittorrent 2.9.x</li>
<li>qBittorrent 3.0.x</li>
<li>rtorrent (libTorrent 0.10.4)</li>
<li>rtorrent (libTorrent 0.11.x)</li>
<li>rtorrent (libTorrent 0.12.x)</li>
<li>rtorrent (libTorrent 0.13.x)</li>
<li>Transmission 1.54 (For OS X 10.4)</li>
<li>Transmission 1.6x</li>
<li>Transmission 1.7x</li>
<li>Transmission 1.8x</li>
<li>Transmission 1.92</li>
<li>Transmission 1.93</li>
<li>Transmission 2.0x</li>
<li>Transmission 2.1x</li>
<li>Transmission 2.2x</li>
<li>Transmission 2.3x</li>
<li>Transmission 2.4x</li>
<li>Transmission 2.51</li>
<li>Transmission 2.5x</li>
<li>Transmission 2.6x</li>
<li>Transmission 2.7x</li>
<li>uTorrent 1.6.1</li>
<li>uTorrent 1.7.6</li>
<li>uTorrent 1.7.7</li>
<li>uTorrent 1.8.x</li>
<li>uTorrent 2.0.x</li>
<li>uTorrent 2.1.x</li>
<li>uTorrent 2.2.x</li>
<li>uTorrent 3.1.3</li>
<li>uTorrent 3.2.0</li>
<li>uTorrent 3.2.1</li>
<li>uTorrent 3.2.2</li>
<li>uTorrent 3.2.3</li>
<li>uTorrent 3.3.0</li>
<li>uTorrent Mac 0.9.x</li>
<li>uTorrent Mac 1.0.x</li>
<li>uTorrent Mac 1.1.x</li>
<li>uTorrent Mac 1.5.x</li>
<li>uTorrent Mac 1.7.13</li>
<li>uTorrent Mac 1.8.0</li>
<li>uTorrent Mac 1.8.1</li>
<li>uTorrent Mac 1.8.2</li>
<li>uTorrent Mac 1.8.3</li>
<li>uTorrent Mac 1.8.4</li>
</ul>
<strong>If there is an &#8220;x&#8221; behind a decimal, that means that any number can be substituted for the x. For example: uTorrent 2.0.x means that uTorrent 2.0.0, 2.0.1, 2.0.2, 2.0.3, 2.0.4, etc. are all on the whitelist.</strong><br /><br />
<em>Last updated: 22 May 2013</em>');

newsbot();

	include('publicfooter.php'); 
?>