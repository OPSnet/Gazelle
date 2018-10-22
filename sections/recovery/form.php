<p style="text-align:center">Membership recovery</p>

<p>Some people are in the recovered backup, some are not. If you had registered before 2017-06-18,
you are in. You will need to supply only your username and email.</p>

<p>If you signed up afterwards, or you have lost your invite email, things are a little more
complicated. We will consider any proof you may be able to supply, such as the complete signup
email, or screenshots of your profile page. Staff has the final say in whether the proof is
sufficient.</p>

<form enctype="multipart/form-data" method="post" action="/recovery.php?action=save">

<h5>Your username</h5>

<p>Should be obvious – your username as it was previously. When you receive your
invite link, you have the chance to change it to something else if that is your
wish.</p>

<input type="text" name="username" />

<h5>Your email</h5>

<p>The email address you used to register with. Any address used up until the 2017-06
point we should be able to find it, if it is the backup.</p>

<input type="text" name="email" />

<h5>Your Password</h5>

<p>This will be hashed upon reception and compared with existing hashes of your password.
When you receive an invite to Orpheus, you should not reuse this password.</p>
<input type="password" name="password" />

<h5>Your announce key</h5>

<p>You can look this up by viewing the properties of an APL torrent. The key is a long string of
hexadecimal (digits 0 to and 9 and letters A to F) characters. This is optional, but provides
additional proof that you are who you say you are.</p>

<input type="text" name="announce" />

<h5>Your original APL/XNX invitation</h5>

<p>Please copy-paste the original raw message.

(In Protonmail, this is "View XXX". In Gmail, this is "View Original". In Exchange and Thunderbird,
this is "View Source".  Other mail clients will have similar options)</p>

<p>If you signed up after 2017-06-18, your account is not in the backup we recovered.</p>

<textarea rows="8" columns="80" name="invite"></textarea>

<h5>Screenshots</h5>

<p>Bundle up any screenshots or HTML "Save as" copies of your profile into a tarball, or any
standard archive format, compressed as you see fit. We should be able to figure out most formats.
Size is limited to 10MiB. Only PNG and JPEG image formats are accepted. All other filetypes will be
discarded.</p>

<input type="file" name="screen" />

<h5>Additional information</h5>

<p>You may optionally supply any additional information you deem necessary, such as your final
user class on Apollo as you remember it.

<textarea rows="4" columns="80" name="info"></textarea>

<p>Upon clicking "Send", your uploaded information will be stored, validated and then reviewed by
Staff. If everything checks out, an invite email will be sent to your address.  Information you
supply will be stored in a holding pen and will be deleted after thirty days (so if you haven't
heard back by then, you never will).</p>

<p>On the next page, a unique token will be generated for you. You should make a note of it, as it
can be used as an identifier on the IRC #registration channel if you need to get in touch with us.
Do not paste it in the channel, a Staff member will ask you to send it via a private message.</p>

<input type="submit" value="Send" />

<p>Rate limiting applies. You cannot submit this form more than once every five minutes from the
same IP address.</p>

</form>
