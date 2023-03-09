create table ssl_host (
    id_ssl_host integer not null primary key generated always as identity,
    hostname text not null,
    port int not null,
    not_before timestamp(0) with time zone not null,
    not_after timestamp(0) with time zone not null,
    created timestamp(0) with time zone not null default current_timestamp
);
