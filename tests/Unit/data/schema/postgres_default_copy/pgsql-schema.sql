CREATE TABLE public.dump_posts (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    status character varying(255) NOT NULL
);

COPY public.migrations (id, migration, batch) FROM stdin;
1	2020_01_01_000000_create_posts_table	1
2	2020_01_02_000000_add_status_to_posts_table	1
\.
