create table donate_monero (
    id_user integer not null primary key,
    token bytea not null unique
);
CREATE EXTENSION IF NOT EXISTS pgcrypto;
