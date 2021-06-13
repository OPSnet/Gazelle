<?php

namespace Gazelle\Manager;

class Wiki extends \Gazelle\Base {
    protected $aliases;
    protected const CACHE_KEY = 'wiki_article_v3_%d';

    /**
     * Find a wiki article based on its title.
     *
     * @param string Title
     * @return int|null id of article if it exists
     */
    public function findByTitle(string $title): ?int {
        return $this->db->scalar("
            SELECT ID
            FROM wiki_articles
            WHERE Title = ?
            ", trim($title)
        );
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
        $this->db->begin_transaction();
        $this->db->prepared_query("
            INSERT INTO wiki_articles
                   (Title, Body, MinClassRead, MinClassEdit, Author)
            VALUES (?,     ?,    ?,            ?,            ?)
            ", $title, trim($body), $minRead, $minEdit, $userId
        );
        $articleId = $this->db->inserted_id();
        $alias = $this->normalizeAlias($title);
        if ($alias && !$this->alias($alias)) {
            $this->addAlias($articleId, $alias, $userId);
        }
        $this->db->commit();
        $this->flushArticle($articleId);
        return $articleId;
    }

    /**
     * Modifiy a wiki article
     *
     * @param int article id
     * @param string title
     * @param string body
     * @param int minimum class to read
     * @param int minimum class to modify
     * @param int author id
     * @return int article id
     */
    public function modify(int $articleId, string $title, string $body, int $minRead, int $minEdit, int $userId) {
        $this->db->begin_transaction();
        $this->db->prepared_query("
            INSERT INTO wiki_revisions
                  (ID, Revision, Title, Body, Author, Date)
            SELECT ID, Revision, Title, Body, Author, Date
            FROM wiki_articles
            WHERE ID = ?
            ORDER BY Revision DESC
            LIMIT 1
            ", $articleId
        );
        $this->db->prepared_query("
            UPDATE wiki_articles SET
                Date = now(),
                Title = ?,
                Body = ?,
                MinClassRead = ?,
                MinClassEdit = ?,
                Author = ?,
                Revision = 1 + (SELECT max(Revision) FROM wiki_articles WHERE ID = ?)
            WHERE ID = ?
            ", trim($title), trim($body), $minRead, $minEdit, $userId, $articleId,
                $articleId
        );
        $this->db->commit();
        return $this->flushArticle($articleId);
    }

