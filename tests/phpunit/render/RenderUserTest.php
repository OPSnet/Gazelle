<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

class RenderUserTest extends TestCase {
    protected \Gazelle\Manager\User $userMan;

    public function setUp(): void {
        $this->userMan = new \Gazelle\Manager\User;
    }

    public function tearDown(): void {}

    public function testProfile() {
        global $Twig;
        global $Viewer;
        $Viewer = $this->userMan->find('@admin');
        $user   = $this->userMan->find('@user');
        $PRL    = new \Gazelle\User\PermissionRateLimit($user);

        $sidebar = $Twig->render('user/sidebar.twig', [
            'applicant'     => new \Gazelle\Manager\Applicant,
            'invite_source' => 'invsrc',
            'user'          => $user,
            'viewer'        => $Viewer,
        ]);
        $this->assertEquals(
            substr_count($sidebar, '<li>') + substr_count($sidebar, '<li class="paranoia_override">'),
            substr_count($sidebar, '</li>'),
            'user-sidebar-general'
        );

        $header = $Twig->render('user/header.twig', [
            'badge_list' => (new \Gazelle\User\Privilege($Viewer))->badgeList(),
            'freeleech' => [
                'item'  => [],
                'other' => null,
            ],
            'hourly_rate'  => 1.235,
            'preview_user' => $user,
            'user'         => $user,
            'userMan'      => $this->userMan,
            'viewer'       => $Viewer,
        ]);
        $this->assertStringContainsString('<div class="header">', $header, 'user-header-div-header');
        $this->assertStringContainsString('<div class="linkbox">', $header, 'user-header-div-linkbox');
        $this->assertStringContainsString('<div class="sidebar">', $header, 'user-header-div-sidebar');

        $tag = $Twig->render('user/tag-snatch.twig', [
            'id' => $Viewer->id(),
            'list' => [
                ['name' => 'jazz', 'n' => 20],
                ['name' => 'rock', 'n' => 10],
                ['name' => 'metal', 'n' => 5],
            ],
        ]);
        $this->assertStringContainsString('Snatched tags', $tag, 'user-header-div-tag-heading');
        $this->assertEquals(3, substr_count($tag, '<li>'), 'user-header-div-tag-total');

        $stats = $Twig->render('user/sidebar-stats.twig', [
            'prl'          => $PRL,
            'upload_total' => [],
            'user'         => $user,
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
