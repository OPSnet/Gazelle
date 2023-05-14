create extension "pgcrypto";

create type user_token_type as enum ('confirm', 'password', 'mfa');

drop table if exists user_token;

create table user_token (
    id_user_token integer not null primary key generated always as identity,
    id_user integer not null,
    type user_token_type not null,
    token char(32) not null default translate(encode(gen_random_bytes(24), 'base64'), '+/', '-_'),
    expiry timestamptz(0) not null default 'infinity'
);

create index ut_user_idx on user_token (id_user, type);
create index ut_expiry_idx on user_token (expiry);
