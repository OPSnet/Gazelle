<?php

namespace Gazelle\Manager;

class Forum extends \Gazelle\BaseManager {
    protected const CACHE_TOC_MAIN   = 'forum_toc_main';
    protected const CACHE_LIST       = 'forum_list';
    protected const CACHE_TRANSITION = 'forum_transition';
    protected const ID_KEY           = 'zz_f_%d';
    protected const ID_THREAD_KEY    = 'zz_ft_%d';
    protected const ID_POST_KEY      = 'zz_fp_%d';

    /**
     * Create a forum
     */
    public function create(
        \Gazelle\User $user,
        int $sequence,
        int $categoryId,
        string $name,
        string $description,
        int $minClassRead,
        int $minClassWrite,
        int $minClassCreate,
        bool $autoLock,
        int $autoLockWeeks,
    ): \Gazelle\Forum {
        self::$db->prepared_query("
            INSERT INTO forums
                   (Sort, CategoryID, Name, Description, MinClassRead, MinClassWrite, MinClassCreate, AutoLock, AutoLockWeeks, LastPostAuthorID)
            VALUES (?,    ?,          ?,    ?,           ?,            ?,             ?,              ?,        ?,             ?)
            ", $sequence, $categoryId, trim($name), trim($description), $minClassRead, $minClassWrite, $minClassCreate,
                $autoLock ? '1' : '0', $autoLockWeeks, $user->id()
        );
        $id = self::$db->inserted_id();
        $this->flushToc();
        return new \Gazelle\Forum($id);
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
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Forum($id) : null;
    }

