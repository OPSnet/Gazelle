<?php

namespace Gazelle\Util;

class Mail {
    protected $from = 'noreply';

    public function setFrom(string $from) {
        $this->from = $from;
        return $this;
    }

    /**
     * Send an email.
     *
     * @param string Recipient address
     * @param string Subject
     * @param string Body
     */
    public function send(string $to, string $subject, string $body) {
        $from = $this->from . '@' . SITE_HOST;
        $msgId = randomString(40);
        $headers = implode("\r\n", [
            "MIME-Version: 1.0",
            "Content-type: text/plain; charset=utf-8",
            "From: " . SITE_NAME . " <$from>",
            "Reply-To: $from",
            "Message-Id: <{$msgId}@" . MAIL_HOST . '>',
            "X-Priority: 3",
            '' // for final "\r\n"
        ]);
        if (DEBUG_EMAIL) {
            $out = fopen(TMPDIR . "/$msgId.mail", "w");
            fwrite($out, $headers . "\n" . $body . "\n");
            fclose($out);
        } else {
            mail($to, $subject, $body, implode("\r\n", $header), "-f $from");
        }
    }
}
