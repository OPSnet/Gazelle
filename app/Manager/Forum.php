<?php

namespace Gazelle\Manager;

class Forum extends \Gazelle\Base {

    protected const CACHE_TOC = 'forum_toc_main';

    /**
     * Create a forum
     * @param array hash of values (keyed on lowercase column names)
     */
    public function create(array $args) {
        $this->db->prepared_query("
            INSERT INTO forums
                   (Sort, CategoryID, Name, Description, MinClassRead, MinClassWrite, MinClassCreate, AutoLock, AutoLockWeeks)
            VALUES (?,    ?,          ?,    ?,           ?,            ?,             ?,              ?,        ?)
            ", (int)$args['sort'], (int)$args['categoryid'], trim($args['name']), trim($args['description']),
               (int)$args['minclassread'], (int)$args['minclasswrite'], (int)$args['minclasscreate'],
               isset($args['autolock']) ? '1' : '0', (int)$args['autolockweeks']
        );
        $this->flushToc();
        return new \Gazelle\Forum($this->db->inserted_id());
    }

    /**
     * Instantiate a forum by its ID
     *
     * @param int id The forum ID.
     * @return \Gazelle\Forum object or null
     */
    public function findById(int $forumId) {
        return $this->db->scalar("SELECT 1 FROM forums WHERE ID = ?", $forumId)
            ? new \Gazelle\Forum($forumId)
            : null;
    }

    /**
     * Instantiate a forum from a thread ID.
     *
     * @param int id The thread ID.
     * @return \Gazelle\Forum object
     */
    public function findByThreadId(int $threadId) {
        if (!($forumId = $this->cache->get_value("thread_forum_" . $threadId))) {
            $forumId = $this->db->scalar("
                SELECT ForumID FROM forums_topics WHERE ID = ?
                ", $threadId
            );
            $this->cache->cache_value("thread_forum_" . $threadId, $forumId, 0);
        }
        if (is_null($forumId)) {
            throw new \Gazelle\Exception\ResourceNotFoundException($threadId);
        }
        return new \Gazelle\Forum($forumId);
    }

    /**
     * Instantiate a forum from a post ID.
     *
     * @param int id The post ID.
     * @return \Gazelle\Forum object
     */
    public function findByPostId(int $postId) {
        $forumId = $this->db->scalar("
            SELECT t.ForumID
            FROM forums_topics t
            INNER JOIN forums_posts AS p ON (p.TopicID = t.ID)
            WHERE p.ID = ?
            ", $postId
        );
        if (is_null($forumId)) {
            throw new \Gazelle\Exception\ResourceNotFoundException($postId);
        }
        return new \Gazelle\Forum($forumId);
    }

    /**
     * Get list of forum names
     */
    public function nameList() {
        $this->db->prepared_query("
            SELECT ID, Name FROM forums ORDER BY Sort
        ");
        return $this->db->to_array();
    }

    /**
     * Get list of forums keyed by category
     */
    public function categoryList() {
        if (($categories = $this->cache->get_value('forums_categories')) === false) {
            $this->db->prepared_query("
                SELECT ID, Name FROM forums_categories ORDER BY Sort, Name
            ");
            $categories = [];
            while (list($id, $name) = $this->db->next_record(MYSQLI_NUM, false)) {
                $categories[$id] = $name;
            }
            $this->cache->cache_value('forums_categories', $categories, 0);
        }
        return $categories;
    }

    /**
     * The forum table of contents (the main /forums.php view)
     *
     * @return array
     *  - string category name "Community"
     *  containing an array of (one per forum):
     *    - int 'ID' Forum id
     *    - string 'Name' Forum name "The Lounge"
     *    - string 'Description' Forum description "The Lounge"
     *    - int 'NumTopics' Number of threads (topics)
     *    - int 'NumPosts' Number of posts (sum of posts of all threads)
     *    - int 'LastPostTopicID' Thread id of most recent post
     *    - int 'MinClassRead' Min class read     \
     *    - int 'MinClassWrite' Min class write   -+-- ACLs
     *    - int 'MinClassCreate' Min class create /
     *    - int 'Sort' Positional rank
     *    - bool 'AutoLock' if true, forum will lock after AutoLockWeeks of inactivity
     *    - int 'AutoLockWeeks' number of weeks for inactivity timer
     *    - string 'Title' Title of most recent thread
     *    - int 'LastPostAuthorID' User id of author of most recent post
     *    - int 'LastPostID' Post id of most recent post
     *    - timestamp 'LastPostTime' Date of most recent thread (creation or post)
     *    - int 'IsSticky' Last post is locked (0/1)
     *    - int 'IsLocked' Last post is sticky (0/1)
     */
    public function tableOfContentsMain() {
        if (!$toc = $this->cache->get_value(self::CACHE_TOC)) {
            $this->db->prepared_query("
                SELECT cat.Name AS categoryName, cat.ID AS categoryId,
                    f.ID, f.Name, f.Description, f.NumTopics, f.NumPosts,
                    f.LastPostTopicID, f.MinClassRead, f.MinClassWrite, f.MinClassCreate,
                    f.Sort, f.AutoLock, f.AutoLockWeeks,
                    ft.Title, ft.LastPostAuthorID, ft.LastPostID, ft.LastPostTime, ft.IsSticky, ft.IsLocked
                FROM forums f
                INNER JOIN forums_categories cat ON (cat.ID = f.CategoryID)
                LEFT JOIN forums_topics ft ON (ft.ID = f.LastPostTopicID)
                ORDER BY cat.Sort, cat.Name, f.Sort, f.Name
            ");
            $toc = [];
            while ($row = $this->db->next_row(MYSQLI_ASSOC)) {
                $category = $row['categoryName'];
                unset($row['categoryName']);
                $row['AutoLock'] = ($row['AutoLock'] == '1');
                if (!isset($toc[$category])) {
                    $toc[$category] = [];
                }
                $toc[$category][] = $row;
            }
            $this->cache->cache_value(self::CACHE_TOC, $toc, 86400 * 10);
        }
        return $toc;
    }

    public function flushToc() {
        $this->cache->delete_value(self::CACHE_TOC);
        return $this;
    }
}