    /**
     * Get list of forum names
     */
    public function nameList(): array {
        self::$db->prepared_query("
            SELECT ID AS id, Name FROM forums ORDER BY Sort
        ");
        return self::$db->to_array('id', MYSQLI_ASSOC, false);
    }

    public function forumList(): array {
        $list = self::$cache->get_value(self::CACHE_LIST);
        if ($list === false) {
            self::$db->prepared_query("
                SELECT f.ID
                FROM forums f
                INNER JOIN forums_categories cat ON (cat.ID = f.CategoryID)
                ORDER BY cat.Sort, cat.Name, f.Sort, f.Name
            ");
            $list = self::$db->collect('ID', false);
            self::$cache->cache_value(self::CACHE_LIST, $list, 86400);
        }
        return $list;
    }

    /**
     * The forum table of contents (the main /forums.php view)
     *
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
    public function tableOfContentsMain(): array {
        $toc = self::$cache->get_value(self::CACHE_TOC_MAIN);
        if ($toc === false ) {
            self::$db->prepared_query("
                SELECT cat.Name AS categoryName, cat.ID AS categoryId,
                    f.ID, f.Name, f.Description, f.NumTopics, f.NumPosts,
                    f.LastPostTopicID, f.MinClassRead, f.MinClassWrite, f.MinClassCreate,
                    f.Sort, f.AutoLock, f.AutoLockWeeks,
                    ft.Title, ft.LastPostAuthorID, ft.LastPostID, ft.LastPostTime, ft.IsSticky, ft.IsLocked,
                    (fp.TopicID IS NOT NULL AND fp.Closed = '0') AS has_poll
                FROM forums f
                INNER JOIN forums_categories cat ON (cat.ID = f.CategoryID)
                LEFT JOIN forums_topics ft ON (ft.ID = f.LastPostTopicID)
                LEFT JOIN forums_polls fp ON (fp.TopicID = ft.ID)
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
            self::$cache->cache_value(self::CACHE_TOC_MAIN, $toc, 86400 * 10);
        }
        return $toc;
    }

    public function tableOfContents(\Gazelle\User $user): array {
        $toc = $this->tableOfContentsMain();
        $userToc = [];
        foreach ($toc as $category => $forumList) {
            $seen = 0;
            foreach ($forumList as $f) {
                $forum = $this->findById($f['ID']);
                if (!$user->readAccess($forum)) {
                    continue;
                }
                $autosubList  = $forum->autoSubscribeForUserList($user);
                $userLastRead = $forum->userLastRead($user);
                if (isset($userLastRead[$f['LastPostTopicID']])) {
                    $isRead       = true;
                    $lastReadPage = (int)$userLastRead[$f['LastPostTopicID']]['Page'];
                    $lastReadPost = $userLastRead[$f['LastPostTopicID']]['PostID'];
                    $catchup      = $userLastRead[$f['LastPostTopicID']]['PostID'] >= $f['LastPostID']
                        || $user->forumCatchupEpoch() >= strtotime($f['LastPostTime']);
                } else {
                    $isRead       = false;
                    $lastReadPage = null;
                    $lastReadPost = null;
                    $catchup      = $f['LastPostTime'] && $user->forumCatchupEpoch() >= strtotime($f['LastPostTime']);
                }

                if (!isset($toc[$category])) {
                    $userToc[$category] = [];
                }
                $userToc[$category][] = [
                    'autosub'          => in_array($f['ID'], $autosubList),
                    'creator'          => $f['MinClassCreate'] <= $user->classLevel(),
                    'category'         => $category,
                    'category_id'      => $f['categoryId'],
                    'cut_title'        => shortenString($f['Title'] ?? '', 50, true),
                    'description'      => $f['ID'] == DONOR_FORUM
                        ? DONOR_FORUM_DESCRIPTION[random_int(0, count(DONOR_FORUM_DESCRIPTION) - 1)]
                        : $f['Description'],
                    'forum'            => $forum,
                    'forum_id'         => $f['ID'],
                    'icon_class'       => (($f['IsLocked'] && !$f['IsSticky']) || $catchup ? 'read' : 'unread')
                        . ($f['IsLocked'] ? '_locked' : '')
                        . ($f['IsSticky'] ? '_sticky' : ''),
                    'id'               => $f['LastPostTopicID'],
                    'is_read'          => $isRead,
                    'has_poll'         => $f['has_poll'],
                    'last_post_time'   => $f['LastPostTime'],
                    'last_post_user'   => $f['LastPostAuthorID'],
                    'name'             => $f['Name'],
                    'num_posts'        => $f['NumPosts'],
                    'num_topics'       => $f['NumTopics'],
                    'threads'          => $f['NumPosts'] > 0,
                    'title'            => $f['Title'],
                    'tooltip'          => $f['ID'] == DONOR_FORUM ? 'tooltip_gold' : 'tooltip',
                    'first'            => (++$seen == 1), // implies <table> needs to be emitted
                    'last_read_page'   => $lastReadPage,
                    'last_read_post'   => $lastReadPost,
                ];
            }
        }
        return $userToc;
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

    public function forumTransitionList(\Gazelle\User $user): array {
        $info = [];
        $items = self::$cache->get_value(self::CACHE_TRANSITION);
        if (!$items) {
            $queryId = self::$db->get_query_id();
            self::$db->prepared_query("
                SELECT forums_transitions_id AS id, source, destination, label, permission_levels,
                       permission_class, permissions, user_ids
                FROM forums_transitions
            ");
            $items = self::$db->to_array('id', MYSQLI_ASSOC, false);
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
        $info['ExtraClasses']    = array_keys((new \Gazelle\User\Privilege($user))->secondaryClassList());
        $info['Permissions']     = array_keys($user->info()['Permission']);
        $info['ExtraClassesOff'] = array_flip(array_map(fn ($i) => -$i, $info['ExtraClasses']));
        $info['PermissionsOff']  = array_flip(array_map(fn ($i) => "-$i", array_keys($user->info()['Permission'])));

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
            fn ($t) => $t['source'] === $forumId
        );
    }

    public function flushToc(): static {
        self::$cache->delete_multi([
            self::CACHE_TOC_MAIN,
            self::CACHE_LIST,
        ]);
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
        return (int)self::$db->scalar("
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
        return (int)self::$db->scalar("
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
}
