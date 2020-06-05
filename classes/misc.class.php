<?php

use \Gazelle\Manager\Notification;

class Misc {
    /**
     * Send an email.
     *
     * @param string $To the email address to send it to.
     * @param string $Subject
     * @param string $Body
     * @param string $From The user part of the user@NONSSL_SITE_URL email address.
     * @param string $ContentType text/plain or text/html
     */

    public static function send_email($To, $Subject, $Body, $From, $ContentType = 'text/plain') {
        $Headers = 'MIME-Version: 1.0'."\r\n";
        $Headers .= 'Content-type: text/plain; charset=iso-8859-1'."\r\n";
        $Headers .= 'From: '.SITE_NAME.' <'.$From.'@'.MAIL_HOST.'>'."\r\n";
        $Headers .= 'Reply-To: '.$From.'@'.MAIL_HOST."\r\n";
        $Headers .= 'Message-Id: <'.Users::make_secret().'@'.MAIL_HOST.">\r\n";
        $Headers .= 'X-Priority: 3'."\r\n";
        mail($To, $Subject, $Body, $Headers, "-f $From@".MAIL_HOST);
    }


    /**
     * Sanitize a string to be allowed as a filename.
     *
     * @param string $EscapeStr the string to escape
     * @return the string with all banned characters removed.
     */
    public static function file_string($EscapeStr) {
        return str_replace(['"', '*', '/', ':', '<', '>', '?', '\\', '|'], '', $EscapeStr);
    }


    /**
     * Sends a PM from $FromId to $ToId.
     *
     * @param string $ToID ID of user to send PM to. If $ToID is an array and $ConvID is empty, a message will be sent to multiple users.
     * @param string $FromID ID of user to send PM from, 0 to send from system
     * @param string $Subject
     * @param string $Body
     * @param int $ConvID The conversation the message goes in. Leave blank to start a new conversation.
     * @return
     */
    public static function send_pm($ToID, $FromID, $Subject, $Body, $ConvID = null) {
        if ($ToID == 0 || $ToID == $FromID) {
            // Don't allow users to send messages to the system or themselves
            return;
        }

        $QueryID = G::$DB->get_query_id();

        if (!$ConvID) {
            // Create a new conversation.
            G::$DB->prepared_query("
                INSERT INTO pm_conversations (Subject) VALUES (?)
                ", $Subject
            );
            $ConvID = G::$DB->inserted_id();
            G::$DB->prepared_query("
                INSERT INTO pm_conversations_users
                       (UserID, ConvID, InInbox, InSentbox, UnRead)
                VALUES (?,      ?,      '1',     '0',       '1')
                ", $ToID, $ConvID
            );
            if ($FromID != 0) {
                G::$DB->prepared_query("
                    INSERT INTO pm_conversations_users
                           (UserID, ConvID, InInbox, InSentbox, UnRead)
                    VALUES (?,      ?,      '0',     '1',       '0')
                    ", $FromID, $ConvID
                );
            }
        } else {
            // Update the pre-existing conversation.
            // TODO: The only time $ConvID is set is when replying to an existing inbox thread
            //       That functionality could be moved to a reply() method
            G::$DB->prepared_query("
                UPDATE pm_conversations_users SET
                    InInbox = '1',
                    UnRead = '1',
                    ReceivedDate = now()
                WHERE UserID = ?
                    AND ConvID = ?
                ", $ToID, $ConvID
            );
            G::$DB->prepared_query("
                UPDATE pm_conversations_users SET
                    InSentbox = '1',
                    SentDate = now()
                WHERE UserID = ?
                    AND ConvID = ?
                ", $FromID, $ConvID
            );
        }

        // Now that we have a $ConvID for sure, send the message.
        G::$DB->prepared_query("
            INSERT INTO pm_messages
                   (SenderID, ConvID, Body)
            VALUES (?,        ?,      ?)
            ", $FromID, $ConvID, $Body
        );

        // Update the cached new message count.
        G::$Cache->cache_value("inbox_new_$ToID",
            G::$DB->scalar("
                SELECT count(*)
                FROM pm_conversations_users
                WHERE UnRead = '1'
                    AND InInbox = '1'
                    AND UserID = ?
                ", $ToID
            )
        );

        $SenderName = G::$DB->scalar("
            SELECT Username
            FROM users_main
            WHERE ID = ?
            ", $FromID
        );
        $notification = new Notification;
        foreach ($ToID as $ID) {
            $notification->push($ID, "Message from $SenderName, Subject: $Subject", $Body, site_url() . 'inbox.php', Notification::INBOX);
        }

        G::$DB->set_query_id($QueryID);
        return $ConvID;
    }

