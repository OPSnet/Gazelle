<?php

declare(strict_types=1);

namespace Gazelle\Search\Comment;

use Gazelle\User;

class Request extends AbstractComment {
    protected string $join;

    public function __construct(
        public readonly string $filter,
        public readonly bool   $ownProfile,
        public readonly User   $user,
        public readonly int    $argOffset,
    ) {}

    public function table(): string {
        return 'request src';
    }

    public function idField(): string {
        return 'id_request';
    }

    public function nameField(): string {
        return 'src.title';
    }

    public function condition(): array {
        if ($this->filter === 'voted') {
            $this->join = 'inner join request_vote rv using (id_request)';
        }
        $placeholder = '$' . $this->argOffset;
        return match ($this->filter) {
            'created' => ["src.id_user = $placeholder and c.\"AuthorID\" != $placeholder"],
            'voted'   => ["rv.id_user = $placeholder and c.\"AuthorID\" != $placeholder"],
            default   => ["c.\"AuthorID\" = $placeholder"],
        };
    }

    public function args(): array {
        return [$this->user->id];
    }

    public function title(): string {
        return match ($this->filter) {
            'created' => 'Comments on requests ' . ($this->ownProfile ? 'you' : 'they') . ' created',
            'voted'   => 'Comments on requests ' . ($this->ownProfile ? 'you' : 'they') . ' voted on',
            default   => 'Request comments',
        };
    }

    public function linkList(): array {
        return match ($this->filter) {
            'created' => [
                [
                    'link'  => '',
                    'title' => 'Request comments'
                ],
                [
                    'link'  => '&amp;type=contributed',
                    'title' => 'Comments on requests ' . ($this->ownProfile ? 'you' : 'they') . ' voted on',
                ],
            ],
            'voted' => [
                [
                    'link'  => '',
                    'title' => 'Request comments'
                ],
                [
                    'link'  => '&amp;type=created',
                    'title' => 'Comments on requests ' . ($this->ownProfile ? 'you' : 'they') . ' created',
                ],
            ],
            default => [
                [
                    'link'  => '&amp;type=created',
                    'title' => 'Comments on requests ' . ($this->ownProfile ? 'you' : 'they') . ' created',
                ],
                [
                    'link'  => '&amp;type=contributed',
                    'title' => 'Comments on requests ' . ($this->ownProfile ? 'you' : 'they') . ' voted on',
                ],
            ],
        };
    }
}
