<?php

namespace Gazelle;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

class SiteInfoTest extends TestCase {
    public function testSiteInfo(): void {
        $info = new SiteInfo();

        $this->assertGreaterThanOrEqual(0, strlen($info->phpinfo()), 'siteinfo-phpinfo');
        $this->assertCount(2, $info->uptime(), 'siteinfo-uptime');
        $this->assertGreaterThan(0, count($info->phinx()), 'siteinfo-phinx');
        $this->assertEquals(0, $info->phinxMigrationsPending(), 'siteinfo-pending-phinx');
        $this->assertStringStartsWith(
            'Composer version 2.',
            $info->composerVersion(),
            'siteinfo-composer-version'
        );
        $package = current($info->composerPackages());
        $this->assertEquals(
            ['name', 'installed', 'require'],
            array_keys($package),
            'siteinfo-composer-packages',
        );
        $this->assertTrue($info->tableExists('users_main'), 'siteinfo-table-exists');
        $this->assertEquals(
            ["ROWS_READ", "ROWS_CHANGED", "ROWS_CHANGED_X_INDEXES"],
            array_keys($info->tableRowsRead('users_main')[0]),
            'siteinfo-table-read'
        );
        $this->assertEquals(
            ['index_name', 'rows_read', 'column_list'],
            array_keys($info->indexRowsRead('users_main')[0]),
            'siteinfo-index-read',
        );
        $this->assertEquals(
            ["TABLE_ROWS", "AVG_ROW_LENGTH", "DATA_LENGTH", "INDEX_LENGTH",
                "DATA_FREE", "ROWS_READ", "ROWS_CHANGED", "ROWS_CHANGED_X_INDEXES"],
            array_keys($info->tableStats('users_main')),
            'siteinfo-table-stats',
        );
        $this->assertEquals([], $info->tablesWithoutPK(), 'siteinfo-table-no-pk');
        $this->assertEquals([], $info->tablesWithDuplicateForeignKeys(), 'siteinfo-table-dup-foreign-keys');
    }

    #[Group('no-ci')]
    public function testGitInfo(): void {
        $info = new SiteInfo();
        $this->assertIsString($info->gitBranch(), 'siteinfo-git-branch'); /** @phpstan-ignore-line */
        $this->assertIsString($info->gitHash(), 'siteinfo-git-hash-local'); /** @phpstan-ignore-line */

        // if the following test fails, you need to push your branch to the remote origin
        $this->assertIsString($info->gitHashRemote(), 'siteinfo-git-hash-remote'); /** @phpstan-ignore-line */
    }
}
