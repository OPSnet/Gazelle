create table user_warning (
    id_user integer not null,
    id_user_warner integer not null,
    warning tstzrange not null default tstzrange(now(), now() + '1 week'::interval),
    primary key (id_user, warning)
);
