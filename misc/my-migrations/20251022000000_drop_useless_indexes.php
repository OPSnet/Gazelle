<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DropUselessIndexes extends AbstractMigration {
    public function up(): void {
        // redundant indexes
        $this->table('reportsv2')
            ->removeIndex('Type') // in fact unused
            ->removeIndex('LastChangeTime')
            ->removeIndex('TorrentID')
            ->save();

        // unused indexes
        $this->table('artists_group')
            ->removeIndex('RevisionID')
            ->save();
        $this->table('forums_topics')
            ->removeIndex('IsSticky')
            ->save();
        $this->table('tag_aliases')
            ->removeIndex('AliasTag')
            ->save();
        $this->table('torrents')
            ->removeIndex('Encoding')
            ->removeIndex('Time')
            ->save();
        $this->table('users_comments_last_read')
            ->removeIndex('UserID')
            ->save();
        $this->table('users_donor_ranks')
            ->removeIndex('DonationTime')
            ->removeIndex('SpecialRank')
            ->save();
        $this->table('users_main')
            ->removeIndex('RequiredRatio')
            ->save();
        $this->table('users_votes')
            ->removeIndex(['upvote', 'GroupID', 'UserID'])
            ->save();
    }

    public function down(): void {
        $this->table('reportsv2')
            ->addIndex('Type')
            ->addIndex('LastChangeTime')
            ->addIndex('TorrentID')
            ->save();

        $this->table('artists_group')
            ->addIndex(['RevisionID'], ['name' => 'Name'])
            ->save();
        $this->table('forums_topics')
            ->addIndex('IsSticky')
            ->save();
        $this->table('tag_aliases')
            ->addIndex('AliasTag')
            ->save();
        $this->table('torrents')
            ->addIndex('Encoding')
            ->addIndex('time', ['name' => 'Time'])
            ->save();
        $this->table('users_comments_last_read')
            ->addIndex('UserID', ['name' => 'uclr_user_idx'])
            ->save();
        $this->table('users_donor_ranks')
            ->addIndex('DonationTime')
            ->addIndex('SpecialRank')
            ->save();
        $this->table('users_main')
            ->addIndex('RequiredRatio')
            ->save();
        $this->table('users_votes')
            ->addIndex(['upvote', 'GroupID', 'UserID'], ['name' => 'uv_t_grp_usr_idx'])
            ->save();
    }
}
