create table forum_autosub (
    id_forum integer not null,
    id_user integer not null,
    created timestamp(0) with time zone not null default current_timestamp,
    primary key (id_forum, id_user)
);

create index fas_user_idx on forum_autosub (id_user);
