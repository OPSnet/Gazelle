<?php

namespace Gazelle\User;

class Stylesheet extends \Gazelle\BaseUser {
    protected const CACHE_KEY = 'u_ss2_%d';
    protected array $info;

    public function flush(): Stylesheet {
        self::$cache->delete_value(sprintf(self::CACHE_KEY, $this->user->id()));
        return $this;
    }
    public function link(): string { return $this->user()->link(); }
    public function location(): string { return $this->user()->location(); }
    public function tableName(): string { return ''; }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $key = sprintf(self::CACHE_KEY, $this->user->id());
        $info = self::$cache->get_value($key);
        if ($info === false) {
            $info = self::$db->rowAssoc("
                SELECT s.ID                          AS style_id,
                    s.Name                           AS name,
                    lower(replace(s.Name, ' ', '_')) AS css_name,
                    ui.StyleURL                      AS style_url,
                    s.theme
                FROM stylesheets s
                INNER JOIN users_info ui ON (ui.StyleID = s.ID)
                WHERE ui.UserID = ?
                ", $this->user->id()
            );
            self::$cache->cache_value($key, $info, 0);
        }
        $this->info = $info;
        return $info;
    }

    public function modifyInfo(int $stylesheetId, ?string $stylesheetUrl): int {
        self::$db->prepared_query("
            UPDATE users_info SET
                StyleID = ?,
                StyleURL = ?
            WHERE UserID = ?
            ", $stylesheetId, empty($stylesheetUrl) ? null : trim($stylesheetUrl),
                $this->user->id()
        );
        $this->flush();
        return self::$db->affected_rows();
    }

    public function cssName(): string {
        return $this->info()['css_name'];
    }

    public function imagePath(): string {
        return STATIC_SERVER . '/styles/' . $this->cssName() . '/images/';
    }

    public function name(): string {
        return $this->info()['style_url'] ? 'External CSS' :  $this->info()['name'];
    }

    public function styleId(): int {
        return $this->info()['style_id'];
    }

    public function theme(): string {
        return $this->info()['theme'];
    }

    public function cssUrl(): string {
        $url = $this->info()['style_url'];
        if (empty($url)) {
            return STATIC_SERVER . '/styles/' . $this->cssName() . '/style.css?v='
                . base_convert(filemtime(SERVER_ROOT . '/sass/' . preg_replace('/\.css$/', '.scss', $this->cssName())), 10, 36);
        }
        $info = parse_url($url);
        if (str_ends_with($info['path'], '.css')
                && (($info['query'] ?? '') . ($info['fragment'] ?? '')) === ''
                && $info['host'] === SITE_HOST
                && file_exists(SERVER_ROOT . $info['path'])) {
            $url .= '?v=' . filemtime(SERVER_ROOT . "/sass/{$info['path']}");
        }
        return $url;
    }
}
