<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MigrateSiteLog extends AbstractMigration {
    public function up(): void {
        $this->table('periodic_task')
             ->insert([
                'name'        => 'Relay Site Log',
                'classname'   => 'RelaySiteLog',
                'description' => 'Copy new site log records to Postgres',
                'period'      => 1,
                'is_enabled'  => 1,
            ])
            ->save();

        $article = <<<EOS
[size=3]Refining matches in full text searches[/size]

[*] [b]"quoted text"[/b] will be considered as a single unit. Records that contain only [b]quoted[/b] or [b]text[/b] or [b]quoted my text[/b] will not be matched.
[*] [b]quoted or text[/b] will match records that contain either [b]quoted[/b] or [b]text[/b]. If you need to match [b]or[/b], quote it: [b]red "or" blue[/b].
[*] If the presence of a word means the record must be excluded, prefix it with [plain]-[/plain] (dash) [b]heavy -metal[/b].

These can be combined: [b]house or "rock and roll" -only[/b].

Exclusion [b]-[/b] (dash) binds tightly, [b]or[/b] binds loosely. The above expression matches records containing [b]rock and roll[/b] when [b]only[/b] is not present, and also records that contain [b]house[/b].

Searching is case-insensitive: folk will match both [b]folk[/b] and [b]Folk[/b].
EOS;

        $this->query("
            INSERT INTO wiki_articles
                (Title, Body, MinClassRead, MinClassEdit, Author)
            VALUES (
                'Fulltext searching tips',
                '$article',
                (SELECT Level FROM permissions WHERE Name = 'User'),
                (SELECT Level FROM permissions WHERE Name = 'User'),
                (SELECT um.ID
                    FROM users_main um
                    INNER JOIN permissions p ON (p.ID = um.PermissionID)
                    ORDER BY p.Level DESC
                    LIMIT 1
                )
            )
        ");
        $this->query("
            INSERT INTO wiki_aliases
                (ArticleID, Alias, UserID)
            VALUES (
                (SELECT ID FROM wiki_articles WHERE Title = 'Fulltext searching tips'),
                'searchfulltext',
                (SELECT um.ID
                    FROM users_main um
                    INNER JOIN permissions p ON (p.ID = um.PermissionID)
                    ORDER BY p.Level DESC
                    LIMIT 1
                )
            )
        ");
    }

    public function down(): void {
        $this->execute("
            DELETE FROM wiki_aliases WHERE Alias = 'searchfulltext'
        ");
        $this->execute("
            DELETE FROM wiki_articles WHERE Title = 'Fulltext searching tips'
        ");
        $this->execute("
            DELETE FROM periodic_task WHERE classname = 'RelaySiteLog'
        ");
    }
}
