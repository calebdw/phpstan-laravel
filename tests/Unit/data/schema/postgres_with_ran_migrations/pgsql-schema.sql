CREATE SCHEMA "audit log";
CREATE TABLE "audit log".app_history (
    id integer NOT NULL,
    batch integer NOT NULL,
    migration character varying(255) NOT NULL
);

CREATE TABLE public.dump_posts (
    id bigint NOT NULL,
    title character varying(255) NOT NULL,
    status character varying(255) NOT NULL
);

COPY "audit log".app_history (id, batch, migration) FROM stdin;
1	1	2020_01_01_000000_create_posts_table
2	1	2020_01_02_000000_add_status_to_posts_table
\.
