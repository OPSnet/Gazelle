<?php

namespace Gazelle\User;

class Stylesheet extends \Gazelle\BaseUser {
    final public const tableName     = '';
    protected const CACHE_KEY = 'u_ss2_%d';

    public function flush(): static {
        unset($this->info);
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->id()));
        return $this;
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->id());
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT s.ID                          AS style_id,
                    s.Name                           AS name,
                    lower(replace(s.Name, ' ', '_')) AS css_name,
                    um.stylesheet_url                AS style_url,
                    s.theme
                FROM stylesheets s
                INNER JOIN users_main um ON (um.stylesheet_id = s.ID)
                WHERE um.ID = ?
                ", $this->id()
            );
            self::$cache->cache_value($key, $info, 0);
        }
        $this->info = $info;
        return $info;
    }

    public function modifyInfo(int $stylesheetId, ?string $stylesheetUrl): int {
        self::$db->prepared_query("
            UPDATE users_main SET
                stylesheet_id = ?,
                stylesheet_url = ?
            WHERE ID = ?
            ", $stylesheetId, empty($stylesheetUrl) ? null : trim($stylesheetUrl),
                $this->id()
        );
        $affected = self::$db->affected_rows();
        $this->flush();
        return $affected;
    }

    public function cssName(): string {
        return $this->info()['css_name'];
    }

    public function imagePath(): string {
        return STATIC_SERVER . '/styles/' . $this->cssName() . '/images/';
    }

    public function name(): string {
        return $this->styleUrl() ? 'External CSS' :  $this->info()['name'];
    }

    public function styleId(): int {
        return $this->info()['style_id'];
    }

    public function styleUrl(): ?string {
        return $this->info()['style_url'];
    }

    public function theme(): string {
        return $this->info()['theme'];
    }

    public function cssUrl(): string {
        $url = $this->styleUrl();
        if (empty($url)) {
            return STATIC_SERVER . '/styles/' . $this->cssName() . '/style.css?v='
                . base_convert((string)filemtime(SERVER_ROOT . '/sass/' . preg_replace('/\.css$/', '.scss', $this->cssName())), 10, 36);
        }
        $info = parse_url($url);
        if (
            str_ends_with($info['path'] ?? '', '.css')
                && (($info['query'] ?? '') . ($info['fragment'] ?? '')) === ''
                && ($info['host'] ?? '') === SITE_HOST
                && file_exists(SERVER_ROOT . $info['path'])
        ) {
            $url .= '?v=' . filemtime(SERVER_ROOT . "/sass/{$info['path']}");
        }
        return $url;
    }
}