    /**
     * Remove an article
     *
     * param int the article to remove
     */
    public function remove(int $articleId) {
        $this->db->begin_transaction();
        $this->db->prepared_query("DELETE FROM wiki_articles WHERE ID = ?", $articleId);
        $this->db->prepared_query("DELETE FROM wiki_aliases WHERE ArticleID = ?", $articleId);
        $this->db->prepared_query("DELETE FROM wiki_revisions WHERE ID = ?", $articleId);
        $this->db->commit();
        return $this->flushArticle($articleId);
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
    public function configureAccess(int $isAdmin, int $class, int $minRead, int $minEdit) {
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

    /**
     * Normalize an alias
     * @param string $str
     * @return string
     */
    public function normalizeAlias(string $alias): string {
        return trim(substr(preg_replace('/[^a-z0-9]/', '', strtolower(htmlentities(trim($alias)))), 0, 50));
    }

    /**
     * Get all aliases in an associative array of Alias => ArticleID
     * @return array
     */
    public function aliasList(): array {
        $this->aliases = $this->cache->get_value('wiki_aliases');
        if (!$this->aliases) {
            $qid = $this->db->get_query_id();
            $this->db->prepared_query("
                SELECT Alias, ArticleID FROM wiki_aliases
            ");
            $this->aliases = [];
            while ([$alias, $articleId] = $this->db->next_row()) {
                $this->aliases[$alias] = (int)$articleId;
            }
            $this->db->set_query_id($qid);
            $this->cache->cache_value('wiki_aliases', $this->aliases, 3600 * 24 * 14); // 2 weeks
        }
        return $this->aliases;
    }

    /**
     * Flush the alias cache. Call this whenever you touch the wiki_aliases table.
     */
    public function flush() {
        $this->cache->delete_value('wiki_aliases');
        return $this;
    }

    /**
     * Get the article an alias points to
     *
     * @param string $alias
     * @return int|null
     */
    public function alias(string $alias): ?int {
        $aliases = $this->aliasList();
        return $aliases[$this->normalizeAlias($alias)] ?? null;
    }

    /**
     * Get an article
     * @param int $articleId
     * @throws Gazelle\Exception\ResourceNotFoundException
     * @return array
     */
    public function article(int $articleId): array {
        $key = sprintf(self::CACHE_KEY, $articleId);
        $contents = $this->cache->get_value($key);
        if (!$contents) {
            $qid = $this->db->get_query_id();
            $contents = $this->db->row("
                SELECT w.Revision,
                    w.Title,
                    w.Body,
                    w.MinClassRead,
                    w.MinClassEdit,
                    w.Date,
                    w.Author,
                    GROUP_CONCAT(a.Alias) as aliases,
                    GROUP_CONCAT(a.UserID) as users
                FROM wiki_articles AS w
                LEFT JOIN wiki_aliases AS a ON (w.ID = a.ArticleID)
                LEFT JOIN users_main AS u ON (u.ID = w.Author)
                WHERE w.ID = ?
                GROUP BY w.ID
                ", $articleId
            );
            if (empty($contents)) {
                throw new \Gazelle\Exception\ResourceNotFoundException($articleId);
            }
            $this->db->set_query_id($qid);
            $this->cache->cache_value($key, $contents, 3600 * 24 * 14); // 2 weeks
        }
        return $contents;
    }

    /**
     * Get the list of wiki articles
     *
     * @param int class of viewer, to return only the articles they are allowed to read
     * @param string first letter of articles to see, or 'All' for all
     * @return array [id, title, date, authorId]
     */
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
        $this->db->prepared_query($sql, ...$args);
        return $this->db->to_array(false, MYSQLI_ASSOC);
    }

    public function revisions(int $articleId) {
        $this->db->prepared_query("
            SELECT Revision,
                Title,
                Author,
                Date
            FROM wiki_revisions
            WHERE ID = ?
            ORDER BY Revision DESC
            ", $articleId
        );
        return $this->db;
    }

    /**
     * Flush an article's cache. Call this whenever you edited a wiki article or its aliases.
     * @param int $articleId
     */
    public function flushArticle(int $articleId) {
        $this->cache->delete_value(sprintf(self::CACHE_KEY, $articleId));
        return $this->flush();
    }

    /**
     * Can the viewer edit this article?
     * NB: currently there is no equivalent readAllowed() method.
     *     Further restructuring is necessary before that makes sense.
     *
     * @param int article id
     * @param int viewer class
     * @return bool viewer can edit
     */
    public function editAllowed(int $articleId, int $class): bool {
        return $class >= $this->db->scalar("
            SELECT MinClassEdit FROM wiki_articles WHERE ID = ?
            ", $articleId
        );
    }

    /**
     * Add an alias to an existing article
     *
     * @param int article id
     * @param string alias
     * @param int user id of the person adding the alias
     */
    public function addAlias(int $articleId, string $alias, int $userId) {
        $this->db->prepared_query("
            INSERT INTO wiki_aliases
                   (ArticleID, Alias, UserID)
            VALUES (?,         ?,     ?)
            ", $articleId, $this->normalizeAlias($alias), $userId
        );
        return $this->flushArticle($articleId);
    }

    /**
     * Remove an alias of an article.
     *
     * @param string the alias to remove
     */
    public function removeAlias(string $alias) {
        $articleId = $this->alias($alias);
        if (!$articleId) {
            return $this;
        }
        $this->db->prepared_query("
            DELETE FROM wiki_aliases WHERE Alias = ?
            ", $this->normalizeAlias(trim($alias))
        );
        return $this->flushArticle($articleId);
    }
}
