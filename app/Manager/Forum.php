<?php

namespace Gazelle\Manager;

class Forum extends \Gazelle\Base {

    protected const CACHE_TOC  = 'forum_toc_mainv3';
    protected const CACHE_LIST = 'forum_list';

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
            return null;
        }
        return new \Gazelle\Forum($forumId);
    }

    /**
     * Find the thread of the poll featured on the front page.
     *
     * @return thread id or null
     */
    public function findThreadIdByFeaturedPoll(): ?int {
        if (($threadId = $this->cache->get_value('polls_featured')) === false) {
            $threadId = $this->db->scalar("
                SELECT TopicID
                FROM forums_polls
                WHERE Featured IS NOT NULL
                ORDER BY Featured DESC
                LIMIT 1
            ");
            $this->cache->cache_value('polls_featured', $threadId, 86400 * 7);
        }
        return $threadId;
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
            while ([$id, $name] = $this->db->next_record(MYSQLI_NUM, false)) {
                $categories[$id] = $name;
            }
            $this->cache->cache_value('forums_categories', $categories, 0);
        }
        return $categories;
    }

    public function forumList(): array {
        if (($list = $this->cache->get_value(self::CACHE_LIST)) === false) {
            $this->db->prepared_query("
                SELECT f.ID
                FROM forums f
                INNER JOIN forums_categories cat ON (cat.ID = f.CategoryID)
                ORDER BY cat.Sort, cat.Name, f.Sort, f.Name
            ");
            $list = $this->db->collect('ID');
            $this->cache->cache_value(self::CACHE_LIST, $list, 86400);
        }
        return $list;
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

    public function forumTransitionList(\Gazelle\User $user) {
        $items = $this->cache->get_value('forum_transitions_v2');
        if (!$items) {
            $queryId = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT forums_transitions_id AS id, source, destination, label, permission_levels,
                       permission_class, permissions, user_ids
                FROM forums_transitions
            ");
            $items = $this->db->to_array('id', MYSQLI_ASSOC);
            $this->db->set_query_id($queryId);
            foreach ($items as &$i) {
                // permission_class == primary class
                // permission_levels == secondary classes
                $i['user_ids'] = array_fill_keys(explode(',', $i['user_ids']), 1);
                $i['permissions'] = array_fill_keys(explode(',', $i['permissions']), 1);
                $i['permission_levels'] = array_fill_keys(explode(',', $i['permission_levels']), 1);
                unset($i['user_ids'][''], $i['permissions'][''], $i['permission_levels']['']);
            }
            unset($i);
            $this->cache->cache_value('forum_transitions', $items, 0);
        }

        $userId = $user->id();
        $info['EffectiveClass']  = $user->effectiveClass();
        $info['ExtraClasses']    = array_keys($user->secondaryClasses());
        $info['Permissions']     = array_keys($user->info()['Permission']);
        $info['ExtraClassesOff'] = array_flip(array_map(function ($i) { return -$i; }, $info['ExtraClasses']));
        $info['PermissionsOff']  = array_flip(array_map(function ($i) { return "-$i"; }, array_keys($user->info()['Permission'])));

        return array_filter($items, function ($item) use ($info, $userId) {
            if (count(array_intersect_key($item['permission_levels'], $info['ExtraClassesOff'])) > 0) {
                return false;
            }

            if (count(array_intersect_key($item['permissions'], $info['PermissionsOff'])) > 0) {
                return false;
            }

            if (count(array_intersect_key($item['user_ids'], [-$userId => 1])) > 0) {
                return false;
            }

            if (count(array_intersect_key($item['permission_levels'], $info['ExtraClasses'])) > 0) {
                return true;
            }

            if (count(array_intersect_key($item['permissions'], $info['Permissions'])) > 0) {
                return true;
            }

            if (count(array_intersect_key($item['user_ids'], [$userId => 1])) > 0) {
                return true;
            }

            if ($item['permission_class'] <= $info['EffectiveClass']) {
                return true;
            }

            return false;
        });
    }

    public function threadTransitionList(\Gazelle\User $user, int $forumId): array {
        return array_filter($this->forumTransitionList($user),
            function ($t) use ($forumId) {return $t['source'] === $forumId;}
        );
    }

    public function flushToc() {
        $this->cache->delete_value(self::CACHE_TOC);
        return $this;
    }
}
