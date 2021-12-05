<?php

namespace Gazelle\Manager;

class Forum extends \Gazelle\Base {

    protected const CACHE_TOC        = 'forum_toc_mainv3';
    protected const CACHE_LIST       = 'forum_list';
    protected const CACHE_TRANSITION = 'forum_transition';
    protected const ID_KEY           = 'zz_f_%d';
    protected const ID_THREAD_KEY    = 'zz_ft_%d';
    protected const ID_POST_KEY      = 'zz_fp_%d';

    /**
     * Create a forum
     * @param array hash of values (keyed on lowercase column names)
     */
    public function create(array $args) {
        self::$db->prepared_query("
            INSERT INTO forums
                   (Sort, CategoryID, Name, Description, MinClassRead, MinClassWrite, MinClassCreate, AutoLock, AutoLockWeeks)
            VALUES (?,    ?,          ?,    ?,           ?,            ?,             ?,              ?,        ?)
            ", (int)$args['sort'], (int)$args['categoryid'], trim($args['name']), trim($args['description']),
               (int)$args['minclassread'], (int)$args['minclasswrite'], (int)$args['minclasscreate'],
               isset($args['autolock']) ? '1' : '0', (int)$args['autolockweeks']
        );
        $this->flushToc();
        return new \Gazelle\Forum(self::$db->inserted_id());
    }

