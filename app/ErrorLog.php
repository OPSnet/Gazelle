<?php

namespace Gazelle;

class ErrorLog extends BasePgObject {
    final public const tableName = 'error_log';
    final public const pkName    = 'error_log_id';

    public function flush(): static {
        unset($this->info);
        return $this;
    }

    public function link(): string {
        return "<a href=\"{$this->url()}\">Case #{$this->id}</a>";
    }

    public function location(): string {
        return "tools.php?action=analysis&case={$this->id}";
    }

    public function info(): array {
        if (isset($this->info)) {
            return $this->info;
        }
        $info = $this->pg()->rowAssoc("
            select id_error_log,
                id_user,
                duration,
                memory,
                nr_query,
                nr_cache,
                seen,
                created,
                updated,
                uri,
                trace,
                request,
                encode(digest, 'base64') as digest,
                error_list
            from error_log
            where id_error_log = ?
            ", $this->id
        );
        $info['trace']      = explode("\n", $info['trace']);
        $info['request']    = json_decode($info['request'], true);
        $info['error_list'] = json_decode($info['error_list'], true);
        $this->info = $info;
        return $this->info;
    }

    public function created(): string {
        return $this->info()['created'];
    }

    public function digest(): string {
        return $this->info()['digest'];
    }

    public function duration(): float {
        return $this->info()['duration'];
    }

    public function errorList(): array {
        return $this->info()['error_list'];
    }

    public function memory(): int {
        return $this->info()['memory'];
    }

    public function nrCache(): int {
        return $this->info()['nr_cache'];
    }

    public function nrQuery(): int {
        return $this->info()['nr_query'];
    }

    public function request(): array {
        return $this->info()['request'];
    }

    public function seen(): int {
        return $this->info()['seen'];
    }

    public function trace(): array {
        return $this->info()['trace'];
    }

    public function updated(): string {
        return $this->info()['updated'];
    }

    public function uri(): string {
        return $this->info()['uri'];
    }

    public function userId(): ?int {
        return $this->info()['id_user'];
    }

    public function remove(): int {
        $affected = $this->pg()->prepared_query("
            delete from error_log where id_error_log = ?
            ", $this->id
        );
        $this->flush();
        return $affected;
    }
}
