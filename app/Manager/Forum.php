<?php

namespace Gazelle\Manager;

class Forum {
	private $db;
	private $cache;

	const HEADLINE_KEY = 'forums_headlines.';
	const HEADLINE_TOPICS_KEY = 'forums_headlines_topics';
	const HEADLINE_REPLIES_KEY = 'forums_headlines_replies';

	/**
	 * Forum constructor.
	 * @param \DB_MYSQL $db
	 * @param \CACHE $cache
	 */
	public function __construct($db, $cache) {
		$this->db = $db;
		$this->cache = $cache;
	}

	public function isHeadline($id) {
		$is_headline = $this->cache->get_value(self::HEADLINE_KEY . $id);
		if (false === $is_headline) {
			$this->db->prepared_query("
				SELECT 1 FROM forums WHERE IsHeadline = '1' AND ID = ?
				", $id
			);
			$is_headline = $this->db->has_results() ? 1 : 0;
			$this->cache->cache_value(self::HEADLINE_KEY . $id, $is_headline, 86400 * 30);
		}
		return $is_headline;
	}

	public function flushHeadlines() {
		$this->cache->delete_value(self::HEADLINE_TOPICS_KEY);
		$this->cache->delete_value(self::HEADLINE_REPLIES_KEY);
	}

	public function create($sort, $categoryid, $name, $description, $minclassread, $minclasswrite, $minclasscreate, $autolock, $autolockweeks, $headline) {
		$this->db->prepared_query("
			INSERT INTO forums
				(Sort, CategoryID, Name, Description, MinClassRead, MinClassWrite, MinClassCreate, AutoLock, AutoLockWeeks, IsHeadline)
			VALUES
				(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
				", $sort, $categoryid, $name, $description, $minclassread, $minclasswrite, $minclasscreate, $autolock, $autolockweeks, $headline
		);
		$this->cache->cache_value(self::HEADLINE_KEY . $this->db->inserted_id(), $headline ? 1 : 0, 86400 * 30);
		$this->cache->delete_value('forums_list');
		return $this->db->affected_rows();
	}

	public function update($id, $sort, $categoryid, $name, $description, $minclassread, $minclasswrite, $minclasscreate, $autolock, $autolockweeks, $headline) {
		$this->db->prepared_query("
			UPDATE forums SET
				Sort = ?,
				CategoryID = ?,
				Name = ?,
				Description = ?,
				MinClassRead = ?,
				MinClassWrite = ?,
				MinClassCreate = ?,
				AutoLock = ?,
				AutoLockWeeks = ?,
				IsHeadline = ?
			WHERE ID = ?
			", $sort, $categoryid, $name, $description, $minclassread, $minclasswrite, $minclasscreate, $autolock, $autolockweeks, $headline
			, $id
		);
		$this->cache->cache_value(self::HEADLINE_KEY . $id, $headline ? 1 : 0, 86400 * 30);
		$this->cache->delete_value('forums_list');
		return $this->db->affected_rows();
	}

	public function delete($id) {
		$this->db->prepared_query('
			DELETE FROM forums WHERE ID = ?
			', $id
		);
		$this->cache->delete_value('forums_list');
		return $this->db->affected_rows();
	}

	public function getMinClassRead($id) {
		$this->db->prepared_query('
			SELECT MinClassRead FROM forums WHERE ID = ?
			', $id
		);
		if (!$this->db->has_results()) {
			return null;
		}
		else {
			list($val) = $this->db->next_record();
			return $val;
		}
	}

	public function getTopic($topic_id) {
		$this->db->prepared_query("
			SELECT
				t.ForumID,
				f.Name,
				f.MinClassWrite,
				COUNT(p.ID) AS Posts,
				t.AuthorID,
				t.Title,
				t.IsLocked,
				t.IsSticky,
				t.Ranking
			FROM forums AS f
			INNER JOIN forums_topics AS t ON (t.ForumID = f.ID)
			LEFT JOIN forums_posts   AS p ON (p.TopicID = t.ID)
			WHERE t.ID = ?
			GROUP BY p.TopicID
			", $topic_id
		);
		return $this->db->next_record(MYSQLI_BOTH, false);
	}

	public function deleteTopic($topic_id, $forum_id) {
		$this->db->prepared_query("
			DELETE FROM forums_posts WHERE TopicID = ?
			", $topic_id
		);
		$this->db->prepared_query("
			DELETE FROM forums_topics WHERE ID = ?
			", $topic_id
		);
		$this->db->prepared_query("
			DELETE FROM users_notify_quoted
			WHERE Page = 'forums'
				AND PageID = ?
			", $topic_id
		);
		if ($this->isHeadline($forum_id)) {
			$this->flushHeadlinesTopic();
		}

		$this->db->prepared_query("
			SELECT
				t.ID,
				t.LastPostID,
				t.Title,
				p.AuthorID,
				um.Username,
				p.AddedTime,
				(
					SELECT COUNT(pp.ID)
					FROM forums_posts AS pp
					INNER JOIN forums_topics AS tt ON (pp.TopicID = tt.ID)
					WHERE tt.ForumID = ?
				),
				t.IsLocked,
				t.IsSticky
			FROM forums_topics AS t
			INNER JOIN forums_posts AS p ON p.ID = t.LastPostID
			LEFT JOIN users_main AS um ON um.ID = p.AuthorID
			WHERE t.ForumID = ?
			GROUP BY t.ID
			ORDER BY t.LastPostID DESC
			LIMIT 1
			", $forum_id, $forum_id
		);
		list($NewLastTopic, $NewLastPostID, $NewLastTitle, $NewLastAuthorID, $NewLastAuthorName, $NewLastAddedTime, $NumPosts, $NewLocked, $NewSticky)
			= $this->db->next_record(MYSQLI_NUM, false);

		$this->db->prepared_query("
			UPDATE forums SET
				NumTopics = NumTopics - 1,
				NumPosts = NumPosts - ?,
				LastPostTopicID = ?,
				LastPostID = ?,
				LastPostAuthorID = ?,
				LastPostTime = ?
			WHERE ID = ?
			", $Posts, $NewLastTopic, $NewLastPostID, $NewLastAuthorID, $NewLastAddedTime, $forum_id
		);

		$Cache->begin_transaction('forums_list');
		$Cache->update_row($forum_id, [
			'NumPosts' => $NumPosts,
			'NumTopics' => '-1',
			'LastPostID' => $NewLastPostID,
			'LastPostAuthorID' => $NewLastAuthorID,
			'LastPostTopicID' => $NewLastTopic,
			'LastPostTime' => $NewLastAddedTime,
			'Title' => $NewLastTitle,
			'IsLocked' => $NewLocked,
			'IsSticky' => $NewSticky
		]);
		$Cache->commit_transaction(0);
		$Cache->delete_value("forums_$forum_id");
		$Cache->delete_value("thread_$topic_id");
		$Cache->delete_value("thread_{$topic_id}_info");
	}

	public function getLatestHeadlineTopics($max = 3, $since = 604800) {
		$headlines = $this->cache->get_value(self::HEADLINE_TOPICS_KEY);
		if ($headlines === false) {
			$this->db->prepared_query("
				SELECT
					f.ID as forum_id,
					f.Name as forum_name,
					t.ID as topic_id,
					t.Title as topic_name,
					t.AuthorID as author_id,
					t.CreatedTime as created_time,
					count(p.ID) - 1 as replies
				FROM forums f
				INNER JOIN forums_topics t ON (t.ForumID = f.ID)
				INNER JOIN forums_posts p ON (p.TopicID = t.ID)
				WHERE f.IsHeadline = 1
					AND t.CreatedTime > ?
				GROUP BY f.ID, f.Name, t.ID, t.Title, t.AuthorID, t.CreatedTime
				ORDER BY t.CreatedTime DESC, t.ID ASC
				LIMIT ?
				", Date('Y-m-d H:i:s', Date('U') - $since), $max
			);
			$headlines = $this->db->has_results() ? $this->db->to_array() : [];
			$this->cache->cache_value(self::HEADLINE_TOPICS_KEY, $headlines, 86400);
		}
		return $headlines;
	}

	public function getLatestHeadlineReplies($max = 3, $since = 604800) {
		$replies = $this->cache->get_value(self::HEADLINE_REPLIES_KEY);
		if ($replies === false) {
			$recent = Date('Y-m-d H:i:s', Date('U') - $since);
			$this->db->prepared_query("
				SELECT
					f.ID as forum_id,
					f.Name as forum_name,
					t.ID as topic_id,
					t.Title as topic_title,
					p.ID as post_id,
					p.AuthorID as post_author_id,
					p.AddedTime as post_added_time
				FROM forums f
				INNER JOIN forums_topics t ON (t.ForumID = f.ID)
				INNER JOIN (
						/* Prevent the query planner from generating a table scan
						 * on forums_topics and/or not using the right index. Choose
						 * enough rows so that we find some topics that were created
						 * over a week ago.
						 */
						SELECT rt.ID
						FROM forums_topics rt
						ORDER BY rt.LastPostTime DESC
						LIMIT 100
					) RECENT ON (RECENT.ID = t.ID)
				INNER JOIN forums_posts p ON (p.TopicID = t.ID)
				WHERE f.IsHeadline = 1
					AND t.IsLocked = '0'
					AND t.CreatedTime <= ?
					AND p.AddedTime > ?
					AND p.AddedTime = t.LastPostTime
				ORDER BY
					p.AddedTime DESC
				", $recent, $recent
			);
			$replies = [];
			while (null !== ($row = $this->db->next_record(MYSQLI_ASSOC))) {
				$replies[] = $row;
				if (count($replies) >= $max) {
					break;
				}
			}
			$this->cache->cache_value(self::HEADLINE_REPLIES_KEY, $replies, 86400);
		}
		return $replies;
	}

	public function lockThread ($id) {
		$this->db->prepared_query('
		DELETE FROM forums_last_read_topics WHERE TopicID = ?
			', $id
		);
		$this->flushHeadlinesReply();
	}

	public function getAdminList() {
		$this->db->query('
			SELECT
				f.ID,
				f.CategoryID,
				f.Sort,
				f.Name,
				f.Description,
				f.MinClassRead,
				f.MinClassWrite,
				f.MinClassCreate,
				f.AutoLock,
				f.AutoLockWeeks,
				f.IsHeadline
			FROM forums AS f
			LEFT JOIN forums_categories AS c ON c.ID = f.CategoryID
			ORDER BY c.Sort, c.Name, f.CategoryID, f.Sort, f.Name
		');
		return $this->db->to_array();
	}

	public function getCategoryList() {
		$list = $this->cache->get_value('forums_categories');
		if ($list === false) {
			$this->db->query('
				SELECT ID, Name
				FROM forums_categories
				ORDER BY Sort, Name
			');
			$list = $this->db->to_array('ID');
			$this->cache->cache_value('forums_categories', $list, 0); //Inf cache.
		}
		return $list;
	}

}