    /**
     * Instantiate a forum by its ID
     */
    public function findById(int $forumId): ?\Gazelle\Forum {
        $key = sprintf(self::ID_KEY, $forumId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM forums WHERE ID = ?
                ", $forumId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\Forum($id) : null;
    }

    /**
     * Instantiate a forum from a thread ID.
     *
     * @param int id The thread ID.
     * @return \Gazelle\Forum object
     */
    public function findByThreadId(int $threadId) {
        $key = sprintf(self::ID_THREAD_KEY, $threadId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ForumID FROM forums_topics WHERE ID = ?
                ", $threadId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\Forum($id) : null;
    }

    /**
     * Instantiate a forum from a post ID.
     *
     * @param int id The post ID.
     * @return \Gazelle\Forum object
     */
    public function findByPostId(int $postId) {
        $key = sprintf(self::ID_POST_KEY, $postId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT t.ForumID
                FROM forums_topics t
                INNER JOIN forums_posts AS p ON (p.TopicID = t.ID)
                WHERE p.ID = ?
                ", $postId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\Forum($id) : null;
    }

    /**
     * Find the thread of the poll featured on the front page.
     *
     * @return thread id or null
     */
    public function findThreadIdByFeaturedPoll(): ?int {
        if (($threadId = self::$cache->get_value('polls_featured')) === false) {
            $threadId = self::$db->scalar("
                SELECT TopicID
                FROM forums_polls
                WHERE Featured IS NOT NULL
                ORDER BY Featured DESC
                LIMIT 1
            ");
            self::$cache->cache_value('polls_featured', $threadId, 86400 * 7);
        }
        return $threadId;
    }

    /**
     * Get list of forum names
     */
    public function nameList() {
        self::$db->prepared_query("
            SELECT ID, Name FROM forums ORDER BY Sort
        ");
        return self::$db->to_array();
    }

    /**
     * Get list of forums categories
     */
    public function categoryList() {
        $categories = self::$cache->get_value('forums_categories');
        if ($categories === false) {
            self::$db->prepared_query("
                SELECT fc.ID,
                    fc.Name
                FROM forums_categories fc
                ORDER BY fc.Sort,
                    fc.Name
            ");
            $categories = self::$db->to_pair('ID', 'Name');
            self::$cache->cache_value('forums_categories', $categories, 0);
        }
        return $categories;
    }

    /**
     * Get list of forums categories by usage
     */
    public function categoryUsageList(): array {
        self::$db->prepared_query("
            SELECT fc.ID AS id,
                fc.Name  AS name,
                fc.Sort  AS sequence,
                IFNULL(f.total, 0) as total
            FROM forums_categories as fc
            LEFT JOIN (
                SELECT f.CategoryID, count(*) AS total FROM forums f GROUP BY f.CategoryID
            ) AS f ON (f.CategoryID = fc.ID)
            ORDER BY fc.Sort
        ");
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    public function forumList(): array {
        if (($list = self::$cache->get_value(self::CACHE_LIST)) === false) {
            self::$db->prepared_query("
                SELECT f.ID
                FROM forums f
                INNER JOIN forums_categories cat ON (cat.ID = f.CategoryID)
                ORDER BY cat.Sort, cat.Name, f.Sort, f.Name
            ");
            $list = self::$db->collect('ID');
            self::$cache->cache_value(self::CACHE_LIST, $list, 86400);
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
        if (!$toc = self::$cache->get_value(self::CACHE_TOC)) {
            self::$db->prepared_query("
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
            while ($row = self::$db->next_row(MYSQLI_ASSOC)) {
                $category = $row['categoryName'];
                $row['AutoLock'] = ($row['AutoLock'] == '1');
                if (!isset($toc[$category])) {
                    $toc[$category] = [];
                }
                $toc[$category][] = $row;
            }
            self::$cache->cache_value(self::CACHE_TOC, $toc, 86400 * 10);
        }
        return $toc;
    }

    protected function flushTransition(): void {
        self::$cache->delete_value(self::CACHE_TRANSITION);
    }

    public function createTransition(array $args): int {
        self::$db->prepared_query("
            INSERT INTO forums_transitions
                   (source, destination, label, permission_levels, permission_class, permissions, user_ids)
            VALUES (?,      ?,           ?,     ?,                 ?,                ?,           ?)
            ", $args['source'], $args['destination'], $args['label'], $args['secondary_classes'],
            $args['permission_class'], $args['permissions'], $args['user_ids']
        );
        $this->flushTransition();
        return self::$db->inserted_id();
    }

    public function modifyTransition(array $args): int {
        self::$db->prepared_query("
            UPDATE forums_transitions SET
                source = ?,
                destination = ?,
                label = ?,
                permission_levels = ?,
                permission_class = ?,
                permissions = ?,
                user_ids = ?
            WHERE forums_transitions_id = ?
            ", $args['source'], $args['destination'], $args['label'], $args['secondary_classes'],
               $args['permission_class'], $args['permissions'], $args['user_ids'],
               $args['id']
        );
        $this->flushTransition();
        return self::$db->affected_rows();
    }

    public function removeTransition(int $id): int {
        self::$db->prepared_query("
            DELETE FROM forums_transitions WHERE forums_transitions_id = ?
            ", $id
        );
        $this->flushTransition();
        return self::$db->affected_rows();
    }

    public function forumTransitionList(\Gazelle\User $user) {
        $items = self::$cache->get_value(self::CACHE_TRANSITION);
        if (!$items) {
            $queryId = self::$db->get_query_id();
            self::$db->prepared_query("
                SELECT forums_transitions_id AS id, source, destination, label, permission_levels,
                       permission_class, permissions, user_ids
                FROM forums_transitions
            ");
            $items = self::$db->to_array('id', MYSQLI_ASSOC);
            self::$db->set_query_id($queryId);
            foreach ($items as &$i) {
                // permission_class == primary class
                // permission_levels == secondary classes
                $i['user_ids'] = array_fill_keys(explode(',', $i['user_ids']), 1);
                $i['permissions'] = array_fill_keys(explode(',', $i['permissions']), 1);
                $i['permission_levels'] = array_fill_keys(explode(',', $i['permission_levels']), 1);
                unset($i['user_ids'][''], $i['permissions'][''], $i['permission_levels']['']);
            }
            unset($i);
            self::$cache->cache_value(self::CACHE_TRANSITION, $items, 0);
        }

        $userId = $user->id();
        $info['EffectiveClass']  = $user->effectiveClass();
        $info['ExtraClasses']    = array_keys($user->secondaryClasses());
        $info['Permissions']     = array_keys($user->info()['Permission']);
        $info['ExtraClassesOff'] = array_flip(array_map(fn($i) => -$i, $info['ExtraClasses']));
        $info['PermissionsOff']  = array_flip(array_map(fn($i) => "-$i", array_keys($user->info()['Permission'])));

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
        self::$cache->delete_value(self::CACHE_TOC);
        return $this;
    }

    /**
     * Configure forum ACLs for a user (what they can see from their class, what they
     * have explicit permission to access, less what they have been forbidden.
     * It is expected to implode(' AND ', ...) the first return parameter within a
     * larger query, and merge the second return parameter into the prepared_query()
     * call.
     *
     * It is a pre-requisite that the `forums` table have the alias f.
     *
     * @param Gazelle\User viewer
     * @return array of [conditions, args]
     */
    public function configureForUser(\Gazelle\User $user): array {
        $permitted = $user->permittedForums();
        if (!empty($permitted)) {
            $cond = ['(f.MinClassRead <= ? OR f.ID IN (' . placeholders($permitted) . '))'];
            $args = array_merge([$user->classLevel()], $permitted);
        } else {
            $cond = ['f.MinClassRead <= ?'];
            $args = [$user->classLevel()];
        }
        $forbidden = $user->forbiddenForums();
        if (!empty($forbidden)) {
            $cond[] = 'f.ID NOT IN (' . placeholders($forbidden) . ')';
            $args = array_merge($args, $forbidden);
        }
        return [$cond, $args];
    }

    public function subscribedForumTotal(\Gazelle\User $user): int {
        [$cond, $args] = $this->configureForUser($user);
        return self::$db->scalar("
            SELECT count(*)
            FROM users_subscriptions AS s
            LEFT JOIN forums_last_read_topics AS l ON (l.UserID = s.UserID AND l.TopicID = s.TopicID)
            INNER JOIN forums_topics AS t ON (t.ID = s.TopicID)
            INNER JOIN forums AS f ON (f.ID = t.ForumID)
            WHERE s.UserID = ?
                AND " . implode(' AND ', $cond),
            $user->id(), ...$args
        );
    }

    public function unreadSubscribedForumTotal(\Gazelle\User $user): int {
        [$cond, $args] = $this->configureForUser($user);
        return self::$db->scalar("
            SELECT count(*)
            FROM users_subscriptions AS s
            LEFT JOIN forums_last_read_topics AS l ON (l.UserID = s.UserID AND l.TopicID = s.TopicID)
            INNER JOIN forums_topics AS t ON (t.ID = s.TopicID)
            INNER JOIN forums AS f ON (f.ID = t.ForumID)
            WHERE if(t.IsLocked = '1' AND t.IsSticky = '0', t.LastPostID, coalesce(l.PostID, 0)) < t.LastPostID
                AND s.UserID = ?
                AND " . implode(' AND ', $cond),
            $user->id(), ...$args
        );
    }

    public function latestPostsList(\Gazelle\User $user, bool $showUnread, int $limit, int $offset): array {
        [$cond, $args] = $this->configureForUser($user);
        if ($showUnread) {
            $cond[] = "if(t.IsLocked = '1' AND t.IsSticky = '0', t.LastPostID, flrt.PostID) < t.LastPostID";
        }
        array_push($cond,
            "s.UserID = ?"
        );
        array_push($args, $user->id(), $limit, $offset);

        self::$db->prepared_query("
            SELECT f.ID            AS forumId,
                f.Name             AS forumName,
                t.ID               AS threadId,
                t.Title            AS threadTitle,
                t.LastPostID       AS lastPostId,
                (t.IsLocked = '1') AS locked,
                (p.ID < t.LastPostID AND t.IsLocked != '1') AS new
            FROM users_subscriptions AS s
            INNER JOIN forums_last_read_topics AS flrt ON (flrt.TopicID = s.TopicID AND flrt.UserID = ?)
            INNER JOIN forums_topics AS t ON (t.ID = s.TopicID)
            INNER JOIN forums_posts AS p ON (p.ID = flrt.PostID and p.TopicID = flrt.TopicID)
            INNER JOIN forums AS f ON (f.ID = t.ForumID)
            WHERE " . implode(' AND ', $cond) . "
            GROUP BY p.TopicID
            ORDER BY t.LastPostID DESC
            LIMIT ? OFFSET ?
            ", $user->id(), ...$args
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    public function lockOldThreads(): int {
        self::$db->prepared_query("
            SELECT t.ID, t.ForumID
            FROM forums_topics AS t
            INNER JOIN forums AS f ON (t.ForumID = f.ID)
            WHERE t.IsLocked = '0'
                AND t.IsSticky = '0'
                AND f.AutoLock = '1'
                AND t.LastPostTime + INTERVAL f.AutoLockWeeks WEEK < now()
        ");
        $ids = self::$db->collect('ID');
        $forumIDs = self::$db->collect('ForumID');

        $forumMan = new \Gazelle\Manager\Forum;
        if (count($ids) > 0) {
            $placeholders = placeholders($ids);
            self::$db->prepared_query("
                UPDATE forums_topics SET
                    IsLocked = '1'
                WHERE ID IN ($placeholders)
            ", ...$ids);

            self::$db->prepared_query("
                DELETE FROM forums_last_read_topics
                WHERE TopicID IN ($placeholders)
            ", ...$ids);

            foreach ($ids as $id) {
                self::$cache->begin_transaction("thread_$id".'_info');
                self::$cache->update_row(false, ['IsLocked' => '1']);
                self::$cache->commit_transaction(3600 * 24 * 30);
                self::$cache->delete_value("thread_$id".'_catalogue_0', 3600 * 24 * 30);
                self::$cache->delete_value("thread_$id".'_info', 3600 * 24 * 30);
                $forumMan->findByThreadId($id)->addThreadNote($id, 0, 'Locked automatically by schedule');
            }

            $forumIDs = array_flip(array_flip($forumIDs));
            foreach ($forumIDs as $forumID) {
                self::$cache->delete_value("forums_$forumID");
            }
        }
        return count($ids);
    }
}
