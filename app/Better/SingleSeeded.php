<?php

declare(strict_types=1);

namespace Gazelle\Better;

use Gazelle\Tag;

class SingleSeeded extends AbstractBetter {
    /** @var array<Tag> $tagIncludeList */
    protected array $tagIncludeList = [];
    /** @var array<Tag> $tagExcludeList */
    protected array $tagExcludeList = [];
    protected string $bssTable;
    protected int $uploaderId;

    public function mode(): string {
        return 'torrent';
    }

    public function heading(): string {
        return 'FLAC releases with a single seeder';
    }

    #[\Override]
    public function tagIncludeList(): array {
        return $this->tagIncludeList;
    }

    #[\Override]
    public function tagExcludeList(): array {
        return $this->tagExcludeList;
    }

    /** @param array<Tag> $tagList */
    #[\Override]
    public function setTagIncludeList(array $tagList): static {
        if ($tagList !== []) {
            $this->tagIncludeList = $tagList;
        }
        return $this;
    }

    /** @param array<Tag> $tagList */
    #[\Override]
    public function setTagExcludeList(array $tagList): static {
        if ($tagList !== []) {
            $this->tagExcludeList = $tagList;
        }
        return $this;
    }

    public function setUploader(\Gazelle\User $user): static {
        $this->uploaderId = $user->id;
        return $this;
    }

    public function configure(): void {
        /* The performance of filtering out on the Mysql side is abysmal.
         * To achieve decent performance, the entire list of single-seeded
         * torrents is fetched from Mysql and stored in a temporary table.
         * An added complication is that we want torrent ids, but tags are
         * attached to the torrent group, so both columns are required.
         * The same table is used for both total() and page() methods.
         */
        if (!isset($this->bssTable)) {
            $this->bssTable = 'better_single_seeded_' .  getmypid() . '_' . randomString(6);
            $this->pg()->query("
                create temporary table {$this->bssTable} (
                    id_torrent int not null primary key,
                    id_tgroup  int not null
                )
            ");
            $cond = [
                't."Format" = $1',
                'tls."Seeders" = $2',
            ];
            $args = [
                'FLAC',
                1,
            ];
            if (isset($this->uploaderId)) {
                $cond[] = "t.\"UserID\" = {$this->pg()->placeholder(count($args))}";
                $args[] = $this->uploaderId;
            }
            if (isset($this->search)) {
                $cond[] = "tg.\"Name\" ~~* ('%' || {$this->pg()->placeholder(count($args))} || '%')";
                $args[] = $this->search;
            }
            $where = implode(' and ', $cond);
            $affected = $this->pg()->query($sql = <<<END_SQL
                insert into {$this->bssTable}
                select t."ID" as id_torrent, tg."ID" as id_tgroup
                from relay.torrents                   t
                inner join relay.torrents_group       tg  on (tg."ID" = t."GroupID")
                inner join relay.torrents_leech_stats tls on (tls."TorrentID" = t."ID")
                where $where
                END_SQL, ...$args
            );

            if ($affected > 40000) {
                $this->pg()->query("
                    create index on {$this->bssTable} (id_tgroup)
                ");
            }
        }
        $this->baseQuery = "select bss.id_torrent from {$this->bssTable} bss";
        foreach ($this->tagIncludeList as $tag) {
            $this->where[] = "exists (select 1 from tgroup_tag tt where tt.id_tgroup = bss.id_tgroup and tt.id_tag = {$this->pg()->placeholder(count($this->args))})";
            $this->args[] = $tag->id;
        }
        $excludeList = array_unique(
            array_merge(
                array_map(fn ($t) => $t->id, $this->tagExcludeList),
                HIDDEN_TAGS,
            )
        );
        if ($excludeList) { /** @phpstan-ignore-line if.alwaysTrue depending on HIDDEN_TAGS */
            $placeholder = $this->pg()->placeholder(count($this->args));
            $this->where[] = "not exists (select 1 from tgroup_tag tt where tt.id_tgroup = bss.id_tgroup and tt.id_tag = any($placeholder))";
            $this->args[] = $this->pg()->integerConverter()->output($excludeList);
        }
        $this->orderBy = "order by random()";
    }

    #[\Override]
    public function totalSql(): string {
        $this->build();
        $sql = "select count(*) from {$this->bssTable} bss";
        if ($this->where !== []) {
            $sql .= " where " . implode(' and ', $this->where);
        }
        return $sql;
    }

    #[\Override]
    public function pageSql(int $limit, int $offset): string {
        $this->build();
        $phLimit = $this->pg()->placeholder(count($this->args));
        $this->args[] = $limit;
        $phOffset = $this->pg()->placeholder(count($this->args));
        $this->args[] = $offset;
        $sql = "select id_torrent from {$this->bssTable} bss";
        if ($this->where !== []) {
            $sql .= " where " . implode(' and ', $this->where);
        }
        $sql .= " {$this->orderBy} limit $phLimit offset $phOffset";
        return $sql;
    }
}
