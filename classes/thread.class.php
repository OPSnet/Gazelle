<?php

/* Conversation thread, allows private staff notes intermingled
 * with the public notes that both staff and member can see.
 * A collection of notes is called a story.
 */

class Thread {
	private $id;	  // the ID of the row in the thread table
	private $type;	// the type of thread
	private $created; // date created
	private $story;   // the array of notes in the conversation

	/**
	 * persistent Thread constructor
	 * @param string $type Thread Type (corresponds to db thread_type.Name)
	 */
	public function __construct($type = null) {
		if (!isset($type)) {
			return;
		}
		$this->type = $type;
		G::$DB->prepared_query("
			INSERT INTO thread (ThreadTypeID) VALUES (
				(SELECT ID FROM thread_type where Name = ?)
			)
		", $type);
		$this->id	= G::$DB->inserted_id();
		$this->story = [];
	}

	/**
	 * @return int The id of a Thread
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Get the array of notes of the thread. A note is a hash with the following items:
	 *  id         - the id in thread_note table
	 *  user_id    - the id of the author in the users_main table
	 *  user_name  - the name of the member (normally we don't need anything else from there).
	 *  visibility - this note is 'public' or just visibible to 'staff'
	 *  created    - when was this note created
	 *  body       - the note text itself
	 * @return array The list of notes in a thread ordered by most recent first.
	 */
	public function get_story() {
		return $this->story;
	}

	/**
	 * Persist a note to the db.
	 * @param int $user_id The note author
	 * @param string $body The note text
	 * @param int $visibility 'public' or 'staff'
	 */
	public function save_note($user_id, $body, $visibility) {
		G::$DB->prepared_query(
			'INSERT INTO thread_note (ThreadID, UserID, Body, Visibility) VALUES (?, ?, ?, ?)',
			$this->id, $user_id, $body, $visibility
		);
		return $this->refresh();
	}

	/**
	 * Persist a change to the note
	 * @param int $id The id to identify a note
	 * @param string $body The note text
	 * @param int $visibility 'public' or 'staff'
	 */
	public function update_note($id, $body, $visibility) {
		G::$DB->prepared_query(
			'UPDATE thread_note SET Body = ?, Visibility = ? WHERE ID = ?',
			$body, $visibility, $id
		);
		return $this->refresh();
	}

	/**
	 * Delete a note.
	 * @param int $note_id The id to identify a note
	 */
	public function delete_note($note_id) {
		G::$DB->prepared_query(
			'DELETE FROM thread_note WHERE ThreadID = ? AND ID = ?',
			$this->id(),
			$note_id
		);
		return $this->refresh();
	}

	/**
	 * Refresh the story cache when a note is added, changed, deleted.
	 */
	private function refresh() {
		$key = "thread_story_" . $this->id;
		G::$DB->prepared_query('
			SELECT ID, UserID, Visibility, Created, Body
			FROM thread_note
			WHERE ThreadID = ?
			ORDER BY created;
		', $this->id);
		$this->story = [];
		if (G::$DB->has_results()) {
			$user_cache = [];
			while (($row = G::$DB->next_record())) {
				if (!in_array($row['UserID'], $user_cache)) {
					$user = Users::user_info($row['UserID']);
					$user_cache[$row['UserID']] = $user['Username'];
				}
				$this->story[] = [
					'id'		 => $row['ID'],
					'user_id'    => $row['UserID'],
					'user_name'  => $user_cache[$row['UserID']],
					'visibility' => $row['Visibility'],
					'created'    => $row['Created'],
					'body'       => $row['Body']
				];
			}
		}
		G::$Cache->cache_value($key, $this->story, 3600);
		return $this;
	}

	// FACTORY METHODS

	/**
	 * Instantiate a new instance of a Thread from an id
	 * @param $id int The id of a Thread
	 * @return a Thread object
	 */
	static public function factory($id) {
		$thread = new self();
		$key = "thread_$id";
		$data = G::$Cache->get_value($key);
		if ($data === false) {
			G::$DB->prepared_query("
				SELECT tt.Name as ThreadType, t.Created
				FROM thread t
				INNER JOIN thread_type tt ON (tt.ID = t.ThreadTypeID)
				WHERE t.ID = ?
			", $id);
			if (G::$DB->has_results()) {
				$data = G::$DB->next_record();
				G::$Cache->cache_value($key, $data, 86400);
			}
		}
		$thread->id	     = $id;
		$thread->type    = $data['ThreadType'];
		$thread->created = $data['Created'];
		return $thread->refresh(); /* load the story */
	}
}
