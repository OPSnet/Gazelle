<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Util\Literal;

final class TgroupTag extends AbstractMigration {
    public function up(): void {
        $this->table('tgroup_tag', ['id' => false, 'primary_key' => 'id_tgroup_tag'])
            ->addColumn('id_tgroup_tag',  'integer', ['identity' => true])
            ->addColumn('id_tgroup',      'integer')
            ->addColumn('id_tag',         'integer')
            ->addColumn('id_user',        'integer')
            ->addColumn('upvote_total',   'integer', ['default' => 0])
            ->addColumn('downvote_total', 'integer', ['default' => 0])
            ->addIndex(['id_tgroup', 'id_tag'], ['name' => 'tt_tg_tag_uidx', 'unique' => true])
            ->addIndex(['id_tag'], ['name' => 'tt_tag_idx'])
            ->addForeignKey('id_tag', 'tag', 'id_tag', ['delete' => 'cascade', 'update' => 'cascade'])
            ->save();

        $this->query(<<<END_SQL
            create table tgroup_tag_summary(
                id_tgroup integer not null primary key,
                tag integer[] not null default '{}'
            )
            END_SQL
        );
        $this->query('create index tts_t_idx on tgroup_tag_summary using gin (tag)');
        $this->query(<<<END_SQL
            create function tgroup_tag_summary_update()
            returns trigger
            volatile
            language plpgsql
            as $$
                begin
                    if tg_op = 'INSERT' then
                        insert into tgroup_tag_summary (id_tgroup, tag)
                        values (new.id_tgroup, array[new.id_tag])
                        on conflict (id_tgroup) do update
                            set tag = array_append(tgroup_tag_summary.tag, new.id_tag);
                    elsif tg_op = 'DELETE' then
                        update tgroup_tag_summary set
                            tag = array_remove(tgroup_tag_summary.tag, old.id_tag)
                        where id_tgroup = old.id_tgroup;
                    end if;
                    return null;
                end;
            $$
            END_SQL
        );
        $this->query("
            create trigger tgroup_tag_summary_update_trigger
                after insert or delete on tgroup_tag
                    for each row
                        execute function tgroup_tag_summary_update()
        ");

        $this->table('tgroup_tag_vote', ['id' => false, 'primary_key' => ['id_tgroup_tag', 'id_user']])
            ->addColumn('id_tgroup_tag', 'integer')
            ->addColumn('id_user',       'integer')
            ->addColumn('upvote',        'boolean', ['default' => true])
            ->addIndex(['id_user'], ['name' => 'ttv_u_idx'])
            ->addForeignKey('id_tgroup_tag', 'tgroup_tag', 'id_tgroup_tag', ['delete' => 'cascade', 'update' => 'cascade'])
            ->save();
        $this->query(<<<END_SQL
            create function tgroup_tag_update()
            returns trigger
            volatile
            language plpgsql
            as $$
                begin
                    merge into tgroup_tag
                        using (select coalesce(NEW.id_tgroup_tag, OLD.id_tgroup_tag) as id_tgroup_tag) as source
                    on tgroup_tag.id_tgroup_tag = source.id_tgroup_tag
                    when matched then update set
                        upvote_total = upvote_total + (case
                            when TG_OP = 'INSERT' and NEW.upvote is true then  1
                            when TG_OP = 'DELETE' and OLD.upvote is true then -1
                            else 0
                        end),
                        downvote_total = downvote_total + (case
                            when TG_OP = 'INSERT' and NEW.upvote is false then  1
                            when TG_OP = 'DELETE' and OLD.upvote is false then -1
                            else 0
                        end);
                    return coalesce(NEW, OLD);
                end;
            $$
            END_SQL
        );
        $this->query("
            create trigger tgroup_tag_update_trigger
                after insert or delete on tgroup_tag_vote
                    for each row
                        execute function tgroup_tag_update()
        ");

        $existing = $this->fetchRow('
            select count(*) from tag
        ');
        if (is_array($existing) && $existing[0] !== 0) {
            return;
        }
        // This is a fresh installation or CI run.
        // This may performed again with bin/migrate/tag
        $this->query(<<<END_SQL
            insert into tag (id_tag, id_user, kind, usage, name)
            select "ID",
                "UserID",
                case when "TagType" = 'genre' then 'genre'::tag_kind_t else 'other'::tag_kind_t end,
                "Uses",
                "Name"
            from relay.tags
            END_SQL
        );
        $this->query(<<<END_SQL
            insert into tag_alias (alias_deny, alias_allow)
            select "BadTag", "AliasTag" 
            from relay.tag_aliases;
            END_SQL
        );
        $this->query("
            with m as (
                select 1 + max(id_tag) as id
                from tag
            )
            select setval('tag_id_tag_seq', m.id)
            from m
        ");
    }

    public function down(): void {
        $this->query('drop function tgroup_tag_summary_update cascade');
        $this->query('drop function tgroup_tag_update cascade');
        $this->table('tgroup_tag_vote')->drop()->save();
        $this->table('tgroup_tag_summary')->drop()->save();
        $this->table('tgroup_tag')->drop()->save();
    }
}
