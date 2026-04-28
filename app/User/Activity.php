<?php

declare(strict_types=1);

namespace Gazelle\User;

class Activity extends \Gazelle\BaseUser {
    final public const tableName = '';

    protected bool $showStaffInbox = false;
    protected array $action = [];
    protected array $alert = [];

    public function flush(): static {
        $this->user()->flush();
        return $this;
    }

    protected function setAction(string $action): static {
        $this->action[] = $action;
        return $this;
    }

    public function setAlert(string $alert): static {
        $this->alert[] = $alert;
        return $this;
    }

    public function actionList(): array {
        return $this->action;
    }

    public function alertList(): array {
        return $this->alert;
    }

    public function showStaffInbox(): bool {
        return $this->showStaffInbox;
    }

    public function configure(): static {
        if ($this->user->onRatioWatch()) {
            $this->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: You have '
                . time_diff($this->user->ratioWatchExpiry(), 3)
                . ' to get your ratio over your required ratio or your leeching abilities will be suspended.'
            );
        } elseif (!$this->user->canLeech()) {
            $this->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: Your downloading privileges are suspended until you meet your required ratio.');
        }
        if ($this->user->permitted('users_mod')) {
            $this->setAction('<a class="nobr" href="tools.php">Toolbox</a>');
        }
        return $this;
    }

    public function setApplicant(\Gazelle\Manager\Applicant $appMan): static {
        if ($appMan->openList($this->user)) {
            $total = $appMan->newTotal($this->user) + $appMan->newReplyTotal($this->user);
            if ($total > 0) {
                $this->setAction(sprintf(
                    '<a href="apply.php?action=view">%d new Applicant event%s</a>', $total, plural($total)
                ));
            }
        }
        return $this;
    }

    public function setAutoReport(\Gazelle\Search\ReportAuto $ru): static {
        if ($this->user->permitted('users_auto_reports')) {
            $open = $ru->setOwner(null)->setState(\Gazelle\Enum\ReportAutoState::open)->total();
            if ($open > 0) {
                $this->setAction('<a class="nobr" href="report_auto.php">' . $open . " Auto report" . plural($open) . "</a>");
            }
        }
        return $this;
    }

    public function setPayment(\Gazelle\Manager\Payment $payMan): static {
        if ($this->user->permitted('admin_manage_payments')) {
            $soon = $payMan->soon();
            if ($soon && $soon['total']) {
                $class = strtotime($soon['next']) < strtotime('+3 days') ? 'sys-error' : 'sys-warning';
                $this->setAlert("<a href=\"tools.php?action=payment_list\"><span title=\"Next payment due: {$soon['next']}, {$soon['total']} due within 1 week\" class=\"tooltip $class\">PAY</span></a>");
            }
        }
        return $this;
    }

    public function setReferral(\Gazelle\Manager\Referral $refMan): static {
        if ($this->user->permitted('admin_site_debug')) {
            if (!apcu_exists('DB_KEY') || !apcu_fetch('DB_KEY')) {
                $this->setAlert('<a href="tools.php?action=dbkey"><span style="color: red">DB key not loaded</span></a>');
            }
        }
        if ($this->user->permitted('admin_manage_referrals')) {
            if (!$refMan->checkBouncer()) {
                $this->setAlert('<a href="tools.php?action=referral_sandbox"><span class="nobr" style="color: red">Referral bouncer not responding</span></a>');
            }
        }
        return $this;
    }

    public function setReport(\Gazelle\Stats\Report $repStat): static {
        if ($this->user->permitted('admin_reports')) {
            $this->setAction("Reports:<a class=\"nobr tooltip\" title=\"Torrent reports\" href=\"reportsv2.php\"> {$repStat->torrentOpenTotal()}
                </a>/<a class=\"nobr tooltip\" title=\"Other reports\" href=\"reports.php\"> {$repStat->otherOpenTotal()} </a>"
            );
        } elseif ($this->user->permitted('site_moderate_forums')) {
            $open = $repStat->forumOpenTotal();
            if ($open > 0) {
                $this->setAction("<a href=\"reports.php\">$open Forum report" . plural($open) . '</a>');
            }
        }
        return $this;
    }

    public function setScheduler(\Gazelle\TaskScheduler $scheduler): static {
        if ($this->user->permitted('admin_periodic_task_view')) {
            if (!$scheduler->isLastRunIntervalOk(SCHEDULER_DELAY)) {
                $this->setAlert("<span class=\"sys-error\" title=\"Cron scheduler not running\">CRON</span>");
            }
            $insane = $scheduler->insaneTaskTotal();
            if ($insane) {
                $this->setAlert("<a title=\"$insane insane task"
                    . plural($insane)
                    . "\" href=\"tools.php?action=periodic&amp;mode=view\"><span class=\"sys-error\">TASK</span></a>"
                );
            }
        }
        return $this;
    }

    public function setSSLHost(\Gazelle\Manager\SSLHost $ssl): static {
        if ($this->user->permitted('site_debug')) {
            $soon = $ssl->expiryDays();
            if ($soon !== null && $soon < 8) {
                ++$soon;
                $this->setAlert(
                    "<a title=\"SSL Certificate will expire within $soon day"
                        . plural($soon)
                        . "\" href=\"tools.php?action=ssl_host\"><span class=\""
                        . ($soon <= 1 ? 'sys-error' : 'sys-warning')
                        . "\">SSL</span></a>"
                );
            }
        }
        return $this;
    }

    public function setStaff(\Gazelle\Staff $staff): static {
        if ($staff->blogAlert()) {
            $this->setAlert('<a class="nobr" href="staffblog.php">New staff blog post!</a>');
        }
        if (FEATURE_EMAIL_REENABLE) {
            $total = new \Gazelle\Manager\AutoEnable()->openTotal();
            if ($total > 0) {
                $this->setAction('<a class="nobr" href="tools.php?action=enable_requests">'
                    . $total . " Enable request" . plural($total) . "</a>");
            }
        }
        return $this;
    }

    public function setStaffPM(\Gazelle\Manager\StaffPM $spm): static {
        $total = $spm->countAtLevel($this->user, ['Unanswered']);
        if ($total > 0) {
            $this->showStaffInbox = true;
            $this->setAction('<a class="nobr" href="staffpm.php">' . $total
                . ' Staff PM' . plural($total) . '</a>');
        }
        return $this;
    }

    public function setStats(int $threshold, \Gazelle\Stats\Torrent $stats): static {
        if ($this->user->permitted('admin_site_debug')) {
            $total = array_reduce(
                array_map(
                    fn ($h) => $h['total'],
                    $stats->recentDownloadTotal()
                ),
                fn ($total, $hour): int => $total += $hour
            );
            if ($total > $threshold) {
                $this->setAction(
                    '<span class="sys-warning">Downloads: <a href="tools.php?action=torrent_stats" title="Downloads over past three hours">'
                    . implode('/', array_map(
                        fn ($s) => number_format($s['total']),
                        $stats->recentDownloadTotal()
                    ))
                    . "</a></span>"
                );
            }
        }
        return $this;
    }
}
