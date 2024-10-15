<?php

namespace Gazelle\Manager;

class Report extends \Gazelle\BaseManager {
    protected const ID_KEY = 'zz_r_%d';

    public function __construct(
        protected \Gazelle\Manager\User $userMan,
    ) {}

    public function create(\Gazelle\User $user, int $id, string $type, string $reason): \Gazelle\Report {
        self::$db->prepared_query("
            INSERT INTO reports
                   (UserID, ThingID, Type, Reason)
            VALUES (?,      ?,       ?,    ?)
            ", $user->id(), $id, $type, $reason
        );
        $id = self::$db->inserted_id();
        if ($type == 'request_update') {
            self::$cache->decrement('num_update_reports');
        }
        self::$cache->delete_value('num_other_reports');
        return $this->findById($id);
    }

    public function findById(int $reportId): ?\Gazelle\Report {
        $key = sprintf(self::ID_KEY, $reportId);
        $id = self::$cache->get_value($key);
        if ($id === false) {
            $id = self::$db->scalar("
                SELECT ID FROM reports WHERE ID = ?
                ", $reportId
            );
            if (!is_null($id)) {
                self::$cache->cache_value($key, $id, 7200);
            }
        }
        if (!$id) {
            return null;
        }
        return (new \Gazelle\Report($id))->setUserManager($this->userMan);
    }

    public function findByReportedUser(\Gazelle\User $user): array {
        self::$db->prepared_query("
            SELECT ID
            FROM reports
            WHERE Type = 'user'
                AND ThingID = ?
            ORDER BY ID DESC
            ", $user->id()
        );
        $reportList = self::$db->collect(0, false);
        return array_map(fn($id) => $this->findById($id), $reportList);
    }

    public function decorate(
        array $idList,
        \Gazelle\Manager\Collage     $collageMan,
        \Gazelle\Manager\Comment     $commentMan,
        \Gazelle\Manager\ForumThread $threadMan,
        \Gazelle\Manager\ForumPost   $postMan,
        \Gazelle\Manager\Request     $requestMan,
    ): array {
        $list = [];
        foreach ($idList as $id) {
            $report = $this->findById($id);
            switch ($report-> subjectType()) {
                case 'collage':
                    $context = [
                        'label'   => 'collage',
                        'subject' => $collageMan->findById($report->subjectId()),
                    ];
                    break;
                case 'comment':
                    $context = [
                        'label'   => 'comment',
                        'subject' => $commentMan->findById($report->subjectId()),
                    ];
                    break;
                case 'request':
                case 'request_update':
                    $context = [
                        'label'   => 'request',
                        'subject' => $requestMan->findById($report->subjectId()),
                    ];
                    break;
                case 'thread':
                    $thread = $threadMan->findById($report->subjectId());
                    $context = [
                        'label'   => 'forum thread',
                        'subject' => $thread,
                        'link'    => $thread
                            ? "{$thread->forum()->link()} › {$thread->link()} created by {$thread->author()->link()}"
                            : null,
                    ];
                    break;
                case 'post':
                    $post = $postMan->findById($report->subjectId());
                    $link  = null;
                    if ($post) {
                        $thread = $post->thread();
                        $link = "{$thread->forum()->link()} › {$thread->link()} › {$post->link()} posted by "
                            . ($this->userMan->findById($post->userId())?->link() ?? 'System');
                    }
                    $context = [
                        'label'   => 'forum post',
                        'subject' => $post,
                        'link'    => $link,
                    ];
                    break;
                case 'user':
                    $context = [
                        'label'   => 'user',
                        'subject' => $this->userMan->findById($report->subjectId()),
                    ];
                    break;
            }
            $context['report'] = $report;
            $list[] = $context;
        }
        return $list;
    }

    public function remainingTotal(): int {
        return (int)self::$db->scalar("
            SELECT count(*) FROM reports WHERE Status = 'New'
        ");
    }
}
