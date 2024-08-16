<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class RenderUserTest extends TestCase {
    protected array $userList;
    protected array $userReports;

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
        }
        foreach ($this->userReports as $report) {
            $report->remove();
        }
    }

    public function testProfile(): void {
        $this->userList['admin'] = Helper::makeUser('admin.' . randomString(6), 'render');
        $this->userList['admin']->setField('PermissionID', SYSOP)->modify();
        $this->userList['user'] = Helper::makeUser('user.' . randomString(6), 'render');

        global $Viewer;
        $Viewer  = $this->userList['admin'];
        $limiter = new \Gazelle\User\UserclassRateLimit($this->userList['user']);

        $sidebar = Gazelle\Util\Twig::factory()->render('user/sidebar.twig', [
            'applicant'     => new \Gazelle\Manager\Applicant(),
            'invite_source' => 'invsrc',
            'user'          => $this->userList['user'],
            'viewer'        => $Viewer,
        ]);
        $this->assertEquals(
            substr_count($sidebar, '<li>') + substr_count($sidebar, '<li class="paranoia_override">'),
            substr_count($sidebar, '</li>'),
            'user-sidebar-general'
        );

        $header = Gazelle\Util\Twig::factory()->render('user/header.twig', [
            'badge_list' => $Viewer->privilege()->badgeList(),
            'bonus'      => new Gazelle\User\Bonus($this->userList['user']),
            'donor'      => new Gazelle\User\Donor($this->userList['user']),
            'freeleech' => [
                'item'  => [],
                'other' => null,
            ],
            'friend'       => new Gazelle\User\Friend($Viewer),
            'preview_user' => $this->userList['user'],
            'user'         => $this->userList['user'],
            'userMan'      => new \Gazelle\Manager\User(),
            'viewer'       => $Viewer,
        ]);
        $this->assertStringContainsString('<div class="header">', $header, 'user-header-div-header');
        $this->assertStringContainsString('<div class="linkbox">', $header, 'user-header-div-linkbox');
        $this->assertStringContainsString('<div class="sidebar">', $header, 'user-header-div-sidebar');

        // This would require a lot more scaffolding to test the actual markup
        $tag = Gazelle\Util\Twig::factory()->render('user/tag-snatch.twig', [
            'user' => $Viewer,
        ]);
        $this->assertEquals('', $tag, 'user-header-div-tag-heading');

        $stats = Gazelle\Util\Twig::factory()->render('user/sidebar-stats.twig', [
            'prl'          => $limiter,
            'upload_total' => [],
            'user'         => $this->userList['user'],
            'viewer'       => $Viewer,
            'visible'      => [
                'collages+'  => PARANOIA_ALLOWED,
                'downloaded' => PARANOIA_HIDE,
            ],
        ]);
        $this->assertStringContainsString('<div class="box box_info box_userinfo_community">', $stats, 'user-header-stats-header');
        $this->assertStringContainsString('<li id="comm_collstart">', $stats, 'user-header-stats-id-collages');
        $this->assertStringNotContainsString('<li id="comm_downloaded">', $stats, 'user-header-stats-id-downloaded');

        // Test reports displayed on profile
        $reportMan = new Gazelle\Manager\Report(new \Gazelle\Manager\User());
        $this->userReports[0] = $reportMan->create($this->userList['admin'], $this->userList['user']->id(), "user", randomString(6));
        $this->userReports[1] =  $reportMan->create($this->userList['admin'], $this->userList['user']->id(), "user", randomString(500));
        $reports = Gazelle\Util\Twig::factory()->render('admin/user-reports-list.twig', [
            'list' => $reportMan->findByReportedUser($this->userList['user'])
        ]);
        $this->assertStringContainsString('<div class="box" id="user-reports-box">', $reports, 'user-reports-box');
        $this->assertStringContainsString('-reason" class="user-report-reason wrap_overflow">', $reports, 'user-reports-reason');
        $this->assertStringContainsString($this->userReports[0]->reason(), $reports, 'user-reports0-reason-text');
        //$this->assertStringContainsString($this->userReports[1]->reason(), $reports, 'user-reports1-reason-text');
    }
}
