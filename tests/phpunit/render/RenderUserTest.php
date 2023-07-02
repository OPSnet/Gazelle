<?php

use \PHPUnit\Framework\TestCase;

require_once(__DIR__ . '/../../../lib/bootstrap.php');

class RenderUserTest extends TestCase {
    protected \Gazelle\Manager\User $userMan;

    public function setUp(): void {
        $this->userMan = new \Gazelle\Manager\User;
    }

    public function testProfile(): void {
        global $Viewer;
        $Viewer = $this->userMan->find('@admin');
        $user   = $this->userMan->find('@user');
        $PRL    = new \Gazelle\User\PermissionRateLimit($user);

        $sidebar = Gazelle\Util\Twig::factory()->render('user/sidebar.twig', [
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

        $header = Gazelle\Util\Twig::factory()->render('user/header.twig', [
            'badge_list' => (new \Gazelle\User\Privilege($Viewer))->badgeList(),
            'bonus'      => new Gazelle\User\Bonus($user),
            'donor'      => new Gazelle\User\Donor($user),
            'freeleech' => [
                'item'  => [],
                'other' => null,
            ],
            'friend'       => new Gazelle\User\Friend($Viewer),
            'preview_user' => $user,
            'user'         => $user,
            'userMan'      => $this->userMan,
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
