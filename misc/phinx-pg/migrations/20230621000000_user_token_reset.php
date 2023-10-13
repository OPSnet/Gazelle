<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UserTokenReset extends AbstractMigration
{
    public function up(): void {
        $this->execute('CREATE TYPE user_token_type as enum (\'confirm\', \'password\', \'mfa\');');

        $this->execute('CREATE TABLE user_token (
            id_user_token integer not null primary key generated always as identity,
            id_user integer not null,
            type user_token_type not null,
            token char(32) not null default translate(encode(gen_random_bytes(24), \'base64\'), \'+/\', \'-_\'),
            expiry timestamptz(0) not null default \'infinity\'
        );');

        $this->execute('CREATE INDEX ut_user_idx on user_token (id_user, type);');
        $this->execute('CREATE INDEX ut_expiry_idx on user_token (expiry);');
    }

    public function down(): void {
        $this->execute('DROP INDEX ut_expiry_idx');
        $this->execute('DROP INDEX ut_user_idx');
        $this->table('user_token')->drop()->save();
        $this->execute('DROP TYPE user_token_type');
    }
}
