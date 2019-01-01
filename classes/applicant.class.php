<?php

class Applicant {
	private $id;
	private $role_id;
	private $user_id;
	private $thread;
	private $resolved;
	private $created;
	private $modified;

	const CACHE_KEY           = 'applicant_%d';
	const CACHE_KEY_OPEN      = 'applicant_list_open_%d';
	const CACHE_KEY_RESOLVED  = 'applicant_list_resolved_%d';
	const CACHE_KEY_NEW_COUNT = 'applicant_new_count';
	const CACHE_KEY_NEW_REPLY = 'applicant_new_reply';
	const ENTRIES_PER_PAGE    = 1000; // TODO: change to 50 and implement pagination

	/**
	 * persistent Applicant constructor
	 * @param int $user_id id of applicant in users_main table
	 * @param string $role The name of the role the applicant is applying for
	 * @param string $body Their application request
	 */
	public function __construct ($user_id = null, $role = null, $body = null) {
		if (!isset($user_id)) {
			return;
		}
		$this->user_id  = $user_id;
		$this->thread   = new Thread('staff-role');
		$this->role_id  = ApplicantRole::get_id($role);
		$this->body	    = $body;
		$this->resovled = false;
		G::$DB->prepared_query("
			INSERT INTO applicant (RoleID, UserID, ThreadID, Body)
			VALUES (?, ?, ?, ?)
			", $this->role_id, $this->user_id, $this->thread->id(), $this->body
		);
		$this->id = G::$DB->inserted_id();
		G::$Cache->delete_value(self::CACHE_KEY_NEW_COUNT);
		$this->flush_applicant_list_cache();
	}

	private function flush_applicant_list_cache() {
		G::$Cache->delete_value('user_is_applicant.' . $this->user_id);
		for ($page = 1; $page; ++$page) {
			$hit = 0;
			$cache_key = [
				sprintf(self::CACHE_KEY_OPEN,     $page),
				sprintf(self::CACHE_KEY_RESOLVED, $page),
				sprintf(self::CACHE_KEY_OPEN     . ".$this->user_id", $page),
				sprintf(self::CACHE_KEY_RESOLVED . ".$this->user_id", $page)
			];
			foreach ($cache_key as $key) {
				if (G::$Cache->get_value($key) !== false) {
					++$hit;
					G::$Cache->delete_value($key);
				}
			}
			if (!$hit) {
				break;
			}
		}
		return $this;
	}

	public function id() {
		return $this->id;
	}

	public function user_id() {
		return $this->user_id;
	}

	public function thread() {
		return $this->thread;
	}

	public function thread_id() {
		return $this->thread->id();
	}

	public function role_title() {
		return ApplicantRole::get_title($this->role_id);
	}

	public function body() {
		return $this->body;
	}

	public function created() {
		return $this->created;
	}

