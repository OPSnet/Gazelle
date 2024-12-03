<?php

namespace Gazelle\Manager;

class Wiki extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_w_%d';

    /**
     * Create a wiki article
     */
    public function create(
        string $title,
        string $body,
        int $minRead,
        int $minEdit,
        \Gazelle\User $user
    ): \Gazelle\Wiki {
        $title = trim($title);
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO wiki_articles
                   (Title, Body, MinClassRead, MinClassEdit, Author)
            VALUES (?,     ?,    ?,            ?,            ?)
            ", $title, trim($body), $minRead, $minEdit, $user->id()
        );
        $article = new \Gazelle\Wiki(self::$db->inserted_id());
        $article->addAlias($title, $user);
        self::$db->commit();
        return $article;
    }

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
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        return $id ? new \Gazelle\Wiki($id) : null;
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
     * Determine what the read and write access levels should be, based on the editor
     *
     * @return array [read class, edit class, error]
     * The error entry will be non-null in case of an error and read and edit will be null.
     */
    public function configureAccess(\Gazelle\User $user, int $minRead, int $minEdit) {
        $isAdmin  = $user->permitted('admin_manage_wiki');
        $isEditor = $user->hasAttr('wiki-edit-readable');
        $class    = $user->privilege()->effectiveClassLevel();

        if (!($isAdmin || $isEditor)) {
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
            if ($minEdit > $class && !$isEditor) {
                return [null, null, 'You cannot restrict edits above your own level'];
            }
        }
        return [$minRead, $minEdit, null];
    }

    public function articles(int $class, string $letter = '1'): array {
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
