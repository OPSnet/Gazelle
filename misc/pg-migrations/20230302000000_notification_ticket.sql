create or replace function modified_now()
returns trigger as $$
begin
  NEW.modified = now();
  return NEW;
end;
$$ language plpgsql;

create type nt_state as enum ('pending', 'stale', 'active', 'done', 'removed', 'error');
create table notification_ticket (
    id_torrent integer not null primary key,
    state nt_state default 'pending',
    reach int default 0,
    retry int default 0,
    created timestamp(0) with time zone not null default current_timestamp,
    modified timestamp(0) with time zone not null default current_timestamp
);

create index nt_state_idx on notification_ticket (state);

create trigger notification_ticket_modified
    before update on notification_ticket
    for each row execute procedure modified_now();
