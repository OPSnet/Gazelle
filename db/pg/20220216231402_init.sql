create role nyala with password 'nyalapw' login;

create database gz with owner nyala;

\c gz nyala

create extension pg_trgm;

create schema geo authorization nyala;

create table geo.asn (
    id_asn bigint not null primary key,
    cc character(2) not null,
    name text not null,
    name_ts tsvector generated always as (to_tsvector('simple', name)) stored
);

create index asn_ts_name_idx on geo.asn using gin (name_ts);

create table geo.asn_network (
    id_asn bigint not null,
    network cidr not null,
    created date not null default current_date,
    foreign key (id_asn) references geo.asn(id_asn) on delete cascade
);

create index asn_network_network_idx on geo.asn_network using gist (network inet_ops);

create table geo.ptr (
    id_ptr integer not null primary key generated always as identity,
    ipv4 inet not null,
    name text not null,
    created date not null default current_date
);

create index ptr_ipv4_idx on geo.ptr using gist (ipv4 inet_ops);

create table geo.asn_trg as select word from ts_stat('select to_tsvector(''simple'', name) from geo.asn');
create index asn_trg_idx on geo.asn_trg using gin (word gin_trgm_ops);
