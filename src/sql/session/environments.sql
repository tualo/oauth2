delimiter ;
create table if not exists oauth2_environments (
    id varchar(255) not null ,
    val longtext not null,
    primary key (id, type)
);
