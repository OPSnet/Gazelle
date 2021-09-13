<?php

namespace Gazelle;

class Inbox extends Base {
    const ALT_SORT = 2;
    const CUR_SORT = 1;
    const DEFAULT_SORT = 0;
    const HTML = true;
    const RAW  = false;
    const SEARCH_FIELDS = [
        'user' => 'um.Username',
        'subject' => 'c.Subject',
        'message' => 'm.Body',
    ];
    const SECTIONS = [
        'inbox' => [
            'title' => 'Inbox',
            'dateField' => 'cu.ReceivedDate',
        ],
        'sentbox' => [
            'title' => 'Sentbox',
            'dateField' => 'cu.SentDate',
        ],
    ];
    // These two need to match values of user option('ListUnreadPMsFirst')
    const UNREAD_FIRST = true;
    const NEWEST_FIRST = false;

    /** @var int */
    private $userId;

    /** @var bool */
    private $unreadFirstDefault;

    /** @var string */
    private $section;

    /** @var bool */
    private $unreadFirst;

    /** @var string */
    private $searchField;

    /** @var string */
    private $searchTerm;

    /** @var string */
    private $sql = '';

    /**
     * Inbox constructor
     *
     * @param int       $userId
     * @param bool      $unreadFirstDefault the user's inbox sort setting
     * @param array     $params             associative config array, usually $_GET
     */
    public function __construct(int $userId, $unreadFirstDefault = self::NEWEST_FIRST, array $params = []) {
        parent::__construct();
        $this->userId = $userId;
        $this->unreadFirstDefault = (bool)$unreadFirstDefault;
        if (empty($params)) {
            $params = $_GET;
        }

        $this->section = $params['section'] ?? $params['action'] ?? key(self::SECTIONS);
        if (!isset(self::SECTIONS[$this->section])) {
            throw new \Exception('Inbox:new:badsection');
        }

        $this->unreadFirst = (isset($params['sort']) && $params['sort'] == 'unread')
            ? self::UNREAD_FIRST
            : self::NEWEST_FIRST;

        $this->searchField = $params['searchtype'] ?? null;
        $this->searchTerm  = $params['search'] ?? null;

        if (isset($this->searchField)
            && !isset(self::SEARCH_FIELDS[$this->searchField])
        ) {
            throw new \Exception('Inbox:new:badsearchfield');
        }

        $this->sql = "
            SELECT %s
            FROM pm_conversations AS c
            INNER JOIN pm_conversations_users AS cu ON (cu.ConvID = c.ID AND cu.UserID = ?)
            LEFT JOIN pm_conversations_users AS cu2 ON (cu2.ConvID = c.ID AND cu2.UserID != ? AND cu2.ForwardedTo = 0)
            LEFT JOIN users_main AS um ON (um.ID = cu2.UserID)
            %s
            WHERE cu.In" . ucfirst($this->section) . " = '1' %s
            ORDER BY cu.Sticky,
                " . (($this->unreadFirst === self::UNREAD_FIRST)
                    ? "cu.Unread = '1' DESC,"
                    : ''
                ) . self::SECTIONS[$this->section]['dateField'] . " DESC
            %s";
    }

    /**
     * Generate the link to a user's inbox.
     *
     * @param string $section whether the inbox or sentbox should be used
     * @param bool   $html    whether the output should have HTML entities
     * @param bool   $sort    whether to sort according to current setting
     * @return string the URL to a user's inbox
     */
    public function getLink($section = null, $html = self::HTML, $sort = self::DEFAULT_SORT) {
        $unreadFirst = self::NEWEST_FIRST;
        if (($sort === self::DEFAULT_SORT && $this->unreadFirstDefault === self::UNREAD_FIRST)
            || ($sort === self::CUR_SORT && $this->unreadFirst === self::UNREAD_FIRST)
            || ($sort === self::ALT_SORT && $this->unreadFirst === self::NEWEST_FIRST)
        ) {
            $unreadFirst = self::UNREAD_FIRST;
        }

        $search = [];
        if ($this->searchField && $this->searchTerm) {
            $search['searchtype'] = $this->searchField;
            $search['search']     = $this->searchTerm;
        }

        return self::getLinkQuick($section, $unreadFirst, $html, $search);
    }

    /**
     * Generate the link to a user's inbox.
     *
     * @param string $section     whether the inbox or sentbox should be used
     * @param bool   $unreadFirst whether to sort by unread first
     * @param bool   $html        whether the output should have HTML entities
     * @return string the URL to a user's inbox
     */
    public static function getLinkQuick($section = null, $unreadFirst = self::NEWEST_FIRST, $html = self::HTML, $search = []) {
        if (empty($section) || !isset(self::SECTIONS[$section])) {
            $section = key(self::SECTIONS);
        }

        $query = [];
        if ($section !== key(self::SECTIONS)) {
            $query['section'] = $section;
        }
        if ($unreadFirst === self::UNREAD_FIRST) {
            $query['sort'] = 'unread';
        }

        $query = http_build_query(array_merge($query, $search));

        return (empty($query))
            ? 'inbox.php'
            : 'inbox.php?'
                . (($html === self::HTML) ? display_str($query) : $query);
    }

    /**
     * Return the current sort value we're using
     *
     * @return string
     */
    public function getSort() {
        return $this->unreadFirst;
    }

    /**
     * Return the current section we're in
     *
     * @return string
     */
    public function section() {
        return $this->section;
    }

    /**
     * Return a section title
     *
     * @param string $section The section's title you want, or 'opposite' of current
     * @return string
     */
    public function title($section = null) {
        if (!isset($section)) {
            $section = $this->section;
        } else if (!isset(self::SECTIONS[$section])) {
            throw new \Exception('Inbox:title:badsection');
        }
        return self::SECTIONS[$section]['title'];
    }

    /**
     * Runs the query and returns the total result count
     * and the results on this page.
     *
     * @return array total messages, pagefull of messages
     */
    public function result(int $limit, int $offset): array {
        $searching = (!empty($this->searchField) && !empty($this->searchTerm));
        $table = ($searching && $this->searchField === 'message')
            ? 'INNER JOIN pm_messages AS m ON (c.ID = m.ConvID) '
            : '';
        $search = '';
        $searchWords = (!empty($this->searchTerm))
            ? array_map(fn($val) => "%$val%", explode(' ', $this->searchTerm))
            : [];
        if ($searching) {
            for ($i = 0, $wc = count($searchWords); $i < $wc; $i++) {
                $search .= 'AND ' . self::SEARCH_FIELDS[$this->searchField] . ' LIKE ? ';
            }
        }

        // No limit - get total matching record count
        $totalCount = $this->db->scalar(
            sprintf($this->sql,
                'count(*)',
                $table,
                $search,
                ''
            ),
            $this->userId,
            $this->userId,
            ...$searchWords
        );

        // Now set up the main query for this page's results
        $cols = "
            c.ID,
            c.Subject,
            cu.Unread,
            cu.Sticky,
            cu.ForwardedTo,
            cu2.UserID,
            " . self::SECTIONS[$this->section]['dateField'];
        $search .= 'GROUP BY c.ID';

        $this->db->prepared_query(
            sprintf($this->sql,
                $cols,
                $table,
                $search,
                "LIMIT ? OFFSET ?"
            ),
            $this->userId,
            $this->userId,
            ...(array_merge($searchWords, [$limit, $offset]))
        );
        return [$totalCount, $this->db->to_array(false, MYSQLI_NUM)];
    }
}
