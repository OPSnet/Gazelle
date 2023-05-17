alter table user_warning add reason text check (char_length(reason) <= 32000);
update user_warning set reason = '';
alter table user_warning alter column reason set not null;
