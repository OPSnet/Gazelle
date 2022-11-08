<?php

namespace Gazelle\Search\Torrent;

class Report extends \Gazelle\Base {
    protected string $orderBy;
    protected string $title;
    protected array $join;
    protected array $cond;
    protected array $args;

    public function __construct(
        protected string $mode,
        protected string $id,
        protected \Gazelle\Manager\Torrent\ReportType $reportTypeMan,
        protected \Gazelle\Manager\User $userMan,
    ) {}


    protected function configure(): void {
        if (isset($this->cond)) {
            return;
        }
        $this->cond = [];
        $this->args = [];
        if (!(int)$this->id) {
            switch ($this->mode) {
                case 'resolved':
                    $this->title   = "Resolved reports";
                    $this->cond[]  = "r.Status = ?";
                    $this->args[]  = "Resolved";
                    $this->orderBy = "ORDER BY r.LastChangeTime DESC";
                    break;
                case 'unauto':
                    $this->title  = "Unassigned reports";
                    $this->cond[] = "r.Status = ?";
                    $this->args[] = "New";
                    break;
                case 'type':
                    $reportType   = $this->reportTypeMan->findByType($this->id);
                    $this->title  = "All new reports of type {$reportType->name()}";
                    $this->cond[] = "r.Status = ?";
                    $this->args[] = "New";
                    $this->cond[] = "r.Type = ?";
                    $this->args[] = $reportType->type();
                    break;
                default:
                    break;
            }
        } else {
            $user = $this->userMan->findById($this->id);
            switch ($this->mode) {
                case 'staff':
                    $this->title  = "{$user->link()}'s claimed reports";
                    $this->cond[] = "r.Status = 'InProgress' AND r.ResolverID = ?";
                    break;
                case 'resolver':
                    $this->title   = "{$user->link()}'s resolved reports";
                    $this->cond[]  = "r.Status = 'Resolved' AND r.ResolverID = ?";
                    $this->orderBy = 'ORDER BY r.LastChangeTime DESC';
                    break;
                case 'group':
                    $this->title  = "Unresolved reports for the torrent group {$this->id}";
                    $this->join[] = "INNER JOIN torrents AS t ON (t.ID = r.TorrentID)";
                    $this->join[] = "INNER JOIN torrents_group AS tg ON (tg.ID = t.GroupID)";
                    $this->cond[] = "r.Status != 'Resolved' AND tg.ID = ?";
                    break;
                case 'torrent':
                    $this->title  = "All reports for the torrent {$this->id}";
                    $this->cond[] = "r.TorrentID = ?";
                    break;
                case 'report':
                    $this->title  = "Viewing resolution of report {$this->id}";
                    $this->cond[] = "r.ID = ?";
                    break;
                case 'reporter':
                    $this->title   = "All reports from {$user->link()}";
                    $this->cond[]  = "r.ReporterID = ?";
                    $this->orderBy = "ORDER BY r.ReportedTime DESC";
                    break;
                case 'uploader':
                    $this->title  = "All reported uploads from {$user->link()}";
                    $this->join[] = "INNER JOIN torrents AS t ON (t.ID = r.TorrentID)";
                    $this->cond[] = "r.Status != 'Resolved' AND t.UserID = ?";
                    break;
                default:
                    break;
            }
            $this->args[] = $this->id;
        }
    }

    public function canUnclaim(\Gazelle\User $user): bool {
        return $this->mode === 'staff' && $user->id() === (int)$this->id;
    }

    public function mode(): string {
        return $this->mode;
    }

    public function title(): string {
        $this->configure();
        return $this->title;
    }

    public function pageSql(): string {
        $this->configure();
        if (!isset($this->orderBy)) {
            $this->orderBy = 'ORDER BY r.ReportedTime';
        }
        return "SELECT r.ID FROM reportsv2 AS r "
            . (isset($this->join) ? implode(' ', $this->join) : '')
            . " WHERE " . implode(' AND ', $this->cond)
            . " {$this->orderBy} LIMIT ? OFFSET ?";
    }

    public function page(\Gazelle\Manager\Torrent\Report $manager, int $limit, int $offset): array {
        self::$db->prepared_query($this->pageSql(), ...[...$this->args, $limit, $offset]);
        return array_map(
            fn ($id) => $manager->findById($id),
            self::$db->collect(0, false)
        );
    }

    public function totalSql(): string {
        $this->configure();
        return "SELECT count(*) FROM reportsv2 AS r "
            . (isset($this->join) ? implode(' ', $this->join) : '')
            . " WHERE " . implode(' AND ', $this->cond);
    }

    public function total(): int {
        return self::$db->scalar($this->totalSql(), ...$this->args);
    }
}
