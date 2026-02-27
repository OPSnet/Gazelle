<?php

declare(strict_types=1);

namespace Gazelle\Search\Comment;

use Gazelle\User;

class TGroup extends AbstractComment {
    public function __construct(
        public readonly string $filter,
        public readonly bool   $ownProfile,
        public readonly User   $user,
        public readonly int    $argOffset,
    ) {}

    public function table(): string {
        return 'relay.torrents_group src';
    }

    public function idField(): string {
        return '"ID"';
    }

    public function nameField(): string {
        return 'src."Name"';
    }

    public function condition(): array {
        if ($this->filter === 'uploaded') {
            $this->join = "inner join relay.torrents t on (t.\"GroupID\" = src.\"ID\")";
        }
        $placeholder = '$' . $this->argOffset;
        return match ($this->filter) {
            'uploaded' => [
                "t.\"UserID\" = $placeholder and c.\"AuthorID\" != $placeholder",
                'c."AddedTime" >= t.created',
            ],
            default => ["c.\"AuthorID\" = $placeholder"],
        };
    }

    public function args(): array {
        return [$this->user->id];
    }

    public function title(): string {
        return $this->filter === 'uploaded'
            ? 'Comments on ' . ($this->ownProfile ? 'your' : 'their') . ' uploads'
            : 'Torrent comments';
    }

    public function linkList(): array {
        return match ($this->filter) {
            'uploaded' => [
                [
                    'link'  => '',
                    'title' => 'Torrent comments'
                ],
            ],
            default => [
                [
                    'link'  => '&amp;type=uploaded',
                    'title' => 'Comments on ' . ($this->ownProfile ? 'your' : 'their') . ' uploads'
                ],
            ],
        };
    }
}
