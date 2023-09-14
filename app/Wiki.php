<?php

namespace Gazelle;

class Wiki extends BaseObject {
    final const tableName = 'wiki_articles';
    protected const CACHE_KEY = 'wiki_%d';

    public function flush(): static {
        unset($this->info);
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id));
        return $this;
    }

    public function link(): string { return sprintf('<a href="%s">%s</a>', $this->url(), display_str($this->title())); }
    public function location(): string { return 'wiki.php?action=article&id=' . $this->id; }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id);
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT w.Title      AS title,
                    w.Body          AS body,
                    w.Date          AS date,
                    w.MinClassEdit  AS min_class_edit,
                    w.MinClassRead  AS min_class_read,
                    w.Revision      AS revision,
                    w.Author        AS author_id,
                    group_concat(a.Alias) as aliases,
                    group_concat(a.UserID) as users
                FROM wiki_articles AS w
                LEFT JOIN wiki_aliases AS a ON (w.ID = a.ArticleID)
                WHERE w.ID = ?
                GROUP BY w.ID
                ", $this->id
            );
            $info['alias'] = array_combine(
                explode(',', $info['aliases']),
                array_map('intval', explode(',', $info['users']))
            );
            $saveToc = \Text::$TOC;
            \Text::$TOC = true;
            \Text::full_format($info['body'], false);
            $info['toc'] = \Text::parse_toc(0);
            \Text::$TOC = $saveToc;
            self::$cache->cache_value($key, $info, 0);
        }
        $this->info = $info;
        return $this->info;
    }

    public function revisionBody(int $revision): string {
        return $revision === $this->revision()
            ? $this->body()
            : (string)self::$db->scalar("
                SELECT Body FROM wiki_revisions WHERE ID = ? AND Revision = ?
                ", $this->id, $revision
            );
    }

    /**
     * Normalize an alias
     */
    static public function normalizeAlias(string $alias): string {
        return trim(substr(preg_replace('/[^a-z0-9]/', '', strtolower(htmlentities(trim($alias)))), 0, 50));
    }

    public function alias(): array {
        return $this->info()['alias'];
    }

    public function shortName(string $name): string {
        return shortenString($name, 20, true);
    }

    public function authorId(): int {
        return $this->info()['author_id'];
    }

    public function body(): string {
        return $this->info()['body'];
    }

    public function date(): string {
        return $this->info()['date'];
    }

    public function minClassEdit(): int {
        return $this->info()['min_class_edit'];
    }

    public function minClassRead(): int {
        return $this->info()['min_class_read'];
    }

    public function revision(): int {
        return $this->info()['revision'];
    }

    public function title(): string {
        return $this->info()['title'];
    }

    public function ToC(): string {
        return $this->info()['toc'] ?? '';
    }

    public function editable(User $user): bool {
        return $this->minClassEdit() <= $user->effectiveClass();
    }

    public function readable(User $user): bool {
        return $this->minClassRead() <= $user->effectiveClass();
    }

    /**
     * Modifiy a wiki article
     */
    public function modify(): bool {
        if (!$this->dirty()) {
            return false;
        }
        self::$db->begin_transaction();
        self::$db->prepared_query("
            INSERT INTO wiki_revisions
                  (ID, Revision, Title, Body, Author, Date)
            SELECT ID, Revision, Title, Body, Author, Date
            FROM wiki_articles
            WHERE ID = ?
            ORDER BY Revision DESC
            LIMIT 1
            ", $this->id
        );
        $revision = self::$db->scalar("
            SELECT 1 + max(Revision) FROM wiki_articles WHERE ID = ?
            ", $this->id
        );
        $this->setField('Revision', $revision);
        $success = parent::modify();
        self::$db->commit();
        return $success;
    }

    /**
     * Remove an article
     *
     * param int the article to remove
     */
    public function remove(): int {
        self::$db->begin_transaction();
        self::$db->prepared_query("DELETE FROM wiki_articles WHERE ID = ?", $this->id);
        $affected = self::$db->affected_rows();

        self::$db->prepared_query("DELETE FROM wiki_aliases WHERE ArticleID = ?", $this->id);
        self::$db->prepared_query("DELETE FROM wiki_revisions WHERE ID = ?", $this->id);
        self::$db->commit();
        self::$cache->delete_value('wiki_aliases');
        $this->flush();
        return $affected;
    }

    public function revisionList(): array {
        self::$db->prepared_query("
            SELECT Revision AS revision,
                Title       AS title,
                Author      AS author_id,
                Date        AS date
            FROM wiki_revisions
            WHERE ID = ?
            ORDER BY Revision DESC
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC, false);
    }

    /**
     * Add an alias to an existing article
     *
     * @throws DB\Mysql_DuplicateKeyException if alias already exists on another article
     */
    public function addAlias(string $alias, User $user): int {
        self::$db->prepared_query("
            INSERT INTO wiki_aliases
                   (ArticleID, Alias, UserID)
            VALUES (?,         ?,     ?)
            ", $this->id, self::normalizeAlias($alias), $user->id()
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    /**
     * Remove an alias of an article.
     *
     * @param string $alias the alias to remove
     */
    public function removeAlias(string $alias): int {
        if (!isset($this->alias()[$alias])) {
            return 0;
        }
        self::$db->prepared_query("
            DELETE FROM wiki_aliases WHERE Alias = ?
            ", $alias
        );
        $this->flush();
        return self::$db->affected_rows();
    }
}