	public function resolve($resolved = true) {
		$this->resolved = $resolved;
		G::$DB->prepared_query("
			UPDATE applicant
			SET Resolved = ?
			WHERE ID = ?
		", $this->resolved, $this->id);
		$key = sprintf(self::CACHE_KEY, $this->id);
		$data = G::$Cache->get_value($key);
		if ($data !== false) {
			$data['Resolved'] = $this->resolved;
			G::$Cache->replace_value($key, $data, 86400);
		}
		G::$Cache->delete_value('user_is_applicant.' . $this->user_id);
		return $this->flush_applicant_list_cache();
	}

	public function is_resolved() {
		return $this->resolved;
	}

	// DELEGATES

	/**
	 * Save the applicant thread note (see Thread class)
	 */
	public function save_note($poster_id, $body, $visibility) {
		$this->thread->save_note($poster_id, $body, $visibility);
		G::$Cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
		G::$Cache->delete_value(self::CACHE_KEY_NEW_REPLY);
		G::$Cache->delete_value(self::CACHE_KEY_NEW_COUNT);
		if ($visibility == 'public' && Permissions::has_permission($poster_id, 'admin_manage_applicants')) {
			$staff = Users::user_info($poster_id);
			$user  = Users::user_info($this->user_id());
			Misc::send_pm(
				$this->user_id(),
				0,
				sprintf('You have a reply to your %s application', $this->role_title()),
				sprintf(<<<END_MSG
Hello %s,

%s has replied to your application for the role of %s.
You can view the reply [url=%s]here[/url].

~%s Staff <3
END_MSG
					, $user['Username']
					, $staff['Username']
					, $this->role_title()
					, site_url() . '/apply.php?action=view&id=' . $this->id()
					, SITE_NAME
				)
			);
		}
		return $this->flush_applicant_list_cache();
	}

	public function delete_note($note_id) {
		G::$Cache->delete_value(self::CACHE_KEY_NEW_REPLY);
		$this->thread()->delete_note($note_id);
		return $this->flush_applicant_list_cache();
	}

	/**
	 * Get the applicant thread story (see Thread class)
	 */
	public function get_story() {
		return $this->thread->get_story();
	}

	// FACTORY METHODS

	/**
	 * Instantiate an instance of an Applicant from an id
	 * @param $id int The id of an Applicant
	 * @return an Applicant object
	 */
	static public function factory($id) {
		$applicant = new self();
		$key = sprintf(self::CACHE_KEY, $id);
		$data = G::$Cache->get_value($key);
		if ($data === false) {
			G::$DB->prepared_query("
				SELECT a.RoleID, a.UserID, a.ThreadID, a.Body, a.Resolved, a.Created, a.Modified
				FROM applicant a
				WHERE a.ID = ?
			", $id);
			if (G::$DB->has_results()) {
				$data = G::$DB->next_record();
				G::$Cache->cache_value($key, $data, 86400);
			}
		}
		$applicant->id	       = $id;
		$applicant->role_id    = $data['RoleID'];
		$applicant->user_id    = $data['UserID'];
		$applicant->thread     = Thread::factory($data['ThreadID']);
		$applicant->body	   = $data['Body'];
		$applicant->resolved   = $data['Resolved'];
		$applicant->created    = $data['Created'];
		$applicant->modified   = $data['Modified'];
		return $applicant;
	}

	/**
	 * Get an array of applicant entries (e.g. for an index/table of contents page).
	 * Each array element is a hash with the following keys:
	 *  ID            - the Applicant id (in the applicant table)
	 *  RoleID        - the role the applicant is applying for
	 *  UserID        - the id of the member making the application
	 *  UserName      - the name of the member making the application
	 *  ThreadID      - the thread story associated with this application
	 *  Created       - when this application was created
	 *  Modified      - when this application was last modified
	 *  nr_notes      - the number of notes (0 or more) in the story
	 *  last_UserID   - user ID of the most recent person to comment in the thread
	 *  last_Username - username of the most recent person to comment in the thread
	 *  last_Created  - the timestamp of the most recent comment
	 * @param $page int The page number to fetch (50 entries) defaults to 1
	 * @param $resolved int Should resolved applications be included (defaults to no).
	 * @param $user_id int If non-zero, return applications of this user_id
	 * @return a list of Applicant information
	 */
	static public function get_list($page = 1, $resolved = false, $user_id = 0) {
		$key = sprintf($resolved ? self::CACHE_KEY_RESOLVED : self::CACHE_KEY_OPEN, $page);
		if ($user_id) {
			$key .= ".$user_id";
		}
		$list = G::$Cache->get_value($key);
		if ($list === false) {
			$user_condition = $user_id ? 'a.UserID = ?' : '0 = ? /* manager */';
			G::$DB->prepared_query($sql = <<<END_SQL
SELECT APP.ID, r.Title as Role, APP.UserID, u.Username, APP.Created, APP.Modified, APP.nr_notes,
	last.UserID as last_UserID, ulast.Username as last_Username, last.Created as last_Created
FROM
(
	SELECT a.ID as ID, a.RoleID, a.UserID, a.ThreadID, a.Created, a.Modified,
	count(n.ID) as nr_notes, max(n.ID) as last_noteid
	FROM applicant a
	LEFT JOIN thread_note n using (ThreadID)
	WHERE a.Resolved = ?
	AND $user_condition
	GROUP BY a.ID, a.RoleID, a.UserID, a.ThreadID, a.Created, a.Modified
) APP
INNER JOIN applicant_role r     ON (r.ID = APP.RoleID)
INNER JOIN users_main     u     ON (u.ID = APP.UserID)
LEFT JOIN thread_note     last  USING (ThreadID)
LEFT JOIN users_main      ulast ON (ulast.ID = last.UserID)
WHERE (last.ID IS NULL or last.ID = APP.last_noteid)
ORDER by r.Modified DESC,
	GREATEST(coalesce(last.Created, '1970-01-01 00:00:00'), APP.Created) DESC
LIMIT ? OFFSET ?
END_SQL
			, $resolved ? 1 : 0, $user_id, self::ENTRIES_PER_PAGE, ($page-1) * self::ENTRIES_PER_PAGE);
			$list = G::$DB->has_results() ? G::$DB->to_array() : [];
			G::$Cache->cache_value($key, $list, 86400);
		}
		return $list;
	}

	public static function user_is_applicant($user_id) {
		$key = 'user_is_applicant.' . $user_id;
		$has_application = G::$Cache->get_value($key);
		if ($has_application === false) {
			$has_application = -1;
			G::$DB->prepared_query('SELECT 1 FROM applicant WHERE UserID = ? LIMIT 1', $user_id);
			if (G::$DB->has_results()) {
				$data = G::$DB->next_record();
				if ($data[0] == 1) {
					$has_application = 1;
				}
			}
			G::$Cache->cache_value($key, $has_application, 86400);
		}
		return $has_application > 0;
	}

	public static function new_applicant_count() {
		$key = self::CACHE_KEY_NEW_COUNT;
		$applicant_count = G::$Cache->get_value($key);
		if ($applicant_count === false) {
			G::$DB->prepared_query("
				SELECT count(a.ID) as nr
				FROM applicant a
				INNER JOIN thread t ON (a.ThreadID = t.ID
					AND t.ThreadTypeID = (SELECT ID FROM thread_type WHERE Name = ?)
				)
				LEFT JOIN thread_note n ON (n.threadid = t.id)
                WHERE a.Resolved = 0
                    AND n.id IS NULL
				", 'staff-role'
			);
			if (G::$DB->has_results()) {
				$data = G::$DB->next_record();
				$applicant_count = $data['nr'];
			}
			G::$Cache->cache_value($key, $applicant_count, 3600);
		}
		return $applicant_count;
	}

	public static function new_reply_count() {
		$key = self::CACHE_KEY_NEW_REPLY;
		$reply_count = G::$Cache->get_value($key);
		if ($reply_count === false) {
			G::$DB->prepared_query("
				SELECT count(*) AS nr
				FROM applicant a
				INNER JOIN thread_note n USING (ThreadID)
				INNER JOIN (
					/* find the last person to comment in an applicant thread */
					SELECT a.ID, max(n.ID) AS noteid
					FROM applicant a
					INNER JOIN thread t ON (a.threadid = t.id
						AND t.ThreadTypeID = (SELECT ID FROM thread_type WHERE Name = ?)
					)
					INNER JOIN thread_note n ON (n.ThreadID = a.ThreadID)
                    WHERE a.Resolved = 0
					GROUP BY a.ID
				) X ON (n.ID = X.noteid)
				WHERE a.UserID = n.UserID /* if they are the applicant: not a staff response Q.E.D. */
				", 'staff-role'
			);
			if (G::$DB->has_results()) {
				$data = G::$DB->next_record();
				$reply_count = $data['nr'];
			}
			G::$Cache->cache_value($key, $reply_count, 3600);
		}
		return $reply_count;
	}
}
