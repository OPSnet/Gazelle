<?php

namespace Gazelle;

/**
 * Classes that inherit from this class acquire the ability to
 * store and use attributes, which are best described as boolean
 * flags. This helps to avoid adding endless boolean columns to
 * the principal table.
 * As a prerequisite, there must exist <class>_attr and
 * <class>_has_attr table in the database.
 */

abstract class BaseAttrObject extends BaseObject {
    /**
     * Note:
     * The naming of attribute columns in Mysql is a bit of a mess. The early
     * tables were created at a time when the framework was much less
     * developed and this present factorization was a distant possibility.
     *
     * The right approach to naming is shown in the artist_attr and
     * artist_has_attr tables, since all the column names can be derived
     * trivially from the stem 'artist'. For the older tables (collage, user
     * and torrent group), a bit more string munging trickery is required,
     * so for the time being, the clearest approach is simply to define the
     * names in full as constants.
     *
     * In time, this blight will disappear when everything is fully
     * migrated to Postgresql.
     */
    // these must be overridden in the concrete class
    protected const ObjectName   = 'unknown';
    protected const PkColumn     = 'unknown';
    protected const JoinColumn   = 'unknown';
    protected const ObjectColumn = 'unknown';

    protected array $attr;

    protected function attrCacheKey(): string {
        return sprintf('attr_%s_%d', static::ObjectName, $this->id);
    }

    public function flush(): static {
        self::$cache->delete_value($this->attrCacheKey());
        unset($this->attr);
        return $this;
    }

    public function attr(): array {
        if (isset($this->attr)) {
            return $this->attr;
        }
        $key  = $this->attrCacheKey();
        $attr = self::$cache->get_value($key);
        if ($attr === false) {
            // SELECT a.ID AS id, a.name AS name
            // FROM user_attr a
            // INNER JOIN user_has_attr ha ON (ha.UserAttrID = a.ID)
            // WHERE ha.UserID = ?
            self::$db->prepared_query("
                SELECT a." . static::PkColumn . " AS id, a.name AS name FROM "
                . static::ObjectName . "_attr a INNER JOIN "
                . static::ObjectName . "_has_attr ha ON (ha."
                . static::JoinColumn . ' = a.' . static::PkColumn
                . ") WHERE ha." . static::ObjectColumn . " = ?
                ", $this->id
            );
            $attr = self::$db->to_pair('name', 'id');
            self::$cache->cache_value($key, $attr, 7200);
        }
        $this->attr = $attr;
        return $attr;
    }

    public function attrList(): array {
        // I apologize in advance for this. It is doing this:
        // WITH ha as (select UserAttrID from user_has_attr where UserID = 1)
        // SELECT a.ID, a.name, if(u.UserAttrID is null, 0, 1) as active
        // FROM user_attr a
        // LEFT JOIN u on (u.UserAttrID = a.ID)
        // ORDER BY a.description;
        self::$db->prepared_query("
            WITH ha AS (SELECT "
            . static::JoinColumn . " FROM "
            . static::ObjectName . "_has_attr WHERE "
            . static::ObjectColumn . " = ?) SELECT a."
            . static::PkColumn . " AS id, a.name AS name, a.description, if(ha."
            . static::JoinColumn . " IS NULL, 0, 1) AS active FROM "
            . static::ObjectName . "_attr a LEFT JOIN ha ON (ha."
            . static::JoinColumn . ' = a.'
            . static::PkColumn . ") ORDER BY a.description
            ", $this->id
        );
        return self::$db->to_array(false, MYSQLI_ASSOC);
    }

    public function hasAttr(string $name): bool {
        return isset($this->attr()[$name]);
    }

    public function toggleAttr(string $attr, bool $enable): bool {
        $hasAttr = $this->hasAttr($attr);
        $toggled = false;
        if (!$enable && $hasAttr) {
            self::$db->prepared_query("
                DELETE FROM " . static::ObjectName . "_has_attr WHERE "
                . static::ObjectColumn . " = ? AND " . static::JoinColumn
                . " = (SELECT " . static::PkColumn . " FROM "
                . static::ObjectName . "_attr WHERE name = ?)
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
            // shim during migration
            if (static::ObjectName === 'torrent_group') {
                self::$db->prepared_query("
                    DELETE FROM tgroup_has_attr
                    WHERE TGroupID = ? AND TGroupAttrID = (
                        SELECT TGroupAttrID FROM torrent_group_attr WHERE Name = ?
                    )
                    ", $this->id, $attr
                );
                $this->pg()->query("
                    delete from tgroup_has_attr
                    where id_tgroup = $1 and id_tgroup_attr = (
                        select id_tgroup_attr from tgroup_attr where name = $2
                    )
                    ", $this->id, $attr
                );
            }
        } elseif ($enable && !$hasAttr) {
            self::$db->prepared_query("
                INSERT INTO " . static::ObjectName . "_has_attr ("
                . static::ObjectColumn . ", " . static::JoinColumn
                . ") SELECT ?, " . static::PkColumn . " FROM "
                . static::ObjectName . "_attr WHERE name = ?
                ", $this->id, $attr
            );
            $toggled = self::$db->affected_rows() === 1;
            // shim during migration
            if (static::ObjectName === 'torrent_group') {
                self::$db->prepared_query("
                    INSERT INTO tgroup_has_attr (TGroupID, TGroupAttrID)
                    SELECT ?, ID FROM tgroup_attr WHERE Name = ?
                    ", $this->id, $attr
                );
                $this->pg()->query("
                    insert into tgroup_has_attr (id_tgroup, id_tgroup_attr)
                    select $1, id_tgroup_attr from tgroup_attr where name = $2
                    ", $this->id, $attr
                );
            }
        }
        if ($toggled) {
            $this->flush();
        }
        return $toggled;
    }

    public function remove(): int {
        self::$db->prepared_query("
            DELETE FROM " . static::ObjectName . "_has_attr WHERE "
                . static::ObjectColumn . " = ?",
                $this->id
        );
        $affected = self::$db->affected_rows();
        $affected += parent::remove(); // BaseObject
        $this->flush();
        return $affected;
    }
}
