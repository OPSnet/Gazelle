<?php

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

    public function setDb(\Gazelle\DB $dbMan): static {
        if ($this->user->permitted('admin_site_debug')) {
            $longRunning = $dbMan->longRunning();
            if ($longRunning > 0) {
                $message = "$longRunning long-running DB operation" . plural($longRunning);
                $this->setAlert("<span title=\"$message\" class=\"sys-error\">DB</span>");
            }
            // If Ocelot can no longer write to xbt_files_users, it will drain after an hour
            // Look for database locks and check the Ocelot log
            if (!self::$db->scalar('SELECT fid FROM xbt_files_users LIMIT 1')) {
                $this->setAlert('<span style="color: red">Ocelot not updating!</span>');
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
            $lastSchedulerRun = self::$db->scalar("
                SELECT now() - max(launch_time) FROM periodic_task_history
            ");
            if ($lastSchedulerRun > SCHEDULER_DELAY) {
                $this->setAlert("<span class=\"sys-error\" title=\"Cron scheduler not running\">CRON</span>");
            }
            $insane = $scheduler->getInsaneTasks();
            if ($insane) {
                $plural = plural($insane);
                $this->setAlert("<a title=\"$insane insane task$plural\" href=\"tools.php?action=periodic&amp;mode=view\"><span class=\"sys-error\">TASK</span></a>");
            }
        }
        return $this;
    }

    public function setSSLHost(\Gazelle\Manager\SSLHost $ssl): static {
        if ($this->user->permitted('site_debug')) {
            $soon = $ssl->expirySoon('1 DAY');
            $url = "tools.php?action=ssl_host";
            if ($soon) {
                $this->setAlert("<a title=\"SSL Certificate will expire in one day\" href=\"$url\"><span class=\"sys-error\">SSL</span></a>");
            } else {
                $soon = $ssl->expirySoon('1 WEEK');
                if ($soon) {
                    $this->setAlert("<a title=\"SSL Certificate will expire in one week\" href=\"$url\"><span class=\"sys-warning\">SSL</span></a>");
                }
            }
        }
        return $this;
    }

    public function setStaff(\Gazelle\Staff $staff): static {
        if ($staff->blogAlert()) {
            $this->setAlert('<a class="nobr" href="staffblog.php">New staff blog post!</a>');
        }
        if (FEATURE_EMAIL_REENABLE) {
            $total = (new \Gazelle\Manager\AutoEnable())->openTotal();
            if ($total > 0) {
                $this->setAction('<a class="nobr" href="tools.php?action=enable_requests">' . $total . " Enable request" . plural($total) . "</a>");
            }
        }
        return $this;
    }

    public function setStaffPM(\Gazelle\Manager\StaffPM $spm): static {
        $total = $spm->countAtLevel($this->user, ['Unanswered']);
        if ($total > 0) {
            $this->showStaffInbox = true;
            $this->setAction('<a class="nobr" href="staffpm.php">' . $total . ' Staff PM' . plural($total) . '</a>');
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
}
