<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class FileCountOrdinal extends AbstractMigration {
    public function up(): void {
        $this->table('user_ordinal')
             ->insert([
                [
                    'name'          => 'file-count-display',
                    'description'   => 'Configure how filelist counts are displayed on torrent pages',
                    'default_value' => 0,
                ],
                [
                    'name'          => 'non-audio-threshold',
                    'description'   => 'Configure torrent display when non-audio filesize totals exceed a threshold',
                    'default_value' => 0,
                ],
             ])
             ->save();
        $this->table('user_attr')
            ->insert([
                [
                    'Name' => 'feature-file-count',
                    'Description' => 'This user has purchased the file count display feature'
                ],
            ])
            ->save();
        $this->table('bonus_item')
            ->insert([
                [
                    'Price' => 12000,
                    'Amount' => 1,
                    'MinClass' => 150,
                    'FreeClass' => 999999,
                    'Label' => 'file-count',
                    'Title' => 'Configure file count display on the torrent detail page',
                    'sequence' => 20,
                ],
            ])
            ->save();
    }

    public function down(): void {
        $this->query("
            delete from user_ordinal where name in ('file-count-display', 'non-audio-threshold');
        ");
        $this->query("
            delete from user_attr where Name = 'feature-file-count';
        ");
        $this->execute("
            delete from bonus_item where Label = 'file-count'
        ");
    }
}
