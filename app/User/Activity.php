<?php

namespace Gazelle\User;

class Activity extends \Gazelle\BaseUser {

    protected bool $showStaffInbox = false;
    protected array $action = [];
    protected array $alert = [];

    protected function setAction(string $action) {
        $this->action[] = $action;
        return $this;
    }

    public function setAlert(string $alert) {
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

    public function configure() {
        if ($this->user->onRatioWatch()) {
            $this->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: You have '
                . time_diff($this->user->ratioWatchExpiry(), 3)
                . ' to get your ratio over your required ratio or your leeching abilities will be disabled.'
            );
        } elseif (!$this->user->canLeech()) {
            $this->setAlert('<a class="nobr" href="rules.php?p=ratio">Ratio Watch</a>: Your downloading privileges are disabled until you meet your required ratio.');
        }
        if ($this->user->permitted('users_mod')) {
            $this->setAction('<a class="nobr" href="tools.php">Toolbox</a>');
        }
        return $this;
    }

    public function setApplicant(\Gazelle\Manager\Applicant $appMan) {
        if ($this->user->permitted('admin_manage_applicants')) {
            $total = $appMan->newApplicantCount() + $appMan->newReplyCount();
            if ($total > 0) {
                $this->setAction(sprintf(
                    '<a href="apply.php?action=view">%d new Applicant event%s</a>', $total, plural($total)
                ));
            }
        }
        return $this;
    }

    public function setDb(\Gazelle\DB $dbMan) {
        if ($this->user->permitted('admin_site_debug')) {
            $longRunning = $dbMan->longRunning();
            if ($longRunning > 0) {
                $this->setAlert('<span style="color: red">' . $longRunning . ' long-running DB operation' . plural($longRunning) . '!</span>');
            }
            // If Ocelot can no longer write to xbt_files_users, it will drain after an hour
            // Look for database locks and check the Ocelot log
            if (!self::$db->scalar('SELECT fid FROM xbt_files_users LIMIT 1')) {
                $this->setAlert('<span style="color: red">Ocelot not updating!</span>');
            }
        }
        return $this;
    }

    public function setPayment(\Gazelle\Manager\Payment $payMan) {
        if ($this->user->permitted('admin_manage_payments')) {
            $due = $payMan->due();
            if ($due) {
                $alert = '<a class="nobr" href="tools.php?action=payment_list">Payments due</a>';
                foreach ($due as $p) {
                    [$Text, $Expiry] = array_values($p);
                    $Color = strtotime($Expiry) < (strtotime('+3 days')) ? 'red' : 'orange';
                    $alert .= sprintf(' | <span style="color: %s">%s: %s</span>', $Color, $Text, date('Y-m-d', strtotime($Expiry)));
                }
                $this->setAlert($alert);
            }
        }
        return $this;
    }

    public function setReferral(\Gazelle\Manager\Referral $refMan) {
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

    public function setReport(\Gazelle\Stats\Report $repStat) {
        if ($this->user->permitted('admin_reports')) {
            $open = $repStat->torrentOpenTotal();
            $this->setAction("<a class=\"nobr\" href=\"reportsv2.php\">$open Report" . plural($open) . '</a>');
            $other = $repStat->otherOpenTotal();
            if ($other > 0) {
                $this->setAction("<a class=\"nobr\" href=\"reports.php\">$other Other report" . plural($other) . '</a>');
            }
        } elseif ($this->user->permitted('site_moderate_forums')) {
            $open = $repStat->forumThreadTrashTotal();
            if ($open > 0) {
                $this->setAction("<a href=\"reports.php\">$open Forum report" . plural($open) . '</a>');
            }
        }
        return $this;
    }

    public function setScheduler(\Gazelle\Schedule\Scheduler $scheduler) {
        if ($this->user->permitted('admin_periodic_task_view')) {
            $lastSchedulerRun = self::$db->scalar("
                SELECT now() - max(launch_time) FROM periodic_task_history
            ");
            $insane = $scheduler->getInsaneTasks();
            if ($insane) {
                $this->setAlert(
                    $insane == 1
                    ? '<a href="tools.php?action=periodic&amp;mode=view">There is an insane task</a>'
                    : sprintf('<a href="tools.php?action=periodic&amp;mode=view">%d insane tasks</a>', $insane)
                );
            }
            if ($lastSchedulerRun > SCHEDULER_DELAY) {
                $this->setAlert('<span style="color: red">Scheduler not running</span>');
            }
        }
        return $this;
    }

    public function setStaff(\Gazelle\Staff $staff) {
        if ($staff->blogAlert()) {
            $this->setAlert('<a class="nobr" href="staffblog.php">New staff blog post!</a>');
        }

        if (FEATURE_EMAIL_REENABLE) {
            $total = self::$cache->get_value(\AutoEnable::CACHE_KEY_NAME);
            if ($total === false) {
                $total = self::$db->scalar("SELECT count(*) FROM users_enable_requests WHERE Outcome IS NULL");
                self::$cache->cache_value(\AutoEnable::CACHE_KEY_NAME, $total);
            }
            if ($total > 0) {
                $this->setAction('<a class="nobr" href="tools.php?action=enable_requests">' . $total . " Enable request" . plural($total) . "</a>");
            }
        }
        return $this;
    }

    public function setStaffPM(\Gazelle\Manager\StaffPM $spm) {
        $total = $spm->countAtLevel($this->user, ['Unanswered']);
        if ($total > 0) {
            $this->showStaffInbox = true;
            $this->setAction('<a class="nobr" href="staffpm.php">' . $total . ' Staff PM' . plural($total) . '</a>');
        }
        return $this;
    }
}
