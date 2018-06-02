<?

include('publicheader.php');
include('sidebar.php');
newstop();

newsbox('Starting the interview', '
<h3>Getting an IRC Client</h3>
<strong>Internet Relay Chat (IRC)</strong> is a beefier version of chat clients you may be used to, like AOL Instant Messenger (AIM) or Yahoo! Messenger.
<h3>Suggested Clients</h3>
<ul>
<li>mIRC (Windows)</li>
<li>Hexchat (Windows, Linux)</li>
<li>Colloquy (Mac OS)</li>
</ul>
<strong>Note:</strong> Web-based IRC clients, such as Mibbit, are very unstable, and therefor not recommended to use.<br /><br />
Once you have downloaded, installed, and set up your IRC client, read on!

<h3>Connecting to ' . $SITENAME . '&#8217;s IRC</h3>
<strong>YOU MAY ONLY INTERVIEW ON YOUR HOME CONNECTION. Proxies, bouncers, shells, office lines, and other non-home connections (including using your friend&#8217;s home connection) are not allowed. You may connect from your university dormitory.<br />
</strong><br /><br />
<strong>IRC Server:</strong> irc.apollo.rip<br />
<strong>Port:</strong> 6667 (+7000 for SSL)<br /><br />

After you have connected to ' . $SITENAME . '&#8217;s IRC network, type: &#8220;/join ' . $interviewchan . '&#8221; (without the quotation marks) and follow the directions in the topic.

<h3>Speedtest</h3>

In order to join the queue, you will have to take a speedtest on your home connection (remember, you may ONLY interview on your home connection!).<br />
If you live at college, please explain this to your interviewer.<br />
<ol>
<li>Go to <a title="Speedtest" href="http://www.speedtest.net/" target="_blank">http://www.speedtest.net</a>.</li>
<li>Click on &#8220;BEGIN TEST&#8221; (blue button).</li>
<li>Wait for your test to finish.</li>
<li>Click on &#8220;SHARE THIS RESULT&#8221; (green button).</li>
<li>Click on the &#8220;IMAGE&#8221; tab</li>
<li>Click &#8220;COPY&#8221; (blue button).</li>
</ol>

<h3>Queuing</h3>
Back in ' . $interviewchan . ', simply wait for an available interviewer.<br />

<h3>How to use the bot</h3>
<strong>To enter the queue,</strong> enter the following command in your IRC client: &#8220;/msg Hermes !queue speedtestlink&#8221; (without the quotations, replacing &#8220;speedtestlink&#8221; with your <strong>correct</strong> speedtest link.) <br />
<strong>Example:</strong> &#8220;/msg Hermes !queue http://www.speedtest.net/result/1234566.png&#8221;<br />
After queueing correctly, you will get a small notice from the Hermes bot. <br />
<strong>How to exit the queue:</strong> To exit the queue, simply type the following command in your IRC client:<br />
&#8220;/msg Hermes !cancel&#8221; <br />
<strong>How to view information about the queue:</strong> To view information about your current position in the queue, and how long you have waited, simply type the following command in your IRC client: &#8220;/msg Hermes !info&#8221;<br /><br />
<strong>Important:</strong><br />
Make sure you are queueing with the correct link and not the &#8220;Web&#8221; link.<br />
<em>Correct:</em> http://www.speedtest.net/result/1234566.png</em><br />
<em>Wrong:</em> http://www.speedtest.net/my-result/1234566</em><br /><br />
Then wait for an interviewer to interview you.<br />
<h3>But I&#8217;ve waited forever!</h3>
All of our interviewers are ' . $SITENAME . ' users who who voluntarily interview, so you may have to wait a while before you are interviewed. Although it seems like a lot of interviewers are in the channel, many of them may be &#8220;idling&#8221; (they are in the channel but are unable to interview).<br /><br />
<h3>More Questions?</h3>
<strong>Note: ' . $interviewchan . ' is the ONLY channel that you will need to join. Other channels are for ' . $SITENAME . ' users only and are closed for non-members.</strong>');

newsbot();

include('publicfooter.php');