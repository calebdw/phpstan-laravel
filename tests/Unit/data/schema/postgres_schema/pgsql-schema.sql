--
-- PostgreSQL database dump
--

\restrict WiphKWY7CqcZzE444kj5RjUTTjHgH9azeyM1LwFhZZgeZgRo8tXaOMOXjtfyEHi

-- Dumped from database version 18.6 (Debian 18.6-1.pgdg13+2)
-- Dumped by pg_dump version 18.6 (Debian 18.6-1.pgdg13+2)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: reporting; Type: SCHEMA; Schema: -; Owner: -
--

CREATE SCHEMA reporting;


--
-- Name: account_status; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.account_status AS ENUM (
    'active',
    'suspended',
    'closed'
);


--
-- Name: positive_int; Type: DOMAIN; Schema: public; Owner: -
--

CREATE DOMAIN public.positive_int AS integer
	CONSTRAINT positive_int_check CHECK ((VALUE > 0));


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: posts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.posts (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    title character varying(255) NOT NULL,
    body text,
    published_at timestamp with time zone
);


--
-- Name: posts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.posts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: posts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.posts_id_seq OWNED BY public.posts.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    active boolean DEFAULT true NOT NULL,
    status public.account_status DEFAULT 'active'::public.account_status NOT NULL,
    login_count integer DEFAULT 0 NOT NULL,
    balance numeric(10,2),
    rating double precision,
    tags text[],
    meta jsonb,
    options json,
    uuid uuid,
    ip inet,
    description text,
    quota public.positive_int,
    birthday date,
    remember_token character varying(100),
    email_verified_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: summaries; Type: TABLE; Schema: reporting; Owner: -
--

CREATE TABLE reporting.summaries (
    id bigint NOT NULL,
    total numeric(12,2) DEFAULT 0 NOT NULL
);


--
-- Name: summaries_id_seq; Type: SEQUENCE; Schema: reporting; Owner: -
--

CREATE SEQUENCE reporting.summaries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: summaries_id_seq; Type: SEQUENCE OWNED BY; Schema: reporting; Owner: -
--

ALTER SEQUENCE reporting.summaries_id_seq OWNED BY reporting.summaries.id;


--
-- Name: posts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts ALTER COLUMN id SET DEFAULT nextval('public.posts_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: summaries id; Type: DEFAULT; Schema: reporting; Owner: -
--

ALTER TABLE ONLY reporting.summaries ALTER COLUMN id SET DEFAULT nextval('reporting.summaries_id_seq'::regclass);


--
-- Name: posts posts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts
    ADD CONSTRAINT posts_pkey PRIMARY KEY (id);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: summaries summaries_pkey; Type: CONSTRAINT; Schema: reporting; Owner: -
--

ALTER TABLE ONLY reporting.summaries
    ADD CONSTRAINT summaries_pkey PRIMARY KEY (id);


--
-- Name: posts posts_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.posts
    ADD CONSTRAINT posts_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict WiphKWY7CqcZzE444kj5RjUTTjHgH9azeyM1LwFhZZgeZgRo8tXaOMOXjtfyEHi

