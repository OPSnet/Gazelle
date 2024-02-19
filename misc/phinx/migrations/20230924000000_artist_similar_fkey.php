<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ArtistSimilarFkey extends AbstractMigration {
    public function up(): void {
        $this->table('artists_similar')
            ->addForeignKey('SimilarID', 'artists_similar_scores', 'SimilarID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();

        $this->table('artists_similar_votes')
            ->addForeignKey('SimilarID', 'artists_similar_scores', 'SimilarID', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
            ->save();
    }

    public function down(): void {
        $this->table('artists_similar')->dropForeignKey('SimilarID')->save();
        $this->table('artists_similar_votes')->dropForeignKey('SimilarID')->save();
    }
}
