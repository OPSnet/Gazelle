<?php

namespace Gazelle\Manager;

class Wiki extends \Gazelle\Base {

    protected const ID_KEY = 'zz_w_%d';

    /**
     * Find a wiki article based on its id.
     *
     * @return \Gazelle\Wiki|null id of article if it exists
     */
    public function findById(int $wikiId): ?\Gazelle\Wiki {
        $key = sprintf(self::ID_KEY, $wikiId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM wiki_articles WHERE ID = ?
                ", $wikiId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 0);
            }
        }
        return $id ? new \Gazelle\Wiki($id) : null;
    }

    /**
     * Find a wiki article based on its title.
     *
     * @return \Gazelle\Wiki|null id of article if it exists
     */
    public function findByTitle(string $title): ?\Gazelle\Wiki {
        return $this->findById((int)self::$db->scalar("
            SELECT ID FROM wiki_articles WHERE Title = ?
            ", trim($title)
        ));
    }

    /**
     * Find a wiki article based on an alias
     *
     * @return \Gazelle\Wiki|null id of article if it exists
     */
    public function findByAlias(string $alias): ?\Gazelle\Wiki {
        return $this->findById((int)self::$db->scalar("
            SELECT ArticleID FROM wiki_aliases WHERE Alias = ?
            ", trim($alias)
        ));
    }

    /**
     * Create a wiki article
     *
     * @param string title
     * @param string body
     * @param int minimum class to read
     * @param int minimum class to modify
     * @param int author id
     * @return int article id
     */
    public function create(string $title, string $body, int $minRead, int $minEdit, int $userId) {
        $title = trim($title);
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO wiki_articles
                   (Title, Body, MinClassRead, MinClassEdit, Author)
            VALUES (?,     ?,    ?,            ?,            ?)
            ", $title, trim($body), $minRead, $minEdit, $userId
        );
        $article = new \Gazelle\Wiki(self::$db->inserted_id());
        $article->addAlias($title, $userId);
        self::$db->commit();
        return $article;
    }

    /**
     * Determine what the read and write access levels should be, based on the editor
     *
     * @param int can the viewer administrate the wiki
     * @param int viewer class
     * @param int the proposed read class
     * @param int the proposed edit class
     * @return array [read class, edit class, error]
     * The error entry will be non-null in case of an error and read and edit will be null.
     */
    public function configureAccess(\Gazelle\User $user, int $minRead, int $minEdit) {
        $isAdmin = $user->permitted('admin_manage_wiki');
        $class = $user->effectiveClass();

        if (!$isAdmin) {
            return [100, 100, null];
        }
        if (!$minRead) {
            return [null, null, 'read permission not set'];
        } elseif ($minRead > $class) {
            return [null, null, 'You cannot restrict views above your own level'];
        }
        if (!$minEdit) {
            return [null, null, 'edit permission not set'];
        } else {
            if ($minEdit < $minRead) {
                $minEdit = $minRead;
            }
            if ($minEdit > $class) {
                return [null, null, 'You cannot restrict edits above your own level'];
            }
        }
        return [$minRead, $minEdit, null];
    }

    public function articles(int $class, $letter): array {
        $sql = "
            SELECT ID,
                Title,
                Date,
                Author
            FROM wiki_articles
            WHERE MinClassRead <= ?
            ";
        $args = [$class];
        if (!empty($letter) && $letter !== '1') { // '1' denotes All
            $sql .= " AND LEFT(Title,1) = ?";
            $args[] = $letter;
        }
        $sql .= " ORDER BY Title";
        self::$db->prepared_query($sql, ...$args);
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }
}
