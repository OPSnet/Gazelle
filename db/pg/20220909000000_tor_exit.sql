create table tor_node (
    id_tor_node integer not null primary key generated always as identity,
    ipv4 inet not null,
    created timestamp(0) with time zone not null default current_timestamp
);

create index tn_ipv4_idx on tor_node using gist (ipv4 inet_ops);
