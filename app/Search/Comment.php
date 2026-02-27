<?php

namespace Gazelle\Search;

use Gazelle\Enum\CommentType;
use Gazelle\User;

class Comment extends \Gazelle\Base {
    protected Comment\AbstractComment $type;

    public function __construct(
        public readonly CommentType $commentType,
        public readonly string      $filter,
        public readonly bool        $ownProfile,
        protected User              $user,
    ) {
        if (!isset($this->type)) {
            $className = "\\Gazelle\\Search\\Comment\\"
                . match ($this->commentType) {
                    CommentType::artist  => 'Artist',
                    CommentType::collage => 'Collage',
                    CommentType::request => 'Request',
                    CommentType::tgroup  => 'TGroup',
                };
            $this->type = new $className(
                $this->filter,
                $this->ownProfile,
                $this->user,
                2, // step past the c.\"Page\" = $1 placeholder
            );
        }
    }

    public function linkList(): array {
        return $this->type->linkList();
    }

    public function title(): string {
        return $this->type->title();
    }

    public function totalSql(): string {
        $where = "where " . implode(' and ', $this->type->condition());
        $count = $this->commentType === CommentType::tgroup
            ? 'count(distinct c."ID")' // deduplicate N torrents in 1 tgroup
            : 'count(*)';
        return <<<END_SQL
            select $count
            from {$this->type->table()}
            inner join relay.comments c ON (c."Page" = $1 AND c."PageID" = src.{$this->type->idField()})            
            {$this->type->join()}
            $where
        END_SQL;
    }

    public function total(): int {
        return (int)$this->pg()->scalarex(
            $this->totalSql(),
            $this->commentType->value, // $1
            ...$this->type->args(),    // $2 and beyond
        );
    }

    public function pageSql(): string {
        $where    = "where " . implode(' and ', $this->type->condition());
        $limit    = '$' . (2 + count($this->type->args()));
        $offset   = '$' . (3 + count($this->type->args()));
        $distinct = $this->commentType === CommentType::tgroup ? ' distinct' : '';
        return <<<END_SQL
            select$distinct c."AuthorID"   as id_user,
                c."Page"                   as "type",
                c."PageID"                 as id_type,
                {$this->type->nameField()} as name,
                c."ID"                     as id,
                c."Body"                   as body,
                c."AddedTime"              as created,
                c."EditedTime"             as edited,
                c."EditedUserID"           as id_editor
            from {$this->type->table()}
            inner join relay.comments c on (c."Page" = $1 and c."PageID" = src.{$this->type->idField()})            
            {$this->type->join()}
            $where
            order by c."ID" desc
            limit $limit offset $offset
        END_SQL;
    }

    public function page(
        int $limit,
        int $offset,
        \Gazelle\Manager\Request $requestMan = new \Gazelle\Manager\Request(),
        \Gazelle\Manager\TGroup  $tgroupMan  = new \Gazelle\Manager\TGroup(),
        \Gazelle\Manager\User    $userMan    = new \Gazelle\Manager\User(),
    ): array {
        $requestList = [];
        $tgroupList  = [];
        $userList    = [];
        $list        = [];
        foreach (
            $this->pg()->allex(
                $this->pageSql(),
                $this->commentType->value,
                ...[
                    ...$this->type->args(),
                    $limit,
                    $offset,
                ]
            ) as $result
        ) {
            if ($this->commentType === CommentType::request) {
                $requestList[$result['id_type']] = $requestMan->findById($result['id_type']);
            } elseif ($this->commentType === CommentType::tgroup) {
                $tgroupList[$result['id_type']] = $tgroupMan->findById($result['id_type']);
            }
            if (!isset($userList[$result['id_user']])) {
                $userList[$result['id_user']] = $userMan->findById($result['id_user']);
            }
            if (isset($result['id_editor']) && !isset($userList[$result['id_editor']])) {
                $userList[$result['id_editor']] = $userMan->findById($result['id_editor']);
            }
            $list[] = $result;
        }
        return [
            'list'    => $list,
            'request' => $requestList,
            'tgroup'  => $tgroupList,
            'user'    => $userList,
        ];
    }
}