    /**
     * Create thread function.
     *
     * @param int $ForumID
     * @param int $AuthorID ID of the user creating the post.
     * @param string $Title
     * @param string $PostBody
     * @return -1 on error, -2 on user not existing, thread id on success.
     */
    public static function create_thread($ForumID, $AuthorID, $Title, $PostBody) {
        global $Time;
        if (!$ForumID || !$AuthorID || !is_number($AuthorID) || !$Title || !$PostBody) {
            return -1;
        }

        $QueryID = G::$DB->get_query_id();

        $User = Users::user_info($AuthorID);
        $AuthorName = $User['Username'];

        $ThreadInfo = [];
        $ThreadInfo['IsLocked'] = 0;
        $ThreadInfo['IsSticky'] = 0;

        $forum = new \Gazelle\Forum($ForumID);
        list($TopicID, $PostID) = $forum->addThread($AuthorId, $Title, $PostBody);
        $Posts = 1;

        // Bump this topic to head of the cache
        list($Forum,,, $Stickies) = G::$Cache->get_value("forums_$ForumID");
        if (!empty($Forum)) {
            if (count($Forum) == TOPICS_PER_PAGE && $Stickies < TOPICS_PER_PAGE) {
                array_pop($Forum);
            }
            G::$DB->prepared_query('
                SELECT IsLocked, IsSticky, NumPosts
                FROM forums_topics
                WHERE ID = ?', $TopicID);
            list($IsLocked, $IsSticky, $NumPosts) = G::$DB->next_record();
            $Part1 = array_slice($Forum, 0, $Stickies, true); //Stickys
            $Part2 = [
                $TopicID => [
                    'ID' => $TopicID,
                    'Title' => $Title,
                    'AuthorID' => $AuthorID,
                    'IsLocked' => $IsLocked,
                    'IsSticky' => $IsSticky,
                    'NumPosts' => $NumPosts,
                    'LastPostID' => $PostID,
                    'LastPostTime' => sqltime(),
                    'LastPostAuthorID' => $AuthorID,
                    ]
                ]; //Bumped thread
            $Part3 = array_slice($Forum, $Stickies, TOPICS_PER_PAGE, true); //Rest of page
            if ($Stickies > 0) {
                $Part1 = array_slice($Forum, 0, $Stickies, true); //Stickies
                $Part3 = array_slice($Forum, $Stickies, TOPICS_PER_PAGE - $Stickies - 1, true); //Rest of page
            } else {
                $Part1 = [];
                $Part3 = $Forum;
            }
            if (is_null($Part1)) {
                $Part1 = [];
            }
            if (is_null($Part3)) {
                $Part3 = [];
            }
            $Forum = $Part1 + $Part2 + $Part3;
            G::$Cache->cache_value("forums_$ForumID", [$Forum, '', 0, $Stickies], 0);
        }

        //Update the forum root
        G::$Cache->begin_transaction('forums_list');
        $UpdateArray = [
            'NumPosts' => '+1',
            'NumTopics' => '+1',
            'LastPostID' => $PostID,
            'LastPostAuthorID' => $AuthorID,
            'LastPostTopicID' => $TopicID,
            'LastPostTime' => sqltime(),
            'Title' => $Title,
            'IsLocked' => $ThreadInfo['IsLocked'],
            'IsSticky' => $ThreadInfo['IsSticky']
            ];

        $UpdateArray['NumTopics'] = '+1';

        G::$Cache->update_row($ForumID, $UpdateArray);
        G::$Cache->commit_transaction(0);

        $CatalogueID = floor((POSTS_PER_PAGE * ceil($Posts / POSTS_PER_PAGE) - POSTS_PER_PAGE) / THREAD_CATALOGUE);
        G::$Cache->begin_transaction('thread_'.$TopicID.'_catalogue_'.$CatalogueID);
        $Post = [
            'ID' => $PostID,
            'AuthorID' => G::$LoggedUser['ID'],
            'AddedTime' => sqltime(),
            'Body' => $PostBody,
            'EditedUserID' => 0,
            'EditedTime' => '0000-00-00 00:00:00',
            'Username' => ''
            ];
        G::$Cache->insert('', $Post);
        G::$Cache->commit_transaction(0);

        G::$Cache->begin_transaction('thread_'.$TopicID.'_info');
        G::$Cache->update_row(false, ['Posts' => '+1', 'LastPostAuthorID' => $AuthorID]);
        G::$Cache->commit_transaction(0);

        G::$DB->set_query_id($QueryID);

        return $TopicID;
    }

    /**
     * Variant of in_array with trailing wildcard support
     *
     * @param string $Needle, array $Haystack
     * @return boolean true if (substring of) $Needle exists in $Haystack
     */
    public static function in_array_partial($Needle, $Haystack) {
        static $Searches = [];
        if (array_key_exists($Needle, $Searches)) {
            return $Searches[$Needle];
        }
        foreach ($Haystack as $String) {
            if (substr($String, -1) == '*') {
                if (!strncmp($Needle, $String, strlen($String) - 1)) {
                    $Searches[$Needle] = true;
                    return true;
                }
            } elseif (!strcmp($Needle, $String)) {
                $Searches[$Needle] = true;
                return true;
            }
        }
        $Searches[$Needle] = false;
        return false;
    }

    /**
     * Used to check if keys in $_POST and $_GET are all set, and throws an error if not.
     * This reduces 'if' statement redundancy for a lot of variables
     *
     * @param array $Request Either $_POST or $_GET, or whatever other array you want to check.
     * @param array $Keys The keys to ensure are set.
     * @param boolean $AllowEmpty If set to true, a key that is in the request but blank will not throw an error.
     * @param int $Error The error code to throw if one of the keys isn't in the array.
     */
    public static function assert_isset_request($Request, $Keys = null, $AllowEmpty = false, $Error = 0) {
        if (isset($Keys)) {
            foreach ($Keys as $K) {
                if (!isset($Request[$K]) || ($AllowEmpty == false && $Request[$K] == '')) {
                    error($Error);
                    break;
                }
            }
        } else {
            foreach ($Request as $R) {
                if (!isset($R) || ($AllowEmpty == false && $R == '')) {
                    error($Error);
                    break;
                }
            }
        }
    }


    /*
     * Write a message to the system log.
     *
     * @param string $Message the message to write.
     */
    public static function write_log($Message) {
        $QueryID = G::$DB->get_query_id();
        G::$DB->prepared_query("
            INSERT INTO log (Message) VALUES (?)
            ", trim($Message)
        );
        G::$DB->set_query_id($QueryID);
    }

    /**
     * HTML escape an entire array for output.
     * @param array $Array, what we want to escape
     * @param boolean|array $Escape
     *    if true, all keys escaped
     *    if false, no escaping.
     *    If array, it's a list of array keys not to escape.
     * @param boolean $Reverse reverses $Escape such that then it's an array of keys to escape
     * @return array mutated version of $Array with values escaped.
     */
    public static function display_array($Array, $Escape = [], $Reverse = false) {
        foreach ($Array as $Key => $Val) {
            if ((!is_array($Escape) && $Escape == true) || (!$Reverse && !in_array($Key, $Escape)) || ($Reverse && in_array($Key, $Escape))) {
                $Array[$Key] = display_str($Val);
            }
        }
        return $Array;
    }

    /**
     * Searches for a key/value pair in an array.
     *
     * @return array of results
     */
    public static function search_array($Array, $Key, $Value) {
        $Results = [];
        if (is_array($Array))
        {
            if (isset($Array[$Key]) && $Array[$Key] == $Value) {
                $Results[] = $Array;
            }

            foreach ($Array as $subarray) {
                $Results = array_merge($Results, self::search_array($subarray, $Key, $Value));
            }
        }
        return $Results;
    }

    /**
     * Search for $Needle in the string $Haystack which is a list of values separated by $Separator.
     * @param string $Haystack
     * @param string $Needle
     * @param string $Separator
     * @param boolean $Strict
     * @return boolean
     */
    public static function search_joined_string($Haystack, $Needle, $Separator = '|', $Strict = true) {
        return (array_search($Needle, explode($Separator, $Haystack), $Strict) !== false);
    }

    /**
     * Check for a ":" in the beginning of a torrent meta data string
     * to see if it's stored in the old base64-encoded format
     *
     * @param string $Torrent the torrent data
     * @return true if the torrent is stored in binary format
     */
    public static function is_new_torrent(&$Data) {
        return strpos(substr($Data, 0, 10), ':') !== false;
    }
}
