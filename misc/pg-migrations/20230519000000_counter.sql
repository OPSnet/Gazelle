create table counter (
    name varchar(20) not null primary key,
    description text not null check(length(description) <= 2000),
    value integer not null default 0
);
