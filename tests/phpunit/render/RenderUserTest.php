<?php

use PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');
require_once(__DIR__ . '/../../helper.php');

class RenderUserTest extends TestCase {
    protected array $userList;

    public function tearDown(): void {
        foreach ($this->userList as $user) {
            $user->remove();
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
            'applicant'     => new \Gazelle\Manager\Applicant,
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
            'badge_list' => (new \Gazelle\User\Privilege($Viewer))->badgeList(),
            'bonus'      => new Gazelle\User\Bonus($this->userList['user']),
            'donor'      => new Gazelle\User\Donor($this->userList['user']),
            'freeleech' => [
                'item'  => [],
                'other' => null,
            ],
            'friend'       => new Gazelle\User\Friend($Viewer),
            'preview_user' => $this->userList['user'],
            'user'         => $this->userList['user'],
            'userMan'      => new \Gazelle\Manager\User,
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
    }
}
