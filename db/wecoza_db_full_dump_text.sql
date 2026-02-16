--
-- PostgreSQL database dump
--

\restrict YZKLCwJqIHseTelKkfqY8aaQpcxUFHmFjPIRt5B8diuvrRX6GQPMqqaSrR9l8wW

-- Dumped from database version 16.11 (Ubuntu 16.11-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 18.2 (Ubuntu 18.2-1.pgdg22.04+1)

-- Started on 2026-02-16 13:33:57 SAST

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
-- TOC entry 5 (class 2615 OID 17280)
-- Name: archive; Type: SCHEMA; Schema: -; Owner: John
--

CREATE SCHEMA archive;


ALTER SCHEMA archive OWNER TO "John";

--
-- TOC entry 4231 (class 0 OID 0)
-- Dependencies: 7
-- Name: SCHEMA public; Type: COMMENT; Schema: -; Owner: pg_database_owner
--

COMMENT ON SCHEMA public IS '';


--
-- TOC entry 6 (class 2615 OID 17281)
-- Name: wecoza_events; Type: SCHEMA; Schema: -; Owner: John
--

CREATE SCHEMA wecoza_events;


ALTER SCHEMA wecoza_events OWNER TO "John";

--
-- TOC entry 4233 (class 0 OID 0)
-- Dependencies: 6
-- Name: SCHEMA wecoza_events; Type: COMMENT; Schema: -; Owner: John
--

COMMENT ON SCHEMA wecoza_events IS 'WeCoza Events Plugin schema for notifications, events, and dashboard management';


--
-- TOC entry 315 (class 1255 OID 17282)
-- Name: fn_sites_same_client(); Type: FUNCTION; Schema: public; Owner: John
--

CREATE FUNCTION public.fn_sites_same_client() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE parent_client_id INT;
BEGIN
  IF NEW.parent_site_id IS NULL THEN
    RETURN NEW;
  END IF;

  SELECT client_id INTO parent_client_id
  FROM public.sites
  WHERE site_id = NEW.parent_site_id;

  IF parent_client_id IS NULL THEN
    RAISE EXCEPTION 'Parent site % does not exist', NEW.parent_site_id;
  END IF;

  IF NEW.client_id <> parent_client_id THEN
    RAISE EXCEPTION 'Child (client_id=%) must match parent (client_id=%)',
      NEW.client_id, parent_client_id;
  END IF;

  RETURN NEW;
END$$;


ALTER FUNCTION public.fn_sites_same_client() OWNER TO "John";

--
-- TOC entry 316 (class 1255 OID 17284)
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: John
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_updated_at_column() OWNER TO "John";

--
-- TOC entry 4234 (class 0 OID 0)
-- Dependencies: 316
-- Name: FUNCTION update_updated_at_column(); Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON FUNCTION public.update_updated_at_column() IS 'Generic trigger function to auto-update updated_at column on UPDATE';


--
-- TOC entry 317 (class 1255 OID 17285)
-- Name: get_dashboard_statistics(); Type: FUNCTION; Schema: wecoza_events; Owner: John
--

CREATE FUNCTION wecoza_events.get_dashboard_statistics() RETURNS TABLE(total_supervisors bigint, active_supervisors bigint, pending_notifications bigint, processed_events bigint, pending_tasks bigint, overdue_tasks bigint)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT
        (SELECT COUNT(*) FROM supervisors) as total_supervisors,
        (SELECT COUNT(*) FROM supervisors WHERE is_active = TRUE) as active_supervisors,
        (SELECT COUNT(*) FROM notification_queue WHERE status = 'pending') as pending_notifications,
        (SELECT COUNT(*) FROM events_log WHERE processed = TRUE) as processed_events,
        (SELECT COUNT(*) FROM dashboard_status WHERE task_status = 'pending') as pending_tasks,
        (SELECT COUNT(*) FROM dashboard_status WHERE task_status = 'pending' AND due_date < CURRENT_TIMESTAMP) as overdue_tasks;
END;
$$;


ALTER FUNCTION wecoza_events.get_dashboard_statistics() OWNER TO "John";

--
-- TOC entry 318 (class 1255 OID 17286)
-- Name: get_pending_notifications(integer); Type: FUNCTION; Schema: wecoza_events; Owner: John
--

CREATE FUNCTION wecoza_events.get_pending_notifications(limit_count integer DEFAULT 50) RETURNS TABLE(id integer, event_name character varying, recipient_email character varying, template_name character varying, payload jsonb, scheduled_at timestamp with time zone)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT
        nq.id,
        nq.event_name,
        nq.recipient_email,
        nq.template_name,
        nq.payload,
        nq.scheduled_at
    FROM notification_queue nq
    WHERE nq.status = 'pending'
        AND nq.scheduled_at <= CURRENT_TIMESTAMP
        AND nq.attempts < nq.max_attempts
    ORDER BY nq.scheduled_at ASC
    LIMIT limit_count;
END;
$$;


ALTER FUNCTION wecoza_events.get_pending_notifications(limit_count integer) OWNER TO "John";

--
-- TOC entry 319 (class 1255 OID 17287)
-- Name: get_unprocessed_events(integer); Type: FUNCTION; Schema: wecoza_events; Owner: John
--

CREATE FUNCTION wecoza_events.get_unprocessed_events(limit_count integer DEFAULT 50) RETURNS TABLE(id integer, event_name character varying, event_payload jsonb, class_id integer, actor_id integer, idempotency_key character varying, occurred_at timestamp with time zone)
    LANGUAGE plpgsql
    AS $$
BEGIN
    RETURN QUERY
    SELECT
        el.id,
        el.event_name,
        el.event_payload,
        el.class_id,
        el.actor_id,
        el.idempotency_key,
        el.occurred_at
    FROM events_log el
    WHERE el.processed = FALSE
    ORDER BY el.occurred_at ASC
    LIMIT limit_count;
END;
$$;


ALTER FUNCTION wecoza_events.get_unprocessed_events(limit_count integer) OWNER TO "John";

--
-- TOC entry 320 (class 1255 OID 17288)
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: wecoza_events; Owner: John
--

CREATE FUNCTION wecoza_events.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION wecoza_events.update_updated_at_column() OWNER TO "John";

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- TOC entry 217 (class 1259 OID 17289)
-- Name: agent_absences; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.agent_absences (
    absence_id integer NOT NULL,
    agent_id integer,
    class_id integer,
    absence_date date,
    reason text,
    reported_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.agent_absences OWNER TO "John";

--
-- TOC entry 4235 (class 0 OID 0)
-- Dependencies: 217
-- Name: TABLE agent_absences; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.agent_absences IS 'Records instances when agents are absent from classes';


--
-- TOC entry 4236 (class 0 OID 0)
-- Dependencies: 217
-- Name: COLUMN agent_absences.absence_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_absences.absence_id IS 'Unique internal absence ID';


--
-- TOC entry 4237 (class 0 OID 0)
-- Dependencies: 217
-- Name: COLUMN agent_absences.agent_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_absences.agent_id IS 'Reference to the absent agent';


--
-- TOC entry 4238 (class 0 OID 0)
-- Dependencies: 217
-- Name: COLUMN agent_absences.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_absences.class_id IS 'Reference to the class affected by the absence';


--
-- TOC entry 4239 (class 0 OID 0)
-- Dependencies: 217
-- Name: COLUMN agent_absences.absence_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_absences.absence_date IS 'Date of the agent''s absence';


--
-- TOC entry 4240 (class 0 OID 0)
-- Dependencies: 217
-- Name: COLUMN agent_absences.reason; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_absences.reason IS 'Reason for the agent''s absence';


--
-- TOC entry 4241 (class 0 OID 0)
-- Dependencies: 217
-- Name: COLUMN agent_absences.reported_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_absences.reported_at IS 'Timestamp when the absence was reported';


--
-- TOC entry 218 (class 1259 OID 17295)
-- Name: agent_absences_absence_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.agent_absences_absence_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_absences_absence_id_seq OWNER TO "John";

--
-- TOC entry 4242 (class 0 OID 0)
-- Dependencies: 218
-- Name: agent_absences_absence_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.agent_absences_absence_id_seq OWNED BY public.agent_absences.absence_id;


--
-- TOC entry 312 (class 1259 OID 24744)
-- Name: agent_meta; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.agent_meta (
    meta_id integer NOT NULL,
    agent_id integer NOT NULL,
    meta_key character varying(255) NOT NULL,
    meta_value text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.agent_meta OWNER TO "John";

--
-- TOC entry 311 (class 1259 OID 24743)
-- Name: agent_meta_meta_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.agent_meta_meta_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_meta_meta_id_seq OWNER TO "John";

--
-- TOC entry 4243 (class 0 OID 0)
-- Dependencies: 311
-- Name: agent_meta_meta_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.agent_meta_meta_id_seq OWNED BY public.agent_meta.meta_id;


--
-- TOC entry 219 (class 1259 OID 17296)
-- Name: agent_notes; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.agent_notes (
    note_id integer NOT NULL,
    agent_id integer,
    note text,
    note_date timestamp without time zone DEFAULT now()
);


ALTER TABLE public.agent_notes OWNER TO "John";

--
-- TOC entry 4244 (class 0 OID 0)
-- Dependencies: 219
-- Name: TABLE agent_notes; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.agent_notes IS 'Stores historical notes and remarks about agents';


--
-- TOC entry 4245 (class 0 OID 0)
-- Dependencies: 219
-- Name: COLUMN agent_notes.note_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_notes.note_id IS 'Unique internal note ID';


--
-- TOC entry 4246 (class 0 OID 0)
-- Dependencies: 219
-- Name: COLUMN agent_notes.agent_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_notes.agent_id IS 'Reference to the agent';


--
-- TOC entry 4247 (class 0 OID 0)
-- Dependencies: 219
-- Name: COLUMN agent_notes.note; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_notes.note IS 'Content of the note regarding the agent';


--
-- TOC entry 4248 (class 0 OID 0)
-- Dependencies: 219
-- Name: COLUMN agent_notes.note_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_notes.note_date IS 'Timestamp when the note was created';


--
-- TOC entry 220 (class 1259 OID 17302)
-- Name: agent_notes_note_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.agent_notes_note_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_notes_note_id_seq OWNER TO "John";

--
-- TOC entry 4249 (class 0 OID 0)
-- Dependencies: 220
-- Name: agent_notes_note_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.agent_notes_note_id_seq OWNED BY public.agent_notes.note_id;


--
-- TOC entry 221 (class 1259 OID 17303)
-- Name: agent_orders; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.agent_orders (
    order_id integer NOT NULL,
    agent_id integer,
    class_id integer,
    order_number character varying(50),
    class_time time without time zone,
    class_days character varying(50),
    order_hours integer,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.agent_orders OWNER TO "John";

--
-- TOC entry 4250 (class 0 OID 0)
-- Dependencies: 221
-- Name: TABLE agent_orders; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.agent_orders IS 'Stores order information related to agents and classes';


--
-- TOC entry 4251 (class 0 OID 0)
-- Dependencies: 221
-- Name: COLUMN agent_orders.order_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_orders.order_id IS 'Unique internal order ID';


--
-- TOC entry 4252 (class 0 OID 0)
-- Dependencies: 221
-- Name: COLUMN agent_orders.agent_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_orders.agent_id IS 'Reference to the agent';


--
-- TOC entry 4253 (class 0 OID 0)
-- Dependencies: 221
-- Name: COLUMN agent_orders.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_orders.class_id IS 'Reference to the class';


--
-- TOC entry 4254 (class 0 OID 0)
-- Dependencies: 221
-- Name: COLUMN agent_orders.order_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_orders.order_number IS 'Valid order number associated with the agent';


--
-- TOC entry 4255 (class 0 OID 0)
-- Dependencies: 221
-- Name: COLUMN agent_orders.class_time; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_orders.class_time IS 'Time when the class is scheduled';


--
-- TOC entry 4256 (class 0 OID 0)
-- Dependencies: 221
-- Name: COLUMN agent_orders.class_days; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_orders.class_days IS 'Days when the class is scheduled';


--
-- TOC entry 4257 (class 0 OID 0)
-- Dependencies: 221
-- Name: COLUMN agent_orders.order_hours; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_orders.order_hours IS 'Number of hours linked to the agent''s order for a specific class';


--
-- TOC entry 4258 (class 0 OID 0)
-- Dependencies: 221
-- Name: COLUMN agent_orders.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_orders.created_at IS 'Timestamp when the order record was created';


--
-- TOC entry 4259 (class 0 OID 0)
-- Dependencies: 221
-- Name: COLUMN agent_orders.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_orders.updated_at IS 'Timestamp when the order record was last updated';


--
-- TOC entry 222 (class 1259 OID 17308)
-- Name: agent_orders_order_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.agent_orders_order_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_orders_order_id_seq OWNER TO "John";

--
-- TOC entry 4260 (class 0 OID 0)
-- Dependencies: 222
-- Name: agent_orders_order_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.agent_orders_order_id_seq OWNED BY public.agent_orders.order_id;


--
-- TOC entry 223 (class 1259 OID 17309)
-- Name: agent_replacements; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.agent_replacements (
    replacement_id integer NOT NULL,
    class_id integer,
    original_agent_id integer,
    replacement_agent_id integer,
    start_date date,
    end_date date,
    reason text
);


ALTER TABLE public.agent_replacements OWNER TO "John";

--
-- TOC entry 4261 (class 0 OID 0)
-- Dependencies: 223
-- Name: TABLE agent_replacements; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.agent_replacements IS 'Records instances of agent replacements in classes';


--
-- TOC entry 4262 (class 0 OID 0)
-- Dependencies: 223
-- Name: COLUMN agent_replacements.replacement_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_replacements.replacement_id IS 'Unique internal replacement ID';


--
-- TOC entry 4263 (class 0 OID 0)
-- Dependencies: 223
-- Name: COLUMN agent_replacements.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_replacements.class_id IS 'Reference to the class';


--
-- TOC entry 4264 (class 0 OID 0)
-- Dependencies: 223
-- Name: COLUMN agent_replacements.original_agent_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_replacements.original_agent_id IS 'Reference to the original agent';


--
-- TOC entry 4265 (class 0 OID 0)
-- Dependencies: 223
-- Name: COLUMN agent_replacements.replacement_agent_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_replacements.replacement_agent_id IS 'Reference to the replacement agent';


--
-- TOC entry 4266 (class 0 OID 0)
-- Dependencies: 223
-- Name: COLUMN agent_replacements.start_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_replacements.start_date IS 'Date when the replacement starts';


--
-- TOC entry 4267 (class 0 OID 0)
-- Dependencies: 223
-- Name: COLUMN agent_replacements.end_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_replacements.end_date IS 'Date when the replacement ends';


--
-- TOC entry 4268 (class 0 OID 0)
-- Dependencies: 223
-- Name: COLUMN agent_replacements.reason; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agent_replacements.reason IS 'Reason for the agent''s replacement';


--
-- TOC entry 224 (class 1259 OID 17314)
-- Name: agent_replacements_replacement_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.agent_replacements_replacement_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agent_replacements_replacement_id_seq OWNER TO "John";

--
-- TOC entry 4269 (class 0 OID 0)
-- Dependencies: 224
-- Name: agent_replacements_replacement_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.agent_replacements_replacement_id_seq OWNED BY public.agent_replacements.replacement_id;


--
-- TOC entry 225 (class 1259 OID 17315)
-- Name: agents; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.agents (
    agent_id integer NOT NULL,
    first_name character varying(50),
    initials character varying(10),
    surname character varying(50),
    gender character varying(10),
    race character varying(20),
    sa_id_no character varying(20),
    passport_number character varying(20),
    tel_number character varying(20),
    email_address character varying(100),
    residential_address_line character varying(100),
    residential_suburb character varying(50),
    residential_postal_code character varying(10),
    preferred_working_area_1 integer,
    preferred_working_area_2 integer,
    preferred_working_area_3 integer,
    highest_qualification character varying(100),
    sace_number character varying(50),
    sace_registration_date date,
    sace_expiry_date date,
    quantum_assessment numeric(5,2),
    agent_training_date date,
    bank_name character varying(50),
    bank_branch_code character varying(20),
    bank_account_number character varying(30),
    signed_agreement_date date,
    agent_notes text,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    title character varying(50),
    id_type character varying(20) DEFAULT 'sa_id'::character varying,
    address_line_2 character varying(255),
    criminal_record_date date,
    criminal_record_file character varying(500),
    province character varying(100),
    city character varying(100),
    phase_registered character varying(50),
    subjects_registered text,
    account_holder character varying(100),
    account_type character varying(50),
    status character varying(50) DEFAULT 'active'::character varying,
    created_by integer,
    updated_by integer,
    second_name character varying(50),
    signed_agreement_file character varying(255),
    quantum_maths_score integer DEFAULT 0,
    quantum_science_score integer DEFAULT 0,
    CONSTRAINT agents_account_type_check CHECK (((account_type)::text = ANY (ARRAY[('Savings'::character varying)::text, ('Current'::character varying)::text, ('Transmission'::character varying)::text]))),
    CONSTRAINT agents_gender_check CHECK (((gender)::text = ANY (ARRAY[('M'::character varying)::text, ('F'::character varying)::text, ('Male'::character varying)::text, ('Female'::character varying)::text]))),
    CONSTRAINT agents_id_type_check CHECK (((id_type)::text = ANY (ARRAY[('sa_id'::character varying)::text, ('passport'::character varying)::text]))),
    CONSTRAINT agents_phase_registered_check CHECK (((phase_registered)::text = ANY (ARRAY[('Foundation'::character varying)::text, ('Intermediate'::character varying)::text, ('Senior'::character varying)::text, ('FET'::character varying)::text]))),
    CONSTRAINT agents_race_check CHECK (((race)::text = ANY (ARRAY[('African'::character varying)::text, ('Coloured'::character varying)::text, ('White'::character varying)::text, ('Indian'::character varying)::text]))),
    CONSTRAINT agents_status_check CHECK (((status)::text = ANY (ARRAY[('active'::character varying)::text, ('inactive'::character varying)::text, ('suspended'::character varying)::text, ('deleted'::character varying)::text]))),
    CONSTRAINT agents_title_check CHECK (((title)::text = ANY (ARRAY[('Mr'::character varying)::text, ('Mrs'::character varying)::text, ('Ms'::character varying)::text, ('Miss'::character varying)::text, ('Dr'::character varying)::text, ('Prof'::character varying)::text]))),
    CONSTRAINT quantum_maths_score_range CHECK (((quantum_maths_score >= 0) AND (quantum_maths_score <= 100))),
    CONSTRAINT quantum_science_score_range CHECK (((quantum_science_score >= 0) AND (quantum_science_score <= 100)))
);


ALTER TABLE public.agents OWNER TO "John";

--
-- TOC entry 4270 (class 0 OID 0)
-- Dependencies: 225
-- Name: TABLE agents; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.agents IS 'Stores information about agents (instructors or facilitators)';


--
-- TOC entry 4271 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.agent_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.agent_id IS 'Unique internal agent ID';


--
-- TOC entry 4272 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.first_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.first_name IS 'Agent''s first name';


--
-- TOC entry 4273 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.initials; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.initials IS 'Agent''s initials';


--
-- TOC entry 4274 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.surname; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.surname IS 'Agent''s surname';


--
-- TOC entry 4275 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.gender; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.gender IS 'Agent''s gender';


--
-- TOC entry 4276 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.race; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.race IS 'Agent''s race; options include ''African'', ''Coloured'', ''White'', ''Indian''';


--
-- TOC entry 4277 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.sa_id_no; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.sa_id_no IS 'Agent''s South African ID number';


--
-- TOC entry 4278 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.passport_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.passport_number IS 'Agent''s passport number if they are a foreigner';


--
-- TOC entry 4279 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.tel_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.tel_number IS 'Agent''s primary telephone number';


--
-- TOC entry 4280 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.email_address; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.email_address IS 'Agent''s email address';


--
-- TOC entry 4281 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.residential_address_line; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.residential_address_line IS 'Agent''s residential street address';


--
-- TOC entry 4282 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.residential_suburb; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.residential_suburb IS 'Agent''s residential suburb';


--
-- TOC entry 4283 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.residential_postal_code; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.residential_postal_code IS 'Postal code of the agent''s residential area';


--
-- TOC entry 4284 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.preferred_working_area_1; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.preferred_working_area_1 IS 'Agent''s first preferred working area';


--
-- TOC entry 4285 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.preferred_working_area_2; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.preferred_working_area_2 IS 'Agent''s second preferred working area';


--
-- TOC entry 4286 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.preferred_working_area_3; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.preferred_working_area_3 IS 'Agent''s third preferred working area';


--
-- TOC entry 4287 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.highest_qualification; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.highest_qualification IS 'Highest qualification the agent has achieved';


--
-- TOC entry 4288 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.sace_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.sace_number IS 'Agent''s SACE (South African Council for Educators) registration number';


--
-- TOC entry 4289 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.sace_registration_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.sace_registration_date IS 'Date when the agent''s SACE registration became effective';


--
-- TOC entry 4290 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.sace_expiry_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.sace_expiry_date IS 'Expiry date of the agent''s provisional SACE registration';


--
-- TOC entry 4291 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.quantum_assessment; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.quantum_assessment IS 'Agent''s competence score in Communications (percentage)';


--
-- TOC entry 4292 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.agent_training_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.agent_training_date IS 'Date when the agent received induction training';


--
-- TOC entry 4293 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.bank_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.bank_name IS 'Name of the agent''s bank';


--
-- TOC entry 4294 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.bank_branch_code; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.bank_branch_code IS 'Branch code of the agent''s bank';


--
-- TOC entry 4295 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.bank_account_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.bank_account_number IS 'Agent''s bank account number';


--
-- TOC entry 4296 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.signed_agreement_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.signed_agreement_date IS 'Date when the agent signed the agreement';


--
-- TOC entry 4297 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.agent_notes; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.agent_notes IS 'Notes regarding the agent''s performance, issues, or other relevant information';


--
-- TOC entry 4298 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.created_at IS 'Timestamp when the agent record was created';


--
-- TOC entry 4299 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.updated_at IS 'Timestamp when the agent record was last updated';


--
-- TOC entry 4300 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.title; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.title IS 'Agent''s title (Mr, Mrs, Ms, etc)';


--
-- TOC entry 4301 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.id_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.id_type IS 'Type of identification: sa_id or passport';


--
-- TOC entry 4302 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.address_line_2; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.address_line_2 IS 'Additional address information';


--
-- TOC entry 4303 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.criminal_record_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.criminal_record_date IS 'Date of criminal record check';


--
-- TOC entry 4304 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.criminal_record_file; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.criminal_record_file IS 'Path to criminal record check file';


--
-- TOC entry 4305 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.province; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.province IS 'Province where the agent resides';


--
-- TOC entry 4306 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.city; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.city IS 'City where the agent resides';


--
-- TOC entry 4307 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.phase_registered; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.phase_registered IS 'Educational phase the agent is registered for';


--
-- TOC entry 4308 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.subjects_registered; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.subjects_registered IS 'Subjects the agent is registered to teach';


--
-- TOC entry 4309 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.account_holder; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.account_holder IS 'Name of the bank account holder';


--
-- TOC entry 4310 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.account_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.account_type IS 'Type of bank account (Savings, Current, etc)';


--
-- TOC entry 4311 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.status IS 'Current status of the agent';


--
-- TOC entry 4312 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.created_by; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.created_by IS 'User ID who created the record';


--
-- TOC entry 4313 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.updated_by; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.updated_by IS 'User ID who last updated the record';


--
-- TOC entry 4314 (class 0 OID 0)
-- Dependencies: 225
-- Name: COLUMN agents.second_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.agents.second_name IS 'Second name of the agent (middle name)';


--
-- TOC entry 226 (class 1259 OID 17335)
-- Name: agents_agent_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.agents_agent_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.agents_agent_id_seq OWNER TO "John";

--
-- TOC entry 4315 (class 0 OID 0)
-- Dependencies: 226
-- Name: agents_agent_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.agents_agent_id_seq OWNED BY public.agents.agent_id;


--
-- TOC entry 227 (class 1259 OID 17336)
-- Name: attendance_registers; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.attendance_registers (
    register_id integer NOT NULL,
    class_id integer,
    date date,
    agent_id integer,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.attendance_registers OWNER TO "John";

--
-- TOC entry 4316 (class 0 OID 0)
-- Dependencies: 227
-- Name: TABLE attendance_registers; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.attendance_registers IS 'Records attendance registers for classes';


--
-- TOC entry 4317 (class 0 OID 0)
-- Dependencies: 227
-- Name: COLUMN attendance_registers.register_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.attendance_registers.register_id IS 'Unique internal attendance register ID';


--
-- TOC entry 4318 (class 0 OID 0)
-- Dependencies: 227
-- Name: COLUMN attendance_registers.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.attendance_registers.class_id IS 'Reference to the class';


--
-- TOC entry 4319 (class 0 OID 0)
-- Dependencies: 227
-- Name: COLUMN attendance_registers.date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.attendance_registers.date IS 'Date of the attendance';


--
-- TOC entry 4320 (class 0 OID 0)
-- Dependencies: 227
-- Name: COLUMN attendance_registers.agent_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.attendance_registers.agent_id IS 'Reference to the agent who conducted the attendance';


--
-- TOC entry 4321 (class 0 OID 0)
-- Dependencies: 227
-- Name: COLUMN attendance_registers.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.attendance_registers.created_at IS 'Timestamp when the attendance register was created';


--
-- TOC entry 4322 (class 0 OID 0)
-- Dependencies: 227
-- Name: COLUMN attendance_registers.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.attendance_registers.updated_at IS 'Timestamp when the attendance register was last updated';


--
-- TOC entry 228 (class 1259 OID 17341)
-- Name: attendance_registers_register_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.attendance_registers_register_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.attendance_registers_register_id_seq OWNER TO "John";

--
-- TOC entry 4323 (class 0 OID 0)
-- Dependencies: 228
-- Name: attendance_registers_register_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.attendance_registers_register_id_seq OWNED BY public.attendance_registers.register_id;


--
-- TOC entry 229 (class 1259 OID 17342)
-- Name: class_agents; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.class_agents (
    class_id integer NOT NULL,
    agent_id integer NOT NULL,
    start_date date NOT NULL,
    end_date date,
    role character varying(50)
);


ALTER TABLE public.class_agents OWNER TO "John";

--
-- TOC entry 4324 (class 0 OID 0)
-- Dependencies: 229
-- Name: TABLE class_agents; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.class_agents IS 'Associates agents with classes they facilitate, including their roles and durations';


--
-- TOC entry 4325 (class 0 OID 0)
-- Dependencies: 229
-- Name: COLUMN class_agents.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_agents.class_id IS 'Reference to the class';


--
-- TOC entry 4326 (class 0 OID 0)
-- Dependencies: 229
-- Name: COLUMN class_agents.agent_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_agents.agent_id IS 'Reference to the agent facilitating the class';


--
-- TOC entry 4327 (class 0 OID 0)
-- Dependencies: 229
-- Name: COLUMN class_agents.start_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_agents.start_date IS 'Date when the agent started facilitating the class';


--
-- TOC entry 4328 (class 0 OID 0)
-- Dependencies: 229
-- Name: COLUMN class_agents.end_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_agents.end_date IS 'Date when the agent stopped facilitating the class';


--
-- TOC entry 4329 (class 0 OID 0)
-- Dependencies: 229
-- Name: COLUMN class_agents.role; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_agents.role IS 'Role of the agent in the class (e.g., ''Original'', ''Backup'', ''Replacement'')';


--
-- TOC entry 310 (class 1259 OID 24717)
-- Name: class_events; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.class_events (
    event_id integer NOT NULL,
    event_type character varying(50) NOT NULL,
    entity_type character varying(50) NOT NULL,
    entity_id integer NOT NULL,
    user_id integer,
    event_data jsonb DEFAULT '{}'::jsonb NOT NULL,
    ai_summary jsonb,
    notification_status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    enriched_at timestamp with time zone,
    sent_at timestamp with time zone,
    viewed_at timestamp with time zone,
    acknowledged_at timestamp with time zone,
    CONSTRAINT chk_entity_type CHECK (((entity_type)::text = ANY ((ARRAY['class'::character varying, 'learner'::character varying])::text[]))),
    CONSTRAINT chk_event_type CHECK (((event_type)::text = ANY ((ARRAY['CLASS_INSERT'::character varying, 'CLASS_UPDATE'::character varying, 'CLASS_DELETE'::character varying, 'LEARNER_ADD'::character varying, 'LEARNER_REMOVE'::character varying, 'LEARNER_UPDATE'::character varying, 'STATUS_CHANGE'::character varying])::text[]))),
    CONSTRAINT chk_notification_status CHECK (((notification_status)::text = ANY ((ARRAY['pending'::character varying, 'enriching'::character varying, 'sending'::character varying, 'sent'::character varying, 'failed'::character varying])::text[])))
);


ALTER TABLE public.class_events OWNER TO "John";

--
-- TOC entry 4330 (class 0 OID 0)
-- Dependencies: 310
-- Name: TABLE class_events; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.class_events IS 'Application-level event storage for WeCoza notification system. Events are captured from class and learner operations, enriched with AI summaries, and processed into notifications.';


--
-- TOC entry 4331 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.event_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.event_id IS 'Auto-incrementing primary key';


--
-- TOC entry 4332 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.event_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.event_type IS 'Type of event: CLASS_INSERT, CLASS_UPDATE, CLASS_DELETE, LEARNER_ADD, LEARNER_REMOVE, LEARNER_UPDATE, STATUS_CHANGE';


--
-- TOC entry 4333 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.entity_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.entity_type IS 'Entity being tracked: class or learner';


--
-- TOC entry 4334 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.entity_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.entity_id IS 'ID of the entity (class_id or learner_id)';


--
-- TOC entry 4335 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.user_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.user_id IS 'WordPress user ID who triggered the event (null for system events)';


--
-- TOC entry 4336 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.event_data; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.event_data IS 'JSONB payload containing new_row, old_row, diff, and metadata';


--
-- TOC entry 4337 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.ai_summary; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.ai_summary IS 'AI-generated summary of the event for notifications';


--
-- TOC entry 4338 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.notification_status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.notification_status IS 'Workflow status: pending -> enriching -> sending -> sent (or failed)';


--
-- TOC entry 4339 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.created_at IS 'When the event was captured';


--
-- TOC entry 4340 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.enriched_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.enriched_at IS 'When AI enrichment completed';


--
-- TOC entry 4341 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.sent_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.sent_at IS 'When notification was delivered';


--
-- TOC entry 4342 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.viewed_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.viewed_at IS 'When user viewed the notification';


--
-- TOC entry 4343 (class 0 OID 0)
-- Dependencies: 310
-- Name: COLUMN class_events.acknowledged_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_events.acknowledged_at IS 'When user acknowledged/dismissed the notification';


--
-- TOC entry 309 (class 1259 OID 24716)
-- Name: class_events_event_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.class_events_event_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_events_event_id_seq OWNER TO "John";

--
-- TOC entry 4344 (class 0 OID 0)
-- Dependencies: 309
-- Name: class_events_event_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.class_events_event_id_seq OWNED BY public.class_events.event_id;


--
-- TOC entry 230 (class 1259 OID 17353)
-- Name: class_material_tracking; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.class_material_tracking (
    id integer NOT NULL,
    class_id integer NOT NULL,
    notification_type character varying(20) NOT NULL,
    notification_sent_at timestamp without time zone,
    materials_delivered_at timestamp without time zone,
    delivery_status character varying(20) DEFAULT 'pending'::character varying,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    CONSTRAINT class_material_tracking_delivery_status_check CHECK (((delivery_status)::text = ANY (ARRAY[('pending'::character varying)::text, ('notified'::character varying)::text, ('delivered'::character varying)::text]))),
    CONSTRAINT class_material_tracking_notification_type_check CHECK (((notification_type)::text = ANY (ARRAY[('orange'::character varying)::text, ('red'::character varying)::text])))
);


ALTER TABLE public.class_material_tracking OWNER TO "John";

--
-- TOC entry 4345 (class 0 OID 0)
-- Dependencies: 230
-- Name: TABLE class_material_tracking; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.class_material_tracking IS 'Tracks material delivery notifications and their status for classes. Prevents duplicate notifications for Orange (7-day) and Red (5-day) warnings.';


--
-- TOC entry 4346 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN class_material_tracking.id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_material_tracking.id IS 'Unique tracking record ID (auto-increment)';


--
-- TOC entry 4347 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN class_material_tracking.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_material_tracking.class_id IS 'Reference to the class requiring material delivery (FK to classes.class_id)';


--
-- TOC entry 4348 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN class_material_tracking.notification_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_material_tracking.notification_type IS 'Notification type: "orange" for 7 days before start, "red" for 5 days before start';


--
-- TOC entry 4349 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN class_material_tracking.notification_sent_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_material_tracking.notification_sent_at IS 'Timestamp when the notification email was successfully sent (NULL = not sent yet)';


--
-- TOC entry 4350 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN class_material_tracking.materials_delivered_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_material_tracking.materials_delivered_at IS 'Timestamp when materials were physically delivered/confirmed (NULL = not delivered yet)';


--
-- TOC entry 4351 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN class_material_tracking.delivery_status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_material_tracking.delivery_status IS 'Current delivery status: "pending" (initial), "notified" (email sent), "delivered" (materials confirmed)';


--
-- TOC entry 4352 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN class_material_tracking.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_material_tracking.created_at IS 'Record creation timestamp (automatically set)';


--
-- TOC entry 4353 (class 0 OID 0)
-- Dependencies: 230
-- Name: COLUMN class_material_tracking.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_material_tracking.updated_at IS 'Record last update timestamp (automatically updated via trigger)';


--
-- TOC entry 231 (class 1259 OID 17361)
-- Name: class_material_tracking_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.class_material_tracking_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_material_tracking_id_seq OWNER TO "John";

--
-- TOC entry 4354 (class 0 OID 0)
-- Dependencies: 231
-- Name: class_material_tracking_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.class_material_tracking_id_seq OWNED BY public.class_material_tracking.id;


--
-- TOC entry 232 (class 1259 OID 17362)
-- Name: class_notes; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.class_notes (
    note_id integer NOT NULL,
    class_id integer,
    note text,
    note_date timestamp without time zone DEFAULT now()
);


ALTER TABLE public.class_notes OWNER TO "John";

--
-- TOC entry 4355 (class 0 OID 0)
-- Dependencies: 232
-- Name: TABLE class_notes; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.class_notes IS 'Stores historical notes and remarks about classes';


--
-- TOC entry 4356 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN class_notes.note_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_notes.note_id IS 'Unique internal note ID';


--
-- TOC entry 4357 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN class_notes.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_notes.class_id IS 'Reference to the class';


--
-- TOC entry 4358 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN class_notes.note; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_notes.note IS 'Content of the note regarding the class';


--
-- TOC entry 4359 (class 0 OID 0)
-- Dependencies: 232
-- Name: COLUMN class_notes.note_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_notes.note_date IS 'Timestamp when the note was created';


--
-- TOC entry 233 (class 1259 OID 17368)
-- Name: class_notes_note_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.class_notes_note_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_notes_note_id_seq OWNER TO "John";

--
-- TOC entry 4360 (class 0 OID 0)
-- Dependencies: 233
-- Name: class_notes_note_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.class_notes_note_id_seq OWNED BY public.class_notes.note_id;


--
-- TOC entry 234 (class 1259 OID 17369)
-- Name: class_schedules; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.class_schedules (
    schedule_id integer NOT NULL,
    class_id integer,
    day_of_week character varying(10),
    start_time time without time zone,
    end_time time without time zone
);


ALTER TABLE public.class_schedules OWNER TO "John";

--
-- TOC entry 4361 (class 0 OID 0)
-- Dependencies: 234
-- Name: TABLE class_schedules; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.class_schedules IS 'Stores scheduling information for classes';


--
-- TOC entry 4362 (class 0 OID 0)
-- Dependencies: 234
-- Name: COLUMN class_schedules.schedule_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_schedules.schedule_id IS 'Unique internal schedule ID';


--
-- TOC entry 4363 (class 0 OID 0)
-- Dependencies: 234
-- Name: COLUMN class_schedules.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_schedules.class_id IS 'Reference to the class';


--
-- TOC entry 4364 (class 0 OID 0)
-- Dependencies: 234
-- Name: COLUMN class_schedules.day_of_week; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_schedules.day_of_week IS 'Day of the week when the class occurs (e.g., ''Monday'')';


--
-- TOC entry 4365 (class 0 OID 0)
-- Dependencies: 234
-- Name: COLUMN class_schedules.start_time; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_schedules.start_time IS 'Class start time';


--
-- TOC entry 4366 (class 0 OID 0)
-- Dependencies: 234
-- Name: COLUMN class_schedules.end_time; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_schedules.end_time IS 'Class end time';


--
-- TOC entry 235 (class 1259 OID 17372)
-- Name: class_schedules_schedule_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.class_schedules_schedule_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_schedules_schedule_id_seq OWNER TO "John";

--
-- TOC entry 4367 (class 0 OID 0)
-- Dependencies: 235
-- Name: class_schedules_schedule_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.class_schedules_schedule_id_seq OWNED BY public.class_schedules.schedule_id;


--
-- TOC entry 236 (class 1259 OID 17373)
-- Name: class_subjects; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.class_subjects (
    class_id integer NOT NULL,
    product_id integer NOT NULL
);


ALTER TABLE public.class_subjects OWNER TO "John";

--
-- TOC entry 4368 (class 0 OID 0)
-- Dependencies: 236
-- Name: TABLE class_subjects; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.class_subjects IS 'Associates classes with the subjects or products being taught';


--
-- TOC entry 4369 (class 0 OID 0)
-- Dependencies: 236
-- Name: COLUMN class_subjects.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_subjects.class_id IS 'Reference to the class';


--
-- TOC entry 4370 (class 0 OID 0)
-- Dependencies: 236
-- Name: COLUMN class_subjects.product_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.class_subjects.product_id IS 'Reference to the subject or product taught in the class';


--
-- TOC entry 302 (class 1259 OID 24601)
-- Name: class_type_subjects; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.class_type_subjects (
    class_type_subject_id integer NOT NULL,
    class_type_id integer NOT NULL,
    subject_code character varying(50) NOT NULL,
    subject_name character varying(100) NOT NULL,
    subject_duration integer NOT NULL,
    display_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.class_type_subjects OWNER TO "John";

--
-- TOC entry 301 (class 1259 OID 24600)
-- Name: class_type_subjects_class_type_subject_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.class_type_subjects_class_type_subject_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_type_subjects_class_type_subject_id_seq OWNER TO "John";

--
-- TOC entry 4371 (class 0 OID 0)
-- Dependencies: 301
-- Name: class_type_subjects_class_type_subject_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.class_type_subjects_class_type_subject_id_seq OWNED BY public.class_type_subjects.class_type_subject_id;


--
-- TOC entry 300 (class 1259 OID 24584)
-- Name: class_types; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.class_types (
    class_type_id integer NOT NULL,
    class_type_code character varying(50) NOT NULL,
    class_type_name character varying(100) NOT NULL,
    subject_selection_mode character varying(20) NOT NULL,
    progression_total_hours integer,
    display_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    CONSTRAINT class_types_subject_selection_mode_check CHECK (((subject_selection_mode)::text = ANY ((ARRAY['own'::character varying, 'all_subjects'::character varying, 'progression'::character varying])::text[])))
);


ALTER TABLE public.class_types OWNER TO "John";

--
-- TOC entry 299 (class 1259 OID 24583)
-- Name: class_types_class_type_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.class_types_class_type_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.class_types_class_type_id_seq OWNER TO "John";

--
-- TOC entry 4372 (class 0 OID 0)
-- Dependencies: 299
-- Name: class_types_class_type_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.class_types_class_type_id_seq OWNED BY public.class_types.class_type_id;


--
-- TOC entry 237 (class 1259 OID 17376)
-- Name: classes; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.classes (
    class_id integer NOT NULL,
    client_id integer,
    class_address_line character varying(100),
    class_type character varying(50),
    original_start_date date,
    seta_funded boolean,
    seta character varying(100),
    exam_class boolean,
    exam_type character varying(50),
    project_supervisor_id integer,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    site_id integer,
    class_subject character varying(100),
    class_code character varying(50),
    class_duration integer,
    class_agent integer,
    learner_ids jsonb DEFAULT '[]'::jsonb,
    backup_agent_ids jsonb DEFAULT '[]'::jsonb,
    schedule_data jsonb DEFAULT '[]'::jsonb,
    stop_restart_dates jsonb DEFAULT '[]'::jsonb,
    class_notes_data jsonb DEFAULT '[]'::jsonb,
    initial_class_agent integer,
    initial_agent_start_date date,
    exam_learners jsonb DEFAULT '[]'::jsonb,
    order_nr character varying,
    event_dates jsonb DEFAULT '[]'::jsonb,
    order_nr_metadata jsonb
);


ALTER TABLE public.classes OWNER TO "John";

--
-- TOC entry 4373 (class 0 OID 0)
-- Dependencies: 237
-- Name: TABLE classes; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.classes IS 'Stores information about classes, including scheduling and associations';


--
-- TOC entry 4374 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.class_id IS 'Unique internal class ID';


--
-- TOC entry 4375 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.client_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.client_id IS 'Reference to the client associated with the class';


--
-- TOC entry 4376 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.class_address_line; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.class_address_line IS 'Street address where the class takes place';


--
-- TOC entry 4377 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.class_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.class_type IS 'Type of class; determines the ''rules'' (e.g., ''Employed'', ''Community'')';


--
-- TOC entry 4378 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.original_start_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.original_start_date IS 'Original start date of the class';


--
-- TOC entry 4379 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.seta_funded; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.seta_funded IS 'Indicates if the project is SETA funded (true) or not (false)';


--
-- TOC entry 4380 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.seta; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.seta IS 'Name of the SETA (Sector Education and Training Authority) the client belongs to';


--
-- TOC entry 4381 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.exam_class; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.exam_class IS 'Indicates if this is an exam project (true) or not (false)';


--
-- TOC entry 4382 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.exam_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.exam_type IS 'Type of exam associated with the class';


--
-- TOC entry 4383 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.project_supervisor_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.project_supervisor_id IS 'Reference to the project supervisor managing the class';


--
-- TOC entry 4384 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.created_at IS 'Timestamp when the class record was created';


--
-- TOC entry 4385 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.updated_at IS 'Timestamp when the class record was last updated';


--
-- TOC entry 4386 (class 0 OID 0)
-- Dependencies: 237
-- Name: COLUMN classes.exam_learners; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.classes.exam_learners IS 'JSON array storing exam learner IDs and 
  metadata for learners taking exams';


--
-- TOC entry 238 (class 1259 OID 17389)
-- Name: classes_class_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.classes_class_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.classes_class_id_seq OWNER TO "John";

--
-- TOC entry 4387 (class 0 OID 0)
-- Dependencies: 238
-- Name: classes_class_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.classes_class_id_seq OWNED BY public.classes.class_id;


--
-- TOC entry 239 (class 1259 OID 17390)
-- Name: client_communications; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.client_communications (
    communication_id integer NOT NULL,
    client_id integer,
    communication_type character varying(50),
    subject character varying(100),
    content text,
    communication_date timestamp without time zone DEFAULT now(),
    user_id integer,
    site_id integer
);


ALTER TABLE public.client_communications OWNER TO "John";

--
-- TOC entry 4388 (class 0 OID 0)
-- Dependencies: 239
-- Name: TABLE client_communications; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.client_communications IS 'Stores records of communications with clients';


--
-- TOC entry 4389 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN client_communications.communication_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.client_communications.communication_id IS 'Unique internal communication ID';


--
-- TOC entry 4390 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN client_communications.client_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.client_communications.client_id IS 'Reference to the client';


--
-- TOC entry 4391 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN client_communications.communication_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.client_communications.communication_type IS 'Type of communication (e.g., ''Email'', ''Phone Call'')';


--
-- TOC entry 4392 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN client_communications.subject; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.client_communications.subject IS 'Subject of the communication';


--
-- TOC entry 4393 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN client_communications.content; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.client_communications.content IS 'Content or summary of the communication';


--
-- TOC entry 4394 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN client_communications.communication_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.client_communications.communication_date IS 'Date and time when the communication occurred';


--
-- TOC entry 4395 (class 0 OID 0)
-- Dependencies: 239
-- Name: COLUMN client_communications.user_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.client_communications.user_id IS 'Reference to the user who communicated with the client';


--
-- TOC entry 240 (class 1259 OID 17396)
-- Name: client_communications_communication_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.client_communications_communication_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.client_communications_communication_id_seq OWNER TO "John";

--
-- TOC entry 4396 (class 0 OID 0)
-- Dependencies: 240
-- Name: client_communications_communication_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.client_communications_communication_id_seq OWNED BY public.client_communications.communication_id;


--
-- TOC entry 241 (class 1259 OID 17397)
-- Name: clients; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.clients (
    client_id integer NOT NULL,
    client_name character varying(100),
    company_registration_number character varying(50),
    seta character varying(100),
    client_status character varying(50),
    financial_year_end date,
    bbbee_verification_date date,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    main_client_id integer,
    contact_person character varying(100),
    contact_person_email character varying(100),
    contact_person_cellphone character varying(20),
    contact_person_tel character varying(20),
    contact_person_position character varying(50),
    deleted_at timestamp without time zone
);


ALTER TABLE public.clients OWNER TO "John";

--
-- TOC entry 4397 (class 0 OID 0)
-- Dependencies: 241
-- Name: TABLE clients; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.clients IS 'Stores information about clients (companies or organizations)';


--
-- TOC entry 4398 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.client_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.client_id IS 'Unique internal client ID';


--
-- TOC entry 4399 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.client_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.client_name IS 'Name of the client company or organization';


--
-- TOC entry 4400 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.company_registration_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.company_registration_number IS 'Company registration number of the client';


--
-- TOC entry 4401 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.seta; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.seta IS 'SETA the client belongs to';


--
-- TOC entry 4402 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.client_status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.client_status IS 'Current status of the client (e.g., ''Active Client'', ''Lost Client'')';


--
-- TOC entry 4403 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.financial_year_end; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.financial_year_end IS 'Date of the client''s financial year-end';


--
-- TOC entry 4404 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.bbbee_verification_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.bbbee_verification_date IS 'Date of the client''s BBBEE verification';


--
-- TOC entry 4405 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.created_at IS 'Timestamp when the client record was created';


--
-- TOC entry 4406 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.updated_at IS 'Timestamp when the client record was last updated';


--
-- TOC entry 4407 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.main_client_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.main_client_id IS 'Reference to the 
main client for sub-client relationships (NULL for main clients)';


--
-- TOC entry 4408 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.contact_person; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.contact_person IS 'Primary contact person name (consolidated approach for new clients)';


--
-- TOC entry 4409 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.contact_person_email; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.contact_person_email IS 'Primary contact person email';


--
-- TOC entry 4410 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.contact_person_cellphone; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.contact_person_cellphone IS 'Primary contact person cellphone';


--
-- TOC entry 4411 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.contact_person_tel; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.contact_person_tel IS 'Primary contact person landline';


--
-- TOC entry 4412 (class 0 OID 0)
-- Dependencies: 241
-- Name: COLUMN clients.contact_person_position; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.clients.contact_person_position IS 'Primary contact person job position';


--
-- TOC entry 242 (class 1259 OID 17404)
-- Name: clients_client_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.clients_client_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.clients_client_id_seq OWNER TO "John";

--
-- TOC entry 4413 (class 0 OID 0)
-- Dependencies: 242
-- Name: clients_client_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.clients_client_id_seq OWNED BY public.clients.client_id;


--
-- TOC entry 243 (class 1259 OID 17405)
-- Name: collections; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.collections (
    collection_id integer NOT NULL,
    class_id integer,
    collection_date date,
    items text,
    status character varying(20),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.collections OWNER TO "John";

--
-- TOC entry 4414 (class 0 OID 0)
-- Dependencies: 243
-- Name: TABLE collections; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.collections IS 'Records collections made from classes';


--
-- TOC entry 4415 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN collections.collection_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.collections.collection_id IS 'Unique internal collection ID';


--
-- TOC entry 4416 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN collections.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.collections.class_id IS 'Reference to the class';


--
-- TOC entry 4417 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN collections.collection_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.collections.collection_date IS 'Date when the collection is scheduled or occurred';


--
-- TOC entry 4418 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN collections.items; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.collections.items IS 'Items collected from the class';


--
-- TOC entry 4419 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN collections.status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.collections.status IS 'Collection status (e.g., ''Pending'', ''Collected'')';


--
-- TOC entry 4420 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN collections.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.collections.created_at IS 'Timestamp when the collection record was created';


--
-- TOC entry 4421 (class 0 OID 0)
-- Dependencies: 243
-- Name: COLUMN collections.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.collections.updated_at IS 'Timestamp when the collection record was last updated';


--
-- TOC entry 244 (class 1259 OID 17412)
-- Name: collections_collection_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.collections_collection_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.collections_collection_id_seq OWNER TO "John";

--
-- TOC entry 4422 (class 0 OID 0)
-- Dependencies: 244
-- Name: collections_collection_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.collections_collection_id_seq OWNED BY public.collections.collection_id;


--
-- TOC entry 245 (class 1259 OID 17413)
-- Name: deliveries; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.deliveries (
    delivery_id integer NOT NULL,
    class_id integer,
    delivery_date date,
    items text,
    status character varying(20),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.deliveries OWNER TO "John";

--
-- TOC entry 4423 (class 0 OID 0)
-- Dependencies: 245
-- Name: TABLE deliveries; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.deliveries IS 'Records deliveries made to classes';


--
-- TOC entry 4424 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN deliveries.delivery_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.deliveries.delivery_id IS 'Unique internal delivery ID';


--
-- TOC entry 4425 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN deliveries.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.deliveries.class_id IS 'Reference to the class';


--
-- TOC entry 4426 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN deliveries.delivery_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.deliveries.delivery_date IS 'Date when the delivery is scheduled or occurred';


--
-- TOC entry 4427 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN deliveries.items; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.deliveries.items IS 'Items included in the delivery';


--
-- TOC entry 4428 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN deliveries.status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.deliveries.status IS 'Delivery status (e.g., ''Pending'', ''Delivered'')';


--
-- TOC entry 4429 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN deliveries.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.deliveries.created_at IS 'Timestamp when the delivery record was created';


--
-- TOC entry 4430 (class 0 OID 0)
-- Dependencies: 245
-- Name: COLUMN deliveries.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.deliveries.updated_at IS 'Timestamp when the delivery record was last updated';


--
-- TOC entry 246 (class 1259 OID 17421)
-- Name: deliveries_delivery_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.deliveries_delivery_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.deliveries_delivery_id_seq OWNER TO "John";

--
-- TOC entry 4431 (class 0 OID 0)
-- Dependencies: 246
-- Name: deliveries_delivery_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.deliveries_delivery_id_seq OWNED BY public.deliveries.delivery_id;


--
-- TOC entry 247 (class 1259 OID 17422)
-- Name: employers; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.employers (
    employer_id integer NOT NULL,
    employer_name character varying(100),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.employers OWNER TO "John";

--
-- TOC entry 4432 (class 0 OID 0)
-- Dependencies: 247
-- Name: TABLE employers; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.employers IS 'Stores information about employers or sponsors of learners';


--
-- TOC entry 4433 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN employers.employer_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.employers.employer_id IS 'Unique internal employer ID';


--
-- TOC entry 4434 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN employers.employer_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.employers.employer_name IS 'Name of the employer or sponsoring organization';


--
-- TOC entry 4435 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN employers.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.employers.created_at IS 'Timestamp when the employer record was created';


--
-- TOC entry 4436 (class 0 OID 0)
-- Dependencies: 247
-- Name: COLUMN employers.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.employers.updated_at IS 'Timestamp when the employer record was last updated';


--
-- TOC entry 248 (class 1259 OID 17427)
-- Name: employers_employer_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.employers_employer_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.employers_employer_id_seq OWNER TO "John";

--
-- TOC entry 4437 (class 0 OID 0)
-- Dependencies: 248
-- Name: employers_employer_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.employers_employer_id_seq OWNED BY public.employers.employer_id;


--
-- TOC entry 249 (class 1259 OID 17428)
-- Name: exam_results; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.exam_results (
    result_id integer NOT NULL,
    exam_id integer,
    learner_id integer,
    subject character varying(100),
    mock_exam_number integer,
    score numeric(5,2),
    result character varying(20),
    exam_date date,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.exam_results OWNER TO "John";

--
-- TOC entry 4438 (class 0 OID 0)
-- Dependencies: 249
-- Name: TABLE exam_results; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.exam_results IS 'Stores detailed exam results for learners';


--
-- TOC entry 4439 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.result_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.result_id IS 'Unique internal exam result ID';


--
-- TOC entry 4440 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.exam_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.exam_id IS 'Reference to the exam';


--
-- TOC entry 4441 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.learner_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.learner_id IS 'Reference to the learner';


--
-- TOC entry 4442 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.subject; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.subject IS 'Subject of the exam';


--
-- TOC entry 4443 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.mock_exam_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.mock_exam_number IS 'Number of the mock exam (e.g., 1, 2, 3)';


--
-- TOC entry 4444 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.score; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.score IS 'Learner''s score in the exam';


--
-- TOC entry 4445 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.result; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.result IS 'Exam result (e.g., ''Pass'', ''Fail'')';


--
-- TOC entry 4446 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.exam_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.exam_date IS 'Date when the exam was taken';


--
-- TOC entry 4447 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.created_at IS 'Timestamp when the exam result was created';


--
-- TOC entry 4448 (class 0 OID 0)
-- Dependencies: 249
-- Name: COLUMN exam_results.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exam_results.updated_at IS 'Timestamp when the exam result was last updated';


--
-- TOC entry 250 (class 1259 OID 17433)
-- Name: exam_results_result_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.exam_results_result_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.exam_results_result_id_seq OWNER TO "John";

--
-- TOC entry 4449 (class 0 OID 0)
-- Dependencies: 250
-- Name: exam_results_result_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.exam_results_result_id_seq OWNED BY public.exam_results.result_id;


--
-- TOC entry 251 (class 1259 OID 17434)
-- Name: exams; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.exams (
    exam_id integer NOT NULL,
    learner_id integer,
    product_id integer,
    exam_date date,
    exam_type character varying(50),
    score numeric(5,2),
    result character varying(20),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.exams OWNER TO "John";

--
-- TOC entry 4450 (class 0 OID 0)
-- Dependencies: 251
-- Name: TABLE exams; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.exams IS 'Stores exam results for learners';


--
-- TOC entry 4451 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN exams.exam_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exams.exam_id IS 'Unique internal exam ID';


--
-- TOC entry 4452 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN exams.learner_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exams.learner_id IS 'Reference to the learner';


--
-- TOC entry 4453 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN exams.product_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exams.product_id IS 'Reference to the product or subject';


--
-- TOC entry 4454 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN exams.exam_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exams.exam_date IS 'Date when the exam was taken';


--
-- TOC entry 4455 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN exams.exam_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exams.exam_type IS 'Type of exam (e.g., ''Mock'', ''Final'')';


--
-- TOC entry 4456 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN exams.score; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exams.score IS 'Learner''s score in the exam';


--
-- TOC entry 4457 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN exams.result; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exams.result IS 'Exam result (e.g., ''Pass'', ''Fail'')';


--
-- TOC entry 4458 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN exams.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exams.created_at IS 'Timestamp when the exam record was created';


--
-- TOC entry 4459 (class 0 OID 0)
-- Dependencies: 251
-- Name: COLUMN exams.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.exams.updated_at IS 'Timestamp when the exam record was last updated';


--
-- TOC entry 252 (class 1259 OID 17439)
-- Name: exams_exam_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.exams_exam_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.exams_exam_id_seq OWNER TO "John";

--
-- TOC entry 4460 (class 0 OID 0)
-- Dependencies: 252
-- Name: exams_exam_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.exams_exam_id_seq OWNED BY public.exams.exam_id;


--
-- TOC entry 253 (class 1259 OID 17440)
-- Name: files; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.files (
    file_id integer NOT NULL,
    owner_type character varying(50),
    owner_id integer,
    file_path character varying(255),
    file_type character varying(50),
    uploaded_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.files OWNER TO "John";

--
-- TOC entry 4461 (class 0 OID 0)
-- Dependencies: 253
-- Name: TABLE files; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.files IS 'Stores references to files associated with various entities';


--
-- TOC entry 4462 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN files.file_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.files.file_id IS 'Unique internal file ID';


--
-- TOC entry 4463 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN files.owner_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.files.owner_type IS 'Type of entity that owns the file (e.g., ''Learner'', ''Class'', ''Agent'')';


--
-- TOC entry 4464 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN files.owner_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.files.owner_id IS 'ID of the owner entity';


--
-- TOC entry 4465 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN files.file_path; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.files.file_path IS 'File path or URL to the stored file';


--
-- TOC entry 4466 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN files.file_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.files.file_type IS 'Type of file (e.g., ''Scanned Portfolio'', ''QA Report'')';


--
-- TOC entry 4467 (class 0 OID 0)
-- Dependencies: 253
-- Name: COLUMN files.uploaded_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.files.uploaded_at IS 'Timestamp when the file was uploaded';


--
-- TOC entry 254 (class 1259 OID 17444)
-- Name: files_file_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.files_file_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.files_file_id_seq OWNER TO "John";

--
-- TOC entry 4468 (class 0 OID 0)
-- Dependencies: 254
-- Name: files_file_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.files_file_id_seq OWNED BY public.files.file_id;


--
-- TOC entry 255 (class 1259 OID 17445)
-- Name: history; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.history (
    history_id integer NOT NULL,
    entity_type character varying(50),
    entity_id integer,
    action character varying(50),
    changes jsonb,
    action_date timestamp without time zone DEFAULT now(),
    user_id integer
);


ALTER TABLE public.history OWNER TO "John";

--
-- TOC entry 4469 (class 0 OID 0)
-- Dependencies: 255
-- Name: TABLE history; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.history IS 'Records historical changes and actions performed on entities';


--
-- TOC entry 4470 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN history.history_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.history.history_id IS 'Unique internal history ID';


--
-- TOC entry 4471 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN history.entity_type; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.history.entity_type IS 'Type of entity the history record refers to (e.g., ''Learner'', ''Agent'', ''Class'')';


--
-- TOC entry 4472 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN history.entity_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.history.entity_id IS 'ID of the entity';


--
-- TOC entry 4473 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN history.action; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.history.action IS 'Type of action performed (e.g., ''Created'', ''Updated'', ''Deleted'')';


--
-- TOC entry 4474 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN history.changes; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.history.changes IS 'Details of the changes made, stored in JSON format';


--
-- TOC entry 4475 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN history.action_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.history.action_date IS 'Timestamp when the action occurred';


--
-- TOC entry 4476 (class 0 OID 0)
-- Dependencies: 255
-- Name: COLUMN history.user_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.history.user_id IS 'Reference to the user who performed the action';


--
-- TOC entry 256 (class 1259 OID 17451)
-- Name: history_history_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.history_history_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.history_history_id_seq OWNER TO "John";

--
-- TOC entry 4477 (class 0 OID 0)
-- Dependencies: 256
-- Name: history_history_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.history_history_id_seq OWNED BY public.history.history_id;


--
-- TOC entry 257 (class 1259 OID 17452)
-- Name: latest_document; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.latest_document (
    id integer NOT NULL,
    class_id integer NOT NULL,
    visit_date date NOT NULL,
    visit_type character varying(255) NOT NULL,
    officer_name character varying(255) NOT NULL,
    report_metadata jsonb,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.latest_document OWNER TO "John";

--
-- TOC entry 306 (class 1259 OID 24656)
-- Name: learner_hours_log; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learner_hours_log (
    log_id integer NOT NULL,
    learner_id integer NOT NULL,
    product_id integer NOT NULL,
    class_id integer,
    tracking_id integer,
    log_date date NOT NULL,
    hours_trained numeric(10,2) DEFAULT 0,
    hours_present numeric(10,2) DEFAULT 0,
    source character varying(20) NOT NULL,
    session_id integer,
    created_by integer,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT learner_hours_log_source_check CHECK (((source)::text = ANY ((ARRAY['schedule'::character varying, 'attendance'::character varying, 'manual'::character varying])::text[])))
);


ALTER TABLE public.learner_hours_log OWNER TO "John";

--
-- TOC entry 4478 (class 0 OID 0)
-- Dependencies: 306
-- Name: TABLE learner_hours_log; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.learner_hours_log IS 'Audit trail for hours logged. Source indicates where hours came from.';


--
-- TOC entry 4479 (class 0 OID 0)
-- Dependencies: 306
-- Name: COLUMN learner_hours_log.source; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_hours_log.source IS 'schedule = auto from class schedule, attendance = from facilitator, manual = office entry';


--
-- TOC entry 305 (class 1259 OID 24655)
-- Name: learner_hours_log_log_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.learner_hours_log_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_hours_log_log_id_seq OWNER TO "John";

--
-- TOC entry 4480 (class 0 OID 0)
-- Dependencies: 305
-- Name: learner_hours_log_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.learner_hours_log_log_id_seq OWNED BY public.learner_hours_log.log_id;


--
-- TOC entry 304 (class 1259 OID 24623)
-- Name: learner_lp_tracking; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learner_lp_tracking (
    tracking_id integer NOT NULL,
    learner_id integer NOT NULL,
    product_id integer NOT NULL,
    class_id integer,
    hours_trained numeric(10,2) DEFAULT 0,
    hours_present numeric(10,2) DEFAULT 0,
    hours_absent numeric(10,2) DEFAULT 0,
    status character varying(20) DEFAULT 'in_progress'::character varying,
    start_date date DEFAULT CURRENT_DATE,
    completion_date date,
    portfolio_file_path character varying(500),
    portfolio_uploaded_at timestamp without time zone,
    marked_complete_by integer,
    marked_complete_date timestamp without time zone,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT learner_lp_tracking_status_check CHECK (((status)::text = ANY ((ARRAY['in_progress'::character varying, 'completed'::character varying, 'on_hold'::character varying])::text[])))
);


ALTER TABLE public.learner_lp_tracking OWNER TO "John";

--
-- TOC entry 4481 (class 0 OID 0)
-- Dependencies: 304
-- Name: TABLE learner_lp_tracking; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.learner_lp_tracking IS 'Tracks current LP status per learner. Only one in_progress LP allowed per learner.';


--
-- TOC entry 4482 (class 0 OID 0)
-- Dependencies: 304
-- Name: COLUMN learner_lp_tracking.hours_trained; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_lp_tracking.hours_trained IS 'Hours from class schedule (scheduled training time)';


--
-- TOC entry 4483 (class 0 OID 0)
-- Dependencies: 304
-- Name: COLUMN learner_lp_tracking.hours_present; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_lp_tracking.hours_present IS 'Hours from facilitator attendance records';


--
-- TOC entry 4484 (class 0 OID 0)
-- Dependencies: 304
-- Name: COLUMN learner_lp_tracking.hours_absent; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_lp_tracking.hours_absent IS 'Calculated: hours_trained - hours_present';


--
-- TOC entry 4485 (class 0 OID 0)
-- Dependencies: 304
-- Name: COLUMN learner_lp_tracking.status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_lp_tracking.status IS 'in_progress = currently working on LP, completed = LP finished, on_hold = paused';


--
-- TOC entry 303 (class 1259 OID 24622)
-- Name: learner_lp_tracking_tracking_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.learner_lp_tracking_tracking_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_lp_tracking_tracking_id_seq OWNER TO "John";

--
-- TOC entry 4486 (class 0 OID 0)
-- Dependencies: 303
-- Name: learner_lp_tracking_tracking_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.learner_lp_tracking_tracking_id_seq OWNED BY public.learner_lp_tracking.tracking_id;


--
-- TOC entry 258 (class 1259 OID 17459)
-- Name: learner_placement_level; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learner_placement_level (
    placement_level_id integer NOT NULL,
    level character varying(255) NOT NULL,
    level_desc character varying(255)
);


ALTER TABLE public.learner_placement_level OWNER TO "John";

--
-- TOC entry 4487 (class 0 OID 0)
-- Dependencies: 258
-- Name: TABLE learner_placement_level; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.learner_placement_level IS 'Stores Learners Placement Levels';


--
-- TOC entry 259 (class 1259 OID 17464)
-- Name: learner_portfolios; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learner_portfolios (
    portfolio_id integer NOT NULL,
    learner_id integer NOT NULL,
    file_path character varying(255) NOT NULL,
    upload_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.learner_portfolios OWNER TO "John";

--
-- TOC entry 260 (class 1259 OID 17468)
-- Name: learner_portfolios_portfolio_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.learner_portfolios_portfolio_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_portfolios_portfolio_id_seq OWNER TO "John";

--
-- TOC entry 4488 (class 0 OID 0)
-- Dependencies: 260
-- Name: learner_portfolios_portfolio_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.learner_portfolios_portfolio_id_seq OWNED BY public.learner_portfolios.portfolio_id;


--
-- TOC entry 261 (class 1259 OID 17469)
-- Name: learner_products; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learner_products (
    learner_id integer NOT NULL,
    product_id integer NOT NULL,
    start_date date,
    end_date date
);


ALTER TABLE public.learner_products OWNER TO "John";

--
-- TOC entry 4489 (class 0 OID 0)
-- Dependencies: 261
-- Name: TABLE learner_products; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.learner_products IS 'Associates learners with the products they are enrolled in';


--
-- TOC entry 4490 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN learner_products.learner_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_products.learner_id IS 'Reference to the learner';


--
-- TOC entry 4491 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN learner_products.product_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_products.product_id IS 'Reference to the product the learner is enrolled in';


--
-- TOC entry 4492 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN learner_products.start_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_products.start_date IS 'Start date of the learner''s enrollment in the product';


--
-- TOC entry 4493 (class 0 OID 0)
-- Dependencies: 261
-- Name: COLUMN learner_products.end_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_products.end_date IS 'End date of the learner''s enrollment in the product';


--
-- TOC entry 308 (class 1259 OID 24689)
-- Name: learner_progression_portfolios; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learner_progression_portfolios (
    file_id integer NOT NULL,
    tracking_id integer NOT NULL,
    file_name character varying(255) NOT NULL,
    file_path character varying(500) NOT NULL,
    file_type character varying(50),
    file_size integer,
    uploaded_by integer,
    uploaded_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.learner_progression_portfolios OWNER TO "John";

--
-- TOC entry 4494 (class 0 OID 0)
-- Dependencies: 308
-- Name: TABLE learner_progression_portfolios; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.learner_progression_portfolios IS 'Portfolio files uploaded when marking LP complete. Required for completion.';


--
-- TOC entry 307 (class 1259 OID 24688)
-- Name: learner_progression_portfolios_file_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.learner_progression_portfolios_file_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_progression_portfolios_file_id_seq OWNER TO "John";

--
-- TOC entry 4495 (class 0 OID 0)
-- Dependencies: 307
-- Name: learner_progression_portfolios_file_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.learner_progression_portfolios_file_id_seq OWNED BY public.learner_progression_portfolios.file_id;


--
-- TOC entry 262 (class 1259 OID 17472)
-- Name: learner_progressions; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learner_progressions (
    progression_id integer NOT NULL,
    learner_id integer,
    from_product_id integer,
    to_product_id integer,
    progression_date date,
    notes text
);


ALTER TABLE public.learner_progressions OWNER TO "John";

--
-- TOC entry 4496 (class 0 OID 0)
-- Dependencies: 262
-- Name: TABLE learner_progressions; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.learner_progressions IS 'Tracks the progression of learners between products';


--
-- TOC entry 4497 (class 0 OID 0)
-- Dependencies: 262
-- Name: COLUMN learner_progressions.progression_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_progressions.progression_id IS 'Unique internal progression ID';


--
-- TOC entry 4498 (class 0 OID 0)
-- Dependencies: 262
-- Name: COLUMN learner_progressions.learner_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_progressions.learner_id IS 'Reference to the learner';


--
-- TOC entry 4499 (class 0 OID 0)
-- Dependencies: 262
-- Name: COLUMN learner_progressions.from_product_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_progressions.from_product_id IS 'Reference to the initial product';


--
-- TOC entry 4500 (class 0 OID 0)
-- Dependencies: 262
-- Name: COLUMN learner_progressions.to_product_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_progressions.to_product_id IS 'Reference to the new product after progression';


--
-- TOC entry 4501 (class 0 OID 0)
-- Dependencies: 262
-- Name: COLUMN learner_progressions.progression_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_progressions.progression_date IS 'Date when the learner progressed to the new product';


--
-- TOC entry 4502 (class 0 OID 0)
-- Dependencies: 262
-- Name: COLUMN learner_progressions.notes; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_progressions.notes IS 'Additional notes regarding the progression';


--
-- TOC entry 263 (class 1259 OID 17477)
-- Name: learner_progressions_progression_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.learner_progressions_progression_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_progressions_progression_id_seq OWNER TO "John";

--
-- TOC entry 4503 (class 0 OID 0)
-- Dependencies: 263
-- Name: learner_progressions_progression_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.learner_progressions_progression_id_seq OWNED BY public.learner_progressions.progression_id;


--
-- TOC entry 264 (class 1259 OID 17478)
-- Name: learner_qualifications; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learner_qualifications (
    id integer NOT NULL,
    qualification character varying(255)
);


ALTER TABLE public.learner_qualifications OWNER TO "John";

--
-- TOC entry 4504 (class 0 OID 0)
-- Dependencies: 264
-- Name: TABLE learner_qualifications; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.learner_qualifications IS 'Table containing a list of possible qualifications that learners can attain.';


--
-- TOC entry 4505 (class 0 OID 0)
-- Dependencies: 264
-- Name: COLUMN learner_qualifications.qualification; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learner_qualifications.qualification IS 'Name of the qualification.';


--
-- TOC entry 265 (class 1259 OID 17481)
-- Name: learner_qualifications_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.learner_qualifications_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_qualifications_id_seq OWNER TO "John";

--
-- TOC entry 4506 (class 0 OID 0)
-- Dependencies: 265
-- Name: learner_qualifications_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.learner_qualifications_id_seq OWNED BY public.learner_qualifications.id;


--
-- TOC entry 314 (class 1259 OID 24762)
-- Name: learner_sponsors; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learner_sponsors (
    id integer NOT NULL,
    learner_id integer NOT NULL,
    employer_id integer NOT NULL,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.learner_sponsors OWNER TO "John";

--
-- TOC entry 313 (class 1259 OID 24761)
-- Name: learner_sponsors_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.learner_sponsors_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learner_sponsors_id_seq OWNER TO "John";

--
-- TOC entry 4507 (class 0 OID 0)
-- Dependencies: 313
-- Name: learner_sponsors_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.learner_sponsors_id_seq OWNED BY public.learner_sponsors.id;


--
-- TOC entry 266 (class 1259 OID 17482)
-- Name: learners; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.learners (
    id integer NOT NULL,
    first_name character varying(50),
    initials character varying(10),
    surname character varying(50),
    gender character varying(10),
    race character varying(20),
    sa_id_no character varying(20),
    passport_number character varying(20),
    tel_number character varying(20),
    alternative_tel_number character varying(20),
    email_address character varying(100),
    address_line_1 character varying(100),
    address_line_2 character varying(100),
    city_town_id integer,
    province_region_id integer,
    postal_code character varying(10),
    assessment_status character varying(20),
    placement_assessment_date date,
    numeracy_level integer,
    employment_status boolean,
    employer_id integer,
    disability_status boolean,
    scanned_portfolio character varying(255),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    highest_qualification integer,
    communication_level integer,
    second_name character varying(255),
    title character varying(16)
);


ALTER TABLE public.learners OWNER TO "John";

--
-- TOC entry 4508 (class 0 OID 0)
-- Dependencies: 266
-- Name: TABLE learners; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.learners IS 'Stores personal, educational, and assessment information about learners';


--
-- TOC entry 4509 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.id IS 'Unique internal learner ID';


--
-- TOC entry 4510 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.first_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.first_name IS 'Learner''s first name';


--
-- TOC entry 4511 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.initials; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.initials IS 'Learner''s initials';


--
-- TOC entry 4512 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.surname; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.surname IS 'Learner''s surname';


--
-- TOC entry 4513 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.gender; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.gender IS 'Learner''s gender';


--
-- TOC entry 4514 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.race; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.race IS 'Learner''s race; options include ''African'', ''Coloured'', ''White'', ''Indian''';


--
-- TOC entry 4515 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.sa_id_no; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.sa_id_no IS 'Learner''s South African ID number';


--
-- TOC entry 4516 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.passport_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.passport_number IS 'Learner''s passport number if they are a foreigner';


--
-- TOC entry 4517 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.tel_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.tel_number IS 'Learner''s primary telephone number';


--
-- TOC entry 4518 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.alternative_tel_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.alternative_tel_number IS 'Learner''s alternative contact number';


--
-- TOC entry 4519 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.email_address; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.email_address IS 'Learner''s email address';


--
-- TOC entry 4520 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.address_line_1; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.address_line_1 IS 'First line of learner''s physical address';


--
-- TOC entry 4521 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.address_line_2; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.address_line_2 IS 'Second line of learner''s physical address';


--
-- TOC entry 4522 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.city_town_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.city_town_id IS 'Reference to the city or town where the learner lives';


--
-- TOC entry 4523 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.province_region_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.province_region_id IS 'Reference to the province/region where the learner lives';


--
-- TOC entry 4524 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.postal_code; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.postal_code IS 'Postal code of the learner''s area';


--
-- TOC entry 4525 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.assessment_status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.assessment_status IS 'Assessment status; indicates if the learner was assessed (''Assessed'', ''Not Assessed'')';


--
-- TOC entry 4526 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.placement_assessment_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.placement_assessment_date IS 'Date when the learner took the placement assessment';


--
-- TOC entry 4527 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.numeracy_level; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.numeracy_level IS 'Learner''s initial placement level in Communications (e.g., ''CL1b'', ''CL1'', ''CL2'')';


--
-- TOC entry 4528 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.employment_status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.employment_status IS 'Indicates if the learner is employed (true) or not (false)';


--
-- TOC entry 4529 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.employer_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.employer_id IS 'Reference to the learner''s employer or sponsor';


--
-- TOC entry 4530 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.disability_status; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.disability_status IS 'Indicates if the learner has a disability (true) or not (false)';


--
-- TOC entry 4531 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.scanned_portfolio; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.scanned_portfolio IS 'File path or URL to the learner''s scanned portfolio in PDF format';


--
-- TOC entry 4532 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.created_at IS 'Timestamp when the learner record was created';


--
-- TOC entry 4533 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.updated_at IS 'Timestamp when the learner record was last updated';


--
-- TOC entry 4534 (class 0 OID 0)
-- Dependencies: 266
-- Name: COLUMN learners.highest_qualification; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.learners.highest_qualification IS 'Foreign key referencing learner_qualifications.id; indicates the learner''s highest qualification.';


--
-- TOC entry 267 (class 1259 OID 17489)
-- Name: learners_learner_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.learners_learner_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.learners_learner_id_seq OWNER TO "John";

--
-- TOC entry 4535 (class 0 OID 0)
-- Dependencies: 267
-- Name: learners_learner_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.learners_learner_id_seq OWNED BY public.learners.id;


--
-- TOC entry 268 (class 1259 OID 17490)
-- Name: locations; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.locations (
    location_id integer NOT NULL,
    suburb character varying(50),
    town character varying(50),
    province character varying(50),
    postal_code character varying(10),
    longitude numeric(9,6),
    latitude numeric(9,6),
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    street_address text,
    CONSTRAINT locations_street_address_nonblank CHECK (((street_address IS NULL) OR (btrim(street_address) <> ''::text)))
);


ALTER TABLE public.locations OWNER TO "John";

--
-- TOC entry 4536 (class 0 OID 0)
-- Dependencies: 268
-- Name: TABLE locations; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.locations IS 'Stores geographical location data for addresses';


--
-- TOC entry 4537 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.location_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.location_id IS 'Unique internal location ID';


--
-- TOC entry 4538 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.suburb; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.suburb IS 'Suburb name';


--
-- TOC entry 4539 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.town; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.town IS 'Town name';


--
-- TOC entry 4540 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.province; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.province IS 'Province name';


--
-- TOC entry 4541 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.postal_code; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.postal_code IS 'Postal code';


--
-- TOC entry 4542 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.longitude; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.longitude IS 'Geographical longitude coordinate';


--
-- TOC entry 4543 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.latitude; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.latitude IS 'Geographical latitude coordinate';


--
-- TOC entry 4544 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.created_at IS 'Timestamp when the location record was created';


--
-- TOC entry 4545 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.updated_at IS 'Timestamp when the location record was last updated';


--
-- TOC entry 4546 (class 0 OID 0)
-- Dependencies: 268
-- Name: COLUMN locations.street_address; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.locations.street_address IS 'Street address line for the location';


--
-- TOC entry 269 (class 1259 OID 17498)
-- Name: locations_location_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.locations_location_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.locations_location_id_seq OWNER TO "John";

--
-- TOC entry 4547 (class 0 OID 0)
-- Dependencies: 269
-- Name: locations_location_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.locations_location_id_seq OWNED BY public.locations.location_id;


--
-- TOC entry 270 (class 1259 OID 17499)
-- Name: products; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.products (
    product_id integer NOT NULL,
    product_name character varying(100),
    product_duration integer,
    learning_area character varying(100),
    learning_area_duration integer,
    reporting_structure text,
    product_notes text,
    product_rules text,
    product_flags text,
    parent_product_id integer,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.products OWNER TO "John";

--
-- TOC entry 4548 (class 0 OID 0)
-- Dependencies: 270
-- Name: TABLE products; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.products IS 'Stores information about educational products or courses';


--
-- TOC entry 4549 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.product_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.product_id IS 'Unique internal product ID';


--
-- TOC entry 4550 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.product_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.product_name IS 'Name of the product or course';


--
-- TOC entry 4551 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.product_duration; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.product_duration IS 'Total duration of the product in hours';


--
-- TOC entry 4552 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.learning_area; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.learning_area IS 'Learning areas covered by the product (e.g., ''Communication'', ''Numeracy'')';


--
-- TOC entry 4553 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.learning_area_duration; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.learning_area_duration IS 'Duration of each learning area in hours';


--
-- TOC entry 4554 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.reporting_structure; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.reporting_structure IS 'Structure of progress reports for the product';


--
-- TOC entry 4555 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.product_notes; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.product_notes IS 'Notes or additional information about the product';


--
-- TOC entry 4556 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.product_rules; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.product_rules IS 'Rules or guidelines associated with the product';


--
-- TOC entry 4557 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.product_flags; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.product_flags IS 'Flags or alerts for the product (e.g., attendance thresholds)';


--
-- TOC entry 4558 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.parent_product_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.parent_product_id IS 'Reference to a parent product for hierarchical structuring';


--
-- TOC entry 4559 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.created_at IS 'Timestamp when the product record was created';


--
-- TOC entry 4560 (class 0 OID 0)
-- Dependencies: 270
-- Name: COLUMN products.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.products.updated_at IS 'Timestamp when the product record was last updated';


--
-- TOC entry 271 (class 1259 OID 17506)
-- Name: products_product_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.products_product_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.products_product_id_seq OWNER TO "John";

--
-- TOC entry 4561 (class 0 OID 0)
-- Dependencies: 271
-- Name: products_product_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.products_product_id_seq OWNED BY public.products.product_id;


--
-- TOC entry 272 (class 1259 OID 17507)
-- Name: progress_reports; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.progress_reports (
    report_id integer NOT NULL,
    class_id integer,
    learner_id integer,
    product_id integer,
    progress_percentage numeric(5,2),
    report_date date,
    remarks text,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.progress_reports OWNER TO "John";

--
-- TOC entry 4562 (class 0 OID 0)
-- Dependencies: 272
-- Name: TABLE progress_reports; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.progress_reports IS 'Stores progress reports for learners in specific classes and products';


--
-- TOC entry 4563 (class 0 OID 0)
-- Dependencies: 272
-- Name: COLUMN progress_reports.report_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.progress_reports.report_id IS 'Unique internal progress report ID';


--
-- TOC entry 4564 (class 0 OID 0)
-- Dependencies: 272
-- Name: COLUMN progress_reports.class_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.progress_reports.class_id IS 'Reference to the class';


--
-- TOC entry 4565 (class 0 OID 0)
-- Dependencies: 272
-- Name: COLUMN progress_reports.learner_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.progress_reports.learner_id IS 'Reference to the learner';


--
-- TOC entry 4566 (class 0 OID 0)
-- Dependencies: 272
-- Name: COLUMN progress_reports.product_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.progress_reports.product_id IS 'Reference to the product or subject';


--
-- TOC entry 4567 (class 0 OID 0)
-- Dependencies: 272
-- Name: COLUMN progress_reports.progress_percentage; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.progress_reports.progress_percentage IS 'Learner''s progress percentage in the product';


--
-- TOC entry 4568 (class 0 OID 0)
-- Dependencies: 272
-- Name: COLUMN progress_reports.report_date; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.progress_reports.report_date IS 'Date when the progress report was generated';


--
-- TOC entry 4569 (class 0 OID 0)
-- Dependencies: 272
-- Name: COLUMN progress_reports.remarks; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.progress_reports.remarks IS 'Additional remarks or comments';


--
-- TOC entry 4570 (class 0 OID 0)
-- Dependencies: 272
-- Name: COLUMN progress_reports.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.progress_reports.created_at IS 'Timestamp when the progress report was created';


--
-- TOC entry 4571 (class 0 OID 0)
-- Dependencies: 272
-- Name: COLUMN progress_reports.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.progress_reports.updated_at IS 'Timestamp when the progress report was last updated';


--
-- TOC entry 273 (class 1259 OID 17514)
-- Name: progress_reports_report_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.progress_reports_report_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.progress_reports_report_id_seq OWNER TO "John";

--
-- TOC entry 4572 (class 0 OID 0)
-- Dependencies: 273
-- Name: progress_reports_report_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.progress_reports_report_id_seq OWNED BY public.progress_reports.report_id;


--
-- TOC entry 274 (class 1259 OID 17515)
-- Name: qa_visits; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.qa_visits (
    id integer NOT NULL,
    class_id integer NOT NULL,
    visit_date date NOT NULL,
    visit_type character varying(255) NOT NULL,
    officer_name character varying(255) NOT NULL,
    latest_document jsonb,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.qa_visits OWNER TO "John";

--
-- TOC entry 275 (class 1259 OID 17522)
-- Name: qa_visits_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.qa_visits_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.qa_visits_id_seq OWNER TO "John";

--
-- TOC entry 4573 (class 0 OID 0)
-- Dependencies: 275
-- Name: qa_visits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.qa_visits_id_seq OWNED BY public.latest_document.id;


--
-- TOC entry 276 (class 1259 OID 17523)
-- Name: qa_visits_id_seq1; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.qa_visits_id_seq1
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.qa_visits_id_seq1 OWNER TO "John";

--
-- TOC entry 4574 (class 0 OID 0)
-- Dependencies: 276
-- Name: qa_visits_id_seq1; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.qa_visits_id_seq1 OWNED BY public.qa_visits.id;


--
-- TOC entry 277 (class 1259 OID 17524)
-- Name: sites; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.sites (
    site_id integer NOT NULL,
    client_id integer NOT NULL,
    site_name character varying(100) NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now(),
    parent_site_id integer,
    place_id integer
);


ALTER TABLE public.sites OWNER TO "John";

--
-- TOC entry 4575 (class 0 OID 0)
-- Dependencies: 277
-- Name: TABLE sites; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.sites IS 'Stores information about client sites with hierarchical structure. Address data is stored in locations table and linked via place_id.';


--
-- TOC entry 4576 (class 0 OID 0)
-- Dependencies: 277
-- Name: COLUMN sites.site_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.sites.site_id IS 'Unique site ID';


--
-- TOC entry 4577 (class 0 OID 0)
-- Dependencies: 277
-- Name: COLUMN sites.client_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.sites.client_id IS 'Reference to the client this site belongs to';


--
-- TOC entry 4578 (class 0 OID 0)
-- Dependencies: 277
-- Name: COLUMN sites.site_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.sites.site_name IS 'Name of the site';


--
-- TOC entry 4579 (class 0 OID 0)
-- Dependencies: 277
-- Name: COLUMN sites.parent_site_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.sites.parent_site_id IS 'Reference to parent site for hierarchical structure. NULL indicates head site.';


--
-- TOC entry 4580 (class 0 OID 0)
-- Dependencies: 277
-- Name: COLUMN sites.place_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.sites.place_id IS 'Foreign key to locations table containing address data.';


--
-- TOC entry 278 (class 1259 OID 17529)
-- Name: sites_address_audit; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.sites_address_audit (
    site_id integer,
    client_id integer,
    site_name character varying(100),
    address_line_1 character varying(120),
    address_line_2 character varying(120),
    address text,
    place_id integer,
    parent_site_id integer,
    created_at timestamp without time zone,
    updated_at timestamp without time zone
);


ALTER TABLE public.sites_address_audit OWNER TO "John";

--
-- TOC entry 279 (class 1259 OID 17534)
-- Name: sites_migration_backup; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.sites_migration_backup (
    site_id integer,
    client_id integer,
    site_name character varying(100),
    address text,
    created_at timestamp without time zone,
    updated_at timestamp without time zone,
    parent_site_id integer,
    place_id integer,
    address_line_1 character varying(120),
    address_line_2 character varying(120)
);


ALTER TABLE public.sites_migration_backup OWNER TO "John";

--
-- TOC entry 280 (class 1259 OID 17539)
-- Name: sites_site_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.sites_site_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.sites_site_id_seq OWNER TO "John";

--
-- TOC entry 4581 (class 0 OID 0)
-- Dependencies: 280
-- Name: sites_site_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.sites_site_id_seq OWNED BY public.sites.site_id;


--
-- TOC entry 281 (class 1259 OID 17540)
-- Name: user_permissions; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.user_permissions (
    permission_id integer NOT NULL,
    user_id integer,
    permission character varying(100)
);


ALTER TABLE public.user_permissions OWNER TO "John";

--
-- TOC entry 4582 (class 0 OID 0)
-- Dependencies: 281
-- Name: TABLE user_permissions; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.user_permissions IS 'Grants specific permissions to users';


--
-- TOC entry 4583 (class 0 OID 0)
-- Dependencies: 281
-- Name: COLUMN user_permissions.permission_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.user_permissions.permission_id IS 'Unique internal permission ID';


--
-- TOC entry 4584 (class 0 OID 0)
-- Dependencies: 281
-- Name: COLUMN user_permissions.user_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.user_permissions.user_id IS 'Reference to the user';


--
-- TOC entry 4585 (class 0 OID 0)
-- Dependencies: 281
-- Name: COLUMN user_permissions.permission; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.user_permissions.permission IS 'Specific permission granted to the user';


--
-- TOC entry 282 (class 1259 OID 17543)
-- Name: user_permissions_permission_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.user_permissions_permission_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_permissions_permission_id_seq OWNER TO "John";

--
-- TOC entry 4586 (class 0 OID 0)
-- Dependencies: 282
-- Name: user_permissions_permission_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.user_permissions_permission_id_seq OWNED BY public.user_permissions.permission_id;


--
-- TOC entry 283 (class 1259 OID 17544)
-- Name: user_roles; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.user_roles (
    role_id integer NOT NULL,
    role_name character varying(50),
    permissions jsonb
);


ALTER TABLE public.user_roles OWNER TO "John";

--
-- TOC entry 4587 (class 0 OID 0)
-- Dependencies: 283
-- Name: TABLE user_roles; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.user_roles IS 'Defines roles and associated permissions for users';


--
-- TOC entry 4588 (class 0 OID 0)
-- Dependencies: 283
-- Name: COLUMN user_roles.role_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.user_roles.role_id IS 'Unique internal role ID';


--
-- TOC entry 4589 (class 0 OID 0)
-- Dependencies: 283
-- Name: COLUMN user_roles.role_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.user_roles.role_name IS 'Name of the role (e.g., ''Admin'', ''Project Supervisor'')';


--
-- TOC entry 4590 (class 0 OID 0)
-- Dependencies: 283
-- Name: COLUMN user_roles.permissions; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.user_roles.permissions IS 'Permissions associated with the role, stored in JSON format';


--
-- TOC entry 284 (class 1259 OID 17549)
-- Name: user_roles_role_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.user_roles_role_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_roles_role_id_seq OWNER TO "John";

--
-- TOC entry 4591 (class 0 OID 0)
-- Dependencies: 284
-- Name: user_roles_role_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.user_roles_role_id_seq OWNED BY public.user_roles.role_id;


--
-- TOC entry 285 (class 1259 OID 17550)
-- Name: users; Type: TABLE; Schema: public; Owner: John
--

CREATE TABLE public.users (
    user_id integer NOT NULL,
    first_name character varying(50),
    surname character varying(50),
    email character varying(100) NOT NULL,
    cellphone_number character varying(20),
    role character varying(50),
    password_hash character varying(255) NOT NULL,
    created_at timestamp without time zone DEFAULT now(),
    updated_at timestamp without time zone DEFAULT now()
);


ALTER TABLE public.users OWNER TO "John";

--
-- TOC entry 4592 (class 0 OID 0)
-- Dependencies: 285
-- Name: TABLE users; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON TABLE public.users IS 'Stores system user information';


--
-- TOC entry 4593 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN users.user_id; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.users.user_id IS 'Unique internal user ID';


--
-- TOC entry 4594 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN users.first_name; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.users.first_name IS 'User''s first name';


--
-- TOC entry 4595 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN users.surname; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.users.surname IS 'User''s surname';


--
-- TOC entry 4596 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN users.email; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.users.email IS 'User''s email address';


--
-- TOC entry 4597 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN users.cellphone_number; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.users.cellphone_number IS 'User''s cellphone number';


--
-- TOC entry 4598 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN users.role; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.users.role IS 'User''s role in the system, e.g., ''Admin'', ''Project Supervisor''';


--
-- TOC entry 4599 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN users.password_hash; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.users.password_hash IS 'Hashed password for user authentication';


--
-- TOC entry 4600 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN users.created_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.users.created_at IS 'Timestamp when the user record was created';


--
-- TOC entry 4601 (class 0 OID 0)
-- Dependencies: 285
-- Name: COLUMN users.updated_at; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON COLUMN public.users.updated_at IS 'Timestamp when the user record was last updated';


--
-- TOC entry 286 (class 1259 OID 17557)
-- Name: users_user_id_seq; Type: SEQUENCE; Schema: public; Owner: John
--

CREATE SEQUENCE public.users_user_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_user_id_seq OWNER TO "John";

--
-- TOC entry 4602 (class 0 OID 0)
-- Dependencies: 286
-- Name: users_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: John
--

ALTER SEQUENCE public.users_user_id_seq OWNED BY public.users.user_id;


--
-- TOC entry 287 (class 1259 OID 17558)
-- Name: v_client_head_sites; Type: VIEW; Schema: public; Owner: John
--

CREATE VIEW public.v_client_head_sites AS
 SELECT s.site_id,
    s.client_id,
    c.client_name,
    s.site_name,
    s.place_id,
    l.street_address,
    l.suburb,
    l.town,
    l.province,
    l.postal_code,
    l.longitude,
    l.latitude,
    s.created_at,
    s.updated_at
   FROM ((public.sites s
     JOIN public.clients c ON ((c.client_id = s.client_id)))
     LEFT JOIN public.locations l ON ((l.location_id = s.place_id)))
  WHERE (s.parent_site_id IS NULL);


ALTER VIEW public.v_client_head_sites OWNER TO "John";

--
-- TOC entry 288 (class 1259 OID 17563)
-- Name: v_client_sub_sites; Type: VIEW; Schema: public; Owner: John
--

CREATE VIEW public.v_client_sub_sites AS
 SELECT s.site_id,
    s.parent_site_id,
    s.client_id,
    s.site_name,
    s.place_id,
    l.street_address,
    l.suburb,
    l.town,
    l.province,
    l.postal_code,
    l.longitude,
    l.latitude,
    parent_s.site_name AS parent_site_name,
    s.created_at,
    s.updated_at
   FROM ((public.sites s
     JOIN public.sites parent_s ON ((parent_s.site_id = s.parent_site_id)))
     LEFT JOIN public.locations l ON ((l.location_id = s.place_id)))
  WHERE (s.parent_site_id IS NOT NULL);


ALTER VIEW public.v_client_sub_sites OWNER TO "John";

--
-- TOC entry 289 (class 1259 OID 17568)
-- Name: audit_log; Type: TABLE; Schema: wecoza_events; Owner: John
--

CREATE TABLE wecoza_events.audit_log (
    id integer NOT NULL,
    level character varying(20) DEFAULT 'info'::character varying NOT NULL,
    action character varying(100) NOT NULL,
    message text NOT NULL,
    context jsonb DEFAULT '{}'::jsonb,
    user_id integer,
    ip_address inet,
    user_agent text,
    request_uri text,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.audit_log OWNER TO "John";

--
-- TOC entry 4603 (class 0 OID 0)
-- Dependencies: 289
-- Name: TABLE audit_log; Type: COMMENT; Schema: wecoza_events; Owner: John
--

COMMENT ON TABLE wecoza_events.audit_log IS 'Security and operation audit trail';


--
-- TOC entry 290 (class 1259 OID 17576)
-- Name: audit_log_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: John
--

CREATE SEQUENCE wecoza_events.audit_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.audit_log_id_seq OWNER TO "John";

--
-- TOC entry 4604 (class 0 OID 0)
-- Dependencies: 290
-- Name: audit_log_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: John
--

ALTER SEQUENCE wecoza_events.audit_log_id_seq OWNED BY wecoza_events.audit_log.id;


--
-- TOC entry 291 (class 1259 OID 17577)
-- Name: dashboard_status; Type: TABLE; Schema: wecoza_events; Owner: John
--

CREATE TABLE wecoza_events.dashboard_status (
    id integer NOT NULL,
    class_id integer NOT NULL,
    task_type character varying(100) NOT NULL,
    task_status character varying(50) DEFAULT 'pending'::character varying,
    responsible_user_id integer,
    due_date timestamp with time zone,
    completed_at timestamp with time zone,
    completion_data jsonb DEFAULT '{}'::jsonb,
    last_reminder timestamp with time zone,
    overdue_notified boolean DEFAULT false,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.dashboard_status OWNER TO "John";

--
-- TOC entry 4605 (class 0 OID 0)
-- Dependencies: 291
-- Name: TABLE dashboard_status; Type: COMMENT; Schema: wecoza_events; Owner: John
--

COMMENT ON TABLE wecoza_events.dashboard_status IS 'Status tracking for class-related tasks';


--
-- TOC entry 292 (class 1259 OID 17587)
-- Name: dashboard_status_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: John
--

CREATE SEQUENCE wecoza_events.dashboard_status_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.dashboard_status_id_seq OWNER TO "John";

--
-- TOC entry 4606 (class 0 OID 0)
-- Dependencies: 292
-- Name: dashboard_status_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: John
--

ALTER SEQUENCE wecoza_events.dashboard_status_id_seq OWNED BY wecoza_events.dashboard_status.id;


--
-- TOC entry 293 (class 1259 OID 17588)
-- Name: events_log; Type: TABLE; Schema: wecoza_events; Owner: John
--

CREATE TABLE wecoza_events.events_log (
    id integer NOT NULL,
    event_name character varying(100) NOT NULL,
    event_payload jsonb DEFAULT '{}'::jsonb,
    class_id integer,
    actor_id integer,
    idempotency_key character varying(255) NOT NULL,
    processed boolean DEFAULT false,
    occurred_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    processed_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.events_log OWNER TO "John";

--
-- TOC entry 4607 (class 0 OID 0)
-- Dependencies: 293
-- Name: TABLE events_log; Type: COMMENT; Schema: wecoza_events; Owner: John
--

COMMENT ON TABLE wecoza_events.events_log IS 'Log of all events processed by the system';


--
-- TOC entry 294 (class 1259 OID 17597)
-- Name: events_log_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: John
--

CREATE SEQUENCE wecoza_events.events_log_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.events_log_id_seq OWNER TO "John";

--
-- TOC entry 4608 (class 0 OID 0)
-- Dependencies: 294
-- Name: events_log_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: John
--

ALTER SEQUENCE wecoza_events.events_log_id_seq OWNED BY wecoza_events.events_log.id;


--
-- TOC entry 295 (class 1259 OID 17598)
-- Name: notification_queue; Type: TABLE; Schema: wecoza_events; Owner: John
--

CREATE TABLE wecoza_events.notification_queue (
    id integer NOT NULL,
    event_name character varying(100) NOT NULL,
    idempotency_key character varying(255) NOT NULL,
    recipient_email character varying(255) NOT NULL,
    recipient_name character varying(255),
    channel character varying(50) DEFAULT 'email'::character varying,
    template_name character varying(100) NOT NULL,
    payload jsonb DEFAULT '{}'::jsonb,
    status character varying(50) DEFAULT 'pending'::character varying,
    attempts integer DEFAULT 0,
    max_attempts integer DEFAULT 3,
    last_error text,
    scheduled_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    sent_at timestamp with time zone,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.notification_queue OWNER TO "John";

--
-- TOC entry 4609 (class 0 OID 0)
-- Dependencies: 295
-- Name: TABLE notification_queue; Type: COMMENT; Schema: wecoza_events; Owner: John
--

COMMENT ON TABLE wecoza_events.notification_queue IS 'Queue for outgoing notifications (email, dashboard, etc.)';


--
-- TOC entry 296 (class 1259 OID 17611)
-- Name: notification_queue_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: John
--

CREATE SEQUENCE wecoza_events.notification_queue_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.notification_queue_id_seq OWNER TO "John";

--
-- TOC entry 4610 (class 0 OID 0)
-- Dependencies: 296
-- Name: notification_queue_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: John
--

ALTER SEQUENCE wecoza_events.notification_queue_id_seq OWNED BY wecoza_events.notification_queue.id;


--
-- TOC entry 297 (class 1259 OID 17612)
-- Name: supervisors; Type: TABLE; Schema: wecoza_events; Owner: John
--

CREATE TABLE wecoza_events.supervisors (
    id integer NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    phone character varying(50),
    role character varying(50) DEFAULT 'supervisor'::character varying,
    client_assignments jsonb DEFAULT '[]'::jsonb,
    site_assignments jsonb DEFAULT '[]'::jsonb,
    is_default boolean DEFAULT false,
    is_active boolean DEFAULT true,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE wecoza_events.supervisors OWNER TO "John";

--
-- TOC entry 4611 (class 0 OID 0)
-- Dependencies: 297
-- Name: TABLE supervisors; Type: COMMENT; Schema: wecoza_events; Owner: John
--

COMMENT ON TABLE wecoza_events.supervisors IS 'Supervisors assigned to manage classes and receive notifications';


--
-- TOC entry 298 (class 1259 OID 17624)
-- Name: supervisors_id_seq; Type: SEQUENCE; Schema: wecoza_events; Owner: John
--

CREATE SEQUENCE wecoza_events.supervisors_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE wecoza_events.supervisors_id_seq OWNER TO "John";

--
-- TOC entry 4612 (class 0 OID 0)
-- Dependencies: 298
-- Name: supervisors_id_seq; Type: SEQUENCE OWNED BY; Schema: wecoza_events; Owner: John
--

ALTER SEQUENCE wecoza_events.supervisors_id_seq OWNED BY wecoza_events.supervisors.id;


--
-- TOC entry 3509 (class 2604 OID 17625)
-- Name: agent_absences absence_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_absences ALTER COLUMN absence_id SET DEFAULT nextval('public.agent_absences_absence_id_seq'::regclass);


--
-- TOC entry 3658 (class 2604 OID 24747)
-- Name: agent_meta meta_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_meta ALTER COLUMN meta_id SET DEFAULT nextval('public.agent_meta_meta_id_seq'::regclass);


--
-- TOC entry 3511 (class 2604 OID 17626)
-- Name: agent_notes note_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_notes ALTER COLUMN note_id SET DEFAULT nextval('public.agent_notes_note_id_seq'::regclass);


--
-- TOC entry 3513 (class 2604 OID 17627)
-- Name: agent_orders order_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_orders ALTER COLUMN order_id SET DEFAULT nextval('public.agent_orders_order_id_seq'::regclass);


--
-- TOC entry 3516 (class 2604 OID 17628)
-- Name: agent_replacements replacement_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_replacements ALTER COLUMN replacement_id SET DEFAULT nextval('public.agent_replacements_replacement_id_seq'::regclass);


--
-- TOC entry 3517 (class 2604 OID 17629)
-- Name: agents agent_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agents ALTER COLUMN agent_id SET DEFAULT nextval('public.agents_agent_id_seq'::regclass);


--
-- TOC entry 3524 (class 2604 OID 17630)
-- Name: attendance_registers register_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.attendance_registers ALTER COLUMN register_id SET DEFAULT nextval('public.attendance_registers_register_id_seq'::regclass);


--
-- TOC entry 3654 (class 2604 OID 24720)
-- Name: class_events event_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_events ALTER COLUMN event_id SET DEFAULT nextval('public.class_events_event_id_seq'::regclass);


--
-- TOC entry 3527 (class 2604 OID 17632)
-- Name: class_material_tracking id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_material_tracking ALTER COLUMN id SET DEFAULT nextval('public.class_material_tracking_id_seq'::regclass);


--
-- TOC entry 3531 (class 2604 OID 17633)
-- Name: class_notes note_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_notes ALTER COLUMN note_id SET DEFAULT nextval('public.class_notes_note_id_seq'::regclass);


--
-- TOC entry 3533 (class 2604 OID 17634)
-- Name: class_schedules schedule_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_schedules ALTER COLUMN schedule_id SET DEFAULT nextval('public.class_schedules_schedule_id_seq'::regclass);


--
-- TOC entry 3635 (class 2604 OID 24604)
-- Name: class_type_subjects class_type_subject_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_type_subjects ALTER COLUMN class_type_subject_id SET DEFAULT nextval('public.class_type_subjects_class_type_subject_id_seq'::regclass);


--
-- TOC entry 3630 (class 2604 OID 24587)
-- Name: class_types class_type_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_types ALTER COLUMN class_type_id SET DEFAULT nextval('public.class_types_class_type_id_seq'::regclass);


--
-- TOC entry 3534 (class 2604 OID 17635)
-- Name: classes class_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.classes ALTER COLUMN class_id SET DEFAULT nextval('public.classes_class_id_seq'::regclass);


--
-- TOC entry 3544 (class 2604 OID 17636)
-- Name: client_communications communication_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.client_communications ALTER COLUMN communication_id SET DEFAULT nextval('public.client_communications_communication_id_seq'::regclass);


--
-- TOC entry 3546 (class 2604 OID 17637)
-- Name: clients client_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.clients ALTER COLUMN client_id SET DEFAULT nextval('public.clients_client_id_seq'::regclass);


--
-- TOC entry 3549 (class 2604 OID 17638)
-- Name: collections collection_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.collections ALTER COLUMN collection_id SET DEFAULT nextval('public.collections_collection_id_seq'::regclass);


--
-- TOC entry 3552 (class 2604 OID 17639)
-- Name: deliveries delivery_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.deliveries ALTER COLUMN delivery_id SET DEFAULT nextval('public.deliveries_delivery_id_seq'::regclass);


--
-- TOC entry 3555 (class 2604 OID 17640)
-- Name: employers employer_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.employers ALTER COLUMN employer_id SET DEFAULT nextval('public.employers_employer_id_seq'::regclass);


--
-- TOC entry 3558 (class 2604 OID 17641)
-- Name: exam_results result_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.exam_results ALTER COLUMN result_id SET DEFAULT nextval('public.exam_results_result_id_seq'::regclass);


--
-- TOC entry 3561 (class 2604 OID 17642)
-- Name: exams exam_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.exams ALTER COLUMN exam_id SET DEFAULT nextval('public.exams_exam_id_seq'::regclass);


--
-- TOC entry 3564 (class 2604 OID 17643)
-- Name: files file_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.files ALTER COLUMN file_id SET DEFAULT nextval('public.files_file_id_seq'::regclass);


--
-- TOC entry 3566 (class 2604 OID 17644)
-- Name: history history_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.history ALTER COLUMN history_id SET DEFAULT nextval('public.history_history_id_seq'::regclass);


--
-- TOC entry 3568 (class 2604 OID 17645)
-- Name: latest_document id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.latest_document ALTER COLUMN id SET DEFAULT nextval('public.qa_visits_id_seq'::regclass);


--
-- TOC entry 3648 (class 2604 OID 24659)
-- Name: learner_hours_log log_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_hours_log ALTER COLUMN log_id SET DEFAULT nextval('public.learner_hours_log_log_id_seq'::regclass);


--
-- TOC entry 3640 (class 2604 OID 24626)
-- Name: learner_lp_tracking tracking_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_lp_tracking ALTER COLUMN tracking_id SET DEFAULT nextval('public.learner_lp_tracking_tracking_id_seq'::regclass);


--
-- TOC entry 3571 (class 2604 OID 17646)
-- Name: learner_portfolios portfolio_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_portfolios ALTER COLUMN portfolio_id SET DEFAULT nextval('public.learner_portfolios_portfolio_id_seq'::regclass);


--
-- TOC entry 3652 (class 2604 OID 24692)
-- Name: learner_progression_portfolios file_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_progression_portfolios ALTER COLUMN file_id SET DEFAULT nextval('public.learner_progression_portfolios_file_id_seq'::regclass);


--
-- TOC entry 3573 (class 2604 OID 17647)
-- Name: learner_progressions progression_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_progressions ALTER COLUMN progression_id SET DEFAULT nextval('public.learner_progressions_progression_id_seq'::regclass);


--
-- TOC entry 3574 (class 2604 OID 17648)
-- Name: learner_qualifications id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_qualifications ALTER COLUMN id SET DEFAULT nextval('public.learner_qualifications_id_seq'::regclass);


--
-- TOC entry 3660 (class 2604 OID 24765)
-- Name: learner_sponsors id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_sponsors ALTER COLUMN id SET DEFAULT nextval('public.learner_sponsors_id_seq'::regclass);


--
-- TOC entry 3575 (class 2604 OID 17649)
-- Name: learners id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learners ALTER COLUMN id SET DEFAULT nextval('public.learners_learner_id_seq'::regclass);


--
-- TOC entry 3578 (class 2604 OID 17650)
-- Name: locations location_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.locations ALTER COLUMN location_id SET DEFAULT nextval('public.locations_location_id_seq'::regclass);


--
-- TOC entry 3581 (class 2604 OID 17651)
-- Name: products product_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.products ALTER COLUMN product_id SET DEFAULT nextval('public.products_product_id_seq'::regclass);


--
-- TOC entry 3584 (class 2604 OID 17652)
-- Name: progress_reports report_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.progress_reports ALTER COLUMN report_id SET DEFAULT nextval('public.progress_reports_report_id_seq'::regclass);


--
-- TOC entry 3587 (class 2604 OID 17653)
-- Name: qa_visits id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.qa_visits ALTER COLUMN id SET DEFAULT nextval('public.qa_visits_id_seq1'::regclass);


--
-- TOC entry 3590 (class 2604 OID 17654)
-- Name: sites site_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.sites ALTER COLUMN site_id SET DEFAULT nextval('public.sites_site_id_seq'::regclass);


--
-- TOC entry 3593 (class 2604 OID 17655)
-- Name: user_permissions permission_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.user_permissions ALTER COLUMN permission_id SET DEFAULT nextval('public.user_permissions_permission_id_seq'::regclass);


--
-- TOC entry 3594 (class 2604 OID 17656)
-- Name: user_roles role_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.user_roles ALTER COLUMN role_id SET DEFAULT nextval('public.user_roles_role_id_seq'::regclass);


--
-- TOC entry 3595 (class 2604 OID 17657)
-- Name: users user_id; Type: DEFAULT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.users ALTER COLUMN user_id SET DEFAULT nextval('public.users_user_id_seq'::regclass);


--
-- TOC entry 3598 (class 2604 OID 17658)
-- Name: audit_log id; Type: DEFAULT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.audit_log ALTER COLUMN id SET DEFAULT nextval('wecoza_events.audit_log_id_seq'::regclass);


--
-- TOC entry 3602 (class 2604 OID 17659)
-- Name: dashboard_status id; Type: DEFAULT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.dashboard_status ALTER COLUMN id SET DEFAULT nextval('wecoza_events.dashboard_status_id_seq'::regclass);


--
-- TOC entry 3608 (class 2604 OID 17660)
-- Name: events_log id; Type: DEFAULT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.events_log ALTER COLUMN id SET DEFAULT nextval('wecoza_events.events_log_id_seq'::regclass);


--
-- TOC entry 3613 (class 2604 OID 17661)
-- Name: notification_queue id; Type: DEFAULT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.notification_queue ALTER COLUMN id SET DEFAULT nextval('wecoza_events.notification_queue_id_seq'::regclass);


--
-- TOC entry 3622 (class 2604 OID 17662)
-- Name: supervisors id; Type: DEFAULT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.supervisors ALTER COLUMN id SET DEFAULT nextval('wecoza_events.supervisors_id_seq'::regclass);


--
-- TOC entry 4130 (class 0 OID 17289)
-- Dependencies: 217
-- Data for Name: agent_absences; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.agent_absences (absence_id, agent_id, class_id, absence_date, reason, reported_at) FROM stdin;
1	1	1	2023-03-10	Sick leave	2024-10-17 13:21:57.870205
3	4	3	2023-04-20	Medical appointment	2024-10-17 13:21:57.870205
5	7	5	2023-05-12	Jury duty	2024-10-17 13:21:57.870205
6	8	6	2023-05-25	Personal leave	2024-10-17 13:21:57.870205
7	10	7	2023-06-08	Car trouble	2024-10-17 13:21:57.870205
9	12	9	2023-07-05	Bereavement	2024-10-17 13:21:57.870205
10	14	10	2023-07-18	Public transport strike	2024-10-17 13:21:57.870205
13	2	13	2023-09-09	Professional conference	2024-10-17 13:21:57.870205
14	3	14	2023-08-23	Sick leave	2024-10-17 13:21:57.870205
15	5	15	2023-09-06	Weather-related travel issues	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4223 (class 0 OID 24744)
-- Dependencies: 312
-- Data for Name: agent_meta; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.agent_meta (meta_id, agent_id, meta_key, meta_value, created_at) FROM stdin;
\.


--
-- TOC entry 4132 (class 0 OID 17296)
-- Dependencies: 219
-- Data for Name: agent_notes; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.agent_notes (note_id, agent_id, note, note_date) FROM stdin;
1	1	Excellent performance in recent training session.	2023-03-15 00:00:00
2	2	Requested additional materials for upcoming class.	2023-03-20 00:00:00
3	3	Completed advanced certification in data analysis.	2023-04-01 00:00:00
4	4	Received positive feedback from learners.	2023-04-10 00:00:00
5	5	Suggested improvements for course curriculum.	2023-04-15 00:00:00
6	6	Conducted successful workshop for new instructors.	2023-04-20 00:00:00
7	7	Requires additional support for technical subjects.	2023-05-01 00:00:00
8	8	Demonstrated exceptional problem-solving skills.	2023-05-10 00:00:00
9	9	Volunteered for community outreach program.	2023-05-15 00:00:00
10	10	Missed scheduled training session. Follow up required.	2023-05-20 00:00:00
11	11	Received award for innovative teaching methods.	2023-06-01 00:00:00
12	12	Requested leave for professional development conference.	2023-06-10 00:00:00
13	13	Consistently delivers high-quality instruction.	2023-06-15 00:00:00
14	14	Needs improvement in time management.	2023-06-20 00:00:00
15	15	Successfully integrated new technology into lessons.	2023-07-01 00:00:00
\.


--
-- TOC entry 4134 (class 0 OID 17303)
-- Dependencies: 221
-- Data for Name: agent_orders; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.agent_orders (order_id, agent_id, class_id, order_number, class_time, class_days, order_hours, created_at, updated_at) FROM stdin;
1	1	1	ORD-2023-001	09:00:00	Monday,Wednesday,Friday	120	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
3	3	3	ORD-2023-003	08:00:00	Monday,Tuesday,Wednesday,Thursday,Friday	200	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	5	5	ORD-2023-005	07:00:00	Monday,Tuesday,Wednesday,Thursday,Friday	180	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	6	6	ORD-2023-006	13:00:00	Tuesday,Thursday	80	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
7	7	7	ORD-2023-007	10:00:00	Monday,Wednesday,Friday	120	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	9	9	ORD-2023-009	14:00:00	Monday,Wednesday,Friday	120	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	10	10	ORD-2023-010	08:00:00	Tuesday,Thursday	80	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	13	13	ORD-2023-013	10:00:00	Saturday	40	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	14	14	ORD-2023-014	13:00:00	Monday,Wednesday,Friday	120	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
15	15	15	ORD-2023-015	09:00:00	Tuesday,Thursday,Saturday	100	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4136 (class 0 OID 17309)
-- Dependencies: 223
-- Data for Name: agent_replacements; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.agent_replacements (replacement_id, class_id, original_agent_id, replacement_agent_id, start_date, end_date, reason) FROM stdin;
1	1	1	2	2023-03-10	2023-03-10	Original agent on sick leave
3	3	4	5	2023-04-20	2023-04-20	Medical appointment of original agent
5	5	7	8	2023-05-12	2023-05-12	Original agent on jury duty
6	6	8	9	2023-05-25	2023-05-25	Personal leave of original agent
7	7	10	11	2023-06-08	2023-06-08	Car trouble of original agent
9	9	12	13	2023-07-05	2023-07-07	Bereavement leave of original agent
10	10	14	15	2023-07-18	2023-07-18	Public transport strike affecting original agent
13	13	2	3	2023-09-09	2023-09-09	Professional conference attendance
14	14	3	4	2023-08-23	2023-08-23	Original agent on sick leave
15	15	5	6	2023-09-06	2023-09-06	Weather-related travel issues of original agent
43	48	1	12	2025-07-25	\N	\N
\.


--
-- TOC entry 4138 (class 0 OID 17315)
-- Dependencies: 225
-- Data for Name: agents; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.agents (agent_id, first_name, initials, surname, gender, race, sa_id_no, passport_number, tel_number, email_address, residential_address_line, residential_suburb, residential_postal_code, preferred_working_area_1, preferred_working_area_2, preferred_working_area_3, highest_qualification, sace_number, sace_registration_date, sace_expiry_date, quantum_assessment, agent_training_date, bank_name, bank_branch_code, bank_account_number, signed_agreement_date, agent_notes, created_at, updated_at, title, id_type, address_line_2, criminal_record_date, criminal_record_file, province, city, phase_registered, subjects_registered, account_holder, account_type, status, created_by, updated_by, second_name, signed_agreement_file, quantum_maths_score, quantum_science_score) FROM stdin;
30	John	JMM	Montgomery	M	White	6702155114080		0791778896	test@test.com	66 Porterfield Road	Blouberg Rise	7441	1	2	3	Matric	35623235cffg	2025-07-02	2025-08-06	66.00	2025-07-08	FNB	1151	5151255155	2025-07-08	\N	2025-07-19 12:00:59	2025-07-19 12:01:01.290967	Mr	sa_id	Under The Tree	2025-07-10	/agents/agent-30-criminal_record_file-1752926460.pdf	Western Cape	Cape Town	Foundation	Maths2	Koos	Savings	active	1	1	Michael	/agents/agent-30-signed_agreement_file-1752926459.pdf	77	88
31	Adam	AR	Evans	M	African	8901200039080		011 668 4300	adamre@gmail.com	7 De Korte Street	Appelpark	0000	9	\N	\N	Higher Education	802u6335	2025-09-02	2026-07-21	80.00	2025-10-02	Capitec	0000	00000000	2025-10-02	\N	2025-10-02 06:03:26	2025-10-02 06:03:28.145105	Mr	sa_id		2025-10-02	\N	Free State	Bloemfontein	FET	Mathematics	AR Evans	Savings	active	2	2	Richard	/agents/agent-31-signed_agreement_file-1759385007.pdf	70	80
1	Michael	M	van der Berg	M	White	8005155080081	\N	+27823456789	michael.vdb@example.com	10 Oak Street	Sandton	2196	1	2	3	Masters in Education	SACE123456	2022-01-15	2027-01-14	92.50	2022-02-15	FNB	250655	62123456789	2022-02-10	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Sandton	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
2	Thandi	T	Nkosi	F	African	8508121234567	\N	+27834567890	thandi.nkosi@example.com	25 Acacia Road	Durbanville	7551	2	3	4	B.Ed	SACE234567	2021-11-20	2026-11-19	90.00	2021-12-15	Standard Bank	051001	001234567	2021-11-25	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Durbanville	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
3	Rajesh	R	Patel	M	Indian	7703035080081	\N	+27845678901	rajesh.patel@example.com	5 Palm Avenue	Umhlanga	4320	3	4	5	PhD in Mathematics	SACE345678	2022-03-10	2027-03-09	94.50	2022-04-15	Nedbank	198765	1122334455	2022-03-20	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Umhlanga	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
4	Lerato	L	Moloi	F	African	9101015080082	\N	+27856789012	lerato.moloi@example.com	15 Birch Lane	Hatfield	0028	4	5	6	B.Sc in Computer Science	SACE456789	2022-05-05	2027-05-04	89.00	2022-06-15	ABSA	632005	9087654321	2022-05-15	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Hatfield	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
5	Johannes	J	Pretorius	M	White	7506075080083	\N	+27867890123	johannes.pretorius@example.com	30 Willow Road	Stellenbosch	7600	5	6	7	M.Sc in Agricultural Sciences	SACE567890	2021-09-01	2026-08-31	88.50	2021-10-15	Capitec	470010	1597532468	2021-09-10	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Stellenbosch	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
6	Nomvula	N	Dlamini	F	African	8807075080084	\N	+27878901234	nomvula.dlamini@example.com	20 Cedar Street	Polokwane	0699	6	7	8	B.A in Communications	SACE678901	2022-07-20	2027-07-19	95.50	2022-08-15	FNB	250655	7531598524	2022-07-25	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Polokwane	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
7	David	D	O'Connor	M	White	7302025080085	\N	+27889012345	david.oconnor@example.com	8 Elm Avenue	Kimberley	8301	7	8	9	B.Eng in Mining Engineering	SACE789012	2021-12-10	2026-12-09	91.00	2022-01-20	Standard Bank	051001	3698521470	2021-12-15	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Kimberley	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
9	Pieter	P	van Zyl	M	White	7609065080087	\N	+27801234567	pieter.vanzyl@example.com	18 Pine Lane	Bloemfontein	9301	9	10	11	M.A in Environmental Studies	SACE901234	2022-04-15	2027-04-14	90.50	2022-05-15	ABSA	632005	7539514560	2022-04-20	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Bloemfontein	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
10	Fatima	F	Ismail	F	Indian	8205075080088	\N	+27812345678	fatima.ismail@example.com	22 Olive Street	Port Elizabeth	6001	10	11	12	B.Sc in Physics	SACE012345	2022-06-30	2027-06-29	94.00	2022-07-30	Capitec	470010	3571592468	2022-07-05	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Port Elizabeth	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
11	Sipho	S	Ndlovu	M	African	7901015080089	\N	+27823456780	sipho.ndlovu@example.com	7 Baobab Avenue	Soweto	1804	11	12	13	B.Com in Accounting	SACE123450	2022-08-10	2027-08-09	87.50	2022-09-15	FNB	250655	9632587410	2022-08-20	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Soweto	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
12	Anita	A	van Rensburg	F	White	8404045080090	\N	+27834567891	anita.vr@example.com	14 Jacaranda Street	Centurion	0157	12	13	14	M.Ed in Curriculum Studies	SACE234501	2022-10-05	2027-10-04	96.00	2022-11-15	Standard Bank	051001	7418529630	2022-10-10	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Centurion	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
13	Themba	T	Mkhize	M	African	7707075080091	\N	+27845678902	themba.mkhize@example.com	9 Protea Road	Paarl	7646	13	14	15	PhD in Education Technology	SACE345012	2022-12-20	2027-12-19	92.00	2023-01-25	Nedbank	198765	8529637410	2022-12-30	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	Paarl	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
15	Lwazi	L	Zuma	M	African	8108085080093	\N	+27867890124	lwazi.zuma@example.com	27 Kingfisher Street	East London	5201	15	1	2	M.A in Linguistics	SACE501234	2023-04-10	2028-04-09	93.00	2023-05-15	Capitec	470010	9630258741	2023-04-15	\N	2024-10-17 13:21:57.870205	2025-07-18 18:38:59.895616	\N	sa_id	East London	\N	\N	\N	\N	\N	\N	\N	\N	active	\N	\N	\N	\N	0	0
14	Sarah	S	Botha	F	White	9005025080092	\N	+27856789013	sarah.botha@example.com	33 Sunbird Lane	Pietermaritzburg	3201	14	15	1	B.Sc in Information Systems	SACE450123	2023-02-15	2028-02-14	89.50	2023-03-15	ABSA	632005	7410852963	2023-02-20	\N	2024-10-17 13:21:57.870205	2025-07-21 13:17:43.059177	\N	sa_id	Pietermaritzburg	\N	\N	\N	\N	\N	\N	\N	\N	deleted	\N	1	\N	\N	0	0
33	Aubrey	AS	Zwane	M	African	7204195345088		0116684300	zwane2017@4agents.co.za	277 Jorissen Street	Krugersdorp	1739	1	\N	\N	Bed	123456	2025-12-10	2029-01-30	74.00	2025-12-10	FNB	250655	12345676	2025-12-10	\N	2025-12-10 15:25:47	2025-12-10 15:25:48.028929	Mr	sa_id		\N	\N	Gauteng	Krugersdorp	Foundation	All Subjects	Zwane	Savings	active	3	3	Sipho	/agents/agent-33-signed_agreement_file-1765373148.pdf	65	75
8	Zanele	Z	Mthembu	F	African	9003035080086	\N	+27890123456	zanele.mthembu@example.com	12 Maple Road	Nelspruit	1200	8	9	10	B.Ed in Special Needs Education	SACE890123	2022-02-25	2027-02-24	93.50	2022-03-25	Nedbank	198765	9517538520	2022-03-01	\N	2024-10-17 13:21:57.870205	2026-02-12 15:01:28.463027	\N	sa_id	Nelspruit	\N	\N	\N	\N	\N	\N	\N	\N	deleted	\N	1	\N	\N	0	0
32	Chloee	CSB	Burger	F	White	7902100039080		011 668 4302	AshBur@gmail.com	7 De Korte Street	Appelpark	0000	7	9	\N	Higher Education	802u6335	2025-10-03	2026-06-12	70.00	2025-10-03	Capitec	12345	1231234	2026-02-11	\N	2025-10-03 05:51:00	2026-02-13 15:06:42.894749	Ms	sa_id		2025-10-03	\N	Free State	Bloemfontein	FET	Mathematics	AR Evans	Savings	active	2	1	Sonnia	/2026/02/code-cheatsheet.pdf	80	90
\.


--
-- TOC entry 4140 (class 0 OID 17336)
-- Dependencies: 227
-- Data for Name: attendance_registers; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.attendance_registers (register_id, class_id, date, agent_id, created_at, updated_at) FROM stdin;
1	1	2023-03-01	1	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
2	1	2023-03-03	1	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	3	2023-03-06	4	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	3	2023-03-07	4	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	5	2023-03-13	7	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	5	2023-03-14	7	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
11	6	2023-04-04	8	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
12	6	2023-04-06	8	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	7	2023-04-17	10	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	7	2023-04-19	10	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
17	9	2023-05-15	12	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
18	9	2023-05-17	12	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
19	10	2023-06-06	14	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
20	10	2023-06-08	14	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
25	13	2023-08-05	2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
26	13	2023-08-12	2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
27	14	2023-07-17	3	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
28	14	2023-07-19	3	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
29	15	2023-08-22	5	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
30	15	2023-08-24	5	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4142 (class 0 OID 17342)
-- Dependencies: 229
-- Data for Name: class_agents; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.class_agents (class_id, agent_id, start_date, end_date, role) FROM stdin;
1	1	2023-02-01	\N	Primary
1	2	2023-02-01	\N	Assistant
3	4	2023-03-01	\N	Primary
3	5	2023-03-01	\N	Assistant
5	7	2023-03-15	\N	Primary
6	8	2023-04-01	\N	Primary
6	9	2023-04-01	\N	Assistant
7	10	2023-04-15	\N	Primary
9	12	2023-05-15	\N	Primary
9	13	2023-05-15	\N	Assistant
10	14	2023-06-01	\N	Primary
13	2	2023-08-01	\N	Primary
14	3	2023-07-15	\N	Primary
14	4	2023-07-15	\N	Assistant
15	5	2023-08-15	\N	Primary
\.


--
-- TOC entry 4221 (class 0 OID 24717)
-- Dependencies: 310
-- Data for Name: class_events; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.class_events (event_id, event_type, entity_type, entity_id, user_id, event_data, ai_summary, notification_status, created_at, enriched_at, sent_at, viewed_at, acknowledged_at) FROM stdin;
1	CLASS_UPDATE	class	58	1	{"diff": {"updated_at": {"new": "2026-02-05 11:10:02", "old": "2026-02-05 12:23:37.964019"}, "event_dates": {"new": [{"date": "2026-02-07", "type": "Deliveries", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery Date"}, {"date": "2026-02-09", "type": "Exams", "notes": "Exam 102 note", "status": "Pending", "description": "Exam 102"}], "old": []}, "schedule_data": {"new": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:10:02+00:00", "validatedAt": "2026-02-05T11:10:02+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "old": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-03T13:55:13+00:00", "validatedAt": "2026-02-03T13:55:13+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "45", "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 11:10:02", "class_agent": null, "event_dates": [{"date": "2026-02-07", "type": "Deliveries", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery Date"}, {"date": "2026-02-09", "type": "Exams", "notes": "Exam 102 note", "status": "Pending", "description": "Exam 102"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:10:02+00:00", "validatedAt": "2026-02-05T11:10:02+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 45, "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 12:23:37.964019", "class_agent": null, "event_dates": [], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-03T13:55:13+00:00", "validatedAt": "2026-02-03T13:55:13+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T11:10:02+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	\N	pending	2026-02-05 13:10:02.965989+02	\N	\N	\N	\N
2	CLASS_UPDATE	class	58	1	{"diff": {"updated_at": {"new": "2026-02-05 11:12:14", "old": "2026-02-05 13:10:20.527738"}, "event_dates": {"new": [{"date": "2026-02-08", "type": "Deliveries", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery Date"}], "old": []}, "schedule_data": {"new": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:12:14+00:00", "validatedAt": "2026-02-05T11:12:14+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "old": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:10:02+00:00", "validatedAt": "2026-02-05T11:10:02+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "45", "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 11:12:14", "class_agent": null, "event_dates": [{"date": "2026-02-08", "type": "Deliveries", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery Date"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:12:14+00:00", "validatedAt": "2026-02-05T11:12:14+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 45, "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 13:10:20.527738", "class_agent": null, "event_dates": [], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:10:02+00:00", "validatedAt": "2026-02-05T11:10:02+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T11:12:14+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	\N	pending	2026-02-05 13:12:14.874554+02	\N	\N	\N	\N
3	CLASS_UPDATE	class	58	1	{"diff": {"updated_at": {"new": "2026-02-05 11:22:37", "old": "2026-02-05 13:12:45.676178"}, "event_dates": {"new": [{"date": "2026-02-27", "type": "Deliveries", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery Date"}], "old": []}, "schedule_data": {"new": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:22:37+00:00", "validatedAt": "2026-02-05T11:22:37+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "old": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:12:14+00:00", "validatedAt": "2026-02-05T11:12:14+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "45", "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 11:22:37", "class_agent": null, "event_dates": [{"date": "2026-02-27", "type": "Deliveries", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery Date"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:22:37+00:00", "validatedAt": "2026-02-05T11:22:37+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 45, "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 13:12:45.676178", "class_agent": null, "event_dates": [], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:12:14+00:00", "validatedAt": "2026-02-05T11:12:14+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T11:22:37+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	\N	pending	2026-02-05 13:22:38.043302+02	\N	\N	\N	\N
4	CLASS_UPDATE	class	57	1	{"diff": {"updated_at": {"new": "2026-02-05 11:51:58", "old": "2026-02-05 13:38:23.014867"}, "event_dates": {"new": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}], "old": []}, "schedule_data": {"new": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:51:58+00:00", "validatedAt": "2026-02-05T11:51:58+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "old": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-12-10T13:32:55+00:00", "validatedAt": "2025-12-10T13:32:55+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "32", "class_id": 57, "order_nr": "4363463466", "client_id": 10, "exam_type": "GETC SMME", "class_code": "10-GETC-SMME4-2025-12-10-15-32", "class_type": "GETC", "created_at": "2025-12-10 13:32:55", "exam_class": true, "updated_at": "2026-02-05 11:51:58", "class_agent": null, "event_dates": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}], "learner_ids": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "SMME4", "exam_learners": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "schedule_data": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:51:58+00:00", "validatedAt": "2026-02-05T11:51:58+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "class_duration": 60, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [], "initial_class_agent": 14, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 32, "class_id": 57, "order_nr": "4363463466", "client_id": 10, "exam_type": "GETC SMME", "class_code": "10-GETC-SMME4-2025-12-10-15-32", "class_type": "GETC", "created_at": "2025-12-10 13:32:55", "exam_class": true, "updated_at": "2026-02-05 13:38:23.014867", "class_agent": null, "event_dates": [], "learner_ids": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "SMME4", "exam_learners": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "schedule_data": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-12-10T13:32:55+00:00", "validatedAt": "2025-12-10T13:32:55+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "class_duration": 60, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [], "initial_class_agent": 14, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T11:51:59+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	{"model": "gpt-4o-mini", "status": "success", "viewed": false, "summary": "- **Class Scheduling**: The class remains scheduled for weekly sessions every Wednesday from 08:00 to 16:00. The next event date is set for 07 February 2026, marked as \\"First Delivery\\" with a pending status.\\n\\n- **Learner Status**: Learner A (SMME4 level) is currently in class (CIC). No changes to enrollment status noted.\\n\\n- **Event Dates Update**: A new event date has been added for 07 February 2026; previous class updates had no scheduled events.\\n\\n- **Risks for Follow-up**: Monitor the \\"First Delivery\\" status, as it is still pending. Ensure that notifications to Front Desk are completed by the specified date.\\n\\n- **Consistent Class Parameters**: No changes in class details such as the exam type (GETC SMME), duration (60 hours), or overall class status (active).", "attempts": 1, "viewed_at": null, "error_code": null, "tokens_used": 1917, "generated_at": "2026-02-05T11:52:05+00:00", "error_message": null, "processing_time_ms": 4846}	failed	2026-02-05 13:51:59.1552+02	2026-02-05 13:52:05.163268+02	\N	\N	\N
5	CLASS_UPDATE	class	57	1	{"diff": {"updated_at": {"new": "2026-02-05 11:52:45", "old": "2026-02-05 11:51:58"}, "event_dates": {"new": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}, {"date": "2026-02-06", "type": "Deliveries", "notes": "Notify Front Desc of delivery", "status": "Pending", "description": "First Delivery Date"}], "old": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}]}, "schedule_data": {"new": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:52:45+00:00", "validatedAt": "2026-02-05T11:52:45+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "old": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:51:58+00:00", "validatedAt": "2026-02-05T11:51:58+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "32", "class_id": 57, "order_nr": "4363463466", "client_id": 10, "exam_type": "GETC SMME", "class_code": "10-GETC-SMME4-2025-12-10-15-32", "class_type": "GETC", "created_at": "2025-12-10 13:32:55", "exam_class": true, "updated_at": "2026-02-05 11:52:45", "class_agent": null, "event_dates": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}, {"date": "2026-02-06", "type": "Deliveries", "notes": "Notify Front Desc of delivery", "status": "Pending", "description": "First Delivery Date"}], "learner_ids": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "SMME4", "exam_learners": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "schedule_data": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:52:45+00:00", "validatedAt": "2026-02-05T11:52:45+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "class_duration": 60, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [], "initial_class_agent": 14, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 32, "class_id": 57, "order_nr": "4363463466", "client_id": 10, "exam_type": "GETC SMME", "class_code": "10-GETC-SMME4-2025-12-10-15-32", "class_type": "GETC", "created_at": "2025-12-10 13:32:55", "exam_class": true, "updated_at": "2026-02-05 11:51:58", "class_agent": null, "event_dates": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}], "learner_ids": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "SMME4", "exam_learners": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "schedule_data": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:51:58+00:00", "validatedAt": "2026-02-05T11:51:58+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "class_duration": 60, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [], "initial_class_agent": 14, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T11:52:45+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	{"model": "gpt-4o-mini", "status": "success", "viewed": false, "summary": "- **Event Dates Update**: Two new events have been scheduled—first delivery on XXXXXX06 (Deliveries) and XXXXXX07 (Collections), both marked as pending.\\n\\n- **Unchanged Schedule**: The class remains scheduled for Wednesdays from 08:00 to 16:00, with no changes to the duration or overall weekly pattern.\\n\\n- **Learner Status**: Learner A remains in class (CIC status) with no reported issues.\\n\\n- **Risks Identified**: The client cancellation on XXXXXX10 could impact scheduling; follow-up is needed to confirm the client's intent and potential class adjustments.\\n\\n- **Class Status**: The class is active and set to be managed under the existing structure with no noted staffing changes. Monitor for any required adjustments or learner feedback.", "attempts": 1, "viewed_at": null, "error_code": null, "tokens_used": 2080, "generated_at": "2026-02-05T11:53:59+00:00", "error_message": null, "processing_time_ms": 5195}	failed	2026-02-05 13:52:45.810409+02	2026-02-05 13:53:59.98319+02	\N	\N	\N
6	CLASS_UPDATE	class	58	1	{"diff": {"updated_at": {"new": "2026-02-05 12:32:58", "old": "2026-02-05 13:38:31.219368"}, "event_dates": {"new": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}], "old": [{"date": "2026-02-27", "type": "Deliveries", "notes": "Notify Front Desc", "status": "Completed", "description": "First Delivery Date", "completed_at": "2026-02-05 11:38:31", "completed_by": 1}]}, "schedule_data": {"new": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T12:32:58+00:00", "validatedAt": "2026-02-05T12:32:58+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "old": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:22:37+00:00", "validatedAt": "2026-02-05T11:22:37+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "45", "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 12:32:58", "class_agent": null, "event_dates": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T12:32:58+00:00", "validatedAt": "2026-02-05T12:32:58+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 45, "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 13:38:31.219368", "class_agent": null, "event_dates": [{"date": "2026-02-27", "type": "Deliveries", "notes": "Notify Front Desc", "status": "Completed", "description": "First Delivery Date", "completed_at": "2026-02-05 11:38:31", "completed_by": 1}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:22:37+00:00", "validatedAt": "2026-02-05T11:22:37+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T12:32:58+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	{"model": "gpt-4o-mini", "status": "success", "viewed": false, "summary": "- **Event Date Change**: The class has a new delivery event scheduled for **XXXXXX06** (pending), replacing the completed first delivery from **XXXXXX27**.\\n  \\n- **No Changes in Schedule**: The class schedule remains unchanged: weekly on **Tuesdays**, from **08:00 to 12:00**.\\n\\n- **Learner Status**: Both **Learner A** (NL4) and **Learner B** (RLC) are currently enrolled and maintaining their status as \\"CIC - Currently in Class.\\"\\n\\n- **Class Active Status**: The class is actively enrolled with a class type of **AET** and duration of **120 hours**.\\n\\n- **Risk Notification**: Follow-up needed on the pending delivery event for potential impacts on course progression and ensure no logistical issues arise before **XXXXXX06**.", "attempts": 1, "viewed_at": null, "error_code": null, "tokens_used": 2056, "generated_at": "2026-02-05T12:33:58+00:00", "error_message": null, "processing_time_ms": 6001}	failed	2026-02-05 14:32:58.831079+02	2026-02-05 14:33:58.7839+02	\N	\N	\N
7	CLASS_UPDATE	class	57	1	{"diff": {"updated_at": {"new": "2026-02-05 12:55:45", "old": "2026-02-05 11:52:45"}, "event_dates": {"new": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}, {"date": "2026-02-06", "type": "Deliveries", "notes": "Notify Front Desc of delivery", "status": "Pending", "description": "First Delivery Date"}, {"date": "2026-02-06", "type": "Mock Exams", "notes": "Mock Exam 102 Notes", "status": "Pending", "description": "Mock Exam 102"}], "old": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}, {"date": "2026-02-06", "type": "Deliveries", "notes": "Notify Front Desc of delivery", "status": "Pending", "description": "First Delivery Date"}]}, "schedule_data": {"new": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T12:55:45+00:00", "validatedAt": "2026-02-05T12:55:45+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "old": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:52:45+00:00", "validatedAt": "2026-02-05T11:52:45+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "32", "class_id": 57, "order_nr": "4363463466", "client_id": 10, "exam_type": "GETC SMME", "class_code": "10-GETC-SMME4-2025-12-10-15-32", "class_type": "GETC", "created_at": "2025-12-10 13:32:55", "exam_class": true, "updated_at": "2026-02-05 12:55:45", "class_agent": null, "event_dates": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}, {"date": "2026-02-06", "type": "Deliveries", "notes": "Notify Front Desc of delivery", "status": "Pending", "description": "First Delivery Date"}, {"date": "2026-02-06", "type": "Mock Exams", "notes": "Mock Exam 102 Notes", "status": "Pending", "description": "Mock Exam 102"}], "learner_ids": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "SMME4", "exam_learners": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "schedule_data": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T12:55:45+00:00", "validatedAt": "2026-02-05T12:55:45+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "class_duration": 60, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [], "initial_class_agent": 14, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 32, "class_id": 57, "order_nr": "4363463466", "client_id": 10, "exam_type": "GETC SMME", "class_code": "10-GETC-SMME4-2025-12-10-15-32", "class_type": "GETC", "created_at": "2025-12-10 13:32:55", "exam_class": true, "updated_at": "2026-02-05 11:52:45", "class_agent": null, "event_dates": [{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}, {"date": "2026-02-06", "type": "Deliveries", "notes": "Notify Front Desc of delivery", "status": "Pending", "description": "First Delivery Date"}], "learner_ids": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "SMME4", "exam_learners": [{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}], "schedule_data": {"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T11:52:45+00:00", "validatedAt": "2026-02-05T11:52:45+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "class_duration": 60, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [], "initial_class_agent": 14, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T12:55:46+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	{"model": "gpt-4o-mini", "status": "success", "viewed": false, "summary": "- **Event Dates Update**: Added a new event for Mock Exam 102 on XXXXXX06, alongside existing collections and delivery dates scheduled for the same day. Ensure front desk notification is timely.\\n  \\n- **Class Schedule**: The class continues with a weekly schedule on Wednesdays, with unchanged start and end times (08:00 - 16:00) and a duration of 8 hours. No immediate changes in staffing or learners.\\n\\n- **Exception Date**: An exception is noted for XXXXXX10 due to a client cancellation. Confirm if additional arrangements are necessary for that date.\\n\\n- **Learner Status**: Learner A remains in class with status \\"CIC - Currently in Class.\\" Monitor any potential learning disruptions due to scheduling changes.\\n\\n- **Follow-Up Risks**: Pending notifications for events and the impact of the client cancellation on Learner A’s attendance may require follow-up to ensure smooth operations.", "attempts": 1, "viewed_at": null, "error_code": null, "tokens_used": 2298, "generated_at": "2026-02-05T12:55:56+00:00", "error_message": null, "processing_time_ms": 5115}	sent	2026-02-05 14:55:46.133376+02	2026-02-05 14:55:56.50509+02	2026-02-05 14:56:00.116855+02	\N	\N
8	CLASS_UPDATE	class	58	1	{"diff": {"updated_at": {"new": "2026-02-05 14:04:47", "old": "2026-02-05 12:32:58"}, "event_dates": {"new": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}, {"date": "2026-02-08", "type": "Exams", "notes": "Exam 101 Note", "status": "Pending", "description": "Exam 101"}], "old": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}]}, "schedule_data": {"new": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:04:47+00:00", "validatedAt": "2026-02-05T14:04:47+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "old": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T12:32:58+00:00", "validatedAt": "2026-02-05T12:32:58+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "45", "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 14:04:47", "class_agent": null, "event_dates": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}, {"date": "2026-02-08", "type": "Exams", "notes": "Exam 101 Note", "status": "Pending", "description": "Exam 101"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:04:47+00:00", "validatedAt": "2026-02-05T14:04:47+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 45, "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 12:32:58", "class_agent": null, "event_dates": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T12:32:58+00:00", "validatedAt": "2026-02-05T12:32:58+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T14:04:47+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	\N	pending	2026-02-05 16:04:47.929087+02	\N	\N	\N	\N
9	CLASS_UPDATE	class	58	1	{"diff": {"updated_at": {"new": "2026-02-05 14:10:35", "old": "2026-02-05 14:04:47"}, "event_dates": {"new": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}, {"date": "2026-02-08", "type": "Exams", "notes": "Exam 101 Note", "status": "Pending", "description": "Exam 101"}, {"date": "2026-02-08", "type": "Mock Exams", "notes": "Mock 101 Note", "status": "Pending", "description": "Mock 101"}], "old": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}, {"date": "2026-02-08", "type": "Exams", "notes": "Exam 101 Note", "status": "Pending", "description": "Exam 101"}]}, "schedule_data": {"new": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:10:34+00:00", "validatedAt": "2026-02-05T14:10:34+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "old": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:04:47+00:00", "validatedAt": "2026-02-05T14:04:47+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "45", "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 14:10:35", "class_agent": null, "event_dates": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}, {"date": "2026-02-08", "type": "Exams", "notes": "Exam 101 Note", "status": "Pending", "description": "Exam 101"}, {"date": "2026-02-08", "type": "Mock Exams", "notes": "Mock 101 Note", "status": "Pending", "description": "Mock 101"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:10:34+00:00", "validatedAt": "2026-02-05T14:10:34+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 45, "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 14:04:47", "class_agent": null, "event_dates": [{"date": "2026-02-06", "type": "Deliveries", "notes": "", "status": "Pending", "description": "Second Delivery Date"}, {"date": "2026-02-08", "type": "Exams", "notes": "Exam 101 Note", "status": "Pending", "description": "Exam 101"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:04:47+00:00", "validatedAt": "2026-02-05T14:04:47+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T14:10:35+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	{"model": "gpt-4o-mini", "status": "success", "viewed": false, "summary": "- **Event Dates**: Additional Mock Exam (Mock 101) scheduled on XXXXXX08 alongside previously scheduled Exam 101, both marked as pending.\\n\\n- **Schedule Consistency**: The weekly schedule remains unchanged. Classes will run on Tuesdays from 08:00 to 12:00 with an End Date of XXXXXX11.\\n\\n- **Learner Status**: Learner A and Learner B remain in class, both categorized as \\"CIC - Currently in Class.\\"\\n\\n- **No Immediate Changes**: No new class agents or staffing changes have been introduced, maintaining the current assignments.\\n\\n- **Risks**: Follow-up required on pending event dates (delivered, exams) to mitigate the risk of scheduling conflicts or learner readiness.", "attempts": 1, "viewed_at": null, "error_code": null, "tokens_used": 2269, "generated_at": "2026-02-05T14:10:49+00:00", "error_message": null, "processing_time_ms": 3803}	sent	2026-02-05 16:10:35.696588+02	2026-02-05 16:10:49.726058+02	2026-02-05 16:10:53.605958+02	\N	\N
10	CLASS_UPDATE	class	58	1	{"diff": {"updated_at": {"new": "2026-02-05 14:52:08", "old": "2026-02-05 16:13:01.103678"}, "event_dates": {"new": [{"date": "2026-02-08", "type": "Deliveries", "notes": "Material Tracking Note", "status": "Pending", "description": "Material Tracking"}], "old": [{"date": "2026-02-06", "type": "Deliveries", "notes": "5235235235", "status": "Completed", "description": "Second Delivery Date", "completed_at": "2026-02-05 14:12:56", "completed_by": 1}, {"date": "2026-02-08", "type": "Exams", "notes": "Exam 101 Note", "status": "Completed", "description": "Exam 101", "completed_at": "2026-02-05 14:12:50", "completed_by": 1}, {"date": "2026-02-08", "type": "Mock Exams", "notes": "Mock 101 Note", "status": "Completed", "description": "Mock 101", "completed_at": "2026-02-05 14:13:01", "completed_by": 1}]}, "schedule_data": {"new": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:52:08+00:00", "validatedAt": "2026-02-05T14:52:08+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "old": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:10:34+00:00", "validatedAt": "2026-02-05T14:10:34+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": "45", "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 14:52:08", "class_agent": null, "event_dates": [{"date": "2026-02-08", "type": "Deliveries", "notes": "Material Tracking Note", "status": "Pending", "description": "Material Tracking"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:52:08+00:00", "validatedAt": "2026-02-05T14:52:08+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 45, "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 16:13:01.103678", "class_agent": null, "event_dates": [{"date": "2026-02-06", "type": "Deliveries", "notes": "5235235235", "status": "Completed", "description": "Second Delivery Date", "completed_at": "2026-02-05 14:12:56", "completed_by": 1}, {"date": "2026-02-08", "type": "Exams", "notes": "Exam 101 Note", "status": "Completed", "description": "Exam 101", "completed_at": "2026-02-05 14:12:50", "completed_by": 1}, {"date": "2026-02-08", "type": "Mock Exams", "notes": "Mock 101 Note", "status": "Completed", "description": "Mock 101", "completed_at": "2026-02-05 14:13:01", "completed_by": 1}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:10:34+00:00", "validatedAt": "2026-02-05T14:10:34+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-05T14:52:09+00:00", "changed_fields": ["schedule_data", "event_dates", "updated_at"]}}	{"model": "gpt-4o-mini", "status": "success", "viewed": false, "summary": "- **Event Dates Update**: A new pending delivery event for material tracking has been scheduled for XXXXXX08, replacing a completed second delivery event that occurred on XXXXXX06 and two completed exams and mock exams on the same date (XXXXXX08).\\n\\n- **Class Scheduling Consistency**: The schedule remains unchanged with classes held weekly on Tuesdays from 08:00 to 12:00, with the current duration set at 4 hours. \\n\\n- **Learner Status**: Both Learner A (NL4) and Learner B (RLC) are actively continuing in class with their current status marked as \\"CIC - Currently in Class\\".\\n\\n- **Potential Risks**: The shift from completed events to pending events for material tracking could impact upcoming class preparations, necessitating a follow-up to ensure materials are delivered on time. \\n\\n- **Follow-Up Required**: Confirm the status of pending delivery by XXXXXX08 to mitigate risks of disruption in class activities and learner experience.", "attempts": 1, "viewed_at": null, "error_code": null, "tokens_used": 2344, "generated_at": "2026-02-05T14:52:18+00:00", "error_message": null, "processing_time_ms": 8050}	sent	2026-02-05 16:52:09.212093+02	2026-02-05 16:52:18.452316+02	2026-02-05 16:52:22.702121+02	\N	\N
14	LEARNER_REMOVE	learner	11	1	{"action": "removed_from_class", "class_id": 58, "metadata": {"timestamp": "2026-02-13T12:02:46+00:00", "learner_id": 11}, "learner_id": 11}	\N	pending	2026-02-13 14:02:46.173045+02	\N	\N	\N	\N
15	LEARNER_REMOVE	learner	35	1	{"action": "removed_from_class", "class_id": 58, "metadata": {"timestamp": "2026-02-13T12:02:46+00:00", "learner_id": 35}, "learner_id": 35}	\N	pending	2026-02-13 14:02:46.284032+02	\N	\N	\N	\N
11	CLASS_UPDATE	class	58	1	{"diff": {"updated_at": {"new": "2026-02-13 12:02:44", "old": "2026-02-05 14:52:08"}, "class_agent": {"new": 7, "old": null}, "event_dates": {"new": [{"date": "2026-02-08", "type": "Deliveries", "notes": "Material Tracking Note", "status": "Pending", "description": "Material Tracking"}], "old": [{"date": "2026-02-08", "type": "Deliveries", "notes": "Material Tracking Note", "status": "Pending", "description": "Material Tracking"}]}, "learner_ids": {"new": [1, 1], "old": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}]}, "schedule_data": {"new": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-13T12:02:42+00:00", "validatedAt": "2026-02-13T12:02:42+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "old": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:52:08+00:00", "validatedAt": "2026-02-05T14:52:08+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}}}, "new_row": {"seta": "", "status": "Active", "site_id": 45, "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-13 12:02:44", "class_agent": 7, "event_dates": [{"date": "2026-02-08", "type": "Deliveries", "notes": "Material Tracking Note", "status": "Pending", "description": "Material Tracking"}], "learner_ids": [1, 1], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-13T12:02:42+00:00", "validatedAt": "2026-02-13T12:02:42+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "old_row": {"seta": "", "status": "Active", "site_id": 45, "class_id": 58, "order_nr": "5352352355", "client_id": 24, "exam_type": "", "class_code": "24-AET-NUM-2025-12-10-16-22", "class_type": "AET", "created_at": "2025-12-10 14:24:38", "exam_class": false, "updated_at": "2026-02-05 14:52:08", "class_agent": null, "event_dates": [{"date": "2026-02-08", "type": "Deliveries", "notes": "Material Tracking Note", "status": "Pending", "description": "Material Tracking"}], "learner_ids": [{"id": "11", "name": "Raj R Singh", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "35", "name": "Peter P.J. Wessels", "level": "RLC", "status": "CIC - Currently in Class"}], "seta_funded": false, "class_subject": "NUM", "exam_learners": [], "schedule_data": {"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T14:52:08+00:00", "validatedAt": "2026-02-05T14:52:08+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "No reason specified"}], "holidayOverrides": []}, "class_duration": 120, "backup_agent_ids": [], "class_notes_data": [], "agent_replacements": [], "class_address_line": "", "stop_restart_dates": [{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}], "initial_class_agent": 7, "original_start_date": "2025-12-10", "project_supervisor_id": 1, "initial_agent_start_date": "2025-12-10"}, "metadata": {"timestamp": "2026-02-13T12:02:45+00:00", "changed_fields": ["class_agent", "learner_ids", "schedule_data", "event_dates", "updated_at"]}}	{"model": "gpt-4o-mini", "status": "success", "viewed": false, "summary": "- **Class Agent Change**: The class agent has been updated from unspecified to Agent 7, which may affect class delivery and oversight.\\n  \\n- **Learner Data Updates**: The current cohort includes two learners identified as \\"Learner A\\" and \\"Learner B,\\" now referenced by numeric IDs instead of their previous identifiers. Monitor for potential impacts on resource allocation and learner tracking.\\n\\n- **Event Dates**: Event dates remain unchanged; however, ensure that all material deliveries are tracked, as the status is still pending.\\n\\n- **Schedule Consistency**: The class schedule remains the same (Tuesdays from 08:00 to 12:00), with an existing cancellation noted for the previously scheduled session. Confirm attendance and engagement metrics post-cancellation.\\n\\n- **Risks and Follow-Up**: Key risks include potential confusion due to learner ID changes and monitoring delivery of educational materials. It is crucial to validate learner performance and engagement after these changes, ensuring no disruption in their academic progress.", "attempts": 1, "viewed_at": null, "error_code": null, "tokens_used": 2112, "generated_at": "2026-02-13T12:02:53+00:00", "error_message": null, "processing_time_ms": 3800}	sent	2026-02-13 14:02:45.723128+02	2026-02-13 14:02:53.916149+02	2026-02-13 14:03:07.199742+02	\N	\N
12	LEARNER_ADD	learner	1	1	{"action": "added_to_class", "class_id": 58, "metadata": {"timestamp": "2026-02-13T12:02:45+00:00", "learner_id": 1}, "learner_id": 1}	{"model": "gpt-4o-mini", "status": "success", "viewed": false, "summary": "- **No Recent Changes**: There are no new rows or differences noted in the class insert, indicating that no modifications were made to scheduling, learners, or staffing for class ID 1.\\n- **Follow-Up Required**: Confirm the absence of expected changes with the scheduling team, as this may indicate an oversight or miscommunication.\\n- **Potential Risks**: The lack of updates may lead to confusion among learners expecting changes; proactive communications may be necessary to clarify class status.\\n- **Monitoring Needed**: Watch for any subsequent class updates or changes that could be missed, requiring check-ins on the class’s progress and needs.\\n- **Scheduled Review**: Consider scheduling a review meeting with the operations team to discuss the implications of no changes occurring and to ensure alignment moving forward.", "attempts": 1, "viewed_at": null, "error_code": null, "tokens_used": 303, "generated_at": "2026-02-13T12:02:58+00:00", "error_message": null, "processing_time_ms": 4240}	sent	2026-02-13 14:02:45.911616+02	2026-02-13 14:02:58.518189+02	2026-02-13 14:03:10.780794+02	\N	\N
13	LEARNER_ADD	learner	1	1	{"action": "added_to_class", "class_id": 58, "metadata": {"timestamp": "2026-02-13T12:02:45+00:00", "learner_id": 1}, "learner_id": 1}	{"model": "gpt-4o-mini", "status": "success", "viewed": false, "summary": "- **No Changes Noted**: The WeCoza class insert operation did not include any modifications to the class ID 1, with no changes in class code, subject, or other details.\\n  \\n- **Scheduling Implications**: Since there are no adjustments, current class schedules remain unaffected.\\n\\n- **Learner Impact**: No learners' data or attendance records have been altered, meaning no immediate instructional impact.\\n\\n- **Staffing Stability**: No staffing changes have been indicated, ensuring continuity of teaching resources.\\n\\n- **Monitor for Future Updates**: Stay vigilant for future updates as the lack of changes indicates potential inactivity that could affect engagement and operational efficiency.", "attempts": 1, "viewed_at": null, "error_code": null, "tokens_used": 280, "generated_at": "2026-02-13T12:03:02+00:00", "error_message": null, "processing_time_ms": 4067}	sent	2026-02-13 14:02:46.041691+02	2026-02-13 14:03:02.920594+02	2026-02-13 14:03:14.664577+02	\N	\N
\.


--
-- TOC entry 4143 (class 0 OID 17353)
-- Dependencies: 230
-- Data for Name: class_material_tracking; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.class_material_tracking (id, class_id, notification_type, notification_sent_at, materials_delivered_at, delivery_status, created_at, updated_at) FROM stdin;
2	53	orange	2025-10-28 09:05:15.572976	2025-10-28 13:49:03.305433	delivered	2025-10-28 09:05:15.572976	2025-10-28 13:49:03.305433
1	53	red	2025-10-28 09:03:23.184057	2025-10-28 13:49:03.305433	delivered	2025-10-28 09:03:23.184057	2025-10-28 13:49:03.305433
3	55	orange	2025-10-28 13:56:07.55537	2025-11-24 10:42:25.477313	delivered	2025-10-28 13:56:07.55537	2025-11-24 10:42:25.477313
4	55	red	2025-10-30 18:56:59.085544	2025-11-24 10:42:25.477313	delivered	2025-10-30 18:56:59.085544	2025-11-24 10:42:25.477313
\.


--
-- TOC entry 4145 (class 0 OID 17362)
-- Dependencies: 232
-- Data for Name: class_notes; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.class_notes (note_id, class_id, note, note_date) FROM stdin;
1	1	High engagement levels observed. Consider extending program.	2023-03-01 00:00:00
3	3	Excellent progress in technical skills. Prepare for certification exam.	2023-04-01 00:00:00
5	5	Incorporate more hands-on activities as per learner feedback.	2023-04-20 00:00:00
6	6	Guest speaker session well-received. Plan for more industry experts.	2023-05-05 00:00:00
7	7	Safety protocols strictly followed. Commend instructor.	2023-05-15 00:00:00
9	9	Innovative projects presented. Showcase in upcoming expo.	2023-06-15 00:00:00
10	10	Additional support needed for struggling learners.	2023-07-01 00:00:00
13	13	Waiting list growing. Consider opening another class.	2023-08-15 00:00:00
14	14	Mock exam results promising. Proceed with final exam preparations.	2023-09-01 00:00:00
15	15	Positive feedback on curriculum relevance to rural healthcare.	2023-09-15 00:00:00
\.


--
-- TOC entry 4147 (class 0 OID 17369)
-- Dependencies: 234
-- Data for Name: class_schedules; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.class_schedules (schedule_id, class_id, day_of_week, start_time, end_time) FROM stdin;
1	1	Monday	09:00:00	13:00:00
2	1	Wednesday	09:00:00	13:00:00
3	1	Friday	09:00:00	13:00:00
6	3	Monday	08:00:00	12:00:00
7	3	Tuesday	08:00:00	12:00:00
8	3	Wednesday	08:00:00	12:00:00
9	3	Thursday	08:00:00	12:00:00
10	3	Friday	08:00:00	12:00:00
13	5	Monday	07:00:00	11:00:00
14	5	Tuesday	07:00:00	11:00:00
15	5	Wednesday	07:00:00	11:00:00
16	5	Thursday	07:00:00	11:00:00
17	5	Friday	07:00:00	11:00:00
18	6	Tuesday	13:00:00	17:00:00
19	6	Thursday	13:00:00	17:00:00
20	7	Monday	10:00:00	14:00:00
21	7	Wednesday	10:00:00	14:00:00
22	7	Friday	10:00:00	14:00:00
26	9	Monday	14:00:00	18:00:00
27	9	Wednesday	14:00:00	18:00:00
28	9	Friday	14:00:00	18:00:00
29	10	Tuesday	08:00:00	12:00:00
30	10	Thursday	08:00:00	12:00:00
36	13	Saturday	10:00:00	15:00:00
37	14	Monday	13:00:00	17:00:00
38	14	Wednesday	13:00:00	17:00:00
39	14	Friday	13:00:00	17:00:00
40	15	Tuesday	09:00:00	13:00:00
41	15	Thursday	09:00:00	13:00:00
42	15	Saturday	09:00:00	13:00:00
\.


--
-- TOC entry 4149 (class 0 OID 17373)
-- Dependencies: 236
-- Data for Name: class_subjects; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.class_subjects (class_id, product_id) FROM stdin;
1	1
1	4
3	3
3	8
5	6
5	12
6	7
6	14
7	8
7	2
9	10
9	14
10	11
10	6
13	14
13	3
14	15
14	10
15	1
15	7
\.


--
-- TOC entry 4213 (class 0 OID 24601)
-- Dependencies: 302
-- Data for Name: class_type_subjects; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.class_type_subjects (class_type_subject_id, class_type_id, subject_code, subject_name, subject_duration, display_order, is_active, created_at, updated_at) FROM stdin;
1	1	COMM	Communication (separate)	120	1	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
2	1	NUM	Numeracy (separate)	120	2	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
3	1	COMM_NUM	Communication & Numeracy (both)	240	3	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
4	4	CL4	Communication level 4	120	1	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
5	4	NL4	Numeracy level 4	120	2	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
6	4	LO4	Life Orientation level 4	90	3	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
7	4	HSS4	Human & Social Sciences level 4	80	4	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
8	4	EMS4	Economic & Management Sciences level 4	94	5	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
9	4	NS4	Natural Sciences level 4	60	6	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
10	4	SMME4	Small Micro Medium Enterprises level 4	60	7	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
11	2	RLC	Communication	160	1	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
12	2	RLN	Numeracy	160	2	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
13	2	RLF	Finance	40	3	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
14	5	BA2LP9	LP9	80	1	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
15	5	BA2LP10	LP10	64	2	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
16	5	BA2LP1	LP1	72	3	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
17	5	BA2LP2	LP2	56	4	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
18	5	BA2LP3	LP3	40	5	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
19	5	BA2LP4	LP4	20	6	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
20	5	BA2LP5	LP5	56	7	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
21	5	BA2LP6	LP6	60	8	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
22	5	BA2LP7	LP7	40	9	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
23	5	BA2LP8	LP8	32	10	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
24	6	BA3LP2	LP2	52	1	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
25	6	BA3LP4	LP4	40	2	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
26	6	BA3LP5	LP5	36	3	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
27	6	BA3LP6	LP6	44	4	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
28	6	BA3LP1	LP1	60	5	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
29	6	BA3LP7	LP7	40	6	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
30	6	BA3LP8	LP8	44	7	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
31	6	BA3LP9	LP9	28	8	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
32	6	BA3LP10	LP10	48	9	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
33	6	BA3LP11	LP11	36	10	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
34	6	BA3LP3	LP3	44	11	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
35	7	BA4LP2	LP2	104	1	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
36	7	BA4LP3	LP3	80	2	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
37	7	BA4LP4	LP4	64	3	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
38	7	BA4LP1	LP1	88	4	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
39	7	BA4LP6	LP6	84	5	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
40	7	BA4LP5	LP5	76	6	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
41	7	BA4LP7	LP7	88	7	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
42	3	IPC	Introduction to Computers	20	1	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
43	3	EQ	Email Etiquette	6	2	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
44	3	TM	Time Management	12	3	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
45	3	SS	Supervisory Skills	40	4	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
46	3	EEPDL	EEP Digital Literacy	40	5	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
47	3	EEPPF	EEP Personal Finance	40	6	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
48	3	EEPWI	EEP Workplace Intelligence	40	7	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
49	3	EEPEI	EEP Emotional Intelligence	40	8	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
50	3	EEPBI	EEP Business Intelligence	40	9	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
\.


--
-- TOC entry 4211 (class 0 OID 24584)
-- Dependencies: 300
-- Data for Name: class_types; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.class_types (class_type_id, class_type_code, class_type_name, subject_selection_mode, progression_total_hours, display_order, is_active, created_at, updated_at) FROM stdin;
1	AET	AET Communication & Numeracy	own	\N	1	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
2	REALLL	REALLL	own	\N	2	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
3	SOFT	Soft Skill Courses	own	\N	3	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
4	GETC	GETC AET	progression	564	4	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
5	BA2	Business Admin NQF 2	progression	520	5	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
6	BA3	Business Admin NQF 3	progression	472	6	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
7	BA4	Business Admin NQF 4	progression	584	7	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
8	WALK	Walk Package	all_subjects	\N	8	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
9	HEXA	Hexa Packages	all_subjects	\N	9	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
10	RUN	Run Packages	all_subjects	\N	10	t	2026-01-27 13:20:28.159136	2026-01-27 13:20:28.159136
\.


--
-- TOC entry 4150 (class 0 OID 17376)
-- Dependencies: 237
-- Data for Name: classes; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.classes (class_id, client_id, class_address_line, class_type, original_start_date, seta_funded, seta, exam_class, exam_type, project_supervisor_id, created_at, updated_at, site_id, class_subject, class_code, class_duration, class_agent, learner_ids, backup_agent_ids, schedule_data, stop_restart_dates, class_notes_data, initial_class_agent, initial_agent_start_date, exam_learners, order_nr, event_dates, order_nr_metadata) FROM stdin;
51	5	166 Central Road, Central, 8756	BA2	2025-09-22	f		f		1	2025-09-21 13:44:15	2025-09-24 16:40:10	18	BA2LP2	5-BA2-BA2LP2-2025-09-21-15-42	56	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[]	{"endDate": "2026-06-09", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-09-24T16:40:07+00:00", "validatedAt": "2025-09-24T16:40:07+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "1.00", "end_time": "08:00", "start_time": "07:00"}, "Tuesday": {"duration": "0.50", "end_time": "07:30", "start_time": "07:00"}}}, "startDate": "2025-09-22", "dayOfMonth": null, "selectedDays": ["Monday", "Tuesday"], "exceptionDates": [{"date": "2025-09-22", "reason": "No reason specified"}], "holidayOverrides": []}	[]	[]	3	2025-09-22	[]	\N	[]	\N
49	2	300 Corporate Avenue, Johannesburg, 2001	BA2	2025-07-30	t	CHIETA	t	IEB	4	2025-07-15 17:29:08	2025-09-26 12:41:00	6	BA2LP1	2-BA2-BA2LP1-2025-07-15-19-26	72	\N	[{"id": "1", "name": "John Doe", "level": "COMM", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "NUM", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "COMM_NUM", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}, {"id": "5", "name": "David Brown", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-30", "agent_id": 9}]	{"endDate": "2025-10-10", "pattern": "biweekly", "version": "2.0", "metadata": {"lastUpdated": "2025-09-26T12:40:57+00:00", "validatedAt": "2025-09-26T12:40:57+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Friday": {"duration": "3.00", "end_time": "12:30", "start_time": "09:30"}, "Wednesday": {"duration": "4.00", "end_time": "13:00", "start_time": "09:00"}}}, "startDate": "2025-07-30", "dayOfMonth": null, "selectedDays": ["Wednesday", "Friday"], "exceptionDates": [{"date": "2025-07-30", "reason": "No reason specified"}], "holidayOverrides": []}	[{"stop_date": "2025-07-01", "restart_date": "2025-07-30"}]	[]	3	2025-07-30	[{"id": "1", "name": "John Doe", "level": "COMM", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "NUM", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "COMM_NUM", "status": "CIC - Currently in Class"}]	\N	[]	\N
1	1	100 Main Street	Corporate	2023-02-01	t	MICT SETA	f	\N	2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
3	3	50 Factory Lane	Corporate	2023-03-01	f	\N	t	Final	11	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
5	5	200 Harvest Road	Specialized	2023-03-15	t	AgriSETA	f	\N	6	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
6	6	150 Wellness Avenue	Corporate	2023-04-01	t	HWSETA	t	Mock	11	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
7	7	300 Mineral Street	Specialized	2023-04-15	t	MQA	f	\N	2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
9	9	50 Eco Street	Specialized	2023-05-15	t	EWSETA	f	\N	11	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
10	10	25 Blueprint Avenue	Corporate	2023-06-01	t	CETA	t	Final	2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
13	13	10 Learning Lane	Community	2023-08-01	t	ETDP SETA	f	\N	2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
14	14	30 Accounting Road	Corporate	2023-07-15	t	FASSET	t	Mock	6	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
15	15	80 Medic Street	Specialized	2023-08-15	t	HWSETA	f	\N	11	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	[]	[]	[]	[]	[]	\N	\N	[]	\N	[]	\N
20	11	Aspen Pharmacare - Head Office, 100 Pharma Rd, Durban, 4001	AET	2025-06-02	t	AgriSETA	t	Koos	8	2025-05-28 16:19:46	2025-05-28 16:19:46	\N	BOTH	AET-BOTH-2025	240	\N	[1, 2, 3, 36]	[]	{"0": {"day": "Sunday", "date": "2025-06-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "1": {"day": "Sunday", "date": "2025-06-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "2": {"day": "Sunday", "date": "2025-06-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "3": {"day": "Sunday", "date": "2025-06-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "4": {"day": "Sunday", "date": "2025-07-06", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "5": {"day": "Sunday", "date": "2025-07-13", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "6": {"day": "Sunday", "date": "2025-07-20", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "7": {"day": "Sunday", "date": "2025-07-27", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "8": {"day": "Sunday", "date": "2025-08-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "9": {"day": "Sunday", "date": "2025-08-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "10": {"day": "Sunday", "date": "2025-08-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "11": {"day": "Sunday", "date": "2025-08-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "12": {"day": "Sunday", "date": "2025-08-31", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "13": {"day": "Sunday", "date": "2025-09-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "14": {"day": "Sunday", "date": "2025-09-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "15": {"day": "Sunday", "date": "2025-09-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "16": {"day": "Sunday", "date": "2025-09-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "17": {"day": "Sunday", "date": "2025-10-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "18": {"day": "Sunday", "date": "2025-10-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "19": {"day": "Sunday", "date": "2025-10-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "20": {"day": "Sunday", "date": "2025-10-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "21": {"day": "Sunday", "date": "2025-11-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "22": {"day": "Sunday", "date": "2025-11-09", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "23": {"day": "Sunday", "date": "2025-11-16", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "24": {"day": "Sunday", "date": "2025-11-23", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "25": {"day": "Sunday", "date": "2025-11-30", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "26": {"day": "Sunday", "date": "2025-12-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "27": {"day": "Sunday", "date": "2025-12-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "28": {"day": "Sunday", "date": "2025-12-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "29": {"day": "Sunday", "date": "2025-12-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "30": {"day": "Sunday", "date": "2026-01-04", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "31": {"day": "Sunday", "date": "2026-01-11", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "32": {"day": "Sunday", "date": "2026-01-18", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "33": {"day": "Sunday", "date": "2026-01-25", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "34": {"day": "Sunday", "date": "2026-02-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "35": {"day": "Sunday", "date": "2026-02-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "36": {"day": "Sunday", "date": "2026-02-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "37": {"day": "Sunday", "date": "2026-02-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "38": {"day": "Sunday", "date": "2026-03-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "39": {"day": "Sunday", "date": "2026-03-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "40": {"day": "Sunday", "date": "2026-03-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "41": {"day": "Sunday", "date": "2026-03-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "42": {"day": "Sunday", "date": "2026-03-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "43": {"day": "Sunday", "date": "2026-04-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "44": {"day": "Sunday", "date": "2026-04-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "45": {"day": "Sunday", "date": "2026-04-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "46": {"day": "Sunday", "date": "2026-04-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "47": {"day": "Sunday", "date": "2026-05-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "48": {"day": "Sunday", "date": "2026-05-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "49": {"day": "Sunday", "date": "2026-05-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "50": {"day": "Sunday", "date": "2026-05-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "51": {"day": "Sunday", "date": "2026-05-31", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "52": {"day": "Sunday", "date": "2026-06-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "53": {"day": "Sunday", "date": "2026-06-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "54": {"day": "Sunday", "date": "2026-06-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "55": {"day": "Sunday", "date": "2026-06-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "56": {"day": "Sunday", "date": "2026-07-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "57": {"day": "Sunday", "date": "2026-07-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "58": {"day": "Sunday", "date": "2026-07-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "59": {"day": "Sunday", "date": "2026-07-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "60": {"day": "Sunday", "date": "2026-08-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "days": "[\\\\\\"Monday\\\\\\",\\\\\\"Tuesday\\\\\\"]", "pattern": "weekly", "end_date": "2026-08-05", "end_time": "07:00", "start_date": "2025-06-02", "start_time": "06:00", "total_hours": "120", "exception_dates": "[{\\\\\\"date\\\\\\":\\\\\\"2025-07-21\\\\\\",\\\\\\"reason\\\\\\":\\\\\\"Agent Absent\\\\\\"}]", "holiday_overrides": "{\\\\\\"2025-09-23\\\\\\":{\\\\\\"date\\\\\\":\\\\\\"2025-09-23\\\\\\",\\\\\\"name\\\\\\":\\\\\\"Heritage Day\\\\\\",\\\\\\"override\\\\\\":true},\\\\\\"2025-12-15\\\\\\":{\\\\\\"date\\\\\\":\\\\\\"2025-12-15\\\\\\",\\\\\\"name\\\\\\":\\\\\\"Day of Reconciliation\\\\\\",\\\\\\"override\\\\\\":true}}"}	[{"stop_date": "2025-09-01", "restart_date": "2025-09-29"}]	["Poor attendance"]	1	2025-06-02	[]	\N	[]	\N
50	5	6756, East Park, Parklands, 6756	GETC	2025-08-01	f		f		5	2025-07-18 10:43:53	2025-09-25 13:29:40	2	NL4	5-GETC-NL4-2025-07-18-12-41	120	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-08-01", "agent_id": 10}]	{"endDate": "2025-12-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-09-25T13:29:37+00:00", "validatedAt": "2025-09-25T13:29:37+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "3.00", "end_time": "12:00", "start_time": "09:00"}, "Thursday": {"duration": "4.00", "end_time": "14:00", "start_time": "10:00"}}}, "startDate": "2025-08-01", "dayOfMonth": null, "selectedDays": ["Monday", "Thursday"], "exceptionDates": [{"date": "2025-08-01", "reason": "No reason specified"}], "holidayOverrides": []}	[]	[]	8	2025-08-01	[]	\N	[]	\N
32	14	Barloworld - Central Branch, 30 Central Blvd, Johannesburg, 2003	REALLL	2025-06-09	t	CATHSSETA	t	Open Book Exam	4	2025-06-04 18:47:47	2025-06-04 18:47:47	\N	RLC	14-REALLL-RLC-2025-06-04-20-45	160	1	[{"id": 1, "name": "John J.M. Smith", "level": "", "status": "Host Company Learner"}, {"id": 2, "name": "Nosipho N. Dlamini", "level": "", "status": "Host Company Learner"}, {"id": 3, "name": "Ahmed A. Patel", "level": "", "status": "Host Company Learner"}, {"id": 4, "name": "Lerato L. Moloi", "level": "", "status": "Host Company Learner"}]	[10]	{"0": {"day": "Monday", "date": "2025-06-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "1": {"day": "Monday", "date": "2025-06-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "2": {"day": "Monday", "date": "2025-06-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "3": {"day": "Monday", "date": "2025-06-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "4": {"day": "Monday", "date": "2025-07-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "5": {"day": "Monday", "date": "2025-07-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "6": {"day": "Monday", "date": "2025-07-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "7": {"day": "Monday", "date": "2025-07-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "8": {"day": "Monday", "date": "2025-08-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "9": {"day": "Monday", "date": "2025-08-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "10": {"day": "Monday", "date": "2025-08-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "11": {"day": "Monday", "date": "2025-08-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "12": {"day": "Monday", "date": "2025-09-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "13": {"day": "Monday", "date": "2025-09-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "14": {"day": "Monday", "date": "2025-09-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "15": {"day": "Monday", "date": "2025-09-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "16": {"day": "Monday", "date": "2025-09-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "17": {"day": "Monday", "date": "2025-10-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "18": {"day": "Monday", "date": "2025-10-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "19": {"day": "Monday", "date": "2025-10-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "20": {"day": "Monday", "date": "2025-10-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "21": {"day": "Monday", "date": "2025-11-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "22": {"day": "Monday", "date": "2025-11-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "23": {"day": "Monday", "date": "2025-11-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "24": {"day": "Monday", "date": "2025-11-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "25": {"day": "Monday", "date": "2025-12-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "26": {"day": "Monday", "date": "2025-12-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "27": {"day": "Monday", "date": "2025-12-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "28": {"day": "Monday", "date": "2025-12-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "29": {"day": "Monday", "date": "2025-12-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "30": {"day": "Monday", "date": "2026-01-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "31": {"day": "Monday", "date": "2026-01-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "32": {"day": "Monday", "date": "2026-01-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "33": {"day": "Monday", "date": "2026-01-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "34": {"day": "Monday", "date": "2026-02-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "35": {"day": "Monday", "date": "2026-02-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "36": {"day": "Monday", "date": "2026-02-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "37": {"day": "Monday", "date": "2026-02-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "38": {"day": "Monday", "date": "2026-03-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "39": {"day": "Monday", "date": "2026-03-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "40": {"day": "Monday", "date": "2026-03-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "41": {"day": "Monday", "date": "2026-03-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "42": {"day": "Monday", "date": "2026-03-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "43": {"day": "Monday", "date": "2026-04-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "44": {"day": "Monday", "date": "2026-04-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "45": {"day": "Monday", "date": "2026-05-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "46": {"day": "Monday", "date": "2026-05-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "47": {"day": "Monday", "date": "2026-05-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "48": {"day": "Monday", "date": "2026-05-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "49": {"day": "Monday", "date": "2026-06-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "50": {"day": "Monday", "date": "2026-06-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "51": {"day": "Monday", "date": "2026-06-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "52": {"day": "Monday", "date": "2026-06-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "53": {"day": "Monday", "date": "2026-06-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "54": {"day": "Monday", "date": "2026-07-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "55": {"day": "Monday", "date": "2026-07-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "56": {"day": "Monday", "date": "2026-07-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "57": {"day": "Monday", "date": "2026-07-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "58": {"day": "Monday", "date": "2026-08-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "59": {"day": "Monday", "date": "2026-08-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "60": {"day": "Monday", "date": "2026-08-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "61": {"day": "Monday", "date": "2026-08-31", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "62": {"day": "Monday", "date": "2026-09-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "63": {"day": "Monday", "date": "2026-09-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "64": {"day": "Monday", "date": "2026-09-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "65": {"day": "Monday", "date": "2026-09-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "66": {"day": "Monday", "date": "2026-10-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "67": {"day": "Monday", "date": "2026-10-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "68": {"day": "Monday", "date": "2026-10-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "69": {"day": "Monday", "date": "2026-10-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "70": {"day": "Monday", "date": "2026-11-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "71": {"day": "Monday", "date": "2026-11-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "72": {"day": "Monday", "date": "2026-11-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "73": {"day": "Monday", "date": "2026-11-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "74": {"day": "Monday", "date": "2026-11-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "75": {"day": "Monday", "date": "2026-12-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "76": {"day": "Monday", "date": "2026-12-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "77": {"day": "Monday", "date": "2026-12-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "78": {"day": "Monday", "date": "2026-12-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "79": {"day": "Monday", "date": "2027-01-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "80": {"day": "Monday", "date": "2027-01-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "81": {"day": "Monday", "date": "2027-01-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "82": {"day": "Monday", "date": "2027-01-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "83": {"day": "Monday", "date": "2027-02-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "84": {"day": "Monday", "date": "2027-02-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "85": {"day": "Monday", "date": "2027-02-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "86": {"day": "Monday", "date": "2027-02-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "87": {"day": "Monday", "date": "2027-03-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "88": {"day": "Monday", "date": "2027-03-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "89": {"day": "Monday", "date": "2027-03-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "90": {"day": "Monday", "date": "2027-03-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "91": {"day": "Monday", "date": "2027-03-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "92": {"day": "Monday", "date": "2027-04-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "93": {"day": "Monday", "date": "2027-04-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "94": {"day": "Monday", "date": "2027-04-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "95": {"day": "Monday", "date": "2027-04-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "96": {"day": "Monday", "date": "2027-05-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "97": {"day": "Monday", "date": "2027-05-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "98": {"day": "Monday", "date": "2027-05-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "99": {"day": "Monday", "date": "2027-05-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "100": {"day": "Monday", "date": "2027-05-31", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "101": {"day": "Monday", "date": "2027-06-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "102": {"day": "Monday", "date": "2027-06-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "103": {"day": "Monday", "date": "2027-06-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "104": {"day": "Monday", "date": "2027-06-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "105": {"day": "Monday", "date": "2027-07-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "106": {"day": "Monday", "date": "2027-07-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "107": {"day": "Monday", "date": "2027-07-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "108": {"day": "Monday", "date": "2027-07-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "109": {"day": "Monday", "date": "2027-08-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "110": {"day": "Monday", "date": "2027-08-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "111": {"day": "Monday", "date": "2027-08-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "112": {"day": "Monday", "date": "2027-08-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "113": {"day": "Monday", "date": "2027-08-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "114": {"day": "Monday", "date": "2027-09-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "115": {"day": "Monday", "date": "2027-09-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "116": {"day": "Monday", "date": "2027-09-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "117": {"day": "Monday", "date": "2027-09-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "118": {"day": "Monday", "date": "2027-10-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "119": {"day": "Monday", "date": "2027-10-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "120": {"day": "Monday", "date": "2027-10-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "121": {"day": "Monday", "date": "2027-10-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "122": {"day": "Monday", "date": "2027-11-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "123": {"day": "Monday", "date": "2027-11-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "124": {"day": "Monday", "date": "2027-11-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "125": {"day": "Monday", "date": "2027-11-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "126": {"day": "Monday", "date": "2027-11-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "127": {"day": "Monday", "date": "2027-12-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "128": {"day": "Monday", "date": "2027-12-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "129": {"day": "Monday", "date": "2027-12-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "130": {"day": "Monday", "date": "2027-12-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "131": {"day": "Monday", "date": "2028-01-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "132": {"day": "Monday", "date": "2028-01-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "133": {"day": "Monday", "date": "2028-01-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "134": {"day": "Monday", "date": "2028-01-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "135": {"day": "Monday", "date": "2028-01-31", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "136": {"day": "Monday", "date": "2028-02-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "137": {"day": "Monday", "date": "2028-02-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "138": {"day": "Monday", "date": "2028-02-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "139": {"day": "Monday", "date": "2028-02-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "140": {"day": "Monday", "date": "2028-03-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "141": {"day": "Monday", "date": "2028-03-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "142": {"day": "Monday", "date": "2028-03-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "143": {"day": "Monday", "date": "2028-03-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "144": {"day": "Monday", "date": "2028-04-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "145": {"day": "Monday", "date": "2028-04-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "146": {"day": "Monday", "date": "2028-04-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "147": {"day": "Monday", "date": "2028-04-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "148": {"day": "Monday", "date": "2028-05-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "149": {"day": "Monday", "date": "2028-05-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "150": {"day": "Monday", "date": "2028-05-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "151": {"day": "Monday", "date": "2028-05-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "152": {"day": "Monday", "date": "2028-05-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "153": {"day": "Monday", "date": "2028-06-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "154": {"day": "Monday", "date": "2028-06-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "155": {"day": "Monday", "date": "2028-06-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "156": {"day": "Monday", "date": "2028-06-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "157": {"day": "Monday", "date": "2028-07-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "158": {"day": "Monday", "date": "2028-07-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "159": {"day": "Monday", "date": "2028-07-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "160": {"day": "Monday", "date": "2028-07-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "161": {"day": "Monday", "date": "2028-07-31", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "162": {"day": "Monday", "date": "2028-08-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "163": {"day": "Monday", "date": "2028-08-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "164": {"day": "Monday", "date": "2028-08-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "165": {"day": "Monday", "date": "2028-08-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "166": {"day": "Monday", "date": "2028-09-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "167": {"day": "Monday", "date": "2028-09-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "168": {"day": "Monday", "date": "2028-09-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "169": {"day": "Monday", "date": "2028-09-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "170": {"day": "Monday", "date": "2028-10-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "171": {"day": "Monday", "date": "2028-10-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "172": {"day": "Monday", "date": "2028-10-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "173": {"day": "Monday", "date": "2028-10-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "174": {"day": "Monday", "date": "2028-10-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "175": {"day": "Monday", "date": "2028-11-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "176": {"day": "Monday", "date": "2028-11-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "177": {"day": "Monday", "date": "2028-11-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "178": {"day": "Monday", "date": "2028-11-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "179": {"day": "Monday", "date": "2028-12-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "180": {"day": "Monday", "date": "2028-12-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "181": {"day": "Monday", "date": "2028-12-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "182": {"day": "Monday", "date": "2028-12-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "183": {"day": "Monday", "date": "2029-01-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "184": {"day": "Monday", "date": "2029-01-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "185": {"day": "Monday", "date": "2029-01-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "186": {"day": "Monday", "date": "2029-01-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "187": {"day": "Monday", "date": "2029-01-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "188": {"day": "Monday", "date": "2029-02-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "189": {"day": "Monday", "date": "2029-02-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "190": {"day": "Monday", "date": "2029-02-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "191": {"day": "Monday", "date": "2029-02-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "192": {"day": "Monday", "date": "2029-03-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "193": {"day": "Monday", "date": "2029-03-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "194": {"day": "Monday", "date": "2029-03-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "195": {"day": "Monday", "date": "2029-03-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "196": {"day": "Monday", "date": "2029-04-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "197": {"day": "Monday", "date": "2029-04-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "198": {"day": "Monday", "date": "2029-04-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "199": {"day": "Monday", "date": "2029-04-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "200": {"day": "Monday", "date": "2029-04-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "201": {"day": "Monday", "date": "2029-05-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "202": {"day": "Monday", "date": "2029-05-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "203": {"day": "Monday", "date": "2029-05-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "204": {"day": "Monday", "date": "2029-05-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "205": {"day": "Monday", "date": "2029-06-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "206": {"day": "Monday", "date": "2029-06-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "207": {"day": "Monday", "date": "2029-06-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "208": {"day": "Monday", "date": "2029-06-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "209": {"day": "Monday", "date": "2029-07-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "210": {"day": "Monday", "date": "2029-07-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "211": {"day": "Monday", "date": "2029-07-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "212": {"day": "Monday", "date": "2029-07-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "213": {"day": "Monday", "date": "2029-07-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "214": {"day": "Monday", "date": "2029-08-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "215": {"day": "Monday", "date": "2029-08-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "216": {"day": "Monday", "date": "2029-08-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "217": {"day": "Monday", "date": "2029-08-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "218": {"day": "Monday", "date": "2029-09-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "219": {"day": "Monday", "date": "2029-09-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "220": {"day": "Monday", "date": "2029-09-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "221": {"day": "Monday", "date": "2029-09-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "222": {"day": "Monday", "date": "2029-10-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "223": {"day": "Monday", "date": "2029-10-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "224": {"day": "Monday", "date": "2029-10-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "225": {"day": "Monday", "date": "2029-10-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "226": {"day": "Monday", "date": "2029-10-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "227": {"day": "Monday", "date": "2029-11-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "228": {"day": "Monday", "date": "2029-11-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "229": {"day": "Monday", "date": "2029-11-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "230": {"day": "Monday", "date": "2029-11-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "231": {"day": "Monday", "date": "2029-12-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "232": {"day": "Monday", "date": "2029-12-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "233": {"day": "Monday", "date": "2029-12-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "234": {"day": "Monday", "date": "2029-12-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "235": {"day": "Monday", "date": "2029-12-31", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "236": {"day": "Monday", "date": "2030-01-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "237": {"day": "Monday", "date": "2030-01-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "238": {"day": "Monday", "date": "2030-01-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "239": {"day": "Monday", "date": "2030-01-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "240": {"day": "Monday", "date": "2030-02-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "241": {"day": "Monday", "date": "2030-02-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "242": {"day": "Monday", "date": "2030-02-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "243": {"day": "Monday", "date": "2030-02-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "244": {"day": "Monday", "date": "2030-03-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "245": {"day": "Monday", "date": "2030-03-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "246": {"day": "Monday", "date": "2030-03-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "247": {"day": "Monday", "date": "2030-03-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "248": {"day": "Monday", "date": "2030-04-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "249": {"day": "Monday", "date": "2030-04-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "250": {"day": "Monday", "date": "2030-04-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "251": {"day": "Monday", "date": "2030-04-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "252": {"day": "Monday", "date": "2030-04-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "253": {"day": "Monday", "date": "2030-05-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "254": {"day": "Monday", "date": "2030-05-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "255": {"day": "Monday", "date": "2030-05-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "256": {"day": "Monday", "date": "2030-05-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "257": {"day": "Monday", "date": "2030-06-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "258": {"day": "Monday", "date": "2030-06-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "259": {"day": "Monday", "date": "2030-06-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "260": {"day": "Monday", "date": "2030-06-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "261": {"day": "Monday", "date": "2030-07-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "262": {"day": "Monday", "date": "2030-07-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "263": {"day": "Monday", "date": "2030-07-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "264": {"day": "Monday", "date": "2030-07-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "265": {"day": "Monday", "date": "2030-07-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "266": {"day": "Monday", "date": "2030-08-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "267": {"day": "Monday", "date": "2030-08-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "268": {"day": "Monday", "date": "2030-08-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "269": {"day": "Monday", "date": "2030-08-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "270": {"day": "Monday", "date": "2030-09-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "271": {"day": "Monday", "date": "2030-09-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "272": {"day": "Monday", "date": "2030-09-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "273": {"day": "Monday", "date": "2030-09-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "274": {"day": "Monday", "date": "2030-09-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "275": {"day": "Monday", "date": "2030-10-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "276": {"day": "Monday", "date": "2030-10-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "277": {"day": "Monday", "date": "2030-10-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "278": {"day": "Monday", "date": "2030-10-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "279": {"day": "Monday", "date": "2030-11-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "280": {"day": "Monday", "date": "2030-11-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "281": {"day": "Monday", "date": "2030-11-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "282": {"day": "Monday", "date": "2030-11-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "283": {"day": "Monday", "date": "2030-12-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "284": {"day": "Monday", "date": "2030-12-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "285": {"day": "Monday", "date": "2030-12-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "286": {"day": "Monday", "date": "2030-12-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "287": {"day": "Monday", "date": "2030-12-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "288": {"day": "Monday", "date": "2031-01-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "289": {"day": "Monday", "date": "2031-01-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "290": {"day": "Monday", "date": "2031-01-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "291": {"day": "Monday", "date": "2031-01-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "292": {"day": "Monday", "date": "2031-02-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "293": {"day": "Monday", "date": "2031-02-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "294": {"day": "Monday", "date": "2031-02-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "295": {"day": "Monday", "date": "2031-02-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "296": {"day": "Monday", "date": "2031-03-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "297": {"day": "Monday", "date": "2031-03-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "298": {"day": "Monday", "date": "2031-03-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "299": {"day": "Monday", "date": "2031-03-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "300": {"day": "Monday", "date": "2031-03-31", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "301": {"day": "Monday", "date": "2031-04-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "302": {"day": "Monday", "date": "2031-04-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "303": {"day": "Monday", "date": "2031-04-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "304": {"day": "Monday", "date": "2031-04-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "305": {"day": "Monday", "date": "2031-05-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "306": {"day": "Monday", "date": "2031-05-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "307": {"day": "Monday", "date": "2031-05-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "308": {"day": "Monday", "date": "2031-05-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "309": {"day": "Monday", "date": "2031-06-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "310": {"day": "Monday", "date": "2031-06-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "311": {"day": "Monday", "date": "2031-06-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "312": {"day": "Monday", "date": "2031-06-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "313": {"day": "Monday", "date": "2031-06-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "314": {"day": "Monday", "date": "2031-07-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "315": {"day": "Monday", "date": "2031-07-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "316": {"day": "Monday", "date": "2031-07-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "317": {"day": "Monday", "date": "2031-07-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "318": {"day": "Monday", "date": "2031-08-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "319": {"day": "Monday", "date": "2031-08-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "320": {"day": "Monday", "date": "2031-08-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "321": {"day": "Monday", "date": "2031-08-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "08:00"}, "days": ["Monday"], "pattern": "weekly", "end_date": "2031-08-26", "end_time": "08:30", "start_date": "2025-06-09", "start_time": "08:00", "total_hours": "160", "holiday_overrides": {"2025-06-16": {"date": "2025-06-16", "name": "Youth Day", "override": true}}}	[{"stop_date": "2025-07-07", "restart_date": "2025-07-28"}]	[]	1	2025-06-09	[]	\N	[]	\N
52	4	1100 Service Lane, Bloemfontein, 9301	REALLL	2025-09-29	f		f		5	2025-09-25 09:05:22	2025-09-29 13:21:45	14	RLN	4-REALLL-RLN-2025-09-25-11-04	160	\N	[{"id": "2", "name": "Jane Smith", "level": "BA2LP1", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "COMM", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "NUM", "status": "CIC - Currently in Class"}, {"id": "5", "name": "David Brown", "level": "HSS4", "status": "CIC - Currently in Class"}]	[]	{"endDate": "2027-04-12", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-09-29T13:21:43+00:00", "validatedAt": "2025-09-29T13:21:43+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "2.00", "end_time": "10:00", "start_time": "08:00"}}}, "startDate": "2025-09-29", "dayOfMonth": null, "selectedDays": ["Monday"], "exceptionDates": [{"date": "2025-09-29", "reason": "No reason specified"}], "holidayOverrides": []}	[]	[]	6	2025-09-29	[]	\N	[]	\N
53	4	1100 Service Lane, Bloemfontein, 9301	BA2	2025-11-04	t	HWSETA	t		1	2025-09-30 08:30:12	2025-10-28 09:04:46	14	BA2LP10	4-BA2-BA2LP10-2025-09-30-10-26	64	\N	[{"id": "1", "name": "John Doe", "level": "BA2LP10", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "BA2LP10", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}]	[]	{"endDate": "2028-04-24", "pattern": "biweekly", "version": "2.0", "metadata": {"lastUpdated": "2025-10-28T09:04:43+00:00", "validatedAt": "2025-10-28T09:04:43+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "1.00", "end_time": "10:00", "start_time": "09:00"}}}, "startDate": "2025-11-04", "dayOfMonth": null, "selectedDays": ["Monday"], "exceptionDates": [{"date": "2025-10-06", "reason": "No reason specified"}], "holidayOverrides": []}	[]	[]	4	2025-10-06	[{"id": "1", "name": "John Doe", "level": "BA2LP10", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "BA2LP10", "status": "CIC - Currently in Class"}]	\N	[]	\N
21	11	Aspen Pharmacare - Head Office, 100 Pharma Rd, Durban, 4001	AET	2025-06-02	t	AgriSETA	t	Koos	1	2025-05-28 17:30:59	2025-05-28 17:30:59	\N	BOTH	AET-BOTH-2025	240	1	[1, 2, 3, 4]	[7]	{"0": {"day": "Sunday", "date": "2025-06-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "1": {"day": "Sunday", "date": "2025-06-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "2": {"day": "Sunday", "date": "2025-06-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "3": {"day": "Sunday", "date": "2025-06-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "4": {"day": "Sunday", "date": "2025-07-06", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "5": {"day": "Sunday", "date": "2025-07-13", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "6": {"day": "Sunday", "date": "2025-07-20", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "7": {"day": "Sunday", "date": "2025-07-27", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "8": {"day": "Sunday", "date": "2025-08-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "9": {"day": "Sunday", "date": "2025-08-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "10": {"day": "Sunday", "date": "2025-08-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "11": {"day": "Sunday", "date": "2025-08-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "12": {"day": "Sunday", "date": "2025-08-31", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "13": {"day": "Sunday", "date": "2025-09-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "14": {"day": "Sunday", "date": "2025-09-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "15": {"day": "Sunday", "date": "2025-09-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "16": {"day": "Sunday", "date": "2025-09-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "17": {"day": "Sunday", "date": "2025-10-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "18": {"day": "Sunday", "date": "2025-10-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "19": {"day": "Sunday", "date": "2025-10-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "20": {"day": "Sunday", "date": "2025-10-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "21": {"day": "Sunday", "date": "2025-11-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "22": {"day": "Sunday", "date": "2025-11-09", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "23": {"day": "Sunday", "date": "2025-11-16", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "24": {"day": "Sunday", "date": "2025-11-23", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "25": {"day": "Sunday", "date": "2025-11-30", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "26": {"day": "Sunday", "date": "2025-12-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "27": {"day": "Sunday", "date": "2025-12-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "28": {"day": "Sunday", "date": "2025-12-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "29": {"day": "Sunday", "date": "2025-12-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "30": {"day": "Sunday", "date": "2026-01-04", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "31": {"day": "Sunday", "date": "2026-01-11", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "32": {"day": "Sunday", "date": "2026-01-18", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "33": {"day": "Sunday", "date": "2026-01-25", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "34": {"day": "Sunday", "date": "2026-02-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "35": {"day": "Sunday", "date": "2026-02-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "36": {"day": "Sunday", "date": "2026-02-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "37": {"day": "Sunday", "date": "2026-02-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "38": {"day": "Sunday", "date": "2026-03-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "39": {"day": "Sunday", "date": "2026-03-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "40": {"day": "Sunday", "date": "2026-03-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "41": {"day": "Sunday", "date": "2026-03-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "42": {"day": "Sunday", "date": "2026-03-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "43": {"day": "Sunday", "date": "2026-04-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "44": {"day": "Sunday", "date": "2026-04-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "45": {"day": "Sunday", "date": "2026-04-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "46": {"day": "Sunday", "date": "2026-04-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "47": {"day": "Sunday", "date": "2026-05-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "48": {"day": "Sunday", "date": "2026-05-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "49": {"day": "Sunday", "date": "2026-05-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "50": {"day": "Sunday", "date": "2026-05-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "51": {"day": "Sunday", "date": "2026-05-31", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "52": {"day": "Sunday", "date": "2026-06-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "53": {"day": "Sunday", "date": "2026-06-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "54": {"day": "Sunday", "date": "2026-06-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "55": {"day": "Sunday", "date": "2026-06-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "56": {"day": "Sunday", "date": "2026-07-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "57": {"day": "Sunday", "date": "2026-07-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "58": {"day": "Sunday", "date": "2026-07-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "59": {"day": "Sunday", "date": "2026-07-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "60": {"day": "Sunday", "date": "2026-08-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "days": "[\\\\\\"Monday\\\\\\",\\\\\\"Tuesday\\\\\\"]", "pattern": "weekly", "end_date": "2026-08-05", "end_time": "07:00", "start_date": "2025-06-02", "start_time": "06:00", "total_hours": "120", "exception_dates": "[{\\\\\\"date\\\\\\":\\\\\\"2025-07-07\\\\\\",\\\\\\"reason\\\\\\":\\\\\\"Client Cancelled\\\\\\"}]", "holiday_overrides": "{\\\\\\"2025-09-23\\\\\\":{\\\\\\"date\\\\\\":\\\\\\"2025-09-23\\\\\\",\\\\\\"name\\\\\\":\\\\\\"Heritage Day\\\\\\",\\\\\\"override\\\\\\":true},\\\\\\"2025-12-15\\\\\\":{\\\\\\"date\\\\\\":\\\\\\"2025-12-15\\\\\\",\\\\\\"name\\\\\\":\\\\\\"Day of Reconciliation\\\\\\",\\\\\\"override\\\\\\":true}}"}	[{"stop_date": "2025-09-01", "restart_date": "2025-09-22"}]	["Poor attendance"]	1	2025-06-02	[]	\N	[]	\N
22	11	Aspen Pharmacare - Production Unit, 101 Pharma Rd, Durban, 4001	AET	2025-06-02	t	HWSETA	t	Koos	8	2025-05-28 17:50:04	2025-05-28 17:50:04	\N	BOTH	AET-BOTH-2025	240	1	[1, 2, 3, 4]	[5]	{"0": {"day": "Sunday", "date": "2025-06-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "1": {"day": "Sunday", "date": "2025-06-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "2": {"day": "Sunday", "date": "2025-06-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "3": {"day": "Sunday", "date": "2025-06-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "4": {"day": "Sunday", "date": "2025-07-06", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "5": {"day": "Sunday", "date": "2025-07-13", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "6": {"day": "Sunday", "date": "2025-07-20", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "7": {"day": "Sunday", "date": "2025-07-27", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "8": {"day": "Sunday", "date": "2025-08-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "9": {"day": "Sunday", "date": "2025-08-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "10": {"day": "Sunday", "date": "2025-08-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "11": {"day": "Sunday", "date": "2025-08-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "12": {"day": "Sunday", "date": "2025-08-31", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "13": {"day": "Sunday", "date": "2025-09-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "14": {"day": "Sunday", "date": "2025-09-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "15": {"day": "Sunday", "date": "2025-09-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "16": {"day": "Sunday", "date": "2025-09-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "17": {"day": "Sunday", "date": "2025-10-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "18": {"day": "Sunday", "date": "2025-10-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "19": {"day": "Sunday", "date": "2025-10-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "20": {"day": "Sunday", "date": "2025-10-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "21": {"day": "Sunday", "date": "2025-11-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "22": {"day": "Sunday", "date": "2025-11-09", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "23": {"day": "Sunday", "date": "2025-11-16", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "24": {"day": "Sunday", "date": "2025-11-23", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "25": {"day": "Sunday", "date": "2025-11-30", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "26": {"day": "Sunday", "date": "2025-12-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "27": {"day": "Sunday", "date": "2025-12-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "28": {"day": "Sunday", "date": "2025-12-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "29": {"day": "Sunday", "date": "2025-12-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "30": {"day": "Sunday", "date": "2026-01-04", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "31": {"day": "Sunday", "date": "2026-01-11", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "32": {"day": "Sunday", "date": "2026-01-18", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "33": {"day": "Sunday", "date": "2026-01-25", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "34": {"day": "Sunday", "date": "2026-02-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "35": {"day": "Sunday", "date": "2026-02-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "36": {"day": "Sunday", "date": "2026-02-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "37": {"day": "Sunday", "date": "2026-02-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "38": {"day": "Sunday", "date": "2026-03-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "39": {"day": "Sunday", "date": "2026-03-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "40": {"day": "Sunday", "date": "2026-03-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "41": {"day": "Sunday", "date": "2026-03-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "42": {"day": "Sunday", "date": "2026-03-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "43": {"day": "Sunday", "date": "2026-04-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "44": {"day": "Sunday", "date": "2026-04-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "45": {"day": "Sunday", "date": "2026-04-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "46": {"day": "Sunday", "date": "2026-04-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "47": {"day": "Sunday", "date": "2026-05-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "48": {"day": "Sunday", "date": "2026-05-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "49": {"day": "Sunday", "date": "2026-05-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "50": {"day": "Sunday", "date": "2026-05-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "51": {"day": "Sunday", "date": "2026-05-31", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "52": {"day": "Sunday", "date": "2026-06-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "53": {"day": "Sunday", "date": "2026-06-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "54": {"day": "Sunday", "date": "2026-06-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "55": {"day": "Sunday", "date": "2026-06-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "56": {"day": "Sunday", "date": "2026-07-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "57": {"day": "Sunday", "date": "2026-07-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "58": {"day": "Sunday", "date": "2026-07-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "59": {"day": "Sunday", "date": "2026-07-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "days": "[\\\\\\"Monday\\\\\\",\\\\\\"Tuesday\\\\\\"]", "pattern": "weekly", "end_date": "2026-07-29", "end_time": "07:00", "start_date": "2025-06-02", "start_time": "06:00", "total_hours": "120", "exception_dates": [{"date": "2025-07-14", "reason": "Other"}], "holiday_overrides": {"2025-09-23": {"date": "2025-09-23", "name": "Heritage Day", "override": true}, "2025-12-15": {"date": "2025-12-15", "name": "Day of Reconciliation", "override": true}}}	[{"stop_date": "2025-09-01", "restart_date": "2025-09-22"}]	["Learners behind schedule"]	1	2025-06-02	[]	\N	[]	\N
23	11	Aspen Pharmacare - Production Unit, 101 Pharma Rd, Durban, 4001	AET	2025-06-02	t	MQA	t	Writen Exam	8	2025-05-29 07:12:33	2025-05-29 07:12:33	\N	BOTH	AET-BOTH-2025	240	1	[1, 2, 3]	[13]	{"0": {"day": "Monday", "date": "2025-06-02", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "1": {"day": "Tuesday", "date": "2025-06-03", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "2": {"day": "Monday", "date": "2025-06-09", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "3": {"day": "Tuesday", "date": "2025-06-10", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "4": {"day": "Tuesday", "date": "2025-06-17", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "5": {"day": "Monday", "date": "2025-06-23", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "6": {"day": "Tuesday", "date": "2025-06-24", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "7": {"day": "Monday", "date": "2025-06-30", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "8": {"day": "Tuesday", "date": "2025-07-01", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "9": {"day": "Monday", "date": "2025-07-07", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "10": {"day": "Tuesday", "date": "2025-07-08", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "11": {"day": "Monday", "date": "2025-07-14", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "12": {"day": "Tuesday", "date": "2025-07-15", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "13": {"day": "Monday", "date": "2025-07-21", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "14": {"day": "Tuesday", "date": "2025-07-22", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "15": {"day": "Monday", "date": "2025-07-28", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "16": {"day": "Tuesday", "date": "2025-07-29", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "17": {"day": "Monday", "date": "2025-08-04", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "18": {"day": "Tuesday", "date": "2025-08-05", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "19": {"day": "Tuesday", "date": "2025-08-12", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "20": {"day": "Monday", "date": "2025-08-18", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "21": {"day": "Tuesday", "date": "2025-08-19", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "22": {"day": "Monday", "date": "2025-08-25", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "23": {"day": "Tuesday", "date": "2025-08-26", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "24": {"day": "Monday", "date": "2025-09-01", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "25": {"day": "Tuesday", "date": "2025-09-02", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "26": {"day": "Tuesday", "date": "2025-09-09", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "27": {"day": "Monday", "date": "2025-09-15", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "28": {"day": "Tuesday", "date": "2025-09-16", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "29": {"day": "Monday", "date": "2025-09-22", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "30": {"day": "Tuesday", "date": "2025-09-23", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "31": {"day": "Monday", "date": "2025-09-29", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "32": {"day": "Tuesday", "date": "2025-09-30", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "33": {"day": "Monday", "date": "2025-10-06", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "34": {"day": "Tuesday", "date": "2025-10-07", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "35": {"day": "Monday", "date": "2025-10-13", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "36": {"day": "Tuesday", "date": "2025-10-14", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "37": {"day": "Monday", "date": "2025-10-20", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "38": {"day": "Tuesday", "date": "2025-10-21", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "39": {"day": "Monday", "date": "2025-10-27", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "40": {"day": "Tuesday", "date": "2025-10-28", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "41": {"day": "Monday", "date": "2025-11-03", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "42": {"day": "Tuesday", "date": "2025-11-04", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "43": {"day": "Monday", "date": "2025-11-10", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "44": {"day": "Tuesday", "date": "2025-11-11", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "45": {"day": "Monday", "date": "2025-11-17", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "46": {"day": "Tuesday", "date": "2025-11-18", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "47": {"day": "Monday", "date": "2025-11-24", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "48": {"day": "Tuesday", "date": "2025-11-25", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "49": {"day": "Monday", "date": "2025-12-01", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "50": {"day": "Tuesday", "date": "2025-12-02", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "51": {"day": "Monday", "date": "2025-12-08", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "52": {"day": "Tuesday", "date": "2025-12-09", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "53": {"day": "Monday", "date": "2025-12-15", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "54": {"day": "Monday", "date": "2025-12-22", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "55": {"day": "Tuesday", "date": "2025-12-23", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "56": {"day": "Monday", "date": "2025-12-29", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "57": {"day": "Tuesday", "date": "2025-12-30", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "58": {"day": "Monday", "date": "2026-01-05", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "59": {"day": "Tuesday", "date": "2026-01-06", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "60": {"day": "Monday", "date": "2026-01-12", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "61": {"day": "Tuesday", "date": "2026-01-13", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "62": {"day": "Monday", "date": "2026-01-19", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "63": {"day": "Tuesday", "date": "2026-01-20", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "64": {"day": "Monday", "date": "2026-01-26", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "65": {"day": "Tuesday", "date": "2026-01-27", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "66": {"day": "Monday", "date": "2026-02-02", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "67": {"day": "Tuesday", "date": "2026-02-03", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "68": {"day": "Monday", "date": "2026-02-09", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "69": {"day": "Tuesday", "date": "2026-02-10", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "70": {"day": "Monday", "date": "2026-02-16", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "71": {"day": "Tuesday", "date": "2026-02-17", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "72": {"day": "Monday", "date": "2026-02-23", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "73": {"day": "Tuesday", "date": "2026-02-24", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "74": {"day": "Monday", "date": "2026-03-02", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "75": {"day": "Tuesday", "date": "2026-03-03", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "76": {"day": "Monday", "date": "2026-03-09", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "77": {"day": "Tuesday", "date": "2026-03-10", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "78": {"day": "Monday", "date": "2026-03-16", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "79": {"day": "Tuesday", "date": "2026-03-17", "type": "class", "notes": "", "end_time": "07:30", "start_time": "06:00"}, "days": ["Monday", "Tuesday"], "pattern": "weekly", "end_date": "2026-03-18", "end_time": "07:30", "start_date": "2025-06-02", "start_time": "06:00", "total_hours": "120", "exception_dates": [{"date": "2025-08-11", "reason": "Agent Absent"}, {"date": "2025-09-08", "reason": "Client Cancelled"}], "holiday_overrides": {"2025-09-23": {"date": "2025-09-23", "name": "Heritage Day", "override": true}, "2025-12-15": {"date": "2025-12-15", "name": "Day of Reconciliation", "override": true}}}	[{"stop_date": "2025-10-13", "restart_date": "2025-10-27"}, {"stop_date": "2025-11-03", "restart_date": "2025-11-17"}]	["Learners behind schedule"]	1	2025-06-02	[]	\N	[]	\N
24	11	Aspen Pharmacare - Research Centre, 102 Pharma Rd, Durban, 4001	AET	2025-06-02	t	BANKSETA	t	External Exam	3	2025-05-29 09:20:59	2025-05-29 09:20:59	\N	BOTH	AET-BOTH-2025	240	1	[1, 2, 3, 4]	[10]	{"0": {"day": "Monday", "date": "2025-06-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "1": {"day": "Tuesday", "date": "2025-06-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "2": {"day": "Monday", "date": "2025-06-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "3": {"day": "Tuesday", "date": "2025-06-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "4": {"day": "Tuesday", "date": "2025-06-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "5": {"day": "Monday", "date": "2025-06-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "6": {"day": "Tuesday", "date": "2025-06-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "7": {"day": "Monday", "date": "2025-06-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "8": {"day": "Tuesday", "date": "2025-07-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "9": {"day": "Monday", "date": "2025-07-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "10": {"day": "Tuesday", "date": "2025-07-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "11": {"day": "Tuesday", "date": "2025-07-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "12": {"day": "Monday", "date": "2025-07-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "13": {"day": "Tuesday", "date": "2025-07-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "14": {"day": "Monday", "date": "2025-07-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "15": {"day": "Tuesday", "date": "2025-07-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "16": {"day": "Monday", "date": "2025-08-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "17": {"day": "Tuesday", "date": "2025-08-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "18": {"day": "Monday", "date": "2025-08-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "19": {"day": "Tuesday", "date": "2025-08-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "20": {"day": "Monday", "date": "2025-08-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "21": {"day": "Tuesday", "date": "2025-08-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "22": {"day": "Monday", "date": "2025-08-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "23": {"day": "Tuesday", "date": "2025-08-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "24": {"day": "Monday", "date": "2025-09-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "25": {"day": "Tuesday", "date": "2025-09-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "26": {"day": "Monday", "date": "2025-09-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "27": {"day": "Tuesday", "date": "2025-09-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "28": {"day": "Monday", "date": "2025-09-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "29": {"day": "Tuesday", "date": "2025-09-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "30": {"day": "Monday", "date": "2025-09-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "31": {"day": "Tuesday", "date": "2025-09-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "32": {"day": "Monday", "date": "2025-09-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "33": {"day": "Tuesday", "date": "2025-09-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "34": {"day": "Monday", "date": "2025-10-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "35": {"day": "Tuesday", "date": "2025-10-07", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "36": {"day": "Monday", "date": "2025-10-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "37": {"day": "Tuesday", "date": "2025-10-14", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "38": {"day": "Monday", "date": "2025-10-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "39": {"day": "Tuesday", "date": "2025-10-21", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "40": {"day": "Monday", "date": "2025-10-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "41": {"day": "Tuesday", "date": "2025-10-28", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "42": {"day": "Monday", "date": "2025-11-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "43": {"day": "Tuesday", "date": "2025-11-04", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "44": {"day": "Monday", "date": "2025-11-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "45": {"day": "Tuesday", "date": "2025-11-11", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "46": {"day": "Monday", "date": "2025-11-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "47": {"day": "Tuesday", "date": "2025-11-18", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "48": {"day": "Monday", "date": "2025-11-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "49": {"day": "Tuesday", "date": "2025-11-25", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "50": {"day": "Monday", "date": "2025-12-01", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "51": {"day": "Tuesday", "date": "2025-12-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "52": {"day": "Monday", "date": "2025-12-08", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "53": {"day": "Tuesday", "date": "2025-12-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "54": {"day": "Monday", "date": "2025-12-15", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "55": {"day": "Monday", "date": "2025-12-22", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "56": {"day": "Tuesday", "date": "2025-12-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "57": {"day": "Monday", "date": "2025-12-29", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "58": {"day": "Tuesday", "date": "2025-12-30", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "59": {"day": "Monday", "date": "2026-01-05", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "60": {"day": "Tuesday", "date": "2026-01-06", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "61": {"day": "Monday", "date": "2026-01-12", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "62": {"day": "Tuesday", "date": "2026-01-13", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "63": {"day": "Monday", "date": "2026-01-19", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "64": {"day": "Tuesday", "date": "2026-01-20", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "65": {"day": "Monday", "date": "2026-01-26", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "66": {"day": "Tuesday", "date": "2026-01-27", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "67": {"day": "Monday", "date": "2026-02-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "68": {"day": "Tuesday", "date": "2026-02-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "69": {"day": "Monday", "date": "2026-02-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "70": {"day": "Tuesday", "date": "2026-02-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "71": {"day": "Monday", "date": "2026-02-16", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "72": {"day": "Tuesday", "date": "2026-02-17", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "73": {"day": "Monday", "date": "2026-02-23", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "74": {"day": "Tuesday", "date": "2026-02-24", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "75": {"day": "Monday", "date": "2026-03-02", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "76": {"day": "Tuesday", "date": "2026-03-03", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "77": {"day": "Monday", "date": "2026-03-09", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "78": {"day": "Tuesday", "date": "2026-03-10", "type": "class", "notes": "", "end_time": "08:30", "start_time": "07:00"}, "days": ["Monday", "Tuesday"], "pattern": "weekly", "end_date": "2026-03-10", "end_time": "08:30", "start_date": "2025-06-02", "start_time": "07:00", "total_hours": "120", "exception_dates": [{"date": "2025-07-14", "reason": "Client Cancelled"}], "holiday_overrides": {"2025-09-23": {"date": "2025-09-23", "name": "Heritage Day", "override": true}, "2025-12-15": {"date": "2025-12-15", "name": "Day of Reconciliation", "override": true}}}	[{"stop_date": "2025-08-04", "restart_date": "2025-08-18"}]	["Poor attendance"]	1	2025-06-02	[]	\N	[]	\N
33	2	35346 South Drive, Mayfair, 2100	AET	2025-06-30	t	BANKSETA	t	Open Book Exam	1	2025-06-30 10:21:40	2025-06-30 10:21:40	23	COMM_NUM	2-AET-COMM_NUM-2025-06-30-12-19	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-06-30", "agent_id": 10}]	{"endDate": "", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-06-30T10:21:39+00:00", "validatedAt": "2025-06-30T10:21:39+00:00"}, "timeData": {"mode": "single"}, "startDate": "", "dayOfMonth": null, "selectedDays": [], "exceptionDates": [], "holidayOverrides": []}	[{"stop_date": "2025-08-11", "restart_date": "2025-08-18"}]	[]	1	2025-06-30	[]	\N	[]	\N
26	14	Barloworld - Northern Branch, 10 Northern Ave, Johannesburg, 2001	AET	2025-06-09	t	CETA	t	Open Book Exam	5	2025-06-02 13:02:38	2025-06-02 13:02:38	\N	COMM_NUM	AET-COMM_NUM-2025-2506021457	240	10	[15, 23, 24, 37]	[14]	{"0": {"day": "Monday", "date": "2025-06-09", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "1": {"day": "Monday", "date": "2025-06-23", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "2": {"day": "Monday", "date": "2025-07-07", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "3": {"day": "Monday", "date": "2025-08-04", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "4": {"day": "Monday", "date": "2025-08-18", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "5": {"day": "Monday", "date": "2025-09-01", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "6": {"day": "Monday", "date": "2025-09-15", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "7": {"day": "Monday", "date": "2025-09-29", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "8": {"day": "Monday", "date": "2025-10-13", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "9": {"day": "Monday", "date": "2025-10-27", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "10": {"day": "Monday", "date": "2025-11-10", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "11": {"day": "Monday", "date": "2025-11-24", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "12": {"day": "Monday", "date": "2025-12-08", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "13": {"day": "Monday", "date": "2025-12-22", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "14": {"day": "Monday", "date": "2026-01-05", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "15": {"day": "Monday", "date": "2026-01-19", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "16": {"day": "Monday", "date": "2026-02-02", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "17": {"day": "Monday", "date": "2026-02-16", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "18": {"day": "Monday", "date": "2026-03-02", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "19": {"day": "Monday", "date": "2026-03-16", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "20": {"day": "Monday", "date": "2026-03-30", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "21": {"day": "Monday", "date": "2026-04-13", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "22": {"day": "Monday", "date": "2026-05-11", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "23": {"day": "Monday", "date": "2026-05-25", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "24": {"day": "Monday", "date": "2026-06-08", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "25": {"day": "Monday", "date": "2026-06-22", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "26": {"day": "Monday", "date": "2026-07-06", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "27": {"day": "Monday", "date": "2026-07-20", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "28": {"day": "Monday", "date": "2026-08-03", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "29": {"day": "Monday", "date": "2026-08-17", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "30": {"day": "Monday", "date": "2026-08-31", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "31": {"day": "Monday", "date": "2026-09-14", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "32": {"day": "Monday", "date": "2026-09-28", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "33": {"day": "Monday", "date": "2026-10-12", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "34": {"day": "Monday", "date": "2026-10-26", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "35": {"day": "Monday", "date": "2026-11-09", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "36": {"day": "Monday", "date": "2026-11-23", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "37": {"day": "Monday", "date": "2026-12-07", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "38": {"day": "Monday", "date": "2026-12-21", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "39": {"day": "Monday", "date": "2027-01-04", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "40": {"day": "Monday", "date": "2027-01-18", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "41": {"day": "Monday", "date": "2027-02-01", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "42": {"day": "Monday", "date": "2027-02-15", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "43": {"day": "Monday", "date": "2027-03-01", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "44": {"day": "Monday", "date": "2027-03-15", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "45": {"day": "Monday", "date": "2027-03-29", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "46": {"day": "Monday", "date": "2027-04-12", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "47": {"day": "Monday", "date": "2027-04-26", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "48": {"day": "Monday", "date": "2027-05-10", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "49": {"day": "Monday", "date": "2027-05-24", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "50": {"day": "Monday", "date": "2027-06-07", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "51": {"day": "Monday", "date": "2027-06-21", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "52": {"day": "Monday", "date": "2027-07-05", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "53": {"day": "Monday", "date": "2027-07-19", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "54": {"day": "Monday", "date": "2027-08-02", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "55": {"day": "Monday", "date": "2027-08-16", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "56": {"day": "Monday", "date": "2027-08-30", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "57": {"day": "Monday", "date": "2027-09-13", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "58": {"day": "Monday", "date": "2027-09-27", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "59": {"day": "Monday", "date": "2027-10-11", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "60": {"day": "Monday", "date": "2027-10-25", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "61": {"day": "Monday", "date": "2027-11-08", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "62": {"day": "Monday", "date": "2027-11-22", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "63": {"day": "Monday", "date": "2027-12-06", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "64": {"day": "Monday", "date": "2027-12-20", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "65": {"day": "Monday", "date": "2028-01-03", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "66": {"day": "Monday", "date": "2028-01-17", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "67": {"day": "Monday", "date": "2028-01-31", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "68": {"day": "Monday", "date": "2028-02-14", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "69": {"day": "Monday", "date": "2028-02-28", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "70": {"day": "Monday", "date": "2028-03-13", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "71": {"day": "Monday", "date": "2028-03-27", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "72": {"day": "Monday", "date": "2028-04-10", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "73": {"day": "Monday", "date": "2028-04-24", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "74": {"day": "Monday", "date": "2028-05-08", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "75": {"day": "Monday", "date": "2028-05-22", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "76": {"day": "Monday", "date": "2028-06-05", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "77": {"day": "Monday", "date": "2028-06-19", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "78": {"day": "Monday", "date": "2028-07-03", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "79": {"day": "Monday", "date": "2028-07-17", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "80": {"day": "Monday", "date": "2028-07-31", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "81": {"day": "Monday", "date": "2028-08-14", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "82": {"day": "Monday", "date": "2028-08-28", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "83": {"day": "Monday", "date": "2028-09-11", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "84": {"day": "Monday", "date": "2028-09-25", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "85": {"day": "Monday", "date": "2028-10-09", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "86": {"day": "Monday", "date": "2028-10-23", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "87": {"day": "Monday", "date": "2028-11-06", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "88": {"day": "Monday", "date": "2028-11-20", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "89": {"day": "Monday", "date": "2028-12-04", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "90": {"day": "Monday", "date": "2028-12-18", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "91": {"day": "Monday", "date": "2029-01-01", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "92": {"day": "Monday", "date": "2029-01-15", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "93": {"day": "Monday", "date": "2029-01-29", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "94": {"day": "Monday", "date": "2029-02-12", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "95": {"day": "Monday", "date": "2029-02-26", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "96": {"day": "Monday", "date": "2029-03-12", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "97": {"day": "Monday", "date": "2029-03-26", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "98": {"day": "Monday", "date": "2029-04-09", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "99": {"day": "Monday", "date": "2029-04-23", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "100": {"day": "Monday", "date": "2029-05-07", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "101": {"day": "Monday", "date": "2029-05-21", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "102": {"day": "Monday", "date": "2029-06-04", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "103": {"day": "Monday", "date": "2029-06-18", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "104": {"day": "Monday", "date": "2029-07-02", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "105": {"day": "Monday", "date": "2029-07-16", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "106": {"day": "Monday", "date": "2029-07-30", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "107": {"day": "Monday", "date": "2029-08-13", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "108": {"day": "Monday", "date": "2029-08-27", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "109": {"day": "Monday", "date": "2029-09-10", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "110": {"day": "Monday", "date": "2029-09-24", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "111": {"day": "Monday", "date": "2029-10-08", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "112": {"day": "Monday", "date": "2029-10-22", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "113": {"day": "Monday", "date": "2029-11-05", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "114": {"day": "Monday", "date": "2029-11-19", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "115": {"day": "Monday", "date": "2029-12-03", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "116": {"day": "Monday", "date": "2029-12-17", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "117": {"day": "Monday", "date": "2029-12-31", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "118": {"day": "Monday", "date": "2030-01-14", "type": "class", "notes": "", "end_time": "10:00", "start_time": "09:00"}, "days": ["Monday"], "pattern": "biweekly", "end_date": "2030-01-15", "end_time": "10:00", "start_date": "2025-06-09", "start_time": "09:00", "total_hours": "120", "exception_dates": [{"date": "2025-07-21", "reason": "Client Cancelled"}], "holiday_overrides": {"2025-12-15": {"date": "2025-12-15", "name": "Day of Reconciliation", "override": true}}}	[{"stop_date": "2025-08-11", "restart_date": "2025-08-25"}]	[]	10	2025-06-09	[]	\N	[]	\N
27	14	Barloworld - Northern Branch, 10 Northern Ave, Johannesburg, 2001	REALLL	2025-06-09	t	LGSETA	t	Open Book Exam	3	2025-06-03 14:00:15	2025-06-03 14:00:15	\N	RLC	14-REALLL-RLC-2025-06-03-15-52	160	1	[1, 2, 3, 4, 9, 10]	[3]	{"0": {"day": "Monday", "date": "2025-06-09", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "1": {"day": "Monday", "date": "2025-06-23", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "2": {"day": "Monday", "date": "2025-06-30", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "3": {"day": "Monday", "date": "2025-07-07", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "4": {"day": "Monday", "date": "2025-07-14", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "5": {"day": "Monday", "date": "2025-07-21", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "6": {"day": "Monday", "date": "2025-07-28", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "7": {"day": "Monday", "date": "2025-08-04", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "8": {"day": "Monday", "date": "2025-08-11", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "9": {"day": "Monday", "date": "2025-08-18", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "10": {"day": "Monday", "date": "2025-08-25", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "11": {"day": "Monday", "date": "2025-09-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "12": {"day": "Monday", "date": "2025-09-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "13": {"day": "Monday", "date": "2025-09-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "14": {"day": "Monday", "date": "2025-09-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "15": {"day": "Monday", "date": "2025-09-29", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "16": {"day": "Monday", "date": "2025-10-06", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "17": {"day": "Monday", "date": "2025-10-13", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "18": {"day": "Monday", "date": "2025-10-20", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "19": {"day": "Monday", "date": "2025-10-27", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "20": {"day": "Monday", "date": "2025-11-03", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "21": {"day": "Monday", "date": "2025-11-10", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "22": {"day": "Monday", "date": "2025-11-17", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "23": {"day": "Monday", "date": "2025-11-24", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "24": {"day": "Monday", "date": "2025-12-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "25": {"day": "Monday", "date": "2025-12-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "26": {"day": "Monday", "date": "2025-12-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "27": {"day": "Monday", "date": "2025-12-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "28": {"day": "Monday", "date": "2025-12-29", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "29": {"day": "Monday", "date": "2026-01-05", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "30": {"day": "Monday", "date": "2026-01-12", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "31": {"day": "Monday", "date": "2026-01-19", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "32": {"day": "Monday", "date": "2026-01-26", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "33": {"day": "Monday", "date": "2026-02-02", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "34": {"day": "Monday", "date": "2026-02-09", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "35": {"day": "Monday", "date": "2026-02-16", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "36": {"day": "Monday", "date": "2026-02-23", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "37": {"day": "Monday", "date": "2026-03-02", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "38": {"day": "Monday", "date": "2026-03-09", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "39": {"day": "Monday", "date": "2026-03-16", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "40": {"day": "Monday", "date": "2026-03-23", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "41": {"day": "Monday", "date": "2026-03-30", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "42": {"day": "Monday", "date": "2026-04-13", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "43": {"day": "Monday", "date": "2026-04-20", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "44": {"day": "Monday", "date": "2026-05-04", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "45": {"day": "Monday", "date": "2026-05-11", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "46": {"day": "Monday", "date": "2026-05-18", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "47": {"day": "Monday", "date": "2026-05-25", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "48": {"day": "Monday", "date": "2026-06-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "49": {"day": "Monday", "date": "2026-06-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "50": {"day": "Monday", "date": "2026-06-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "51": {"day": "Monday", "date": "2026-06-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "52": {"day": "Monday", "date": "2026-06-29", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "53": {"day": "Monday", "date": "2026-07-06", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "54": {"day": "Monday", "date": "2026-07-13", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "55": {"day": "Monday", "date": "2026-07-20", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "56": {"day": "Monday", "date": "2026-07-27", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "57": {"day": "Monday", "date": "2026-08-03", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "58": {"day": "Monday", "date": "2026-08-17", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "59": {"day": "Monday", "date": "2026-08-24", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "60": {"day": "Monday", "date": "2026-08-31", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "61": {"day": "Monday", "date": "2026-09-07", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "62": {"day": "Monday", "date": "2026-09-14", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "63": {"day": "Monday", "date": "2026-09-21", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "64": {"day": "Monday", "date": "2026-09-28", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "65": {"day": "Monday", "date": "2026-10-05", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "66": {"day": "Monday", "date": "2026-10-12", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "67": {"day": "Monday", "date": "2026-10-19", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "68": {"day": "Monday", "date": "2026-10-26", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "69": {"day": "Monday", "date": "2026-11-02", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "70": {"day": "Monday", "date": "2026-11-09", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "71": {"day": "Monday", "date": "2026-11-16", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "72": {"day": "Monday", "date": "2026-11-23", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "73": {"day": "Monday", "date": "2026-11-30", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "74": {"day": "Monday", "date": "2026-12-07", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "75": {"day": "Monday", "date": "2026-12-14", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "76": {"day": "Monday", "date": "2026-12-21", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "77": {"day": "Monday", "date": "2026-12-28", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "78": {"day": "Monday", "date": "2027-01-04", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "79": {"day": "Monday", "date": "2027-01-11", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "80": {"day": "Monday", "date": "2027-01-18", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "81": {"day": "Monday", "date": "2027-01-25", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "82": {"day": "Monday", "date": "2027-02-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "83": {"day": "Monday", "date": "2027-02-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "84": {"day": "Monday", "date": "2027-02-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "85": {"day": "Monday", "date": "2027-02-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "86": {"day": "Monday", "date": "2027-03-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "87": {"day": "Monday", "date": "2027-03-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "88": {"day": "Monday", "date": "2027-03-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "89": {"day": "Monday", "date": "2027-03-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "90": {"day": "Monday", "date": "2027-03-29", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "91": {"day": "Monday", "date": "2027-04-05", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "92": {"day": "Monday", "date": "2027-04-12", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "93": {"day": "Monday", "date": "2027-04-19", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "94": {"day": "Monday", "date": "2027-04-26", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "95": {"day": "Monday", "date": "2027-05-03", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "96": {"day": "Monday", "date": "2027-05-10", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "97": {"day": "Monday", "date": "2027-05-17", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "98": {"day": "Monday", "date": "2027-05-24", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "99": {"day": "Monday", "date": "2027-05-31", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "100": {"day": "Monday", "date": "2027-06-07", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "101": {"day": "Monday", "date": "2027-06-14", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "102": {"day": "Monday", "date": "2027-06-21", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "103": {"day": "Monday", "date": "2027-06-28", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "days": ["Monday"], "pattern": "weekly", "end_date": "2027-06-29", "end_time": "11:30", "start_date": "2025-06-09", "start_time": "10:00", "total_hours": "160", "holiday_overrides": {"2025-12-15": {"date": "2025-12-15", "name": "Day of Reconciliation", "override": true}}}	[{"stop_date": "2025-09-08", "restart_date": "2025-09-22"}]	[]	1	2025-06-09	[]	\N	[]	\N
28	14	Barloworld - Northern Branch, 10 Northern Ave, Johannesburg, 2001	REALLL	2025-06-09	t	LGSETA	t	Open Book Exam	3	2025-06-03 15:06:33	2025-06-03 15:06:33	\N	RLC	14-REALLL-RLC-2025-06-03-15-52	160	1	[{"id": 1, "name": "John J.M. Smith", "level": "", "status": "Host Company Learner"}, {"id": 2, "name": "Nosipho N. Dlamini", "level": "", "status": "Host Company Learner"}, {"id": 3, "name": "Ahmed A. Patel", "level": "", "status": "Host Company Learner"}, {"id": 4, "name": "Lerato L. Moloi", "level": "", "status": "Host Company Learner"}, {"id": 9, "name": "Willem W. Botha", "level": "", "status": "Host Company Learner"}, {"id": 10, "name": "Nomsa N. Tshabalala", "level": "", "status": "Host Company Learner"}]	[3]	{"0": {"day": "Monday", "date": "2025-06-09", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "1": {"day": "Monday", "date": "2025-06-23", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "2": {"day": "Monday", "date": "2025-06-30", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "3": {"day": "Monday", "date": "2025-07-07", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "4": {"day": "Monday", "date": "2025-07-14", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "5": {"day": "Monday", "date": "2025-07-21", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "6": {"day": "Monday", "date": "2025-07-28", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "7": {"day": "Monday", "date": "2025-08-04", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "8": {"day": "Monday", "date": "2025-08-11", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "9": {"day": "Monday", "date": "2025-08-18", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "10": {"day": "Monday", "date": "2025-08-25", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "11": {"day": "Monday", "date": "2025-09-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "12": {"day": "Monday", "date": "2025-09-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "13": {"day": "Monday", "date": "2025-09-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "14": {"day": "Monday", "date": "2025-09-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "15": {"day": "Monday", "date": "2025-09-29", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "16": {"day": "Monday", "date": "2025-10-06", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "17": {"day": "Monday", "date": "2025-10-13", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "18": {"day": "Monday", "date": "2025-10-20", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "19": {"day": "Monday", "date": "2025-10-27", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "20": {"day": "Monday", "date": "2025-11-03", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "21": {"day": "Monday", "date": "2025-11-10", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "22": {"day": "Monday", "date": "2025-11-17", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "23": {"day": "Monday", "date": "2025-11-24", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "24": {"day": "Monday", "date": "2025-12-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "25": {"day": "Monday", "date": "2025-12-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "26": {"day": "Monday", "date": "2025-12-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "27": {"day": "Monday", "date": "2025-12-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "28": {"day": "Monday", "date": "2025-12-29", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "29": {"day": "Monday", "date": "2026-01-05", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "30": {"day": "Monday", "date": "2026-01-12", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "31": {"day": "Monday", "date": "2026-01-19", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "32": {"day": "Monday", "date": "2026-01-26", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "33": {"day": "Monday", "date": "2026-02-02", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "34": {"day": "Monday", "date": "2026-02-09", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "35": {"day": "Monday", "date": "2026-02-16", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "36": {"day": "Monday", "date": "2026-02-23", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "37": {"day": "Monday", "date": "2026-03-02", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "38": {"day": "Monday", "date": "2026-03-09", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "39": {"day": "Monday", "date": "2026-03-16", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "40": {"day": "Monday", "date": "2026-03-23", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "41": {"day": "Monday", "date": "2026-03-30", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "42": {"day": "Monday", "date": "2026-04-13", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "43": {"day": "Monday", "date": "2026-04-20", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "44": {"day": "Monday", "date": "2026-05-04", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "45": {"day": "Monday", "date": "2026-05-11", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "46": {"day": "Monday", "date": "2026-05-18", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "47": {"day": "Monday", "date": "2026-05-25", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "48": {"day": "Monday", "date": "2026-06-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "49": {"day": "Monday", "date": "2026-06-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "50": {"day": "Monday", "date": "2026-06-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "51": {"day": "Monday", "date": "2026-06-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "52": {"day": "Monday", "date": "2026-06-29", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "53": {"day": "Monday", "date": "2026-07-06", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "54": {"day": "Monday", "date": "2026-07-13", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "55": {"day": "Monday", "date": "2026-07-20", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "56": {"day": "Monday", "date": "2026-07-27", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "57": {"day": "Monday", "date": "2026-08-03", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "58": {"day": "Monday", "date": "2026-08-17", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "59": {"day": "Monday", "date": "2026-08-24", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "60": {"day": "Monday", "date": "2026-08-31", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "61": {"day": "Monday", "date": "2026-09-07", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "62": {"day": "Monday", "date": "2026-09-14", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "63": {"day": "Monday", "date": "2026-09-21", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "64": {"day": "Monday", "date": "2026-09-28", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "65": {"day": "Monday", "date": "2026-10-05", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "66": {"day": "Monday", "date": "2026-10-12", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "67": {"day": "Monday", "date": "2026-10-19", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "68": {"day": "Monday", "date": "2026-10-26", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "69": {"day": "Monday", "date": "2026-11-02", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "70": {"day": "Monday", "date": "2026-11-09", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "71": {"day": "Monday", "date": "2026-11-16", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "72": {"day": "Monday", "date": "2026-11-23", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "73": {"day": "Monday", "date": "2026-11-30", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "74": {"day": "Monday", "date": "2026-12-07", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "75": {"day": "Monday", "date": "2026-12-14", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "76": {"day": "Monday", "date": "2026-12-21", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "77": {"day": "Monday", "date": "2026-12-28", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "78": {"day": "Monday", "date": "2027-01-04", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "79": {"day": "Monday", "date": "2027-01-11", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "80": {"day": "Monday", "date": "2027-01-18", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "81": {"day": "Monday", "date": "2027-01-25", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "82": {"day": "Monday", "date": "2027-02-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "83": {"day": "Monday", "date": "2027-02-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "84": {"day": "Monday", "date": "2027-02-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "85": {"day": "Monday", "date": "2027-02-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "86": {"day": "Monday", "date": "2027-03-01", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "87": {"day": "Monday", "date": "2027-03-08", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "88": {"day": "Monday", "date": "2027-03-15", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "89": {"day": "Monday", "date": "2027-03-22", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "90": {"day": "Monday", "date": "2027-03-29", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "91": {"day": "Monday", "date": "2027-04-05", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "92": {"day": "Monday", "date": "2027-04-12", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "93": {"day": "Monday", "date": "2027-04-19", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "94": {"day": "Monday", "date": "2027-04-26", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "95": {"day": "Monday", "date": "2027-05-03", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "96": {"day": "Monday", "date": "2027-05-10", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "97": {"day": "Monday", "date": "2027-05-17", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "98": {"day": "Monday", "date": "2027-05-24", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "99": {"day": "Monday", "date": "2027-05-31", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "100": {"day": "Monday", "date": "2027-06-07", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "101": {"day": "Monday", "date": "2027-06-14", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "102": {"day": "Monday", "date": "2027-06-21", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "103": {"day": "Monday", "date": "2027-06-28", "type": "class", "notes": "", "end_time": "11:30", "start_time": "10:00"}, "days": ["Monday"], "pattern": "weekly", "end_date": "2027-06-29", "end_time": "11:30", "start_date": "2025-06-09", "start_time": "10:00", "total_hours": "160", "holiday_overrides": {"2025-12-15": {"date": "2025-12-15", "name": "Day of Reconciliation", "override": true}}}	[{"stop_date": "2025-09-08", "restart_date": "2025-09-22"}]	[]	1	2025-06-09	[]	\N	[]	\N
29	14	Barloworld - Northern Branch, 10 Northern Ave, Johannesburg, 2001	GETC	2025-06-09	t	BANKSETA	t	Writen Exam	11	2025-06-04 17:21:05	2025-06-04 17:21:05	\N	SMME4	14-GETC-SMME4-2025-06-04-19-18	60	1	[{"id": 1, "name": "John J.M. Smith", "level": "", "status": "Host Company Learner"}, {"id": 2, "name": "Nosipho N. Dlamini", "level": "", "status": "Host Company Learner"}, {"id": 3, "name": "Ahmed A. Patel", "level": "", "status": "Host Company Learner"}, {"id": 9, "name": "Willem W. Botha", "level": "", "status": "Host Company Learner"}, {"id": 10, "name": "Nomsa N. Tshabalala", "level": "", "status": "Host Company Learner"}]	[10]	{"0": {"day": "Tuesday", "date": "2025-06-10", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "1": {"day": "Tuesday", "date": "2025-06-24", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "2": {"day": "Tuesday", "date": "2025-07-08", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "3": {"day": "Tuesday", "date": "2025-07-22", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "4": {"day": "Tuesday", "date": "2025-08-05", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "5": {"day": "Tuesday", "date": "2025-08-19", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "6": {"day": "Tuesday", "date": "2025-09-02", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "7": {"day": "Tuesday", "date": "2025-09-16", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "8": {"day": "Tuesday", "date": "2025-09-30", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "9": {"day": "Tuesday", "date": "2025-10-14", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "10": {"day": "Tuesday", "date": "2025-10-28", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "11": {"day": "Tuesday", "date": "2025-11-11", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "12": {"day": "Tuesday", "date": "2025-11-25", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "13": {"day": "Tuesday", "date": "2025-12-09", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "14": {"day": "Tuesday", "date": "2025-12-23", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "15": {"day": "Tuesday", "date": "2026-01-06", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "16": {"day": "Tuesday", "date": "2026-01-20", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "17": {"day": "Tuesday", "date": "2026-02-03", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "18": {"day": "Tuesday", "date": "2026-02-17", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "19": {"day": "Tuesday", "date": "2026-03-03", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "20": {"day": "Tuesday", "date": "2026-03-17", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "21": {"day": "Tuesday", "date": "2026-03-31", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "22": {"day": "Tuesday", "date": "2026-04-14", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "23": {"day": "Tuesday", "date": "2026-04-28", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "24": {"day": "Tuesday", "date": "2026-05-12", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "25": {"day": "Tuesday", "date": "2026-05-26", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "26": {"day": "Tuesday", "date": "2026-06-09", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "27": {"day": "Tuesday", "date": "2026-06-23", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "28": {"day": "Tuesday", "date": "2026-07-07", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "29": {"day": "Tuesday", "date": "2026-07-21", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "30": {"day": "Tuesday", "date": "2026-08-04", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "31": {"day": "Tuesday", "date": "2026-08-18", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "32": {"day": "Tuesday", "date": "2026-09-01", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "33": {"day": "Tuesday", "date": "2026-09-15", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "34": {"day": "Tuesday", "date": "2026-09-29", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "35": {"day": "Tuesday", "date": "2026-10-13", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "36": {"day": "Tuesday", "date": "2026-10-27", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "37": {"day": "Tuesday", "date": "2026-11-10", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "38": {"day": "Tuesday", "date": "2026-11-24", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "39": {"day": "Tuesday", "date": "2026-12-08", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "40": {"day": "Tuesday", "date": "2026-12-22", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "41": {"day": "Tuesday", "date": "2027-01-05", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "42": {"day": "Tuesday", "date": "2027-01-19", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "43": {"day": "Tuesday", "date": "2027-02-02", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "44": {"day": "Tuesday", "date": "2027-02-16", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "45": {"day": "Tuesday", "date": "2027-03-02", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "46": {"day": "Tuesday", "date": "2027-03-16", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "47": {"day": "Tuesday", "date": "2027-03-30", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "48": {"day": "Tuesday", "date": "2027-04-13", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "49": {"day": "Tuesday", "date": "2027-04-27", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "50": {"day": "Tuesday", "date": "2027-05-11", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "51": {"day": "Tuesday", "date": "2027-05-25", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "52": {"day": "Tuesday", "date": "2027-06-08", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "53": {"day": "Tuesday", "date": "2027-06-22", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "54": {"day": "Tuesday", "date": "2027-07-06", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "55": {"day": "Tuesday", "date": "2027-07-20", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "56": {"day": "Tuesday", "date": "2027-08-03", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "57": {"day": "Tuesday", "date": "2027-08-17", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "58": {"day": "Tuesday", "date": "2027-08-31", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "59": {"day": "Tuesday", "date": "2027-09-14", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "60": {"day": "Tuesday", "date": "2027-09-28", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "61": {"day": "Tuesday", "date": "2027-10-12", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "62": {"day": "Tuesday", "date": "2027-10-26", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "63": {"day": "Tuesday", "date": "2027-11-09", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "64": {"day": "Tuesday", "date": "2027-11-23", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "65": {"day": "Tuesday", "date": "2027-12-07", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "66": {"day": "Tuesday", "date": "2027-12-21", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "67": {"day": "Tuesday", "date": "2028-01-04", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "68": {"day": "Tuesday", "date": "2028-01-18", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "69": {"day": "Tuesday", "date": "2028-02-01", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "70": {"day": "Tuesday", "date": "2028-02-15", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "71": {"day": "Tuesday", "date": "2028-02-29", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "72": {"day": "Tuesday", "date": "2028-03-14", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "73": {"day": "Tuesday", "date": "2028-03-28", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "74": {"day": "Tuesday", "date": "2028-04-11", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "75": {"day": "Tuesday", "date": "2028-04-25", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "76": {"day": "Tuesday", "date": "2028-05-09", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "77": {"day": "Tuesday", "date": "2028-05-23", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "78": {"day": "Tuesday", "date": "2028-06-06", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "79": {"day": "Tuesday", "date": "2028-06-20", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "80": {"day": "Tuesday", "date": "2028-07-04", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "81": {"day": "Tuesday", "date": "2028-07-18", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "82": {"day": "Tuesday", "date": "2028-08-01", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "83": {"day": "Tuesday", "date": "2028-08-15", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "84": {"day": "Tuesday", "date": "2028-08-29", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "85": {"day": "Tuesday", "date": "2028-09-12", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "86": {"day": "Tuesday", "date": "2028-09-26", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "87": {"day": "Tuesday", "date": "2028-10-10", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "88": {"day": "Tuesday", "date": "2028-10-24", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "89": {"day": "Tuesday", "date": "2028-11-07", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "90": {"day": "Tuesday", "date": "2028-11-21", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "91": {"day": "Tuesday", "date": "2028-12-05", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "92": {"day": "Tuesday", "date": "2028-12-19", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "93": {"day": "Tuesday", "date": "2029-01-02", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "94": {"day": "Tuesday", "date": "2029-01-16", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "95": {"day": "Tuesday", "date": "2029-01-30", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "96": {"day": "Tuesday", "date": "2029-02-13", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "97": {"day": "Tuesday", "date": "2029-02-27", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "98": {"day": "Tuesday", "date": "2029-03-13", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "99": {"day": "Tuesday", "date": "2029-03-27", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "100": {"day": "Tuesday", "date": "2029-04-10", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "101": {"day": "Tuesday", "date": "2029-04-24", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "102": {"day": "Tuesday", "date": "2029-05-08", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "103": {"day": "Tuesday", "date": "2029-05-22", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "104": {"day": "Tuesday", "date": "2029-06-05", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "105": {"day": "Tuesday", "date": "2029-06-19", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "106": {"day": "Tuesday", "date": "2029-07-03", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "107": {"day": "Tuesday", "date": "2029-07-17", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "108": {"day": "Tuesday", "date": "2029-07-31", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "109": {"day": "Tuesday", "date": "2029-08-14", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "110": {"day": "Tuesday", "date": "2029-08-28", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "111": {"day": "Tuesday", "date": "2029-09-11", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "112": {"day": "Tuesday", "date": "2029-09-25", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "113": {"day": "Tuesday", "date": "2029-10-09", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "114": {"day": "Tuesday", "date": "2029-10-23", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "115": {"day": "Tuesday", "date": "2029-11-06", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "116": {"day": "Tuesday", "date": "2029-11-20", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "117": {"day": "Tuesday", "date": "2029-12-04", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "118": {"day": "Tuesday", "date": "2029-12-18", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "119": {"day": "Tuesday", "date": "2030-01-01", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "120": {"day": "Tuesday", "date": "2030-01-15", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "121": {"day": "Tuesday", "date": "2030-01-29", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "122": {"day": "Tuesday", "date": "2030-02-12", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "123": {"day": "Tuesday", "date": "2030-02-26", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "124": {"day": "Tuesday", "date": "2030-03-12", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "125": {"day": "Tuesday", "date": "2030-03-26", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "126": {"day": "Tuesday", "date": "2030-04-09", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "127": {"day": "Tuesday", "date": "2030-04-23", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "128": {"day": "Tuesday", "date": "2030-05-07", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "129": {"day": "Tuesday", "date": "2030-05-21", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "130": {"day": "Tuesday", "date": "2030-06-04", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "131": {"day": "Tuesday", "date": "2030-06-18", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "132": {"day": "Tuesday", "date": "2030-07-02", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "133": {"day": "Tuesday", "date": "2030-07-16", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "134": {"day": "Tuesday", "date": "2030-07-30", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "135": {"day": "Tuesday", "date": "2030-08-13", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "136": {"day": "Tuesday", "date": "2030-08-27", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "137": {"day": "Tuesday", "date": "2030-09-10", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "138": {"day": "Tuesday", "date": "2030-09-24", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "139": {"day": "Tuesday", "date": "2030-10-08", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "140": {"day": "Tuesday", "date": "2030-10-22", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "141": {"day": "Tuesday", "date": "2030-11-05", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "142": {"day": "Tuesday", "date": "2030-11-19", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "143": {"day": "Tuesday", "date": "2030-12-03", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "144": {"day": "Tuesday", "date": "2030-12-17", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "145": {"day": "Tuesday", "date": "2030-12-31", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "146": {"day": "Tuesday", "date": "2031-01-14", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "147": {"day": "Tuesday", "date": "2031-01-28", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "148": {"day": "Tuesday", "date": "2031-02-11", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "149": {"day": "Tuesday", "date": "2031-02-25", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "150": {"day": "Tuesday", "date": "2031-03-11", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "151": {"day": "Tuesday", "date": "2031-03-25", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "152": {"day": "Tuesday", "date": "2031-04-08", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "153": {"day": "Tuesday", "date": "2031-04-22", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "154": {"day": "Tuesday", "date": "2031-05-06", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "155": {"day": "Tuesday", "date": "2031-05-20", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "156": {"day": "Tuesday", "date": "2031-06-03", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "157": {"day": "Tuesday", "date": "2031-06-17", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "158": {"day": "Tuesday", "date": "2031-07-01", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "159": {"day": "Tuesday", "date": "2031-07-15", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "160": {"day": "Tuesday", "date": "2031-07-29", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "161": {"day": "Tuesday", "date": "2031-08-12", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "162": {"day": "Tuesday", "date": "2031-08-26", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "163": {"day": "Tuesday", "date": "2031-09-09", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "164": {"day": "Tuesday", "date": "2031-09-23", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "165": {"day": "Tuesday", "date": "2031-10-07", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "166": {"day": "Tuesday", "date": "2031-10-21", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "167": {"day": "Tuesday", "date": "2031-11-04", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "168": {"day": "Tuesday", "date": "2031-11-18", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "169": {"day": "Tuesday", "date": "2031-12-02", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "170": {"day": "Tuesday", "date": "2031-12-16", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "171": {"day": "Tuesday", "date": "2031-12-30", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "172": {"day": "Tuesday", "date": "2032-01-13", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "173": {"day": "Tuesday", "date": "2032-01-27", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "174": {"day": "Tuesday", "date": "2032-02-10", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "175": {"day": "Tuesday", "date": "2032-02-24", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "176": {"day": "Tuesday", "date": "2032-03-09", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "177": {"day": "Tuesday", "date": "2032-03-23", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "178": {"day": "Tuesday", "date": "2032-04-06", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "179": {"day": "Tuesday", "date": "2032-04-20", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "180": {"day": "Tuesday", "date": "2032-05-04", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "181": {"day": "Tuesday", "date": "2032-05-18", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "182": {"day": "Tuesday", "date": "2032-06-01", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "183": {"day": "Tuesday", "date": "2032-06-15", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "184": {"day": "Tuesday", "date": "2032-06-29", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "185": {"day": "Tuesday", "date": "2032-07-13", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "186": {"day": "Tuesday", "date": "2032-07-27", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "187": {"day": "Tuesday", "date": "2032-08-10", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "188": {"day": "Tuesday", "date": "2032-08-24", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "189": {"day": "Tuesday", "date": "2032-09-07", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "190": {"day": "Tuesday", "date": "2032-09-21", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "191": {"day": "Tuesday", "date": "2032-10-05", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "192": {"day": "Tuesday", "date": "2032-10-19", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "193": {"day": "Tuesday", "date": "2032-11-02", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "194": {"day": "Tuesday", "date": "2032-11-16", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "195": {"day": "Tuesday", "date": "2032-11-30", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "196": {"day": "Tuesday", "date": "2032-12-14", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "197": {"day": "Tuesday", "date": "2032-12-28", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "198": {"day": "Tuesday", "date": "2033-01-11", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "199": {"day": "Tuesday", "date": "2033-01-25", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "200": {"day": "Tuesday", "date": "2033-02-08", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "201": {"day": "Tuesday", "date": "2033-02-22", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "202": {"day": "Tuesday", "date": "2033-03-08", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "203": {"day": "Tuesday", "date": "2033-03-22", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "204": {"day": "Tuesday", "date": "2033-04-05", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "205": {"day": "Tuesday", "date": "2033-04-19", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "206": {"day": "Tuesday", "date": "2033-05-03", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "207": {"day": "Tuesday", "date": "2033-05-17", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "208": {"day": "Tuesday", "date": "2033-05-31", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "209": {"day": "Tuesday", "date": "2033-06-14", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "210": {"day": "Tuesday", "date": "2033-06-28", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "211": {"day": "Tuesday", "date": "2033-07-12", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "212": {"day": "Tuesday", "date": "2033-07-26", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "213": {"day": "Tuesday", "date": "2033-08-09", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "214": {"day": "Tuesday", "date": "2033-08-23", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "215": {"day": "Tuesday", "date": "2033-09-06", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "216": {"day": "Tuesday", "date": "2033-09-20", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "217": {"day": "Tuesday", "date": "2033-10-04", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "218": {"day": "Tuesday", "date": "2033-10-18", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "219": {"day": "Tuesday", "date": "2033-11-01", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "220": {"day": "Tuesday", "date": "2033-11-15", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "221": {"day": "Tuesday", "date": "2033-11-29", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "222": {"day": "Tuesday", "date": "2033-12-13", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "223": {"day": "Tuesday", "date": "2033-12-27", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "224": {"day": "Tuesday", "date": "2034-01-10", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "225": {"day": "Tuesday", "date": "2034-01-24", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "226": {"day": "Tuesday", "date": "2034-02-07", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "227": {"day": "Tuesday", "date": "2034-02-21", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "228": {"day": "Tuesday", "date": "2034-03-07", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "229": {"day": "Tuesday", "date": "2034-03-21", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "230": {"day": "Tuesday", "date": "2034-04-04", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "231": {"day": "Tuesday", "date": "2034-04-18", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "232": {"day": "Tuesday", "date": "2034-05-02", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "233": {"day": "Tuesday", "date": "2034-05-16", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "234": {"day": "Tuesday", "date": "2034-05-30", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "235": {"day": "Tuesday", "date": "2034-06-13", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "236": {"day": "Tuesday", "date": "2034-06-27", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "237": {"day": "Tuesday", "date": "2034-07-11", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "238": {"day": "Tuesday", "date": "2034-07-25", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "239": {"day": "Tuesday", "date": "2034-08-08", "type": "class", "notes": "", "end_time": "10:30", "start_time": "10:00"}, "days": ["Tuesday"], "pattern": "biweekly", "end_date": "2034-08-09", "end_time": "10:30", "start_date": "2025-06-10", "start_time": "10:00", "total_hours": "120", "exception_dates": [{"date": "2025-08-12", "reason": "Other"}], "holiday_overrides": []}	[{"stop_date": "2025-08-19", "restart_date": "2025-08-26"}]	[]	1	2025-06-10	[]	\N	[]	\N
30	14	Barloworld - Northern Branch, 10 Northern Ave, Johannesburg, 2001	GETC	2025-06-09	t	BANKSETA	t	Open Book Exam	1	2025-06-04 17:41:53	2025-06-04 17:41:53	\N	SMME4	14-GETC-SMME4-2025-06-04-19-39	60	1	[{"id": 1, "name": "John J.M. Smith", "level": "", "status": "Host Company Learner"}, {"id": 2, "name": "Nosipho N. Dlamini", "level": "", "status": "Host Company Learner"}, {"id": 3, "name": "Ahmed A. Patel", "level": "", "status": "Host Company Learner"}, {"id": 35, "name": "Peter P.J. Wessels", "level": "", "status": "Host Company Learner"}, {"id": 36, "name": "Peter 2 P.J. Wessels2", "level": "", "status": "Host Company Learner"}]	[9]	{"0": {"day": "Monday", "date": "2025-06-09", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "1": {"day": "Monday", "date": "2025-06-23", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "2": {"day": "Monday", "date": "2025-07-07", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "3": {"day": "Monday", "date": "2025-07-21", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "4": {"day": "Monday", "date": "2025-08-04", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "5": {"day": "Monday", "date": "2025-08-18", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "6": {"day": "Monday", "date": "2025-09-01", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "7": {"day": "Monday", "date": "2025-09-15", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "8": {"day": "Monday", "date": "2025-09-29", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "9": {"day": "Monday", "date": "2025-10-13", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "10": {"day": "Monday", "date": "2025-10-27", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "11": {"day": "Monday", "date": "2025-11-10", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "12": {"day": "Monday", "date": "2025-11-24", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "13": {"day": "Monday", "date": "2025-12-08", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "14": {"day": "Monday", "date": "2025-12-22", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "15": {"day": "Monday", "date": "2026-01-05", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "16": {"day": "Monday", "date": "2026-01-19", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "17": {"day": "Monday", "date": "2026-02-02", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "18": {"day": "Monday", "date": "2026-02-16", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "19": {"day": "Monday", "date": "2026-03-02", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "20": {"day": "Monday", "date": "2026-03-16", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "21": {"day": "Monday", "date": "2026-03-30", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "22": {"day": "Monday", "date": "2026-04-13", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "23": {"day": "Monday", "date": "2026-05-11", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "24": {"day": "Monday", "date": "2026-05-25", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "25": {"day": "Monday", "date": "2026-06-08", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "26": {"day": "Monday", "date": "2026-06-22", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "27": {"day": "Monday", "date": "2026-07-06", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "28": {"day": "Monday", "date": "2026-07-20", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "29": {"day": "Monday", "date": "2026-08-03", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "30": {"day": "Monday", "date": "2026-08-17", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "31": {"day": "Monday", "date": "2026-08-31", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "32": {"day": "Monday", "date": "2026-09-14", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "33": {"day": "Monday", "date": "2026-09-28", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "34": {"day": "Monday", "date": "2026-10-12", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "35": {"day": "Monday", "date": "2026-10-26", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "36": {"day": "Monday", "date": "2026-11-09", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "37": {"day": "Monday", "date": "2026-11-23", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "38": {"day": "Monday", "date": "2026-12-07", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "39": {"day": "Monday", "date": "2026-12-21", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "40": {"day": "Monday", "date": "2027-01-04", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "41": {"day": "Monday", "date": "2027-01-18", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "42": {"day": "Monday", "date": "2027-02-01", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "43": {"day": "Monday", "date": "2027-02-15", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "44": {"day": "Monday", "date": "2027-03-01", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "45": {"day": "Monday", "date": "2027-03-15", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "46": {"day": "Monday", "date": "2027-03-29", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "47": {"day": "Monday", "date": "2027-04-12", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "48": {"day": "Monday", "date": "2027-04-26", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "49": {"day": "Monday", "date": "2027-05-10", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "50": {"day": "Monday", "date": "2027-05-24", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "51": {"day": "Monday", "date": "2027-06-07", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "52": {"day": "Monday", "date": "2027-06-21", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "53": {"day": "Monday", "date": "2027-07-05", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "54": {"day": "Monday", "date": "2027-07-19", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "55": {"day": "Monday", "date": "2027-08-02", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "56": {"day": "Monday", "date": "2027-08-16", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "57": {"day": "Monday", "date": "2027-08-30", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "58": {"day": "Monday", "date": "2027-09-13", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "59": {"day": "Monday", "date": "2027-09-27", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "60": {"day": "Monday", "date": "2027-10-11", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "61": {"day": "Monday", "date": "2027-10-25", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "62": {"day": "Monday", "date": "2027-11-08", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "63": {"day": "Monday", "date": "2027-11-22", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "64": {"day": "Monday", "date": "2027-12-06", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "65": {"day": "Monday", "date": "2027-12-20", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "66": {"day": "Monday", "date": "2028-01-03", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "67": {"day": "Monday", "date": "2028-01-17", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "68": {"day": "Monday", "date": "2028-01-31", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "69": {"day": "Monday", "date": "2028-02-14", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "70": {"day": "Monday", "date": "2028-02-28", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "71": {"day": "Monday", "date": "2028-03-13", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "72": {"day": "Monday", "date": "2028-03-27", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "73": {"day": "Monday", "date": "2028-04-10", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "74": {"day": "Monday", "date": "2028-04-24", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "75": {"day": "Monday", "date": "2028-05-08", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "76": {"day": "Monday", "date": "2028-05-22", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "77": {"day": "Monday", "date": "2028-06-05", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "78": {"day": "Monday", "date": "2028-06-19", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "79": {"day": "Monday", "date": "2028-07-03", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "80": {"day": "Monday", "date": "2028-07-17", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "81": {"day": "Monday", "date": "2028-07-31", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "82": {"day": "Monday", "date": "2028-08-14", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "83": {"day": "Monday", "date": "2028-08-28", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "84": {"day": "Monday", "date": "2028-09-11", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "85": {"day": "Monday", "date": "2028-09-25", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "86": {"day": "Monday", "date": "2028-10-09", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "87": {"day": "Monday", "date": "2028-10-23", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "88": {"day": "Monday", "date": "2028-11-06", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "89": {"day": "Monday", "date": "2028-11-20", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "90": {"day": "Monday", "date": "2028-12-04", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "91": {"day": "Monday", "date": "2028-12-18", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "92": {"day": "Monday", "date": "2029-01-01", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "93": {"day": "Monday", "date": "2029-01-15", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "94": {"day": "Monday", "date": "2029-01-29", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "95": {"day": "Monday", "date": "2029-02-12", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "96": {"day": "Monday", "date": "2029-02-26", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "97": {"day": "Monday", "date": "2029-03-12", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "98": {"day": "Monday", "date": "2029-03-26", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "99": {"day": "Monday", "date": "2029-04-09", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "100": {"day": "Monday", "date": "2029-04-23", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "101": {"day": "Monday", "date": "2029-05-07", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "102": {"day": "Monday", "date": "2029-05-21", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "103": {"day": "Monday", "date": "2029-06-04", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "104": {"day": "Monday", "date": "2029-06-18", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "105": {"day": "Monday", "date": "2029-07-02", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "106": {"day": "Monday", "date": "2029-07-16", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "107": {"day": "Monday", "date": "2029-07-30", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "108": {"day": "Monday", "date": "2029-08-13", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "109": {"day": "Monday", "date": "2029-08-27", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "110": {"day": "Monday", "date": "2029-09-10", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "111": {"day": "Monday", "date": "2029-09-24", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "112": {"day": "Monday", "date": "2029-10-08", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "113": {"day": "Monday", "date": "2029-10-22", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "114": {"day": "Monday", "date": "2029-11-05", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "115": {"day": "Monday", "date": "2029-11-19", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "116": {"day": "Monday", "date": "2029-12-03", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "117": {"day": "Monday", "date": "2029-12-17", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "118": {"day": "Monday", "date": "2029-12-31", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "119": {"day": "Monday", "date": "2030-01-14", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "120": {"day": "Monday", "date": "2030-01-28", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "121": {"day": "Monday", "date": "2030-02-11", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "122": {"day": "Monday", "date": "2030-02-25", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "123": {"day": "Monday", "date": "2030-03-11", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "124": {"day": "Monday", "date": "2030-03-25", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "125": {"day": "Monday", "date": "2030-04-08", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "126": {"day": "Monday", "date": "2030-04-22", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "127": {"day": "Monday", "date": "2030-05-06", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "128": {"day": "Monday", "date": "2030-05-20", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "129": {"day": "Monday", "date": "2030-06-03", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "130": {"day": "Monday", "date": "2030-06-17", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "131": {"day": "Monday", "date": "2030-07-01", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "132": {"day": "Monday", "date": "2030-07-15", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "133": {"day": "Monday", "date": "2030-07-29", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "134": {"day": "Monday", "date": "2030-08-12", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "135": {"day": "Monday", "date": "2030-08-26", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "136": {"day": "Monday", "date": "2030-09-09", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "137": {"day": "Monday", "date": "2030-09-23", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "138": {"day": "Monday", "date": "2030-10-07", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "139": {"day": "Monday", "date": "2030-10-21", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "140": {"day": "Monday", "date": "2030-11-04", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "141": {"day": "Monday", "date": "2030-11-18", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "142": {"day": "Monday", "date": "2030-12-02", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "143": {"day": "Monday", "date": "2030-12-16", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "144": {"day": "Monday", "date": "2030-12-30", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "145": {"day": "Monday", "date": "2031-01-13", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "146": {"day": "Monday", "date": "2031-01-27", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "147": {"day": "Monday", "date": "2031-02-10", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "148": {"day": "Monday", "date": "2031-02-24", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "149": {"day": "Monday", "date": "2031-03-10", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "150": {"day": "Monday", "date": "2031-03-24", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "151": {"day": "Monday", "date": "2031-04-07", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "152": {"day": "Monday", "date": "2031-04-21", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "153": {"day": "Monday", "date": "2031-05-05", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "154": {"day": "Monday", "date": "2031-05-19", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "155": {"day": "Monday", "date": "2031-06-02", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "156": {"day": "Monday", "date": "2031-06-16", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "157": {"day": "Monday", "date": "2031-06-30", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "158": {"day": "Monday", "date": "2031-07-14", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "159": {"day": "Monday", "date": "2031-07-28", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "160": {"day": "Monday", "date": "2031-08-11", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "161": {"day": "Monday", "date": "2031-08-25", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "162": {"day": "Monday", "date": "2031-09-08", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "163": {"day": "Monday", "date": "2031-09-22", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "164": {"day": "Monday", "date": "2031-10-06", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "165": {"day": "Monday", "date": "2031-10-20", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "166": {"day": "Monday", "date": "2031-11-03", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "167": {"day": "Monday", "date": "2031-11-17", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "168": {"day": "Monday", "date": "2031-12-01", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "169": {"day": "Monday", "date": "2031-12-15", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "170": {"day": "Monday", "date": "2031-12-29", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "171": {"day": "Monday", "date": "2032-01-12", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "172": {"day": "Monday", "date": "2032-01-26", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "173": {"day": "Monday", "date": "2032-02-09", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "174": {"day": "Monday", "date": "2032-02-23", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "175": {"day": "Monday", "date": "2032-03-08", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "176": {"day": "Monday", "date": "2032-03-22", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "177": {"day": "Monday", "date": "2032-04-05", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "178": {"day": "Monday", "date": "2032-04-19", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "179": {"day": "Monday", "date": "2032-05-03", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "180": {"day": "Monday", "date": "2032-05-17", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "181": {"day": "Monday", "date": "2032-05-31", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "182": {"day": "Monday", "date": "2032-06-14", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "183": {"day": "Monday", "date": "2032-06-28", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "184": {"day": "Monday", "date": "2032-07-12", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "185": {"day": "Monday", "date": "2032-07-26", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "186": {"day": "Monday", "date": "2032-08-09", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "187": {"day": "Monday", "date": "2032-08-23", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "188": {"day": "Monday", "date": "2032-09-06", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "189": {"day": "Monday", "date": "2032-09-20", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "190": {"day": "Monday", "date": "2032-10-04", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "191": {"day": "Monday", "date": "2032-10-18", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "192": {"day": "Monday", "date": "2032-11-01", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "193": {"day": "Monday", "date": "2032-11-15", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "194": {"day": "Monday", "date": "2032-11-29", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "195": {"day": "Monday", "date": "2032-12-13", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "196": {"day": "Monday", "date": "2032-12-27", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "197": {"day": "Monday", "date": "2033-01-10", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "198": {"day": "Monday", "date": "2033-01-24", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "199": {"day": "Monday", "date": "2033-02-07", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "200": {"day": "Monday", "date": "2033-02-21", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "201": {"day": "Monday", "date": "2033-03-07", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "202": {"day": "Monday", "date": "2033-03-21", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "203": {"day": "Monday", "date": "2033-04-04", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "204": {"day": "Monday", "date": "2033-04-18", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "205": {"day": "Monday", "date": "2033-05-02", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "206": {"day": "Monday", "date": "2033-05-16", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "207": {"day": "Monday", "date": "2033-05-30", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "208": {"day": "Monday", "date": "2033-06-13", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "209": {"day": "Monday", "date": "2033-06-27", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "210": {"day": "Monday", "date": "2033-07-11", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "211": {"day": "Monday", "date": "2033-07-25", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "212": {"day": "Monday", "date": "2033-08-08", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "213": {"day": "Monday", "date": "2033-08-22", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "214": {"day": "Monday", "date": "2033-09-05", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "215": {"day": "Monday", "date": "2033-09-19", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "216": {"day": "Monday", "date": "2033-10-03", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "217": {"day": "Monday", "date": "2033-10-17", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "218": {"day": "Monday", "date": "2033-10-31", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "219": {"day": "Monday", "date": "2033-11-14", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "220": {"day": "Monday", "date": "2033-11-28", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "221": {"day": "Monday", "date": "2033-12-12", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "222": {"day": "Monday", "date": "2033-12-26", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "223": {"day": "Monday", "date": "2034-01-09", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "224": {"day": "Monday", "date": "2034-01-23", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "225": {"day": "Monday", "date": "2034-02-06", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "226": {"day": "Monday", "date": "2034-02-20", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "227": {"day": "Monday", "date": "2034-03-06", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "228": {"day": "Monday", "date": "2034-03-20", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "229": {"day": "Monday", "date": "2034-04-03", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "230": {"day": "Monday", "date": "2034-04-17", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "231": {"day": "Monday", "date": "2034-05-01", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "232": {"day": "Monday", "date": "2034-05-15", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "233": {"day": "Monday", "date": "2034-05-29", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "234": {"day": "Monday", "date": "2034-06-12", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "235": {"day": "Monday", "date": "2034-06-26", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "236": {"day": "Monday", "date": "2034-07-10", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "237": {"day": "Monday", "date": "2034-07-24", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "238": {"day": "Monday", "date": "2034-08-07", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "239": {"day": "Monday", "date": "2034-08-21", "type": "class", "notes": "", "end_time": "09:30", "start_time": "09:00"}, "days": ["Monday"], "pattern": "biweekly", "end_date": "2034-08-22", "end_time": "09:30", "start_date": "2025-06-09", "start_time": "09:00", "total_hours": "120", "exception_dates": [{"date": "2025-08-11", "reason": "Other"}], "holiday_overrides": {"2025-06-16": {"date": "2025-06-16", "name": "Youth Day", "override": true}}}	[]	[]	1	2025-06-09	[]	\N	[]	\N
31	11	Aspen Pharmacare - Head Office, 100 Pharma Rd, Durban, 4001	AET	2025-06-09	t	BANKSETA	t	Open Book Exam	3	2025-06-04 17:52:43	2025-06-04 17:52:43	\N	COMM_NUM	11-AET-COMM_NUM-2025-06-04-19-50	240	1	[{"id": 1, "name": "John J.M. Smith", "level": "", "status": "Host Company Learner"}, {"id": 2, "name": "Nosipho N. Dlamini", "level": "", "status": "Host Company Learner"}, {"id": 3, "name": "Ahmed A. Patel", "level": "", "status": "Host Company Learner"}, {"id": 4, "name": "Lerato L. Moloi", "level": "", "status": "Host Company Learner"}, {"id": 23, "name": "Sibusiso eryery. Montgomery", "level": "", "status": "Host Company Learner"}]	[9]	{"0": {"day": "Monday", "date": "2025-06-09", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "1": {"day": "Tuesday", "date": "2025-06-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "2": {"day": "Monday", "date": "2025-06-16", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "3": {"day": "Tuesday", "date": "2025-06-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "4": {"day": "Monday", "date": "2025-06-23", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "5": {"day": "Tuesday", "date": "2025-06-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "6": {"day": "Monday", "date": "2025-06-30", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "7": {"day": "Tuesday", "date": "2025-07-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "8": {"day": "Monday", "date": "2025-07-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "9": {"day": "Tuesday", "date": "2025-07-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "10": {"day": "Tuesday", "date": "2025-07-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "11": {"day": "Monday", "date": "2025-07-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "12": {"day": "Tuesday", "date": "2025-07-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "13": {"day": "Monday", "date": "2025-07-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "14": {"day": "Tuesday", "date": "2025-07-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "15": {"day": "Monday", "date": "2025-08-04", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "16": {"day": "Tuesday", "date": "2025-08-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "17": {"day": "Monday", "date": "2025-08-11", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "18": {"day": "Tuesday", "date": "2025-08-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "19": {"day": "Monday", "date": "2025-08-18", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "20": {"day": "Tuesday", "date": "2025-08-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "21": {"day": "Monday", "date": "2025-08-25", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "22": {"day": "Tuesday", "date": "2025-08-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "23": {"day": "Monday", "date": "2025-09-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "24": {"day": "Tuesday", "date": "2025-09-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "25": {"day": "Monday", "date": "2025-09-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "26": {"day": "Tuesday", "date": "2025-09-09", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "27": {"day": "Monday", "date": "2025-09-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "28": {"day": "Tuesday", "date": "2025-09-16", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "29": {"day": "Monday", "date": "2025-09-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "30": {"day": "Tuesday", "date": "2025-09-23", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "31": {"day": "Monday", "date": "2025-09-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "32": {"day": "Tuesday", "date": "2025-09-30", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "33": {"day": "Monday", "date": "2025-10-06", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "34": {"day": "Tuesday", "date": "2025-10-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "35": {"day": "Monday", "date": "2025-10-13", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "36": {"day": "Tuesday", "date": "2025-10-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "37": {"day": "Monday", "date": "2025-10-20", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "38": {"day": "Tuesday", "date": "2025-10-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "39": {"day": "Monday", "date": "2025-10-27", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "40": {"day": "Tuesday", "date": "2025-10-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "41": {"day": "Monday", "date": "2025-11-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "42": {"day": "Tuesday", "date": "2025-11-04", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "43": {"day": "Monday", "date": "2025-11-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "44": {"day": "Tuesday", "date": "2025-11-11", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "45": {"day": "Monday", "date": "2025-11-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "46": {"day": "Tuesday", "date": "2025-11-18", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "47": {"day": "Monday", "date": "2025-11-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "48": {"day": "Tuesday", "date": "2025-11-25", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "49": {"day": "Monday", "date": "2025-12-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "50": {"day": "Tuesday", "date": "2025-12-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "51": {"day": "Monday", "date": "2025-12-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "52": {"day": "Tuesday", "date": "2025-12-09", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "53": {"day": "Monday", "date": "2025-12-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "54": {"day": "Tuesday", "date": "2025-12-16", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "55": {"day": "Monday", "date": "2025-12-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "56": {"day": "Tuesday", "date": "2025-12-23", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "57": {"day": "Monday", "date": "2025-12-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "58": {"day": "Tuesday", "date": "2025-12-30", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "59": {"day": "Monday", "date": "2026-01-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "60": {"day": "Tuesday", "date": "2026-01-06", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "61": {"day": "Monday", "date": "2026-01-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "62": {"day": "Tuesday", "date": "2026-01-13", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "63": {"day": "Monday", "date": "2026-01-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "64": {"day": "Tuesday", "date": "2026-01-20", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "65": {"day": "Monday", "date": "2026-01-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "66": {"day": "Tuesday", "date": "2026-01-27", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "67": {"day": "Monday", "date": "2026-02-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "68": {"day": "Tuesday", "date": "2026-02-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "69": {"day": "Monday", "date": "2026-02-09", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "70": {"day": "Tuesday", "date": "2026-02-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "71": {"day": "Monday", "date": "2026-02-16", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "72": {"day": "Tuesday", "date": "2026-02-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "73": {"day": "Monday", "date": "2026-02-23", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "74": {"day": "Tuesday", "date": "2026-02-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "75": {"day": "Monday", "date": "2026-03-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "76": {"day": "Tuesday", "date": "2026-03-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "77": {"day": "Monday", "date": "2026-03-09", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "78": {"day": "Tuesday", "date": "2026-03-10", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "79": {"day": "Monday", "date": "2026-03-16", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "80": {"day": "Tuesday", "date": "2026-03-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "81": {"day": "Monday", "date": "2026-03-23", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "82": {"day": "Tuesday", "date": "2026-03-24", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "83": {"day": "Monday", "date": "2026-03-30", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "84": {"day": "Tuesday", "date": "2026-03-31", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "85": {"day": "Tuesday", "date": "2026-04-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "86": {"day": "Monday", "date": "2026-04-13", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "87": {"day": "Tuesday", "date": "2026-04-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "88": {"day": "Monday", "date": "2026-04-20", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "89": {"day": "Tuesday", "date": "2026-04-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "90": {"day": "Tuesday", "date": "2026-04-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "91": {"day": "Monday", "date": "2026-05-04", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "92": {"day": "Tuesday", "date": "2026-05-05", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "93": {"day": "Monday", "date": "2026-05-11", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "94": {"day": "Tuesday", "date": "2026-05-12", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "95": {"day": "Monday", "date": "2026-05-18", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "96": {"day": "Tuesday", "date": "2026-05-19", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "97": {"day": "Monday", "date": "2026-05-25", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "98": {"day": "Tuesday", "date": "2026-05-26", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "99": {"day": "Monday", "date": "2026-06-01", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "100": {"day": "Tuesday", "date": "2026-06-02", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "101": {"day": "Monday", "date": "2026-06-08", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "102": {"day": "Tuesday", "date": "2026-06-09", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "103": {"day": "Monday", "date": "2026-06-15", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "104": {"day": "Monday", "date": "2026-06-22", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "105": {"day": "Tuesday", "date": "2026-06-23", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "106": {"day": "Monday", "date": "2026-06-29", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "107": {"day": "Tuesday", "date": "2026-06-30", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "108": {"day": "Monday", "date": "2026-07-06", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "109": {"day": "Tuesday", "date": "2026-07-07", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "110": {"day": "Monday", "date": "2026-07-13", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "111": {"day": "Tuesday", "date": "2026-07-14", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "112": {"day": "Monday", "date": "2026-07-20", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "113": {"day": "Tuesday", "date": "2026-07-21", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "114": {"day": "Monday", "date": "2026-07-27", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "115": {"day": "Tuesday", "date": "2026-07-28", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "116": {"day": "Monday", "date": "2026-08-03", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "117": {"day": "Tuesday", "date": "2026-08-04", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "118": {"day": "Tuesday", "date": "2026-08-11", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "119": {"day": "Monday", "date": "2026-08-17", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "120": {"day": "Tuesday", "date": "2026-08-18", "type": "class", "notes": "", "end_time": "07:00", "start_time": "06:00"}, "days": ["Monday", "Tuesday"], "pattern": "weekly", "end_date": "2026-08-18", "end_time": "07:00", "start_date": "2025-06-09", "start_time": "06:00", "total_hours": "120", "exception_dates": [{"date": "2025-07-14", "reason": "Other"}], "holiday_overrides": {"2025-06-16": {"date": "2025-06-16", "name": "Youth Day", "override": true}, "2025-12-16": {"date": "2025-12-16", "name": "Day of Reconciliation", "override": true}}}	[]	[]	1	2025-06-09	[]	\N	[]	\N
34	2	35346 South Drive, Mayfair, 2100	AET	2025-06-30	t	CHIETA	t	Open Book Exam	1	2025-06-30 11:09:39	2025-06-30 11:09:39	23	COMM_NUM	2-AET-COMM_NUM-2025-06-30-13-07	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "", "agent_id": 10}]	{"endDate": "", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-06-30T11:09:38+00:00", "validatedAt": "2025-06-30T11:09:38+00:00"}, "timeData": {"mode": "single"}, "startDate": "", "dayOfMonth": null, "selectedDays": [], "exceptionDates": [], "holidayOverrides": []}	[{"stop_date": "2025-09-15", "restart_date": "2025-09-22"}]	[]	1	2025-06-30	[{"id": "1", "name": "John Doe"}, {"id": "2", "name": "Jane Smith"}]	\N	[]	\N
35	2	35346 South Drive, Mayfair, 2100	AET	2025-06-30	t	CHIETA	t	Open Book Exam	2	2025-06-30 17:34:38	2025-06-30 17:34:38	23	COMM_NUM	2-AET-COMM_NUM-2025-06-30-19-32	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "5", "name": "David Brown", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-06-30", "agent_id": 7}]	{"endDate": "", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-06-30T17:34:36+00:00", "validatedAt": "2025-06-30T17:34:36+00:00"}, "timeData": {"mode": "single"}, "startDate": "", "dayOfMonth": null, "selectedDays": [], "exceptionDates": [], "holidayOverrides": []}	[{"stop_date": "2025-09-08", "restart_date": "2025-09-15"}]	[]	1	2025-06-30	[{"id": "1", "name": "John Doe"}, {"id": "5", "name": "David Brown"}]	\N	[]	\N
36	2	35346 South Drive, Mayfair, 2100	AET	2025-07-07	t	BANKSETA	t	Open Book Exam	4	2025-07-01 12:43:11	2025-07-01 12:43:11	23	COMM_NUM	2-AET-COMM_NUM-2025-07-01-14-40	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-07", "agent_id": 9}]	{"endDate": "", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-01T12:43:09+00:00", "validatedAt": "2025-07-01T12:43:09+00:00"}, "timeData": {"mode": "single"}, "startDate": "", "dayOfMonth": null, "selectedDays": [], "exceptionDates": [], "holidayOverrides": []}	[{"stop_date": "2025-08-04", "restart_date": "2025-08-11"}]	[]	2	2025-07-07	[{"id": "2", "name": "Jane Smith"}, {"id": "3", "name": "Mike Johnson"}]	\N	[]	\N
37	2	35346 South Drive, Mayfair, 2100	AET	2025-07-07	t	CHIETA	t	Open Book Exam	4	2025-07-01 12:57:57	2025-07-01 12:57:57	23	COMM_NUM	2-AET-COMM_NUM-2025-07-01-14-55	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-07", "agent_id": 9}]	{"endDate": "2025-07-26", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-01T12:57:56+00:00", "validatedAt": "2025-07-01T12:57:56+00:00"}, "timeData": {"mode": "single"}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Monday", "Tuesday", "Wednesday", "Thursday"], "exceptionDates": [{"date": "2025-07-07", "reason": "Client Cancelled"}], "holidayOverrides": []}	[{"stop_date": "2025-07-14", "restart_date": "2025-07-19"}]	[]	2	2025-07-07	[{"id": "1", "name": "John Doe"}, {"id": "4", "name": "Sarah Wilson"}]	\N	[]	\N
38	5	66 Kalahari Drive, Bloemfontein East, 2356	AET	2025-07-07	t	FOODBEV	t	Open Book Exam	5	2025-07-01 13:18:50	2025-07-01 13:18:50	21	COMM_NUM	5-AET-COMM_NUM-2025-07-01-15-15	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-07", "agent_id": 15}]	{"endDate": "2025-07-26", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-01T13:18:49+00:00", "validatedAt": "2025-07-01T13:18:49+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "1.00", "end_time": "07:00", "start_time": "06:00"}, "Wednesday": {"duration": "0.50", "end_time": "07:30", "start_time": "07:00"}}}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-07-14", "reason": "Client Cancelled"}], "holidayOverrides": []}	[{"stop_date": "2025-07-21", "restart_date": "2025-07-28"}]	[]	3	2025-07-07	[{"id": "1", "name": "John Doe"}, {"id": "4", "name": "Sarah Wilson"}]	\N	[]	\N
39	2	500 Science Park, Randburg, 2194	AET	2025-07-08	t	HWSETA	t	Open Book Exam	5	2025-07-01 13:43:01	2025-07-01 13:43:01	8	COMM_NUM	2-AET-COMM_NUM-2025-07-01-15-41	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}, {"id": "5", "name": "David Brown", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-08", "agent_id": 12}]	{"endDate": "2027-11-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-01T13:43:00+00:00", "validatedAt": "2025-07-01T13:43:00+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "0.50", "end_time": "06:30", "start_time": "06:00"}, "Wednesday": {"duration": "0.50", "end_time": "07:30", "start_time": "07:00"}}}, "startDate": "2025-07-08", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-07-14", "reason": "Client Cancelled"}], "holidayOverrides": {"2025-09-24": true}}	[{"stop_date": "2025-07-28", "restart_date": "2025-07-31"}]	[]	7	2025-07-08	[{"id": "3", "name": "Mike Johnson"}, {"id": "4", "name": "Sarah Wilson"}]	\N	[]	\N
40	2	35346 South Drive, Mayfair, 2100	AET	2025-07-07	t	CHIETA	t	Open Book Exam	1	2025-07-02 14:10:34	2025-07-02 14:10:34	23	COMM_NUM	2-AET-COMM_NUM-2025-07-02-16-08	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-07", "agent_id": 9}]	{"endDate": "2030-02-28", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-02T14:10:32+00:00", "validatedAt": "2025-07-02T14:10:32+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "0.50", "end_time": "06:30", "start_time": "06:00"}, "Wednesday": {"duration": "0.50", "end_time": "07:30", "start_time": "07:00"}}}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-07-14", "reason": "Client Cancelled"}], "holidayOverrides": {"2025-09-24": true}}	[{"stop_date": "2025-09-08", "restart_date": "2025-09-17"}]	[]	3	2025-07-07	[{"id": "1", "name": "John Doe"}, {"id": "3", "name": "Mike Johnson"}]	\N	[]	\N
41	2	35346 South Drive, Mayfair, 2100	AET	2025-07-07	t	BANKSETA	t	Open Book Exam	2	2025-07-02 14:58:13	2025-07-02 14:58:13	23	COMM_NUM	2-AET-COMM_NUM-2025-07-02-16-54	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-07", "agent_id": 9}]	{"endDate": "2030-02-25", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-02T14:58:11+00:00", "validatedAt": "2025-07-02T14:58:11+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "0.50", "end_time": "06:30", "start_time": "06:00"}, "Wednesday": {"duration": "0.50", "end_time": "07:30", "start_time": "07:00"}}}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-08-11", "reason": "Client Cancelled"}], "holidayOverrides": {"2025-09-24": true}}	[{"stop_date": "2025-09-15", "restart_date": "2025-09-22"}]	[]	3	2025-07-07	[{"id": "1", "name": "John Doe"}, {"id": "2", "name": "Jane Smith"}]	\N	[]	\N
42	2	35346 South Drive, Mayfair, 2100	AET	2025-07-07	t	CHIETA	t		2	2025-07-02 15:27:34	2025-07-02 15:27:34	23	COMM_NUM	2-AET-COMM_NUM-2025-07-02-17-24	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-07", "agent_id": 9}]	{"endDate": "2030-02-25", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-02T15:27:32+00:00", "validatedAt": "2025-07-02T15:27:32+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "0.50", "end_time": "06:30", "start_time": "06:00"}, "Wednesday": {"duration": "0.50", "end_time": "07:30", "start_time": "07:00"}}}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-07-21", "reason": "Client Cancelled"}], "holidayOverrides": {"2025-09-24": true}}	[{"stop_date": "2025-09-15", "restart_date": "2025-09-22"}]	[]	2	2025-07-07	[{"id": "1", "name": "John Doe"}, {"id": "3", "name": "Mike Johnson"}]	\N	[]	\N
43	2	35346 South Drive, Mayfair, 2100	AET	2025-07-07	t	BANKSETA	t		2	2025-07-02 15:51:48	2025-07-02 15:51:48	23	COMM_NUM	2-AET-COMM_NUM-2025-07-02-17-48	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-07", "agent_id": 9}]	{"endDate": "2030-02-18", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-02T15:51:47+00:00", "validatedAt": "2025-07-02T15:51:47+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "0.50", "end_time": "06:30", "start_time": "06:00"}, "Wednesday": {"duration": "0.50", "end_time": "07:30", "start_time": "07:00"}}}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-08-11", "reason": "Client Cancelled"}], "holidayOverrides": []}	[]	[]	3	2025-07-07	[{"id": "1", "name": "John Doe"}, {"id": "4", "name": "Sarah Wilson"}]	\N	[]	\N
44	2	35346 South Drive, Mayfair, 2100	AET	2025-07-07	t	CHIETA	t	Open Book Exam	2	2025-07-02 16:08:00	2025-07-02 16:08:00	23	COMM_NUM	2-AET-COMM_NUM-2025-07-02-18-06	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-07", "agent_id": 9}]	{"endDate": "2030-02-18", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-02T16:07:58+00:00", "validatedAt": "2025-07-02T16:07:58+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "0.50", "end_time": "06:30", "start_time": "06:00"}, "Wednesday": {"duration": "0.50", "end_time": "07:30", "start_time": "07:00"}}}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-09-15", "reason": "Client Cancelled"}], "holidayOverrides": []}	[]	[]	3	2025-07-07	[{"id": "1", "name": "John Doe"}, {"id": "3", "name": "Mike Johnson"}]	\N	[]	\N
45	2	35346 South Drive, Mayfair, 2100	AET	2025-07-07	t	CHIETA	t	Open Book Exam	3	2025-07-02 16:43:35	2025-07-02 16:43:35	23	COMM_NUM	2-AET-COMM_NUM-2025-07-02-18-40	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[]	{"endDate": "2026-06-22", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-02T16:43:34+00:00", "validatedAt": "2025-07-02T16:43:34+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "2.50", "end_time": "08:30", "start_time": "06:00"}, "Wednesday": {"duration": "2.50", "end_time": "09:30", "start_time": "07:00"}}}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-07-21", "reason": "Client Cancelled"}], "holidayOverrides": {"2025-09-24": true}}	[{"stop_date": "2025-08-11", "restart_date": "2025-08-20"}]	[]	3	2025-07-07	[{"id": "1", "name": "John Doe"}, {"id": "3", "name": "Mike Johnson"}]	\N	[]	\N
46	2	35346 South Drive, Mayfair, 2100	AET	2025-07-07	f		f		2	2025-07-02 17:57:27	2025-07-02 17:57:27	23	COMM_NUM	2-AET-COMM_NUM-2025-07-02-19-56	240	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}, {"id": "5", "name": "David Brown", "level": "", "status": "CIC - Currently in Class"}]	[]	{"endDate": "2026-06-22", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-02T17:57:26+00:00", "validatedAt": "2025-07-02T17:57:26+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "2.50", "end_time": "08:30", "start_time": "06:00"}, "Wednesday": {"duration": "2.50", "end_time": "09:30", "start_time": "07:00"}}}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-07-21", "reason": "Other"}], "holidayOverrides": {"2025-09-24": true}}	[{"stop_date": "2025-08-11", "restart_date": "2025-08-20"}]	[]	10	2025-07-07	[]	\N	[]	\N
47	5	1200 Silicon Avenue, Cape Town, 8001	GETC	2025-07-07	t	HWSETA	t	Open Book Exam	4	2025-07-03 08:10:27	2025-07-03 08:10:27	15	CL4	5-GETC-CL4-2025-07-03-10-07	120	\N	[{"id": "1", "name": "John Doe", "level": "", "status": "CIC - Currently in Class"}, {"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-07", "agent_id": 9}]	{"endDate": "2027-04-13", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-07-03T08:10:25+00:00", "validatedAt": "2025-07-03T08:10:25+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"0": {"start_time": "06:00"}, "1": {"end_time": "06:30"}, "2": {"duration": "0.50"}, "Tuesday": {"duration": "0.50", "end_time": "06:30", "start_time": "06:00"}, "Wednesday": {"duration": "1.00", "end_time": "07:00", "start_time": "06:00"}}}, "startDate": "2025-07-07", "dayOfMonth": null, "selectedDays": ["Tuesday", "Wednesday"], "exceptionDates": [{"date": "2025-07-22", "reason": "Other"}, {"date": "2025-07-23", "reason": "Other"}], "holidayOverrides": {"2025-09-24": true}}	[{"stop_date": "2025-07-14", "restart_date": "2025-07-16"}]	[]	3	2025-07-07	[{"id": "3", "name": "Mike Johnson"}, {"id": "4", "name": "Sarah Wilson"}]	\N	[]	\N
54	5	66 Kalahari Drive, Bloemfontein East, 2356	AET	2025-10-23	f		f		2	2025-10-02 05:56:03	2025-10-22 12:35:51.964222	20	COMM_NUM	5-AET-COMM_NUM-2025-10-02-07-51	240	\N	[{"id": "1", "name": "John Doe", "level": "CL4", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "NL4", "status": "CIC - Currently in Class"}, {"id": "5", "name": "David Brown", "level": "", "status": "CIC - Currently in Class"}]	[]	{"endDate": "2027-01-01", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-10-22T10:32:14+00:00", "validatedAt": "2025-10-22T10:32:14+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Friday": {"duration": "2.00", "end_time": "10:00", "start_time": "08:00"}, "Wednesday": {"duration": "2.00", "end_time": "10:00", "start_time": "08:00"}}}, "startDate": "2025-10-23", "dayOfMonth": null, "selectedDays": ["Wednesday", "Friday"], "exceptionDates": [{"date": "2025-10-31", "reason": "Other"}, {"date": "2025-11-28", "reason": "Other"}, {"date": "2026-01-30", "reason": "Other"}], "holidayOverrides": {"2025-12-26": true}}	[{"stop_date": "2025-12-12", "restart_date": "2026-01-05"}]	[]	2	2025-10-06	[]	YDCOZA101	[]	\N
48	2	300 Corporate Avenue, Johannesburg, 2001	SKILL	2025-07-14	t	BANKSETA	t		4	2025-07-07 19:13:18	2025-09-26 12:29:52	6	WALK	2-SKILL-WALK-2025-07-07-21-10	120	\N	[{"id": "2", "name": "Jane Smith", "level": "", "status": "CIC - Currently in Class"}, {"id": "3", "name": "Mike Johnson", "level": "WALK", "status": "CIC - Currently in Class"}, {"id": "5", "name": "David Brown", "level": "SMME4", "status": "CIC - Currently in Class"}, {"id": "4", "name": "Sarah Wilson", "level": "CL4", "status": "CIC - Currently in Class"}]	[{"date": "2025-07-14", "agent_id": 13}]	{"endDate": "2030-02-27", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-09-26T12:29:49+00:00", "validatedAt": "2025-09-26T12:29:49+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "0.50", "end_time": "06:30", "start_time": "06:00"}}}, "startDate": "2025-07-14", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-07-28", "reason": "Other"}], "holidayOverrides": {"2025-09-24": true}}	[{"stop_date": "2025-08-11", "restart_date": "2025-08-20"}]	[{"id": "note_6873c79293436", "content": "Generate Ghanaian-style dummy content in one click.\\n\\nThis plugin helps you quickly populate your Figma designs with local names, emails, bios, and pixel avatars—perfect for prototyping profile cards, team sections, or any user-based UI.", "category": ["Poor attendance"], "priority": "low", "author_id": 1, "created_at": "2025-07-13T14:49:54+00:00", "updated_at": "2025-07-13T14:49:54+00:00", "attachments": [{"id": "280", "url": "http://localhost/wecoza/wp-content/uploads/wecoza-classes/2025/07/toaz.info-forex-ict-amp-mmm-notespdf-pr_62f4bf2dd422bf57eec6ef9f915e116d.pdf", "name": "toaz.info-forex-ict-amp-mmm-notespdf-pr_62f4bf2dd422bf57eec6ef9f915e116d.pdf", "size": "8594559", "type": "application/pdf"}]}, {"id": "note_6875084a75b38", "content": "The widening skills gap in this industry is due to rapid technological advancement which has outpaced traditional workplace training programmes, exacerbated by the growth in demand for e-commerce and shifting consumer preferences.", "category": ["Material shortage"], "priority": "high", "author_id": 1, "created_at": "2025-07-14T13:38:18+00:00", "updated_at": "2025-07-14T13:38:18+00:00", "attachments": []}, {"id": "note_6875086d78610", "content": "Emotional intelligence is another “soft” skill that is in very short supply in most industries. Emotionally intelligent supply chain professionals gain the trust and respect of team members, ensuring better collaboration and more successful outcomes across the supply chain.", "category": ["Learners unhappy", "Client unhappy"], "priority": "medium", "author_id": 1, "created_at": "2025-07-14T13:38:53+00:00", "updated_at": "2025-07-14T13:38:53+00:00", "attachments": []}, {"id": "note_6875094e6d579", "content": "AET classes are compulsory. Therefore, we have agreed to facilitate training at the workplace throughout the working day to ensure 100% participation.", "category": ["Agent Absent", "Venue issues"], "priority": "high", "author_id": 1, "created_at": "2025-07-14T13:42:38+00:00", "updated_at": "2025-07-14T13:42:38+00:00", "attachments": []}]	1	2025-07-14	[]	\N	[]	\N
55	5	6756, East Park, Parklands, 6756	AET	2025-11-04	t	FOODBEV	f		2	2025-10-03 05:38:40	2025-10-28 13:50:40	2	COMM_NUM	5-AET-COMM_NUM-2025-10-03-07-29	240	\N	[{"id": "29", "name": "John46 WQR Montgomery", "level": "", "status": "CIC - Currently in Class"}, {"id": "30", "name": "Koos2 WET Montgomery", "level": "", "status": "CIC - Currently in Class"}]	[]	{"endDate": "2027-01-20", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2025-10-28T13:50:38+00:00", "validatedAt": "2025-10-28T13:50:38+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Monday": {"duration": "2.00", "end_time": "10:00", "start_time": "08:00"}, "Wednesday": {"duration": "2.00", "end_time": "12:00", "start_time": "10:00"}}}, "startDate": "2025-11-04", "dayOfMonth": null, "selectedDays": ["Monday", "Wednesday"], "exceptionDates": [{"date": "2025-10-29", "reason": "Client Cancelled"}], "holidayOverrides": []}	[{"stop_date": "2025-12-10", "restart_date": "2026-01-12"}]	[]	2	2025-10-06	[]	EBB12B55	[]	\N
56	4		AET	2025-12-10	t	BANKSETA	f		1	2025-12-10 13:04:30	2025-12-10 15:06:28.271881	13	COMM	4-AET-COMM-2025-12-10-15-01	120	\N	[{"id": "2", "name": "Nosipho N Dlamini", "level": "CL4", "status": "CIC - Currently in Class"}, {"id": "15", "name": "Themba T Maseko", "level": "CL4", "status": "CIC - Currently in Class"}, {"id": "9", "name": "Willem W Botha", "level": "CL4", "status": "CIC - Currently in Class"}]	[]	{"endDate": "2026-04-02", "pattern": "biweekly", "version": "2.0", "metadata": {"lastUpdated": "2025-12-10T13:04:30+00:00", "validatedAt": "2025-12-10T13:04:30+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}, "Thursday": {"duration": "4.00", "end_time": "13:00", "start_time": "09:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday", "Thursday"], "exceptionDates": [{"date": "2026-02-19", "reason": "Client Cancelled"}, {"date": "2026-03-19", "reason": "Agent Absent"}], "holidayOverrides": []}	[{"stop_date": "2026-04-02", "restart_date": "2026-04-07"}]	[]	15	2025-12-10	[]	123345	[]	\N
57	10		GETC	2025-12-10	f		t	GETC SMME	1	2025-12-10 13:32:55	2026-02-10 18:10:02.013373	32	SMME4	10-GETC-SMME4-2025-12-10-15-32	60	\N	[{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}]	[]	{"endDate": "2026-02-04", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-05T12:55:45+00:00", "validatedAt": "2026-02-05T12:55:45+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Wednesday": {"duration": "8.00", "end_time": "16:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Wednesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}	[]	[]	14	2025-12-10	[{"id": "15", "name": "Themba T Maseko", "level": "SMME4", "status": "CIC - Currently in Class"}]	4363463466	[{"date": "2026-02-07", "type": "Collections", "notes": "Notify Front Desc", "status": "Pending", "description": "First Delivery"}, {"date": "2026-02-06", "type": "Deliveries", "notes": "Notify Front Desc of delivery", "status": "completed", "description": "First Delivery Date", "completed_at": "2026-02-10 18:10:01", "completed_by": "1"}, {"date": "2026-02-06", "type": "Mock Exams", "notes": "Mock Exam 102 Notes", "status": "Pending", "description": "Mock Exam 102"}]	{"completed_at": "2026-02-05 11:38:22", "completed_by": 1}
58	24		AET	2025-12-10	f		f		1	2025-12-10 14:24:38	2026-02-13 12:02:44	45	NUM	24-AET-NUM-2025-12-10-16-22	120	7	[1, 1]	[]	{"endDate": "2026-08-11", "pattern": "weekly", "version": "2.0", "metadata": {"lastUpdated": "2026-02-13T12:02:42+00:00", "validatedAt": "2026-02-13T12:02:42+00:00"}, "timeData": {"mode": "per-day", "perDayTimes": {"Tuesday": {"duration": "4.00", "end_time": "12:00", "start_time": "08:00"}}}, "startDate": "2025-12-10", "dayOfMonth": null, "selectedDays": ["Tuesday"], "exceptionDates": [{"date": "2025-12-10", "reason": "Client Cancelled"}], "holidayOverrides": []}	[{"stop_date": "2025-12-16", "restart_date": "2026-01-13"}]	[]	7	2025-12-10	[]	5352352355	[{"date": "2026-02-08", "type": "Deliveries", "notes": "Material Tracking Note", "status": "Pending", "description": "Material Tracking"}]	\N
\.


--
-- TOC entry 4152 (class 0 OID 17390)
-- Dependencies: 239
-- Data for Name: client_communications; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.client_communications (communication_id, client_id, communication_type, subject, content, communication_date, user_id, site_id) FROM stdin;
1	1	Email	Course Feedback Request	Requesting feedback on the recent training program	2023-03-20 00:00:00	1	27
2	2	Phone Call	Schedule Changes	Discussed potential changes to the class schedule	2023-04-05 00:00:00	2	6
3	3	Meeting	Curriculum Review	Met to review and update the curriculum for next quarter	2023-04-15 00:00:00	3	9
4	4	Email	Invoice Query	Clarification sought on recent invoice details	2023-03-25 00:00:00	4	12
5	5	Video Call	Progress Report Discussion	Presented and discussed the monthly progress report	2023-05-10 00:00:00	5	2
6	6	Email	New Course Proposal	Sent proposal for a new specialized course	2023-05-20 00:00:00	1	28
7	7	Phone Call	Learner Performance Concerns	Discussed concerns about certain learners' performance	2023-06-02 00:00:00	2	29
8	8	Meeting	Contract Renewal	Met to discuss terms for contract renewal	2023-06-15 00:00:00	3	30
9	9	Email	Equipment Request	Request for additional equipment for practical sessions	2023-06-25 00:00:00	4	31
10	10	Video Call	End of Program Review	Conducted end-of-program review and discussed outcomes	2023-07-05 00:00:00	5	32
11	11	Email	Community Event Invitation	Invited client to upcoming community showcase event	2023-07-15 00:00:00	1	33
12	12	Phone Call	Learner Placement Discussion	Discussed potential placement opportunities for top performers	2023-07-25 00:00:00	2	34
13	13	Meeting	New Location Scouting	Met to explore potential new locations for classes	2023-08-05 00:00:00	3	35
14	14	Email	Certification Query	Provided information about certification process for learners	2023-08-15 00:00:00	4	36
15	15	Video Call	Quarterly Performance Review	Conducted quarterly review of program performance and impact	2023-08-25 00:00:00	5	37
16	16	Cold Call	Client communication: Cold Call	Communication type recorded as Cold Call.	2025-10-08 10:40:45.556932	1	16
17	18	Lead	Client communication: Lead	Communication type recorded as Lead.	2025-10-08 12:35:17.888882	1	18
18	19	Lead	Client communication: Lead	Communication type recorded as Lead.	2025-10-08 12:38:40.459142	1	19
21	23	Active Client	Client communication: Active Client	Communication type recorded as Active Client.	2025-10-13 11:56:56.520683	1	23
22	24	Active Client	Client communication: Active Client	Communication type recorded as Active Client.	2025-12-10 12:22:22.926821	3	24
23	5	Active Client	Client communication: Active Client	Communication type recorded as Active Client.	2026-02-13 14:27:15.443645	1	2
\.


--
-- TOC entry 4154 (class 0 OID 17397)
-- Dependencies: 241
-- Data for Name: clients; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.clients (client_id, client_name, company_registration_number, seta, client_status, financial_year_end, bbbee_verification_date, created_at, updated_at, main_client_id, contact_person, contact_person_email, contact_person_cellphone, contact_person_tel, contact_person_position, deleted_at) FROM stdin;
1	TechCorp Solutions	2020/123456/07	MICT SETA	Active Client	2024-02-28	2023-06-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
2	EduLearn Academy	2018/987654/07	ETDP SETA	Active Client	2024-03-31	2023-09-22	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
3	IndustrialTech Ltd	2019/246810/07	MerSETA	Active Client	2024-01-31	2023-11-30	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
4	FinanceFirst Corp	2017/135790/07	FASSET	Active Client	2024-06-30	2023-08-18	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
6	HealthCare Plus	2016/864209/07	HWSETA	Active Client	2024-04-30	2023-10-05	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
7	MiningPro Resources	2015/753951/07	MQA	Active Client	2024-05-31	2023-07-12	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
8	LogisticsMaster	2019/159753/07	TETA	Active Client	2024-03-31	2023-09-08	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
9	GreenEnergy Solutions	2020/852741/07	EWSETA	Active Client	2023-12-31	2023-06-25	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
10	ConstructBuild Ltd	2017/369258/07	CETA	Active Client	2024-02-29	2023-08-30	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
11	RetailPro Stores	2018/147258/07	W&RSETA	Active Client	2024-01-31	2023-11-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N	\N	\N	\N	\N	\N	\N
12	TechCorp Solutions - Pretoria Branch	2020/123456/07	MICT SETA	Active Client	2024-02-28	2023-06-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	1	\N	\N	\N	\N	\N	\N
13	EduLearn Academy - Paarl Campus	2018/987654/07	ETDP SETA	Active Client	2024-03-31	2023-09-22	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	2	\N	\N	\N	\N	\N	\N
14	FinanceFirst Corp - KZN Branch	2017/135790/07	FASSET	Active Client	2024-06-30	2023-08-18	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	4	\N	\N	\N	\N	\N	\N
15	HealthCare Plus - Eastern Cape	2016/864209/07	HWSETA	Active Client	2024-04-30	2023-10-05	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	6	\N	\N	\N	\N	\N	\N
16	Koos Kombuis	232326236236236236	Services SETA	Cold Call	2025-10-22	2025-10-30	2025-10-08 14:40:42	2025-10-08 14:40:42	5	\N	\N	\N	\N	\N	\N
17	Sannie S	2323262362362368888	HWSETA	Lead	2025-10-30	2025-10-31	2025-10-08 15:57:35	2025-10-08 15:57:35	2	\N	\N	\N	\N	\N	\N
18	Sannie S2	23232623623623699	MQA	Lead	2025-10-15	2025-10-23	2025-10-08 16:35:14	2025-10-08 16:35:14	2	\N	\N	\N	\N	\N	\N
19	Sannie S3	457457547	CETA	Lead	2025-10-22	2025-10-31	2025-10-08 16:38:37	2025-10-08 16:38:37	\N	\N	\N	\N	\N	\N	\N
23	Test Client 01	2323262362362368	PSETA	Active Client	2025-10-31	2025-10-10	2025-10-13 15:56:53	2025-10-13 15:56:53	5	Test Client 01 person	Test@test.com	0791778896	011 123 1234	BigBoss	\N
24	Raumix Queenstown	2000/003265/07	CETA	Active Client	2026-02-28	2025-12-10	2025-12-10 16:22:22	2025-12-10 16:22:22	\N	Mario Maree	mario@eee.co.za	0655179069			\N
25	Phase 22 Test Client	2026/222222/07	BANKSETA	Lead	2026-02-28	\N	2026-02-11 11:57:57	2026-02-11 11:58:31	\N	Test Person	test@example.com	0821234567	\N	\N	\N
26	Test Soft Delete Client	2026/333333/07	BANKSETA	Lead	\N	\N	2026-02-11 12:00:20	2026-02-11 12:00:20	\N	Test	test@test.com	\N	\N	\N	\N
5	AgriGrow Farms	2021/579135/07	AgriSETA	Active Client	2023-12-31	2023-05-20	2024-10-17 13:21:57.870205	2026-02-13 15:39:18	\N	John Montgomery	laudes.michael@gmail.com	0791778555			\N
\.


--
-- TOC entry 4156 (class 0 OID 17405)
-- Dependencies: 243
-- Data for Name: collections; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.collections (collection_id, class_id, collection_date, items, status, created_at, updated_at) FROM stdin;
1	1	2023-06-30	Completed Assignments, Project Reports	Collected	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
3	3	2023-08-01	Lab Reports, Practical Assessment Results	Collected	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	5	2023-08-15	Crop Yield Reports, Soil Analysis Results	Scheduled	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	6	2023-09-01	Patient Care Simulations, Health Assessment Reports	Scheduled	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
7	7	2023-09-15	Safety Procedure Manuals, Risk Assessment Reports	Scheduled	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	9	2023-10-15	Renewable Energy Project Proposals, Efficiency Calculations	Scheduled	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	10	2023-11-01	Building Design Projects, Material Usage Reports	Scheduled	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	13	2023-12-15	Community Impact Assessments, Learning Progress Reports	Scheduled	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	14	2023-12-15	Financial Portfolios, Investment Strategy Reports	Scheduled	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
15	15	2024-01-15	Rural Healthcare Challenges Reports, Patient Care Strategies	Scheduled	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4158 (class 0 OID 17413)
-- Dependencies: 245
-- Data for Name: deliveries; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.deliveries (delivery_id, class_id, delivery_date, items, status, created_at, updated_at) FROM stdin;
1	1	2023-01-25	Textbooks, Stationery	Delivered	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
3	3	2023-02-20	Lab Equipment, Safety Gear	Delivered	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	5	2023-03-10	Agricultural Tools, Seeds	Delivered	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	6	2023-03-25	Medical Mannequins, First Aid Kits	Delivered	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
7	7	2023-04-10	Mining Simulation Software, Helmets	Delivered	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	9	2023-05-10	Solar Panels, Wind Turbine Models	Delivered	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	10	2023-05-25	Construction Materials, Safety Harnesses	Delivered	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	13	2023-07-25	Educational Posters, Learning Games	Pending	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	14	2023-07-10	Financial Analysis Software, Calculators	Delivered	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
15	15	2023-08-10	Medical Textbooks, Anatomical Models	In Transit	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4160 (class 0 OID 17422)
-- Dependencies: 247
-- Data for Name: employers; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.employers (employer_id, employer_name, created_at, updated_at) FROM stdin;
1	Sasol Limited	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
2	Standard Bank Group	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
3	Shoprite Holdings	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
4	MTN Group	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	Naspers	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	Vodacom Group	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
7	Woolworths Holdings	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
8	FirstRand	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	Bidvest Group	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	Sanlam	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
11	Aspen Pharmacare	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
12	Nedbank Group	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	Tiger Brands	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	Barloworld	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
15	Multichoice Group	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4162 (class 0 OID 17428)
-- Dependencies: 249
-- Data for Name: exam_results; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.exam_results (result_id, exam_id, learner_id, subject, mock_exam_number, score, result, exam_date, created_at, updated_at) FROM stdin;
1	1	1	Basic Communication Skills	1	82.50	Pass	2023-05-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
2	1	2	Basic Communication Skills	1	75.00	Pass	2023-05-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
3	2	3	Intermediate Mathematics	1	68.50	Fail	2023-06-01	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
4	2	4	Intermediate Mathematics	1	88.00	Pass	2023-06-01	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	3	5	Advanced Business Writing	1	91.50	Pass	2023-06-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	3	6	Advanced Business Writing	1	79.00	Pass	2023-06-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
7	4	7	Introduction to Data Analysis	1	72.50	Pass	2023-05-01	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
8	4	8	Introduction to Data Analysis	1	85.00	Pass	2023-05-01	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	5	9	Financial Literacy	1	77.50	Pass	2023-07-01	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	5	10	Financial Literacy	1	89.50	Pass	2023-07-01	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
11	6	11	Technical Report Writing	1	94.00	Pass	2023-07-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
12	6	12	Technical Report Writing	1	81.50	Pass	2023-07-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	7	13	Statistical Analysis	1	76.00	Pass	2023-08-01	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	7	14	Statistical Analysis	1	87.50	Pass	2023-08-01	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
15	8	15	English as Second Language	1	83.00	Pass	2023-08-15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4164 (class 0 OID 17434)
-- Dependencies: 251
-- Data for Name: exams; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.exams (exam_id, learner_id, product_id, exam_date, exam_type, score, result, created_at, updated_at) FROM stdin;
1	1	1	2023-06-30	Final	85.50	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
2	2	1	2023-06-30	Final	78.00	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
3	3	2	2023-07-15	Final	72.50	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
4	4	2	2023-07-15	Final	81.00	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	5	3	2023-08-01	Final	92.50	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	6	3	2023-08-01	Final	76.00	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
7	7	5	2023-06-15	Final	68.50	Fail	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
8	8	5	2023-06-15	Final	83.00	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	9	6	2023-08-15	Final	79.50	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	10	6	2023-08-15	Final	87.00	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
11	11	7	2023-09-01	Final	94.50	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
12	12	7	2023-09-01	Final	80.00	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	13	8	2023-09-15	Final	75.50	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	14	8	2023-09-15	Final	86.00	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
15	15	9	2023-10-01	Final	82.50	Pass	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4166 (class 0 OID 17440)
-- Dependencies: 253
-- Data for Name: files; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.files (file_id, owner_type, owner_id, file_path, file_type, uploaded_at) FROM stdin;
1	Learner	1	/documents/learners/john_smith_portfolio.pdf	Scanned Portfolio	2024-10-17 13:21:57.870205
2	Learner	2	/documents/learners/nosipho_dlamini_portfolio.pdf	Scanned Portfolio	2024-10-17 13:21:57.870205
3	Agent	1	/documents/agents/michael_vdb_agreement.pdf	Signed Agreement	2024-10-17 13:21:57.870205
4	Agent	2	/documents/agents/thandi_nkosi_agreement.pdf	Signed Agreement	2024-10-17 13:21:57.870205
5	Class	1	/documents/classes/techcorp_class_syllabus.pdf	Class Syllabus	2024-10-17 13:21:57.870205
6	Class	2	/documents/classes/edulearn_class_schedule.pdf	Class Schedule	2024-10-17 13:21:57.870205
7	Client	1	/documents/clients/techcorp_contract.pdf	Client Contract	2024-10-17 13:21:57.870205
8	Client	2	/documents/clients/edulearn_agreement.pdf	Client Agreement	2024-10-17 13:21:57.870205
9	Learner	3	/documents/learners/ahmed_patel_certificate.pdf	Completion Certificate	2024-10-17 13:21:57.870205
10	Agent	3	/documents/agents/rajesh_patel_cv.pdf	Curriculum Vitae	2024-10-17 13:21:57.870205
11	Class	3	/documents/classes/industrialtech_assessment.pdf	Assessment Guidelines	2024-10-17 13:21:57.870205
12	Learner	4	/documents/learners/lerato_moloi_assignment.pdf	Assignment Submission	2024-10-17 13:21:57.870205
13	Agent	4	/documents/agents/lerato_moloi_training_cert.pdf	Training Certificate	2024-10-17 13:21:57.870205
14	Class	4	/documents/classes/financefirst_exam_results.pdf	Exam Results	2024-10-17 13:21:57.870205
15	Client	3	/documents/clients/industrialtech_feedback.pdf	Client Feedback	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4168 (class 0 OID 17445)
-- Dependencies: 255
-- Data for Name: history; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.history (history_id, entity_type, entity_id, action, changes, action_date, user_id) FROM stdin;
1	Learner	1	Created	{"email": "john.smith@example.com", "surname": "Smith", "first_name": "John"}	2023-01-15 00:00:00	1
2	Agent	1	Updated	{"tel_number": "+27823456789"}	2023-02-01 00:00:00	2
3	Class	1	Created	{"start_date": "2023-02-01", "class_site_name": "TechCorp Training Center"}	2023-01-25 00:00:00	3
4	Client	1	Updated	{"address_line": "100 Main Street, Sandton"}	2023-03-10 00:00:00	4
5	Product	1	Updated	{"product_duration": 45}	2023-04-05 00:00:00	1
6	Learner	2	Updated	{"assessment_status": "Assessed"}	2023-01-21 00:00:00	2
7	Agent	2	Created	{"email": "thandi.nkosi@example.com", "surname": "Nkosi", "first_name": "Thandi"}	2021-11-20 00:00:00	3
8	Class	2	Updated	{"class_status": "Active"}	2023-02-15 00:00:00	4
9	Client	2	Created	{"seta": "ETDP SETA", "client_name": "EduLearn Academy"}	2023-01-16 00:00:00	1
10	Product	2	Created	{"product_name": "Intermediate Mathematics", "product_duration": 60}	2023-01-05 00:00:00	2
11	Learner	3	Updated	{"highest_qualification": "Diploma in IT"}	2023-03-01 00:00:00	3
12	Agent	3	Updated	{"sace_expiry_date": "2027-03-09"}	2023-03-10 00:00:00	4
13	Class	3	Updated	{"exam_type": "Final", "exam_class": true}	2023-04-15 00:00:00	1
14	Client	3	Updated	{"client_status": "Active Client"}	2023-05-01 00:00:00	2
15	Product	3	Updated	{"product_notes": "Updated course content for industry relevance"}	2023-05-15 00:00:00	3
\.


--
-- TOC entry 4170 (class 0 OID 17452)
-- Dependencies: 257
-- Data for Name: latest_document; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.latest_document (id, class_id, visit_date, visit_type, officer_name, report_metadata, created_at, updated_at) FROM stdin;
1	21	2025-07-14	Initial QA Visit	Unknown	\N	2025-07-15 12:04:27.467134+02	2025-07-15 12:04:27.467134+02
2	22	2025-07-14	Initial QA Visit	Unknown	\N	2025-07-15 12:04:27.467134+02	2025-07-15 12:04:27.467134+02
3	23	2025-08-11	Initial QA Visit	Unknown	\N	2025-07-15 12:04:27.467134+02	2025-07-15 12:04:27.467134+02
4	24	2025-07-21	Initial QA Visit	Unknown	\N	2025-07-15 12:04:27.467134+02	2025-07-15 12:04:27.467134+02
12	48	2025-07-29	Follow-up QA	Koos Kombuis	{"date": "2025-07-29", "type": "Follow-up QA", "officer": "Koos Kombuis", "file_url": "http://localhost/wecoza/wp-content/uploads/qa-reports/qa_report_20250715_114705_68763fb90cc44.pdf", "filename": "qa_report_20250715_114705_68763fb90cc44.pdf", "file_path": "qa-reports/qa_report_20250715_114705_68763fb90cc44.pdf", "file_size": 1214850, "upload_date": "2025-07-15 11:47:05", "uploaded_by": "Laudes", "original_name": "Smart-Money-Concept-trading-strategy-PDF.pdf"}	2025-07-15 13:47:05+02	2025-07-15 13:47:05+02
\.


--
-- TOC entry 4217 (class 0 OID 24656)
-- Dependencies: 306
-- Data for Name: learner_hours_log; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learner_hours_log (log_id, learner_id, product_id, class_id, tracking_id, log_date, hours_trained, hours_present, source, session_id, created_by, notes, created_at) FROM stdin;
\.


--
-- TOC entry 4215 (class 0 OID 24623)
-- Dependencies: 304
-- Data for Name: learner_lp_tracking; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learner_lp_tracking (tracking_id, learner_id, product_id, class_id, hours_trained, hours_present, hours_absent, status, start_date, completion_date, portfolio_file_path, portfolio_uploaded_at, marked_complete_by, marked_complete_date, notes, created_at, updated_at) FROM stdin;
\.


--
-- TOC entry 4171 (class 0 OID 17459)
-- Dependencies: 258
-- Data for Name: learner_placement_level; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learner_placement_level (placement_level_id, level, level_desc) FROM stdin;
7	NL1	AET Numeracy level 1
8	NL2	AET Numeracy level 2
9	NL3	AET Numeracy level 3
10	NL4	AET Numeracy level 4
11	LO4	AET level 4 Life Orientation
12	HSS4	AET level 4 Human & Social Sciences
13	EMS4	AET level 4 Economic & Management Sciences
14	NS4	AET level 4 Natural Sciences
15	SMME4	AET level 4 Small Micro Medium Enterprises
16	RLC	REALLL Communication
17	RLN	REALLL Numeracy
18	RLF	REALLL Finance
19	BA2LP1	Business Admin NQF 2 - LP1
20	BA2LP2	Business Admin NQF 2 - LP2
21	BA2LP3	Business Admin NQF 2 - LP3
22	BA2LP4	Business Admin NQF 2 - LP4
23	BA2LP5	Business Admin NQF 2 - LP5
24	BA2LP6	Business Admin NQF 2 - LP6
25	BA2LP7	Business Admin NQF 2 - LP7
26	BA2LP8	Business Admin NQF 2 - LP8
27	BA2LP9	Business Admin NQF 2 - LP9
28	BA2LP10	Business Admin NQF 2 - LP10
29	BA3LP1	Business Admin NQF 3 - LP1
30	BA3LP2	Business Admin NQF 3 - LP2
31	BA3LP3	Business Admin NQF 3 - LP3
32	BA3LP4	Business Admin NQF 3 - LP4
33	BA3LP5	Business Admin NQF 3 - LP5
34	BA3LP6	Business Admin NQF 3 - LP6
35	BA3LP7	Business Admin NQF 3 - LP7
36	BA3LP8	Business Admin NQF 3 - LP8
37	BA3LP9	Business Admin NQF 3 - LP9
38	BA3LP10	Business Admin NQF 3 - LP10
39	BA3LP11	Business Admin NQF 3 - LP11
40	BA4LP1	Business Admin NQF 4 - LP1
41	BA4LP2	Business Admin NQF 4 - LP2
42	BA4LP3	Business Admin NQF 4 - LP3
43	BA4LP4	Business Admin NQF 4 - LP4
44	BA4LP5	Business Admin NQF 4 - LP5
45	BA4LP6	Business Admin NQF 4 - LP6
46	BA4LP7	Business Admin NQF 4 - LP7
47	IPC	Introduction to Computers
48	EQ	Email Ettiquette
49	TM	Time Management
50	SS	Supervisory Skills
51	EEPDL	EEP Digital Literacy
52	EEPPF	EEP Personal Finance
53	EEPWI	EEP Workplace Inteligence
54	EEPEI	EEP Emotional Inteligence
55	EEPBI	EEP Business Inteligence
3	CL2	AET Communication level 2
4	CL3	AET Communication level 3
5	CL4	AET Communication level 4
1	CL1b	AET Communication level 1 Basic
2	CL1	AET Communication level 1
6	NL1B	AET Numeracy level 1 Basic
\.


--
-- TOC entry 4172 (class 0 OID 17464)
-- Dependencies: 259
-- Data for Name: learner_portfolios; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learner_portfolios (portfolio_id, learner_id, file_path, upload_date) FROM stdin;
10	1	portfolios/portfolio_6720752cccc1c2.73545383.pdf	2024-10-29 05:39:56.770881
11	1	portfolios/portfolio_6720752d307e01.14382765.pdf	2024-10-29 05:39:56.770881
13	33	portfolios/portfolio_6745e21588e300.47062868.pdf	2024-11-26 14:58:29.479737
16	37	portfolios/portfolio_67838a7a104755.24536021.pdf	2025-01-12 09:25:13.974567
\.


--
-- TOC entry 4174 (class 0 OID 17469)
-- Dependencies: 261
-- Data for Name: learner_products; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learner_products (learner_id, product_id, start_date, end_date) FROM stdin;
1	1	2023-02-01	2023-06-30
1	4	2023-02-01	2023-06-30
2	2	2023-02-15	2023-07-15
2	6	2023-02-15	2023-07-15
3	3	2023-03-01	2023-08-01
3	8	2023-03-01	2023-08-01
4	5	2023-01-10	2023-06-15
4	10	2023-01-10	2023-06-15
5	6	2023-03-15	2023-08-15
5	12	2023-03-15	2023-08-15
6	7	2023-04-01	2023-09-01
6	14	2023-04-01	2023-09-01
7	8	2023-04-15	2023-09-15
7	2	2023-04-15	2023-09-15
8	9	2023-05-01	2023-10-01
8	4	2023-05-01	2023-10-01
9	10	2023-05-15	2023-10-15
9	14	2023-05-15	2023-10-15
10	11	2023-06-01	2023-11-01
10	6	2023-06-01	2023-11-01
11	12	2023-06-15	2023-11-15
11	1	2023-06-15	2023-11-15
12	13	2023-07-01	2023-12-01
12	8	2023-07-01	2023-12-01
13	14	2023-08-01	2023-12-15
13	3	2023-08-01	2023-12-15
14	15	2023-07-15	2023-12-15
14	10	2023-07-15	2023-12-15
15	1	2023-08-15	2024-01-15
15	7	2023-08-15	2024-01-15
\.


--
-- TOC entry 4219 (class 0 OID 24689)
-- Dependencies: 308
-- Data for Name: learner_progression_portfolios; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learner_progression_portfolios (file_id, tracking_id, file_name, file_path, file_type, file_size, uploaded_by, uploaded_at) FROM stdin;
\.


--
-- TOC entry 4175 (class 0 OID 17472)
-- Dependencies: 262
-- Data for Name: learner_progressions; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learner_progressions (progression_id, learner_id, from_product_id, to_product_id, progression_date, notes) FROM stdin;
1	1	1	3	2023-07-01	Excellent progress in communication skills, ready for advanced course
2	2	1	3	2023-07-15	Good improvement, moving to advanced level with additional support
3	3	2	6	2023-08-01	Strong grasp of basic concepts, progressing to financial applications
4	4	2	6	2023-08-01	Exceptional performance, fast-tracked to advanced course
5	5	3	7	2023-08-15	Demonstrated proficiency, moving to specialized technical writing
6	6	3	7	2023-08-15	Solid writing skills, progressing to technical focus with monitoring
7	7	4	8	2023-06-15	Strong analytical skills, advancing to in-depth statistical analysis
8	8	4	8	2023-06-15	Excellent data handling, moving to advanced statistical methods
9	9	6	12	2023-09-01	Proficient in finance basics, progressing to business mathematics
10	10	6	12	2023-09-01	Outstanding financial acumen, advancing to complex business math
11	11	7	13	2023-09-15	Excellent technical writing, moving to intercultural communication
12	12	7	13	2023-09-15	Strong writing foundation, progressing to broader communication skills
13	13	8	14	2023-10-01	Mastered statistics, advancing to data visualization techniques
14	14	8	14	2023-10-01	Exceptional statistical analysis, moving to advanced data presentation
15	15	9	1	2023-09-01	Completed ESL program, transitioning to general communication skills
\.


--
-- TOC entry 4177 (class 0 OID 17478)
-- Dependencies: 264
-- Data for Name: learner_qualifications; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learner_qualifications (id, qualification) FROM stdin;
1	Grade 7 Certificate
2	Grade 9 Certificate
3	Grade 10 Certificate
4	Grade 12 Certificate (Matric)
5	National Certificate (Vocational) Level 4
\.


--
-- TOC entry 4225 (class 0 OID 24762)
-- Dependencies: 314
-- Data for Name: learner_sponsors; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learner_sponsors (id, learner_id, employer_id, created_at) FROM stdin;
1	37	10	2026-02-13 14:47:51.592375
\.


--
-- TOC entry 4179 (class 0 OID 17482)
-- Dependencies: 266
-- Data for Name: learners; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.learners (id, first_name, initials, surname, gender, race, sa_id_no, passport_number, tel_number, alternative_tel_number, email_address, address_line_1, address_line_2, city_town_id, province_region_id, postal_code, assessment_status, placement_assessment_date, numeracy_level, employment_status, employer_id, disability_status, scanned_portfolio, created_at, updated_at, highest_qualification, communication_level, second_name, title) FROM stdin;
3	Ahmed	A	Patel	Male	Indian	8805035080083	\N	+27845678901	+27315789012	ahmed.patel@example.com	5 Palm Avenue	Chatsworth	3	3	4092	Assessed	2023-01-22	4	t	2	f	portfolios/ahmed_patel.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	3	\N	\N	\N
4	Lerato	L	Moloi	Female	African	9407045080084	\N	+27856789012	+27128901234	lerato.moloi@example.com	15 Acacia Lane	Soshanguve	4	1	0152	Not Assessed	\N	6	t	3	f	\N	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	4	\N	\N	\N
5	Pieter	P	van der Merwe	Male	White	9609055080085	\N	+27867890123	+27219012345	pieter.vdm@example.com	30 Vineyard Road	Stellenbosch	5	2	7600	Assessed	2023-01-24	2	t	4	f	portfolios/pieter_vdm.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	5	\N	\N	\N
7	Daniel	D	O'Connor	Male	White	8802075080087	\N	+27889012345	+27538901234	daniel.oconnor@example.com	8 Diamond Street	Kimberley	7	7	8301	Assessed	2023-01-26	1	t	5	f	portfolios/daniel_oconnor.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	4	\N	\N	\N
9	Willem	W	Botha	Male	White	9106095080089	\N	+27901234567	+27514567890	willem.botha@example.com	18 Sunflower Street	Bloemfontein	9	9	9301	Assessed	2023-01-28	1	t	7	f	portfolios/willem_botha.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	2	\N	\N	\N
11	Raj	R	Singh	Male	Indian	9010115080091	\N	+27923456789	+27112345678	raj.singh@example.com	7 Magnolia Crescent	Lenasia	11	1	1827	Assessed	2023-01-30	6	t	9	f	portfolios/raj_singh.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	5	\N	\N	\N
12	Emma	E	van Wyk	Female	White	9712125080092	\N	+27934567890	+27124567890	emma.vanwyk@example.com	14 Willow Lane	Centurion	12	1	0157	Not Assessed	\N	4	f	\N	f	\N	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	5	\N	\N	\N
14	Charmaine	C	Pillay	Female	Indian	9605145080094	\N	+27956789012	+27337890123	charmaine.pillay@example.com	33 Hill Street	Pietermaritzburg	14	3	3201	Assessed	2023-02-02	2	t	11	f	portfolios/charmaine_pillay.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	4	\N	\N	\N
15	Themba	T	Maseko	Male	African	9307155080095	\N	+27967890123	+27436789012	themba.maseko@example.com	27 Beach Road	Quigney	15	10	5201	Assessed	2023-02-03	2	f	\N	t	portfolios/themba_maseko.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	3	\N	\N	\N
2	Nosipho	N	Dlamini	Female	African	9203025080082	\N	+27834567890	+27215678901	nosipho.dlamini@example.com	25 Protea Road	Khayelitsha	2	2	7784	Assessed	2023-01-21	2	f	\N	f	portfolios/nosipho_dlamini.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	2	\N	\N	\N
13	Sibusiso	S	Ngcobo	Male	African	8909135080093	\N	+27945678901	+27218901234	sibusiso.ngcobo@example.com	9 Winery Road	Paarl	13	2	7646	Assessed	2023-02-01	3	t	10	f	portfolios/sibusiso_ngcobo.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	4	\N	\N	\N
10	Nomsa	N	Tshabalala	Female	African	9308105080090	\N	+27912345678	+27413456789	nomsa.tshabalala@example.com	22 Ocean View Drive	Summerstrand	10	10	6001	Assessed	2023-01-29	3	t	8	f	portfolios/nomsa_tshabalala.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	1	\N	\N	\N
8	Zinhle	Z	Mthembu	Female	African	9504085080088	\N	+27890123456	+27137890123	zinhle.mthembu@example.com	12 Jacaranda Avenue	Nelspruit	8	8	1200	Assessed	2023-01-27	3	t	6	f	portfolios/zinhle_mthembu.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	3	\N	\N	\N
6	Thandi	T	Nkosi	Female	African	9211065080086	\N	+27878901234	+27151234567	thandi.nkosi@example.com	20 Baobab Street	Polokwane	6	6	0699	Assessed	2023-01-25	3	f	\N	f	portfolios/thandi_nkosi.pdf	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	5	\N	\N	\N
28	John44	TRTY	Montgomery	Male	White	35235235235525		0791771970		laudes.michael@gmail.com	97 Klipper Street		4	1	6500	Not Assessed	\N	\N	f	\N	f		2024-10-28 13:24:49	2024-10-28 13:24:49	3	\N	\N	\N
29	John46	WQR	Montgomery	Male	White	8909135080093		0791771970		laudes.michael@gmail.com	97 Klipper Street		14	3	6500	Not Assessed	\N	\N	f	\N	f	portfolios/portfolio_671f9ab077b5e8.97078191.pdf,portfolios/portfolio_671f9ab0dcc461.48933566.pdf	2024-10-28 14:07:42	2024-10-28 14:07:42	1	\N	\N	\N
23	Sibusiso	eryery	Montgomery	Male	Black	46346346346346346		0791771970		laudes.michael@gmail.com	97 Klipper Street		2	2	6500	Not Assessed	\N	\N	f	\N	f		2024-10-28 12:01:49	2024-10-28 12:01:49	3	\N	\N	\N
24	John2	ey	Montgomery	Male	White	8909135080093		0791771970		laudes.michael@gmail.com	97 Klipper Street		2	2	6500	Not Assessed	\N	\N	f	\N	f		2024-10-28 12:17:09	2024-10-28 12:17:09	3	\N	\N	\N
27	Sibusiso	YU	Montgomery	Male	Black	46346346346346		0791771970		laudes.michael@gmail.com	97 Klipper Street		13	2	6500	Not Assessed	\N	\N	f	\N	f		2024-10-28 13:13:06	2024-10-28 13:13:06	1	\N	\N	\N
35	Peter	P.J.	Wessels	Male	Black	6702155114087		0791778898		test@test.com	22 Street		2	2	7800	Assessed	2024-12-07	8	t	6	f		2024-12-11 12:22:03	2024-12-11 12:22:03	2	\N	\N	\N
31	Koos88	ET	Montgomery	Female	White		4634634634634688	0791771970		laudes.michael@gmail.com	97 Klipper Street		9	9	6500	Not Assessed	\N	\N	f	\N	f		2024-10-28 14:36:09	2024-10-28 14:36:09	3	\N	\N	\N
32	Koos88	ET	Montgomery	Female	White		4634634634634688	0791771970		laudes.michael@gmail.com	97 Klipper Street		9	9	6500	Not Assessed	\N	\N	f	\N	f		2024-10-28 15:00:12	2024-10-28 15:00:12	3	\N	\N	\N
33	Sibusiso	yeryery	Montgomery	Male	White	6702155114089		0791771970	0791771977	laudes.michael@gmail.com	97 Klipper Street		9	9	6500	Assessed	2024-11-06	2	t	8	f	portfolios/portfolio_6745e21588e300.47062868.pdf	2024-11-26 14:58:27	2024-11-26 14:58:27	4	\N	\N	\N
30	Koos2	WET	Montgomery	Male	Coloured	35235235235525		0791771970		laudes.michael@gmail.com	97 Klipper Street		3	3	6500	Not Assessed	\N	\N	f	\N	f	\N	2024-10-28 14:21:00	2024-10-28 14:21:00	4	\N	\N	\N
1	John	J.M	Smith	Male	White	3401015800086		+27823456789	+27114567890	john.smith@example.com	10 Oak Street	Parktown	1	1	2196	Assessed	2023-01-20	3	t	15	t	portfolios/portfolio_6720752cccc1c2.73545383.pdf, portfolios/portfolio_6720752d307e01.14382765.pdf	2024-10-17 13:21:57.870205	2025-01-07 07:55:39	1	\N	Michael	Mr
37	Comm	C	Wessels	Male	Indian	6702155114080	\N	0791778898	1231231234	test@test.com	22 Street	\N	2	2	7551	Assessed	2025-01-02	14	t	13	f	portfolios/portfolio_67838a7a104755.24536021.pdf	2025-01-12 09:25:12	2026-02-13 12:47:51	3	5	\N	Mr
\.


--
-- TOC entry 4181 (class 0 OID 17490)
-- Dependencies: 268
-- Data for Name: locations; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.locations (location_id, suburb, town, province, postal_code, longitude, latitude, created_at, updated_at, street_address) FROM stdin;
1	Sandton	Johannesburg	Gauteng	2196	28.047300	-26.107800	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
2	Durbanville	Cape Town	Western Cape	7551	18.649800	-33.831200	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
3	Umhlanga	Durban	KwaZulu-Natal	4320	31.066600	-29.707500	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
4	Hatfield	Pretoria	Gauteng	0028	28.229200	-25.748700	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
5	Stellenbosch	Stellenbosch	Western Cape	7600	18.860200	-33.932100	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
6	Polokwane	Polokwane	Limpopo	0699	29.457100	-23.904500	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
7	Kimberley	Kimberley	Northern Cape	8301	24.766800	-28.728200	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
8	Nelspruit	Mbombela	Mpumalanga	1200	30.974700	-25.475300	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
9	Bloemfontein	Bloemfontein	Free State	9301	26.204100	-29.085200	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
11	Soweto	Johannesburg	Gauteng	1804	27.868000	-26.248500	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
12	Centurion	Pretoria	Gauteng	0157	28.190500	-25.861900	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
14	Pietermaritzburg	Pietermaritzburg	KwaZulu-Natal	3201	30.370500	-29.616800	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205	\N
16	Mossel Bay	Garden Route District Municipality	Western Cape	6500	22.143792	-34.185102	2025-10-03 18:02:14	2025-10-03 18:02:14	97 Klipper Street
17	Mossel Bay	Garden Route District Municipality	Western Cape	6500	22.141624	-34.185443	2025-10-06 13:54:14	2025-10-09 13:46:25	45 Klipper Street
15	CBD	Buffalo City Metropolitan Municipality	Eastern Cape	5247	27.902991	-33.019428	2024-10-17 13:21:57.870205	2026-01-22 16:06:00	11 Gilwell Road
10	Southernwood	Buffalo City Metropolitan Municipality	Eastern Cape	5213	27.901467	-33.006005	2024-10-17 13:21:57.870205	2026-01-22 16:13:08	6 Muir Street
13	Lemoenkloof	Cape Winelands District Municipality	Western Cape	7646	18.969349	-33.727948	2024-10-17 13:21:57.870205	2026-01-22 16:17:48	39 Dorp Street
18	Marabastad	City of Tshwane Metropolitan Municipality	Gauteng	0001	28.173594	-25.740050	2026-02-13 15:34:36	2026-02-13 15:34:36	88 Boom Street
\.


--
-- TOC entry 4183 (class 0 OID 17499)
-- Dependencies: 270
-- Data for Name: products; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.products (product_id, product_name, product_duration, learning_area, learning_area_duration, reporting_structure, product_notes, product_rules, product_flags, parent_product_id, created_at, updated_at) FROM stdin;
1	Basic Communication Skills	40	Communication	40	Weekly progress reports	Foundational course for effective communication	Minimum 80% attendance required	Beginner level	\N	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
2	Intermediate Mathematics	60	Mathematics	60	Bi-weekly assessments	Builds on basic math concepts	Prerequisite: Basic Mathematics	Intermediate level	\N	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
3	Advanced Business Writing	30	Communication	30	Project-based evaluation	Focuses on professional writing skills	Final project required	Advanced level	1	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
4	Introduction to Data Analysis	50	Mathematics	50	Monthly progress checks	Covers basic statistical concepts	Access to computer lab required	Beginner level	\N	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	Public Speaking Mastery	25	Communication	25	Video submissions	Enhances presentation skills	Final live presentation	Advanced level	1	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	Financial Literacy	45	Mathematics	45	Case study evaluations	Personal and business finance basics	Group project included	Intermediate level	2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
7	Technical Report Writing	35	Communication	35	Peer review system	Focused on STEM fields	Individual consultations	Advanced level	1	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
8	Statistical Analysis	55	Mathematics	55	Data-driven assignments	Covers advanced statistical methods	Access to statistical software required	Advanced level	4	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	English as Second Language	80	Communication	80	Continuous assessment	For non-native English speakers	Placement test required	Multi-level	\N	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	Quantitative Reasoning	40	Mathematics	40	Problem-solving challenges	Logic and analytical skills development	Weekly quizzes	Intermediate level	2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
11	Creative Writing Workshop	30	Communication	30	Portfolio development	Explores various writing genres	Final anthology submission	Intermediate level	1	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
12	Business Mathematics	50	Mathematics	50	Real-world application projects	Focuses on practical business applications	Internship component	Advanced level	2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	Intercultural Communication	35	Communication	35	Role-playing assessments	Develops cross-cultural communication skills	Group discussions	Intermediate level	1	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	Data Visualization	40	Mathematics	40	Visual project submissions	Teaches effective data presentation	Requires basic coding knowledge	Intermediate level	4	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
15	Academic Writing	45	Communication	45	Research paper submissions	Prepares for higher education writing	Plagiarism checks enforced	Advanced level	1	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4185 (class 0 OID 17507)
-- Dependencies: 272
-- Data for Name: progress_reports; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.progress_reports (report_id, class_id, learner_id, product_id, progress_percentage, report_date, remarks, created_at, updated_at) FROM stdin;
1	1	1	1	75.50	2023-04-15	Good progress in communication skills.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
2	1	2	1	82.00	2023-04-15	Excellent participation in group activities.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	3	5	3	88.50	2023-05-15	Outstanding progress in advanced topics.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	3	6	3	72.00	2023-05-15	Showing improvement in practical applications.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	5	9	6	77.50	2023-05-31	Good understanding of financial principles.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	5	10	6	84.00	2023-05-31	Excels in applying concepts to real-world scenarios.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
11	6	11	7	90.50	2023-06-15	Exceptional progress in technical writing.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
12	6	12	7	76.00	2023-06-15	Improving in structuring complex information.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	7	13	8	71.50	2023-06-30	Needs more practice with statistical software.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	7	14	8	83.00	2023-06-30	Strong grasp of statistical concepts.	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
\.


--
-- TOC entry 4187 (class 0 OID 17515)
-- Dependencies: 274
-- Data for Name: qa_visits; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.qa_visits (id, class_id, visit_date, visit_type, officer_name, latest_document, created_at, updated_at) FROM stdin;
20	48	2025-07-21	Initial QA Visit	Sannie Koekemoer	\N	2025-09-26 14:29:54+02	2025-09-26 14:29:54+02
\.


--
-- TOC entry 4190 (class 0 OID 17524)
-- Dependencies: 277
-- Data for Name: sites; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.sites (site_id, client_id, site_name, created_at, updated_at, parent_site_id, place_id) FROM stdin;
6	2	Beta Industries Main Office	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
8	2	Beta Industries Research Lab	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
9	3	Gamma Solutions HQ	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
10	3	Gamma Solutions R&D	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
11	3	Gamma Solutions Training Ctr	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
12	4	Delta Enterprises Office	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
13	4	Delta Enterprises Branch	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
14	4	Delta Enterprises Call Center	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
15	5	Epsilon Tech Hub	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
16	5	Epsilon Tech Showroom	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
17	5	Epsilon Tech Support Center	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N
18	5	Central	2025-06-20 14:25:12	2025-06-20 14:25:12	\N	\N
20	5	Bloem Central	2025-06-21 12:24:19	2025-06-21 12:24:19	\N	\N
23	2	EduLearn South	2025-06-23 14:58:15	2025-06-23 14:58:15	\N	\N
24	5	Randfontein	2025-10-02 06:09:20	2025-10-02 06:09:20	\N	\N
25	3	Randfontein	2025-10-03 05:57:04	2025-10-03 05:57:04	\N	\N
19	5	Central 2	2025-06-20 14:47:25	2025-06-20 14:47:25	\N	\N
21	5	Bloem Central 2	2025-06-21 12:24:47	2025-06-21 12:24:47	\N	\N
26	3	Randfontein 2	2025-10-03 05:57:22	2025-10-03 05:57:22	\N	\N
27	1	TechCorp Solutions – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	1
28	6	HealthCare Plus – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	6
29	7	MiningPro Resources – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	7
30	8	LogisticsMaster – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	8
31	9	GreenEnergy Solutions – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	9
32	10	ConstructBuild Ltd – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	10
33	11	RetailPro Stores – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	11
34	12	TechCorp Solutions - Pretoria Branch – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	27	12
35	13	EduLearn Academy - Paarl Campus – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	6	13
36	14	FinanceFirst Corp - KZN Branch – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	12	14
37	15	HealthCare Plus - Eastern Cape – Head Office	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	28	15
38	16	Central	2025-10-08 14:40:42	2025-10-08 14:40:42	\N	5
39	17	sannie	2025-10-08 15:57:36	2025-10-08 15:57:36	\N	6
40	18	SannieS2	2025-10-08 16:35:14	2025-10-08 16:35:14	\N	15
41	19	SannieS3	2025-10-08 16:38:37	2025-10-08 16:38:37	\N	15
44	23	Test Client Site Name 101	2025-10-13 15:56:54	2025-10-13 15:56:54	\N	16
45	24	Queenstown	2025-12-10 16:22:22	2025-12-10 16:22:22	\N	10
2	5	East Park	2025-06-20 09:55:36	2026-02-13 15:39:18	\N	15
\.


--
-- TOC entry 4191 (class 0 OID 17529)
-- Dependencies: 278
-- Data for Name: sites_address_audit; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.sites_address_audit (site_id, client_id, site_name, address_line_1, address_line_2, address, place_id, parent_site_id, created_at, updated_at) FROM stdin;
2	5	East Park	\N	\N	6756, East Park, Parklands, 6756	\N	\N	2025-06-20 09:55:36	2025-06-20 09:57:44
6	2	Beta Industries Main Office	\N	\N	300 Corporate Avenue, Johannesburg, 2001	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
8	2	Beta Industries Research Lab	\N	\N	500 Science Park, Randburg, 2194	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
9	3	Gamma Solutions HQ	\N	\N	600 Tech Boulevard, Midrand, 1682	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
10	3	Gamma Solutions R&D	\N	\N	700 Innovation Drive, Sandton, 2196	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
11	3	Gamma Solutions Training Ctr	\N	\N	800 Learning Street, Pretoria, 0002	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
12	4	Delta Enterprises Office	\N	\N	900 Business Park, Durban, 4001	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
13	4	Delta Enterprises Branch	\N	\N	1000 Bay Road, Port Elizabeth, 6001	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
14	4	Delta Enterprises Call Center	\N	\N	1100 Service Lane, Bloemfontein, 9301	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
15	5	Epsilon Tech Hub	\N	\N	1200 Silicon Avenue, Cape Town, 8001	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
16	5	Epsilon Tech Showroom	\N	\N	1300 Display Road, Durban, 4001	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
17	5	Epsilon Tech Support Center	\N	\N	1400 Helpdesk Drive, Johannesburg, 2001	\N	\N	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665
18	5	Central	\N	\N	166 Central Road, Central, 8756	\N	\N	2025-06-20 14:25:12	2025-06-20 14:25:12
20	5	Bloem Central	\N	\N	66 Kalahari Drive, Bloemfontein East, 2356	\N	\N	2025-06-21 12:24:19	2025-06-21 12:24:19
23	2	EduLearn South	\N	\N	35346 South Drive, Mayfair, 2100	\N	\N	2025-06-23 14:58:15	2025-06-23 14:58:15
24	5	Randfontein	\N	\N	10 Plaaitjies Street\r\nToekomsrus\r\nRandfontein\r\n0000	\N	\N	2025-10-02 06:09:20	2025-10-02 06:09:20
25	3	Randfontein	\N	\N	2 Kort Street \r\nToekomsrus\r\nRandfontein\r\n0000	\N	\N	2025-10-03 05:57:04	2025-10-03 05:57:04
19	5	Central 2	\N	\N	166 Central Road, Central, 8756	\N	\N	2025-06-20 14:47:25	2025-06-20 14:47:25
21	5	Bloem Central 2	\N	\N	66 Kalahari Drive, Bloemfontein East, 2356	\N	\N	2025-06-21 12:24:47	2025-06-21 12:24:47
26	3	Randfontein 2	\N	\N	2 Kort Street \r\nToekomsrus\r\nRandfontein\r\n0000	\N	\N	2025-10-03 05:57:22	2025-10-03 05:57:22
27	1	TechCorp Solutions – Head Office	100 Main Street	\N	100 Main Street	1	\N	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
28	6	HealthCare Plus – Head Office	150 Wellness Avenue	\N	150 Wellness Avenue	6	\N	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
29	7	MiningPro Resources – Head Office	300 Mineral Street	\N	300 Mineral Street	7	\N	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
30	8	LogisticsMaster – Head Office	100 Transport Road	\N	100 Transport Road	8	\N	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
31	9	GreenEnergy Solutions – Head Office	50 Eco Street	\N	50 Eco Street	9	\N	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
32	10	ConstructBuild Ltd – Head Office	25 Blueprint Avenue	\N	25 Blueprint Avenue	10	\N	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
33	11	RetailPro Stores – Head Office	75 Shop Street	\N	75 Shop Street	11	\N	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
34	12	TechCorp Solutions - Pretoria Branch – Head Office	50 Innovation Drive	\N	50 Innovation Drive	12	27	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
35	13	EduLearn Academy - Paarl Campus – Head Office	10 Learning Lane	\N	10 Learning Lane	13	6	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
36	14	FinanceFirst Corp - KZN Branch – Head Office	30 Accounting Road	\N	30 Accounting Road	14	12	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
37	15	HealthCare Plus - Eastern Cape – Head Office	80 Medic Street	\N	80 Medic Street	15	28	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634
38	16	Central	97 Klipper street	\N	97 Klipper street	5	\N	2025-10-08 14:40:42	2025-10-08 14:40:42
39	17	sannie	97 Klipper street	\N	97 Klipper street	6	\N	2025-10-08 15:57:36	2025-10-08 15:57:36
40	18	SannieS2	97 Klipper street	\N	97 Klipper street	15	\N	2025-10-08 16:35:14	2025-10-08 16:35:14
41	19	SannieS3	97 Klipper street	Address Line 2	97 Klipper street Address Line 2	15	\N	2025-10-08 16:38:37	2025-10-08 16:38:37
\.


--
-- TOC entry 4192 (class 0 OID 17534)
-- Dependencies: 279
-- Data for Name: sites_migration_backup; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.sites_migration_backup (site_id, client_id, site_name, address, created_at, updated_at, parent_site_id, place_id, address_line_1, address_line_2) FROM stdin;
2	5	East Park	6756, East Park, Parklands, 6756	2025-06-20 09:55:36	2025-06-20 09:57:44	\N	\N	\N	\N
6	2	Beta Industries Main Office	300 Corporate Avenue, Johannesburg, 2001	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
8	2	Beta Industries Research Lab	500 Science Park, Randburg, 2194	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
9	3	Gamma Solutions HQ	600 Tech Boulevard, Midrand, 1682	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
10	3	Gamma Solutions R&D	700 Innovation Drive, Sandton, 2196	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
11	3	Gamma Solutions Training Ctr	800 Learning Street, Pretoria, 0002	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
12	4	Delta Enterprises Office	900 Business Park, Durban, 4001	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
13	4	Delta Enterprises Branch	1000 Bay Road, Port Elizabeth, 6001	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
14	4	Delta Enterprises Call Center	1100 Service Lane, Bloemfontein, 9301	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
15	5	Epsilon Tech Hub	1200 Silicon Avenue, Cape Town, 8001	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
16	5	Epsilon Tech Showroom	1300 Display Road, Durban, 4001	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
17	5	Epsilon Tech Support Center	1400 Helpdesk Drive, Johannesburg, 2001	2025-06-20 10:51:04.063665	2025-06-20 10:51:04.063665	\N	\N	\N	\N
18	5	Central	166 Central Road, Central, 8756	2025-06-20 14:25:12	2025-06-20 14:25:12	\N	\N	\N	\N
20	5	Bloem Central	66 Kalahari Drive, Bloemfontein East, 2356	2025-06-21 12:24:19	2025-06-21 12:24:19	\N	\N	\N	\N
23	2	EduLearn South	35346 South Drive, Mayfair, 2100	2025-06-23 14:58:15	2025-06-23 14:58:15	\N	\N	\N	\N
24	5	Randfontein	10 Plaaitjies Street\r\nToekomsrus\r\nRandfontein\r\n0000	2025-10-02 06:09:20	2025-10-02 06:09:20	\N	\N	\N	\N
25	3	Randfontein	2 Kort Street \r\nToekomsrus\r\nRandfontein\r\n0000	2025-10-03 05:57:04	2025-10-03 05:57:04	\N	\N	\N	\N
19	5	Central 2	166 Central Road, Central, 8756	2025-06-20 14:47:25	2025-06-20 14:47:25	\N	\N	\N	\N
21	5	Bloem Central 2	66 Kalahari Drive, Bloemfontein East, 2356	2025-06-21 12:24:47	2025-06-21 12:24:47	\N	\N	\N	\N
26	3	Randfontein 2	2 Kort Street \r\nToekomsrus\r\nRandfontein\r\n0000	2025-10-03 05:57:22	2025-10-03 05:57:22	\N	\N	\N	\N
27	1	TechCorp Solutions – Head Office	100 Main Street	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	1	100 Main Street	\N
28	6	HealthCare Plus – Head Office	150 Wellness Avenue	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	6	150 Wellness Avenue	\N
29	7	MiningPro Resources – Head Office	300 Mineral Street	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	7	300 Mineral Street	\N
30	8	LogisticsMaster – Head Office	100 Transport Road	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	8	100 Transport Road	\N
31	9	GreenEnergy Solutions – Head Office	50 Eco Street	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	9	50 Eco Street	\N
32	10	ConstructBuild Ltd – Head Office	25 Blueprint Avenue	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	10	25 Blueprint Avenue	\N
33	11	RetailPro Stores – Head Office	75 Shop Street	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	\N	11	75 Shop Street	\N
34	12	TechCorp Solutions - Pretoria Branch – Head Office	50 Innovation Drive	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	27	12	50 Innovation Drive	\N
35	13	EduLearn Academy - Paarl Campus – Head Office	10 Learning Lane	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	6	13	10 Learning Lane	\N
36	14	FinanceFirst Corp - KZN Branch – Head Office	30 Accounting Road	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	12	14	30 Accounting Road	\N
37	15	HealthCare Plus - Eastern Cape – Head Office	80 Medic Street	2025-10-03 06:49:27.628634	2025-10-03 06:49:27.628634	28	15	80 Medic Street	\N
38	16	Central	97 Klipper street	2025-10-08 14:40:42	2025-10-08 14:40:42	\N	5	97 Klipper street	\N
39	17	sannie	97 Klipper street	2025-10-08 15:57:36	2025-10-08 15:57:36	\N	6	97 Klipper street	\N
40	18	SannieS2	97 Klipper street	2025-10-08 16:35:14	2025-10-08 16:35:14	\N	15	97 Klipper street	\N
41	19	SannieS3	97 Klipper street Address Line 2	2025-10-08 16:38:37	2025-10-08 16:38:37	\N	15	97 Klipper street	Address Line 2
\.


--
-- TOC entry 4194 (class 0 OID 17540)
-- Dependencies: 281
-- Data for Name: user_permissions; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.user_permissions (permission_id, user_id, permission) FROM stdin;
1	1	all
2	2	view_classes
3	2	edit_classes
4	2	view_agents
5	2	edit_agents
6	2	view_learners
7	2	edit_learners
8	3	view_classes
9	3	view_learners
10	3	edit_attendance
11	3	submit_reports
12	4	view_agents
13	4	edit_agents
14	4	view_learners
15	5	view_clients
16	5	edit_clients
17	5	view_orders
18	5	edit_orders
19	6	view_classes
20	6	edit_classes
21	6	view_agents
22	6	edit_agents
23	6	view_learners
24	6	edit_learners
25	7	view_classes
26	7	view_learners
27	7	edit_attendance
28	7	submit_reports
29	8	view_classes
30	8	view_learners
31	8	view_agents
32	9	view_classes
33	9	view_agents
34	9	submit_qa_reports
35	10	view_users
36	10	edit_users
37	10	view_system_logs
38	11	view_classes
39	11	edit_classes
40	11	view_agents
41	11	edit_agents
42	11	view_learners
43	11	edit_learners
44	12	view_classes
45	12	view_learners
46	12	edit_attendance
47	12	submit_reports
48	13	view_agents
49	13	view_learners
50	14	view_clients
51	14	edit_clients
52	14	view_orders
53	14	edit_orders
54	15	all
\.


--
-- TOC entry 4196 (class 0 OID 17544)
-- Dependencies: 283
-- Data for Name: user_roles; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.user_roles (role_id, role_name, permissions) FROM stdin;
1	Admin	{"all": true}
2	Project Supervisor	{"edit_agents": true, "view_agents": true, "edit_classes": true, "view_classes": true, "edit_learners": true, "view_learners": true}
3	Instructor	{"view_classes": true, "view_learners": true, "submit_reports": true, "edit_attendance": true}
4	HR Manager	{"edit_agents": true, "view_agents": true, "view_learners": true}
5	Finance Officer	{"edit_orders": true, "view_orders": true, "edit_clients": true, "view_clients": true}
6	Admin Assistant	{"view_agents": true, "view_classes": true, "view_learners": true}
7	Quality Assurance	{"view_agents": true, "view_classes": true, "submit_qa_reports": true}
8	IT Support	{"edit_users": true, "view_users": true, "view_system_logs": true}
9	Admin	{"all": true}
10	Project Supervisor	{"edit_agents": true, "view_agents": true, "edit_classes": true, "view_classes": true, "edit_learners": true, "view_learners": true, "generate_reports": true}
11	Instructor	{"view_classes": true, "view_learners": true, "submit_reports": true, "edit_attendance": true, "view_course_materials": true}
12	HR Manager	{"edit_agents": true, "run_payroll": true, "view_agents": true, "view_learners": true, "manage_contracts": true}
13	Finance Officer	{"edit_orders": true, "view_orders": true, "edit_clients": true, "view_clients": true, "manage_invoices": true, "run_financial_reports": true}
14	Quality Assurance Specialist	{"view_agents": true, "view_classes": true, "conduct_qa_visits": true, "submit_qa_reports": true, "view_learner_progress": true}
15	Learner Support Coordinator	{"view_learners": true, "edit_learner_profiles": true, "view_progress_reports": true, "manage_support_requests": true}
16	Client Relationship Manager	{"edit_clients": true, "view_clients": true, "schedule_meetings": true, "manage_communications": true, "view_contract_details": true}
\.


--
-- TOC entry 4198 (class 0 OID 17550)
-- Dependencies: 285
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: John
--

COPY public.users (user_id, first_name, surname, email, cellphone_number, role, password_hash, created_at, updated_at) FROM stdin;
1	John	Smith	john.smith@example.com	+27821234567	Admin	hashed_password_1	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
2	Sarah	Johnson	sarah.johnson@example.com	+27829876543	Project Supervisor	hashed_password_2	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
3	Michael	Lee	michael.lee@example.com	+27823456789	Instructor	hashed_password_3	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
4	Emily	Brown	emily.brown@example.com	+27827654321	HR Manager	hashed_password_4	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
5	David	Nkosi	david.nkosi@example.com	+27825678901	Finance Officer	hashed_password_5	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
6	Thembi	Zulu	thembi.zulu@example.com	+27828901234	Project Supervisor	hashed_password_6	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
7	Robert	van der Merwe	robert.vdm@example.com	+27824567890	Instructor	hashed_password_7	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
8	Lerato	Moloi	lerato.moloi@example.com	+27820123456	Admin Assistant	hashed_password_8	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
9	Fatima	Patel	fatima.patel@example.com	+27826789012	Quality Assurance	hashed_password_9	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
10	Sipho	Ndlovu	sipho.ndlovu@example.com	+27823210987	IT Support	hashed_password_10	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
11	Anita	Naidoo	anita.naidoo@example.com	+27827890123	Project Supervisor	hashed_password_11	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
12	Trevor	Mkhize	trevor.mkhize@example.com	+27824321098	Instructor	hashed_password_12	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
13	Nomsa	Khumalo	nomsa.khumalo@example.com	+27828765432	HR Assistant	hashed_password_13	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
14	Pieter	Botha	pieter.botha@example.com	+27821098765	Finance Manager	hashed_password_14	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
15	Zandile	Dlamini	zandile.dlamini@example.com	+27825432109	Admin	hashed_password_15	2024-10-17 13:21:57.870205	2024-10-17 13:21:57.870205
16	System	Administrator	admin@wecoza.co.za	\N	admin	$2y$10$GVG9yU0QKmcFZ9lMQoNrC.ypY.lgzRgGLuZQEhXej4eBmZ/Dgrs4K	2025-05-29 17:05:46.655459	2025-05-29 20:42:30
\.


--
-- TOC entry 4200 (class 0 OID 17568)
-- Dependencies: 289
-- Data for Name: audit_log; Type: TABLE DATA; Schema: wecoza_events; Owner: John
--

COPY wecoza_events.audit_log (id, level, action, message, context, user_id, ip_address, user_agent, request_uri, created_at) FROM stdin;
1	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-21 11:29:37+02
2	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-21 11:29:38+02
3	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-21 11:30:16+02
4	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-21 11:30:18+02
5	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-21 15:31:21+02
6	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-21 15:31:23+02
7	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-22 09:53:15+02
8	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-22 09:53:17+02
9	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	102.67.178.186	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-22 14:03:26+02
10	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	102.67.178.186	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-22 14:03:28+02
11	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-24 11:57:58+02
12	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	::1	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-24 11:57:59+02
13	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	102.67.178.186	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-29 16:22:50+02
14	info	user_login	Admin user Laudes logged in	{"user_id": "1", "user_email": "laudes.michael@gmail.com", "user_login": "Laudes"}	1	102.67.178.186	Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36	/wecoza/wp-login.php	2025-09-29 16:22:52+02
\.


--
-- TOC entry 4202 (class 0 OID 17577)
-- Dependencies: 291
-- Data for Name: dashboard_status; Type: TABLE DATA; Schema: wecoza_events; Owner: John
--

COPY wecoza_events.dashboard_status (id, class_id, task_type, task_status, responsible_user_id, due_date, completed_at, completion_data, last_reminder, overdue_notified, created_at, updated_at) FROM stdin;
202	51	load_learners	pending	1	2025-09-24 17:47:03+02	\N	{}	\N	f	2025-09-21 17:47:04.02224+02	2025-09-21 17:47:04.02224+02
203	51	agent_order	pending	1	2025-09-26 17:47:04+02	\N	{}	\N	f	2025-09-21 17:47:05.020964+02	2025-09-21 17:47:05.020964+02
204	51	training_schedule	pending	1	2025-09-28 17:47:05+02	\N	{}	\N	f	2025-09-21 17:47:06.029249+02	2025-09-21 17:47:06.029249+02
205	51	material_delivery	pending	1	2025-10-01 17:47:06+02	\N	{}	\N	f	2025-09-21 17:47:07.017214+02	2025-09-21 17:47:07.017214+02
206	51	agent_paperwork	pending	1	2025-10-05 17:47:07+02	\N	{}	\N	f	2025-09-21 17:47:08.036527+02	2025-09-21 17:47:08.036527+02
207	51	supervisor_approval	pending	1	2025-09-23 17:47:08+02	\N	{}	\N	f	2025-09-21 17:47:09.035736+02	2025-09-21 17:47:09.035736+02
209	50	load_learners	pending	1	2025-09-24 17:47:14+02	\N	{}	\N	f	2025-09-21 17:47:15.107637+02	2025-09-21 17:47:15.107637+02
210	50	agent_order	pending	1	2025-09-26 17:47:15+02	\N	{}	\N	f	2025-09-21 17:47:16.115084+02	2025-09-21 17:47:16.115084+02
211	50	training_schedule	pending	1	2025-09-28 17:47:16+02	\N	{}	\N	f	2025-09-21 17:47:17.137646+02	2025-09-21 17:47:17.137646+02
212	50	material_delivery	pending	1	2025-10-01 17:47:17+02	\N	{}	\N	f	2025-09-21 17:47:18.138594+02	2025-09-21 17:47:18.138594+02
213	50	agent_paperwork	pending	1	2025-10-05 17:47:18+02	\N	{}	\N	f	2025-09-21 17:47:19.149235+02	2025-09-21 17:47:19.149235+02
214	50	supervisor_approval	pending	1	2025-09-23 17:47:19+02	\N	{}	\N	f	2025-09-21 17:47:20.152613+02	2025-09-21 17:47:20.152613+02
216	49	load_learners	pending	1	2025-09-24 17:47:25+02	\N	{}	\N	f	2025-09-21 17:47:26.237836+02	2025-09-21 17:47:26.237836+02
217	49	agent_order	pending	1	2025-09-26 17:47:26+02	\N	{}	\N	f	2025-09-21 17:47:27.265263+02	2025-09-21 17:47:27.265263+02
218	49	training_schedule	pending	1	2025-09-28 17:47:27+02	\N	{}	\N	f	2025-09-21 17:47:28.293247+02	2025-09-21 17:47:28.293247+02
219	49	material_delivery	pending	1	2025-10-01 17:47:28+02	\N	{}	\N	f	2025-09-21 17:47:29.310554+02	2025-09-21 17:47:29.310554+02
220	49	agent_paperwork	pending	1	2025-10-05 17:47:29+02	\N	{}	\N	f	2025-09-21 17:47:30.329657+02	2025-09-21 17:47:30.329657+02
221	49	supervisor_approval	pending	1	2025-09-23 17:47:30+02	\N	{}	\N	f	2025-09-21 17:47:31.322414+02	2025-09-21 17:47:31.322414+02
223	48	load_learners	pending	1	2025-09-24 17:47:36+02	\N	{}	\N	f	2025-09-21 17:47:37.365314+02	2025-09-21 17:47:37.365314+02
224	48	agent_order	pending	1	2025-09-26 17:47:37+02	\N	{}	\N	f	2025-09-21 17:47:38.363645+02	2025-09-21 17:47:38.363645+02
225	48	training_schedule	pending	1	2025-09-28 17:47:38+02	\N	{}	\N	f	2025-09-21 17:47:39.371265+02	2025-09-21 17:47:39.371265+02
226	48	material_delivery	pending	1	2025-10-01 17:47:39+02	\N	{}	\N	f	2025-09-21 17:47:40.368747+02	2025-09-21 17:47:40.368747+02
227	48	agent_paperwork	pending	1	2025-10-05 17:47:40+02	\N	{}	\N	f	2025-09-21 17:47:41.380995+02	2025-09-21 17:47:41.380995+02
228	48	supervisor_approval	pending	1	2025-09-23 17:47:41+02	\N	{}	\N	f	2025-09-21 17:47:42.377172+02	2025-09-21 17:47:42.377172+02
230	47	load_learners	pending	1	2025-09-24 17:47:47+02	\N	{}	\N	f	2025-09-21 17:47:48.473794+02	2025-09-21 17:47:48.473794+02
231	47	agent_order	pending	1	2025-09-26 17:47:48+02	\N	{}	\N	f	2025-09-21 17:47:49.475124+02	2025-09-21 17:47:49.475124+02
232	47	training_schedule	pending	1	2025-09-28 17:47:49+02	\N	{}	\N	f	2025-09-21 17:47:50.472641+02	2025-09-21 17:47:50.472641+02
233	47	material_delivery	pending	1	2025-10-01 17:47:50+02	\N	{}	\N	f	2025-09-21 17:47:51.474281+02	2025-09-21 17:47:51.474281+02
234	47	agent_paperwork	pending	1	2025-10-05 17:47:51+02	\N	{}	\N	f	2025-09-21 17:47:52.477699+02	2025-09-21 17:47:52.477699+02
235	47	supervisor_approval	pending	1	2025-09-23 17:47:52+02	\N	{}	\N	f	2025-09-21 17:47:53.48139+02	2025-09-21 17:47:53.48139+02
237	46	load_learners	pending	1	2025-09-24 17:47:58+02	\N	{}	\N	f	2025-09-21 17:47:59.476664+02	2025-09-21 17:47:59.476664+02
238	46	agent_order	pending	1	2025-09-26 17:47:59+02	\N	{}	\N	f	2025-09-21 17:48:00.477216+02	2025-09-21 17:48:00.477216+02
239	46	training_schedule	pending	1	2025-09-28 17:48:00+02	\N	{}	\N	f	2025-09-21 17:48:01.488229+02	2025-09-21 17:48:01.488229+02
240	46	material_delivery	pending	1	2025-10-01 17:48:01+02	\N	{}	\N	f	2025-09-21 17:48:02.500148+02	2025-09-21 17:48:02.500148+02
241	46	agent_paperwork	pending	1	2025-10-05 17:48:02+02	\N	{}	\N	f	2025-09-21 17:48:03.509185+02	2025-09-21 17:48:03.509185+02
242	46	supervisor_approval	pending	1	2025-09-23 17:48:03+02	\N	{}	\N	f	2025-09-21 17:48:04.507941+02	2025-09-21 17:48:04.507941+02
244	45	load_learners	pending	1	2025-09-24 17:48:09+02	\N	{}	\N	f	2025-09-21 17:48:10.545815+02	2025-09-21 17:48:10.545815+02
245	45	agent_order	pending	1	2025-09-26 17:48:10+02	\N	{}	\N	f	2025-09-21 17:48:11.550231+02	2025-09-21 17:48:11.550231+02
246	45	training_schedule	pending	1	2025-09-28 17:48:11+02	\N	{}	\N	f	2025-09-21 17:48:12.548842+02	2025-09-21 17:48:12.548842+02
247	45	material_delivery	pending	1	2025-10-01 17:48:12+02	\N	{}	\N	f	2025-09-21 17:48:13.542695+02	2025-09-21 17:48:13.542695+02
248	45	agent_paperwork	pending	1	2025-10-05 17:48:13+02	\N	{}	\N	f	2025-09-21 17:48:14.537722+02	2025-09-21 17:48:14.537722+02
249	45	supervisor_approval	pending	1	2025-09-23 17:48:14+02	\N	{}	\N	f	2025-09-21 17:48:15.548141+02	2025-09-21 17:48:15.548141+02
251	44	load_learners	pending	1	2025-09-24 17:48:20+02	\N	{}	\N	f	2025-09-21 17:48:21.569714+02	2025-09-21 17:48:21.569714+02
252	44	agent_order	pending	1	2025-09-26 17:48:21+02	\N	{}	\N	f	2025-09-21 17:48:22.558818+02	2025-09-21 17:48:22.558818+02
253	44	training_schedule	pending	1	2025-09-28 17:48:22+02	\N	{}	\N	f	2025-09-21 17:48:23.560301+02	2025-09-21 17:48:23.560301+02
254	44	material_delivery	pending	1	2025-10-01 17:48:23+02	\N	{}	\N	f	2025-09-21 17:48:24.58679+02	2025-09-21 17:48:24.58679+02
255	44	agent_paperwork	pending	1	2025-10-05 17:48:24+02	\N	{}	\N	f	2025-09-21 17:48:25.595877+02	2025-09-21 17:48:25.595877+02
256	44	supervisor_approval	pending	1	2025-09-23 17:48:25+02	\N	{}	\N	f	2025-09-21 17:48:26.610683+02	2025-09-21 17:48:26.610683+02
215	50	class_created	open	5	\N	\N	{"site_id": 2, "client_id": 5, "synced_at": "2025-09-22 16:21:34", "class_code": "5-GETC-NL4-2025-07-18-12-41", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-18 10:43:53", "class_updated_at": "2025-07-18 10:43:53", "responsible_user_id": 5}	\N	f	2025-09-21 17:47:21.167747+02	2025-09-22 16:21:34.375505+02
222	49	class_created	open	4	\N	\N	{"site_id": 6, "client_id": 2, "synced_at": "2025-09-22 16:21:34", "class_code": "2-BA2-BA2LP1-2025-07-15-19-26", "class_type": "BA2", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-15 17:29:08", "class_updated_at": "2025-07-15 17:29:08", "responsible_user_id": 4}	\N	f	2025-09-21 17:47:32.317713+02	2025-09-22 16:21:34.898807+02
229	48	class_created	open	4	\N	\N	{"site_id": 6, "client_id": 2, "synced_at": "2025-09-22 16:21:35", "class_code": "2-SKILL-WALK-2025-07-07-21-10", "class_type": "SKILL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-07 19:13:18", "class_updated_at": "2025-07-15 14:16:50", "responsible_user_id": 4}	\N	f	2025-09-21 17:47:43.365768+02	2025-09-22 16:21:35.457533+02
236	47	class_created	open	4	\N	\N	{"site_id": 15, "client_id": 5, "synced_at": "2025-09-22 16:21:35", "class_code": "5-GETC-CL4-2025-07-03-10-07", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-03 08:10:27", "class_updated_at": "2025-07-03 08:10:27", "responsible_user_id": 4}	\N	f	2025-09-21 17:47:54.482354+02	2025-09-22 16:21:35.996274+02
243	46	class_created	open	2	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:36", "class_code": "2-AET-COMM_NUM-2025-07-02-19-56", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 17:57:27", "class_updated_at": "2025-07-02 17:57:27", "responsible_user_id": 2}	\N	f	2025-09-21 17:48:05.509704+02	2025-09-22 16:21:36.533791+02
250	45	class_created	open	3	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:36", "class_code": "2-AET-COMM_NUM-2025-07-02-18-40", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 16:43:35", "class_updated_at": "2025-07-02 16:43:35", "responsible_user_id": 3}	\N	f	2025-09-21 17:48:16.546163+02	2025-09-22 16:21:37.064108+02
258	43	load_learners	pending	1	2025-09-24 17:48:31+02	\N	{}	\N	f	2025-09-21 17:48:32.653687+02	2025-09-21 17:48:32.653687+02
259	43	agent_order	pending	1	2025-09-26 17:48:32+02	\N	{}	\N	f	2025-09-21 17:48:33.651182+02	2025-09-21 17:48:33.651182+02
260	43	training_schedule	pending	1	2025-09-28 17:48:33+02	\N	{}	\N	f	2025-09-21 17:48:34.638655+02	2025-09-21 17:48:34.638655+02
261	43	material_delivery	pending	1	2025-10-01 17:48:34+02	\N	{}	\N	f	2025-09-21 17:48:35.642872+02	2025-09-21 17:48:35.642872+02
262	43	agent_paperwork	pending	1	2025-10-05 17:48:35+02	\N	{}	\N	f	2025-09-21 17:48:36.643646+02	2025-09-21 17:48:36.643646+02
263	43	supervisor_approval	pending	1	2025-09-23 17:48:36+02	\N	{}	\N	f	2025-09-21 17:48:37.644351+02	2025-09-21 17:48:37.644351+02
265	42	load_learners	pending	1	2025-09-24 17:48:42+02	\N	{}	\N	f	2025-09-21 17:48:43.64334+02	2025-09-21 17:48:43.64334+02
266	42	agent_order	pending	1	2025-09-26 17:48:43+02	\N	{}	\N	f	2025-09-21 17:48:44.665058+02	2025-09-21 17:48:44.665058+02
267	42	training_schedule	pending	1	2025-09-28 17:48:44+02	\N	{}	\N	f	2025-09-21 17:48:45.687875+02	2025-09-21 17:48:45.687875+02
268	42	material_delivery	pending	1	2025-10-01 17:48:45+02	\N	{}	\N	f	2025-09-21 17:48:46.686542+02	2025-09-21 17:48:46.686542+02
269	42	agent_paperwork	pending	1	2025-10-05 17:48:46+02	\N	{}	\N	f	2025-09-21 17:48:47.682709+02	2025-09-21 17:48:47.682709+02
270	42	supervisor_approval	pending	1	2025-09-23 17:48:47+02	\N	{}	\N	f	2025-09-21 17:48:48.681714+02	2025-09-21 17:48:48.681714+02
272	41	load_learners	pending	1	2025-09-24 17:48:53+02	\N	{}	\N	f	2025-09-21 17:48:54.712325+02	2025-09-21 17:48:54.712325+02
273	41	agent_order	pending	1	2025-09-26 17:48:54+02	\N	{}	\N	f	2025-09-21 17:48:55.716268+02	2025-09-21 17:48:55.716268+02
274	41	training_schedule	pending	1	2025-09-28 17:48:55+02	\N	{}	\N	f	2025-09-21 17:48:56.713596+02	2025-09-21 17:48:56.713596+02
275	41	material_delivery	pending	1	2025-10-01 17:48:56+02	\N	{}	\N	f	2025-09-21 17:48:57.718359+02	2025-09-21 17:48:57.718359+02
276	41	agent_paperwork	pending	1	2025-10-05 17:48:57+02	\N	{}	\N	f	2025-09-21 17:48:58.71769+02	2025-09-21 17:48:58.71769+02
277	41	supervisor_approval	pending	1	2025-09-23 17:48:58+02	\N	{}	\N	f	2025-09-21 17:48:59.714711+02	2025-09-21 17:48:59.714711+02
279	40	load_learners	pending	1	2025-09-24 17:49:05+02	\N	{}	\N	f	2025-09-21 17:49:05.761159+02	2025-09-21 17:49:05.761159+02
280	40	agent_order	pending	1	2025-09-26 17:49:06+02	\N	{}	\N	f	2025-09-21 17:49:06.77228+02	2025-09-21 17:49:06.77228+02
281	40	training_schedule	pending	1	2025-09-28 17:49:07+02	\N	{}	\N	f	2025-09-21 17:49:07.774048+02	2025-09-21 17:49:07.774048+02
282	40	material_delivery	pending	1	2025-10-01 17:49:08+02	\N	{}	\N	f	2025-09-21 17:49:08.766259+02	2025-09-21 17:49:08.766259+02
283	40	agent_paperwork	pending	1	2025-10-05 17:49:09+02	\N	{}	\N	f	2025-09-21 17:49:09.768239+02	2025-09-21 17:49:09.768239+02
284	40	supervisor_approval	pending	1	2025-09-23 17:49:10+02	\N	{}	\N	f	2025-09-21 17:49:10.776756+02	2025-09-21 17:49:10.776756+02
286	39	load_learners	pending	1	2025-09-24 17:49:16+02	\N	{}	\N	f	2025-09-21 17:49:16.849932+02	2025-09-21 17:49:16.849932+02
287	39	agent_order	pending	1	2025-09-26 17:49:17+02	\N	{}	\N	f	2025-09-21 17:49:17.85627+02	2025-09-21 17:49:17.85627+02
288	39	training_schedule	pending	1	2025-09-28 17:49:18+02	\N	{}	\N	f	2025-09-21 17:49:18.95086+02	2025-09-21 17:49:18.95086+02
289	39	material_delivery	pending	1	2025-10-01 17:49:19+02	\N	{}	\N	f	2025-09-21 17:49:19.975723+02	2025-09-21 17:49:19.975723+02
290	39	agent_paperwork	pending	1	2025-10-05 17:49:20+02	\N	{}	\N	f	2025-09-21 17:49:20.975717+02	2025-09-21 17:49:20.975717+02
291	39	supervisor_approval	pending	1	2025-09-23 17:49:21+02	\N	{}	\N	f	2025-09-21 17:49:21.980414+02	2025-09-21 17:49:21.980414+02
293	38	load_learners	pending	1	2025-09-24 17:49:27+02	\N	{}	\N	f	2025-09-21 17:49:28.431255+02	2025-09-21 17:49:28.431255+02
294	38	agent_order	pending	1	2025-09-26 17:49:28+02	\N	{}	\N	f	2025-09-21 17:49:29.428585+02	2025-09-21 17:49:29.428585+02
295	38	training_schedule	pending	1	2025-09-28 17:49:29+02	\N	{}	\N	f	2025-09-21 17:49:30.43538+02	2025-09-21 17:49:30.43538+02
296	38	material_delivery	pending	1	2025-10-01 17:49:30+02	\N	{}	\N	f	2025-09-21 17:49:31.447879+02	2025-09-21 17:49:31.447879+02
297	38	agent_paperwork	pending	1	2025-10-05 17:49:31+02	\N	{}	\N	f	2025-09-21 17:49:32.447404+02	2025-09-21 17:49:32.447404+02
298	38	supervisor_approval	pending	1	2025-09-23 17:49:32+02	\N	{}	\N	f	2025-09-21 17:49:33.463558+02	2025-09-21 17:49:33.463558+02
300	37	load_learners	pending	1	2025-09-24 17:49:38+02	\N	{}	\N	f	2025-09-21 17:49:39.486746+02	2025-09-21 17:49:39.486746+02
301	37	agent_order	pending	1	2025-09-26 17:49:39+02	\N	{}	\N	f	2025-09-21 17:49:40.504666+02	2025-09-21 17:49:40.504666+02
302	37	training_schedule	pending	1	2025-09-28 17:49:40+02	\N	{}	\N	f	2025-09-21 17:49:41.512268+02	2025-09-21 17:49:41.512268+02
303	37	material_delivery	pending	1	2025-10-01 17:49:41+02	\N	{}	\N	f	2025-09-21 17:49:42.519731+02	2025-09-21 17:49:42.519731+02
304	37	agent_paperwork	pending	1	2025-10-05 17:49:42+02	\N	{}	\N	f	2025-09-21 17:49:43.528927+02	2025-09-21 17:49:43.528927+02
305	37	supervisor_approval	pending	1	2025-09-23 17:49:43+02	\N	{}	\N	f	2025-09-21 17:49:44.536734+02	2025-09-21 17:49:44.536734+02
307	36	load_learners	pending	1	2025-09-24 17:49:49+02	\N	{}	\N	f	2025-09-21 17:49:50.580051+02	2025-09-21 17:49:50.580051+02
308	36	agent_order	pending	1	2025-09-26 17:49:50+02	\N	{}	\N	f	2025-09-21 17:49:51.586793+02	2025-09-21 17:49:51.586793+02
309	36	training_schedule	pending	1	2025-09-28 17:49:51+02	\N	{}	\N	f	2025-09-21 17:49:52.591414+02	2025-09-21 17:49:52.591414+02
271	42	class_created	open	2	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:38", "class_code": "2-AET-COMM_NUM-2025-07-02-17-24", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 15:27:34", "class_updated_at": "2025-07-02 15:27:34", "responsible_user_id": 2}	\N	f	2025-09-21 17:48:49.691325+02	2025-09-22 16:21:38.662461+02
292	39	class_created	open	5	\N	\N	{"site_id": 8, "client_id": 2, "synced_at": "2025-09-22 16:21:40", "class_code": "2-AET-COMM_NUM-2025-07-01-15-41", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 13:43:01", "class_updated_at": "2025-07-01 13:43:01", "responsible_user_id": 5}	\N	f	2025-09-21 17:49:22.979142+02	2025-09-22 16:21:40.265964+02
278	41	class_created	open	2	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:38", "class_code": "2-AET-COMM_NUM-2025-07-02-16-54", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 14:58:13", "class_updated_at": "2025-07-02 14:58:13", "responsible_user_id": 2}	\N	f	2025-09-21 17:49:00.719705+02	2025-09-22 16:21:39.199311+02
299	38	class_created	open	5	\N	\N	{"site_id": 21, "client_id": 5, "synced_at": "2025-09-22 16:21:40", "class_code": "5-AET-COMM_NUM-2025-07-01-15-15", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 13:18:50", "class_updated_at": "2025-07-01 13:18:50", "responsible_user_id": 5}	\N	f	2025-09-21 17:49:34.461424+02	2025-09-22 16:21:40.795294+02
306	37	class_created	open	4	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:41", "class_code": "2-AET-COMM_NUM-2025-07-01-14-55", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 12:57:57", "class_updated_at": "2025-07-01 12:57:57", "responsible_user_id": 4}	\N	f	2025-09-21 17:49:45.544399+02	2025-09-22 16:21:41.328688+02
264	43	class_created	open	2	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:37", "class_code": "2-AET-COMM_NUM-2025-07-02-17-48", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 15:51:48", "class_updated_at": "2025-07-02 15:51:48", "responsible_user_id": 2}	\N	f	2025-09-21 17:48:38.640367+02	2025-09-22 16:21:38.132177+02
310	36	material_delivery	pending	1	2025-10-01 17:49:52+02	\N	{}	\N	f	2025-09-21 17:49:53.592715+02	2025-09-21 17:49:53.592715+02
311	36	agent_paperwork	pending	1	2025-10-05 17:49:53+02	\N	{}	\N	f	2025-09-21 17:49:54.597074+02	2025-09-21 17:49:54.597074+02
312	36	supervisor_approval	pending	1	2025-09-23 17:49:54+02	\N	{}	\N	f	2025-09-21 17:49:55.602878+02	2025-09-21 17:49:55.602878+02
314	35	load_learners	pending	1	2025-09-24 17:50:00+02	\N	{}	\N	f	2025-09-21 17:50:01.615768+02	2025-09-21 17:50:01.615768+02
315	35	agent_order	pending	1	2025-09-26 17:50:01+02	\N	{}	\N	f	2025-09-21 17:50:02.620733+02	2025-09-21 17:50:02.620733+02
316	35	training_schedule	pending	1	2025-09-28 17:50:02+02	\N	{}	\N	f	2025-09-21 17:50:03.617419+02	2025-09-21 17:50:03.617419+02
317	35	material_delivery	pending	1	2025-10-01 17:50:03+02	\N	{}	\N	f	2025-09-21 17:50:04.622441+02	2025-09-21 17:50:04.622441+02
318	35	agent_paperwork	pending	1	2025-10-05 17:50:04+02	\N	{}	\N	f	2025-09-21 17:50:05.623298+02	2025-09-21 17:50:05.623298+02
319	35	supervisor_approval	pending	1	2025-09-23 17:50:05+02	\N	{}	\N	f	2025-09-21 17:50:06.650549+02	2025-09-21 17:50:06.650549+02
321	34	load_learners	pending	1	2025-09-24 17:50:11+02	\N	{}	\N	f	2025-09-21 17:50:12.729004+02	2025-09-21 17:50:12.729004+02
322	34	agent_order	pending	1	2025-09-26 17:50:12+02	\N	{}	\N	f	2025-09-21 17:50:13.725468+02	2025-09-21 17:50:13.725468+02
323	34	training_schedule	pending	1	2025-09-28 17:50:13+02	\N	{}	\N	f	2025-09-21 17:50:14.753376+02	2025-09-21 17:50:14.753376+02
324	34	material_delivery	pending	1	2025-10-01 17:50:15+02	\N	{}	\N	f	2025-09-21 17:50:15.769689+02	2025-09-21 17:50:15.769689+02
325	34	agent_paperwork	pending	1	2025-10-05 17:50:16+02	\N	{}	\N	f	2025-09-21 17:50:16.766399+02	2025-09-21 17:50:16.766399+02
326	34	supervisor_approval	pending	1	2025-09-23 17:50:17+02	\N	{}	\N	f	2025-09-21 17:50:17.768773+02	2025-09-21 17:50:17.768773+02
328	33	load_learners	pending	1	2025-09-24 17:50:23+02	\N	{}	\N	f	2025-09-21 17:50:23.823446+02	2025-09-21 17:50:23.823446+02
329	33	agent_order	pending	1	2025-09-26 17:50:24+02	\N	{}	\N	f	2025-09-21 17:50:24.816628+02	2025-09-21 17:50:24.816628+02
330	33	training_schedule	pending	1	2025-09-28 17:50:25+02	\N	{}	\N	f	2025-09-21 17:50:25.816163+02	2025-09-21 17:50:25.816163+02
331	33	material_delivery	pending	1	2025-10-01 17:50:26+02	\N	{}	\N	f	2025-09-21 17:50:26.822295+02	2025-09-21 17:50:26.822295+02
332	33	agent_paperwork	pending	1	2025-10-05 17:50:27+02	\N	{}	\N	f	2025-09-21 17:50:27.818006+02	2025-09-21 17:50:27.818006+02
333	33	supervisor_approval	pending	1	2025-09-23 17:50:28+02	\N	{}	\N	f	2025-09-21 17:50:28.828399+02	2025-09-21 17:50:28.828399+02
335	32	load_learners	pending	1	2025-09-24 17:50:34+02	\N	{}	\N	f	2025-09-21 17:50:34.856364+02	2025-09-21 17:50:34.856364+02
336	32	agent_order	pending	1	2025-09-26 17:50:35+02	\N	{}	\N	f	2025-09-21 17:50:35.861838+02	2025-09-21 17:50:35.861838+02
337	32	training_schedule	pending	1	2025-09-28 17:50:36+02	\N	{}	\N	f	2025-09-21 17:50:36.857649+02	2025-09-21 17:50:36.857649+02
338	32	material_delivery	pending	1	2025-10-01 17:50:37+02	\N	{}	\N	f	2025-09-21 17:50:37.860109+02	2025-09-21 17:50:37.860109+02
339	32	agent_paperwork	pending	1	2025-10-05 17:50:38+02	\N	{}	\N	f	2025-09-21 17:50:38.862324+02	2025-09-21 17:50:38.862324+02
340	32	supervisor_approval	pending	1	2025-09-23 17:50:39+02	\N	{}	\N	f	2025-09-21 17:50:39.877486+02	2025-09-21 17:50:39.877486+02
342	31	load_learners	pending	1	2025-09-24 17:50:45+02	\N	{}	\N	f	2025-09-21 17:50:45.921781+02	2025-09-21 17:50:45.921781+02
343	31	agent_order	pending	1	2025-09-26 17:50:46+02	\N	{}	\N	f	2025-09-21 17:50:46.923678+02	2025-09-21 17:50:46.923678+02
344	31	training_schedule	pending	1	2025-09-28 17:50:47+02	\N	{}	\N	f	2025-09-21 17:50:47.922329+02	2025-09-21 17:50:47.922329+02
345	31	material_delivery	pending	1	2025-10-01 17:50:48+02	\N	{}	\N	f	2025-09-21 17:50:48.957808+02	2025-09-21 17:50:48.957808+02
346	31	agent_paperwork	pending	1	2025-10-05 17:50:49+02	\N	{}	\N	f	2025-09-21 17:50:49.958387+02	2025-09-21 17:50:49.958387+02
347	31	supervisor_approval	pending	1	2025-09-23 17:50:50+02	\N	{}	\N	f	2025-09-21 17:50:50.964531+02	2025-09-21 17:50:50.964531+02
349	30	load_learners	pending	1	2025-09-24 17:50:56+02	\N	{}	\N	f	2025-09-21 17:50:56.990105+02	2025-09-21 17:50:56.990105+02
350	30	agent_order	pending	1	2025-09-26 17:50:57+02	\N	{}	\N	f	2025-09-21 17:50:57.987292+02	2025-09-21 17:50:57.987292+02
351	30	training_schedule	pending	1	2025-09-28 17:50:58+02	\N	{}	\N	f	2025-09-21 17:50:59.004849+02	2025-09-21 17:50:59.004849+02
352	30	material_delivery	pending	1	2025-10-01 17:50:59+02	\N	{}	\N	f	2025-09-21 17:51:00.018104+02	2025-09-21 17:51:00.018104+02
353	30	agent_paperwork	pending	1	2025-10-05 17:51:00+02	\N	{}	\N	f	2025-09-21 17:51:01.016033+02	2025-09-21 17:51:01.016033+02
354	30	supervisor_approval	pending	1	2025-09-23 17:51:01+02	\N	{}	\N	f	2025-09-21 17:51:02.009505+02	2025-09-21 17:51:02.009505+02
356	29	load_learners	pending	1	2025-09-24 17:51:07+02	\N	{}	\N	f	2025-09-21 17:51:08.046167+02	2025-09-21 17:51:08.046167+02
357	29	agent_order	pending	1	2025-09-26 17:51:08+02	\N	{}	\N	f	2025-09-21 17:51:09.040786+02	2025-09-21 17:51:09.040786+02
358	29	training_schedule	pending	1	2025-09-28 17:51:09+02	\N	{}	\N	f	2025-09-21 17:51:10.055383+02	2025-09-21 17:51:10.055383+02
359	29	material_delivery	pending	1	2025-10-01 17:51:10+02	\N	{}	\N	f	2025-09-21 17:51:11.070317+02	2025-09-21 17:51:11.070317+02
360	29	agent_paperwork	pending	1	2025-10-05 17:51:11+02	\N	{}	\N	f	2025-09-21 17:51:12.06692+02	2025-09-21 17:51:12.06692+02
361	29	supervisor_approval	pending	1	2025-09-23 17:51:12+02	\N	{}	\N	f	2025-09-21 17:51:13.076387+02	2025-09-21 17:51:13.076387+02
355	30	class_created	open	1	\N	\N	{"site_id": 0, "client_id": 14, "synced_at": "2025-09-22 16:21:44", "class_code": "14-GETC-SMME4-2025-06-04-19-39", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:41:53", "class_updated_at": "2025-06-04 17:41:53", "responsible_user_id": 1}	\N	f	2025-09-21 17:51:03.01288+02	2025-09-22 16:21:45.046881+02
341	32	class_created	open	4	\N	\N	{"site_id": 0, "client_id": 14, "synced_at": "2025-09-22 16:21:43", "class_code": "14-REALLL-RLC-2025-06-04-20-45", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 18:47:47", "class_updated_at": "2025-06-04 18:47:47", "responsible_user_id": 4}	\N	f	2025-09-21 17:50:40.884584+02	2025-09-22 16:21:43.966803+02
334	33	class_created	open	1	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:43", "class_code": "2-AET-COMM_NUM-2025-06-30-12-19", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 10:21:40", "class_updated_at": "2025-06-30 10:21:40", "responsible_user_id": 1}	\N	f	2025-09-21 17:50:29.826533+02	2025-09-22 16:21:43.439217+02
362	29	class_created	open	11	\N	\N	{"site_id": 0, "client_id": 14, "synced_at": "2025-09-22 16:21:45", "class_code": "14-GETC-SMME4-2025-06-04-19-18", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:21:05", "class_updated_at": "2025-06-04 17:21:05", "responsible_user_id": 11}	\N	f	2025-09-21 17:51:14.078181+02	2025-09-22 16:21:45.575243+02
348	31	class_created	open	3	\N	\N	{"site_id": 0, "client_id": 11, "synced_at": "2025-09-22 16:21:44", "class_code": "11-AET-COMM_NUM-2025-06-04-19-50", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:52:43", "class_updated_at": "2025-06-04 17:52:43", "responsible_user_id": 3}	\N	f	2025-09-21 17:50:51.958635+02	2025-09-22 16:21:44.513878+02
320	35	class_created	open	2	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:42", "class_code": "2-AET-COMM_NUM-2025-06-30-19-32", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 17:34:38", "class_updated_at": "2025-06-30 17:34:38", "responsible_user_id": 2}	\N	f	2025-09-21 17:50:07.652317+02	2025-09-22 16:21:42.390387+02
363	28	load_learners	pending	1	2025-09-24 17:51:18+02	\N	{}	\N	f	2025-09-21 17:51:19.088442+02	2025-09-21 17:51:19.088442+02
364	28	agent_order	pending	1	2025-09-26 17:51:19+02	\N	{}	\N	f	2025-09-21 17:51:20.102833+02	2025-09-21 17:51:20.102833+02
365	28	training_schedule	pending	1	2025-09-28 17:51:20+02	\N	{}	\N	f	2025-09-21 17:51:21.098807+02	2025-09-21 17:51:21.098807+02
366	28	material_delivery	pending	1	2025-10-01 17:51:21+02	\N	{}	\N	f	2025-09-21 17:51:22.096224+02	2025-09-21 17:51:22.096224+02
367	28	agent_paperwork	pending	1	2025-10-05 17:51:22+02	\N	{}	\N	f	2025-09-21 17:51:23.105313+02	2025-09-21 17:51:23.105313+02
368	28	supervisor_approval	pending	1	2025-09-23 17:51:23+02	\N	{}	\N	f	2025-09-21 17:51:24.107378+02	2025-09-21 17:51:24.107378+02
370	27	load_learners	pending	1	2025-09-24 17:51:29+02	\N	{}	\N	f	2025-09-21 17:51:30.109656+02	2025-09-21 17:51:30.109656+02
371	27	agent_order	pending	1	2025-09-26 17:51:30+02	\N	{}	\N	f	2025-09-21 17:51:31.109294+02	2025-09-21 17:51:31.109294+02
372	27	training_schedule	pending	1	2025-09-28 17:51:31+02	\N	{}	\N	f	2025-09-21 17:51:32.108103+02	2025-09-21 17:51:32.108103+02
373	27	material_delivery	pending	1	2025-10-01 17:51:32+02	\N	{}	\N	f	2025-09-21 17:51:33.118627+02	2025-09-21 17:51:33.118627+02
374	27	agent_paperwork	pending	1	2025-10-05 17:51:33+02	\N	{}	\N	f	2025-09-21 17:51:34.123345+02	2025-09-21 17:51:34.123345+02
375	27	supervisor_approval	pending	1	2025-09-23 17:51:34+02	\N	{}	\N	f	2025-09-21 17:51:35.115866+02	2025-09-21 17:51:35.115866+02
377	26	load_learners	pending	1	2025-09-24 17:51:40+02	\N	{}	\N	f	2025-09-21 17:51:41.140045+02	2025-09-21 17:51:41.140045+02
378	26	agent_order	pending	1	2025-09-26 17:51:41+02	\N	{}	\N	f	2025-09-21 17:51:42.145604+02	2025-09-21 17:51:42.145604+02
379	26	training_schedule	pending	1	2025-09-28 17:51:42+02	\N	{}	\N	f	2025-09-21 17:51:43.157938+02	2025-09-21 17:51:43.157938+02
380	26	material_delivery	pending	1	2025-10-01 17:51:43+02	\N	{}	\N	f	2025-09-21 17:51:44.164906+02	2025-09-21 17:51:44.164906+02
381	26	agent_paperwork	pending	1	2025-10-05 17:51:44+02	\N	{}	\N	f	2025-09-21 17:51:45.164982+02	2025-09-21 17:51:45.164982+02
382	26	supervisor_approval	pending	1	2025-09-23 17:51:45+02	\N	{}	\N	f	2025-09-21 17:51:46.163132+02	2025-09-21 17:51:46.163132+02
384	24	load_learners	pending	1	2025-09-24 17:51:51+02	\N	{}	\N	f	2025-09-21 17:51:52.195116+02	2025-09-21 17:51:52.195116+02
385	24	agent_order	pending	1	2025-09-26 17:51:52+02	\N	{}	\N	f	2025-09-21 17:51:53.197703+02	2025-09-21 17:51:53.197703+02
386	24	training_schedule	pending	1	2025-09-28 17:51:53+02	\N	{}	\N	f	2025-09-21 17:51:54.191636+02	2025-09-21 17:51:54.191636+02
387	24	material_delivery	pending	1	2025-10-01 17:51:54+02	\N	{}	\N	f	2025-09-21 17:51:55.194646+02	2025-09-21 17:51:55.194646+02
388	24	agent_paperwork	pending	1	2025-10-05 17:51:55+02	\N	{}	\N	f	2025-09-21 17:51:56.204562+02	2025-09-21 17:51:56.204562+02
389	24	supervisor_approval	pending	1	2025-09-23 17:51:56+02	\N	{}	\N	f	2025-09-21 17:51:57.200284+02	2025-09-21 17:51:57.200284+02
391	23	load_learners	pending	1	2025-09-24 17:52:02+02	\N	{}	\N	f	2025-09-21 17:52:03.253498+02	2025-09-21 17:52:03.253498+02
392	23	agent_order	pending	1	2025-09-26 17:52:03+02	\N	{}	\N	f	2025-09-21 17:52:04.262628+02	2025-09-21 17:52:04.262628+02
393	23	training_schedule	pending	1	2025-09-28 17:52:04+02	\N	{}	\N	f	2025-09-21 17:52:05.25915+02	2025-09-21 17:52:05.25915+02
394	23	material_delivery	pending	1	2025-10-01 17:52:05+02	\N	{}	\N	f	2025-09-21 17:52:06.263449+02	2025-09-21 17:52:06.263449+02
395	23	agent_paperwork	pending	1	2025-10-05 17:52:06+02	\N	{}	\N	f	2025-09-21 17:52:07.264429+02	2025-09-21 17:52:07.264429+02
396	23	supervisor_approval	pending	1	2025-09-23 17:52:07+02	\N	{}	\N	f	2025-09-21 17:52:08.270698+02	2025-09-21 17:52:08.270698+02
398	22	load_learners	pending	1	2025-09-24 17:52:13+02	\N	{}	\N	f	2025-09-21 17:52:14.291687+02	2025-09-21 17:52:14.291687+02
399	22	agent_order	pending	1	2025-09-26 17:52:14+02	\N	{}	\N	f	2025-09-21 17:52:15.28843+02	2025-09-21 17:52:15.28843+02
400	22	training_schedule	pending	1	2025-09-28 17:52:15+02	\N	{}	\N	f	2025-09-21 17:52:16.295477+02	2025-09-21 17:52:16.295477+02
401	22	material_delivery	pending	1	2025-10-01 17:52:16+02	\N	{}	\N	f	2025-09-21 17:52:17.304417+02	2025-09-21 17:52:17.304417+02
402	22	agent_paperwork	pending	1	2025-10-05 17:52:17+02	\N	{}	\N	f	2025-09-21 17:52:18.306433+02	2025-09-21 17:52:18.306433+02
403	22	supervisor_approval	pending	1	2025-09-23 17:52:18+02	\N	{}	\N	f	2025-09-21 17:52:19.315989+02	2025-09-21 17:52:19.315989+02
405	21	load_learners	pending	1	2025-09-24 17:52:24+02	\N	{}	\N	f	2025-09-21 17:52:25.318313+02	2025-09-21 17:52:25.318313+02
406	21	agent_order	pending	1	2025-09-26 17:52:25+02	\N	{}	\N	f	2025-09-21 17:52:26.319358+02	2025-09-21 17:52:26.319358+02
407	21	training_schedule	pending	1	2025-09-28 17:52:26+02	\N	{}	\N	f	2025-09-21 17:52:27.317927+02	2025-09-21 17:52:27.317927+02
408	21	material_delivery	pending	1	2025-10-01 17:52:27+02	\N	{}	\N	f	2025-09-21 17:52:28.322484+02	2025-09-21 17:52:28.322484+02
409	21	agent_paperwork	pending	1	2025-10-05 17:52:28+02	\N	{}	\N	f	2025-09-21 17:52:29.318366+02	2025-09-21 17:52:29.318366+02
410	21	supervisor_approval	pending	1	2025-09-23 17:52:29+02	\N	{}	\N	f	2025-09-21 17:52:30.32268+02	2025-09-21 17:52:30.32268+02
412	20	load_learners	pending	1	2025-09-24 17:52:35+02	\N	{}	\N	f	2025-09-21 17:52:36.333335+02	2025-09-21 17:52:36.333335+02
413	20	agent_order	pending	1	2025-09-26 17:52:36+02	\N	{}	\N	f	2025-09-21 17:52:37.323993+02	2025-09-21 17:52:37.323993+02
414	20	training_schedule	pending	1	2025-09-28 17:52:37+02	\N	{}	\N	f	2025-09-21 17:52:38.319926+02	2025-09-21 17:52:38.319926+02
415	20	material_delivery	pending	1	2025-10-01 17:52:38+02	\N	{}	\N	f	2025-09-21 17:52:39.342311+02	2025-09-21 17:52:39.342311+02
416	20	agent_paperwork	pending	1	2025-10-05 17:52:39+02	\N	{}	\N	f	2025-09-21 17:52:40.349453+02	2025-09-21 17:52:40.349453+02
417	20	supervisor_approval	pending	1	2025-09-23 17:52:40+02	\N	{}	\N	f	2025-09-21 17:52:41.352507+02	2025-09-21 17:52:41.352507+02
472	5	agent_paperwork	pending	1	2025-10-05 17:54:08+02	\N	{}	\N	f	2025-09-21 17:54:09.01822+02	2025-09-21 17:54:09.01822+02
390	24	class_created	open	3	\N	\N	{"site_id": 0, "client_id": 11, "synced_at": "2025-09-22 16:21:47", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-29 09:20:59", "class_updated_at": "2025-05-29 09:20:59", "responsible_user_id": 3}	\N	f	2025-09-21 17:51:58.215121+02	2025-09-22 16:21:47.694231+02
397	23	class_created	open	8	\N	\N	{"site_id": 0, "client_id": 11, "synced_at": "2025-09-22 16:21:47", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-29 07:12:33", "class_updated_at": "2025-05-29 07:12:33", "responsible_user_id": 8}	\N	f	2025-09-21 17:52:09.263599+02	2025-09-22 16:21:48.216763+02
404	22	class_created	open	8	\N	\N	{"site_id": 0, "client_id": 11, "synced_at": "2025-09-22 16:21:48", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 17:50:04", "class_updated_at": "2025-05-28 17:50:04", "responsible_user_id": 8}	\N	f	2025-09-21 17:52:20.310221+02	2025-09-22 16:21:48.751409+02
411	21	class_created	open	1	\N	\N	{"site_id": 0, "client_id": 11, "synced_at": "2025-09-22 16:21:49", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 17:30:59", "class_updated_at": "2025-05-28 17:30:59", "responsible_user_id": 1}	\N	f	2025-09-21 17:52:31.317145+02	2025-09-22 16:21:49.286883+02
383	26	class_created	open	5	\N	\N	{"site_id": 0, "client_id": 14, "synced_at": "2025-09-22 16:21:46", "class_code": "AET-COMM_NUM-2025-2506021457", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-02 13:02:38", "class_updated_at": "2025-06-02 13:02:38", "responsible_user_id": 5}	\N	f	2025-09-21 17:51:47.162127+02	2025-09-22 16:21:47.168074+02
376	27	class_created	open	3	\N	\N	{"site_id": 0, "client_id": 14, "synced_at": "2025-09-22 16:21:46", "class_code": "14-REALLL-RLC-2025-06-03-15-52", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-03 14:00:15", "class_updated_at": "2025-06-03 14:00:15", "responsible_user_id": 3}	\N	f	2025-09-21 17:51:36.121381+02	2025-09-22 16:21:46.641783+02
419	15	load_learners	pending	1	2025-09-24 17:52:46+02	\N	{}	\N	f	2025-09-21 17:52:47.417208+02	2025-09-21 17:52:47.417208+02
420	15	agent_order	pending	1	2025-09-26 17:52:47+02	\N	{}	\N	f	2025-09-21 17:52:48.411448+02	2025-09-21 17:52:48.411448+02
421	15	training_schedule	pending	1	2025-09-28 17:52:48+02	\N	{}	\N	f	2025-09-21 17:52:49.423996+02	2025-09-21 17:52:49.423996+02
422	15	material_delivery	pending	1	2025-10-01 17:52:49+02	\N	{}	\N	f	2025-09-21 17:52:50.436532+02	2025-09-21 17:52:50.436532+02
423	15	agent_paperwork	pending	1	2025-10-05 17:52:50+02	\N	{}	\N	f	2025-09-21 17:52:51.451752+02	2025-09-21 17:52:51.451752+02
424	15	supervisor_approval	pending	1	2025-09-23 17:52:51+02	\N	{}	\N	f	2025-09-21 17:52:52.454471+02	2025-09-21 17:52:52.454471+02
426	14	load_learners	pending	1	2025-09-24 17:52:57+02	\N	{}	\N	f	2025-09-21 17:52:58.459518+02	2025-09-21 17:52:58.459518+02
427	14	agent_order	pending	1	2025-09-26 17:52:58+02	\N	{}	\N	f	2025-09-21 17:52:59.477175+02	2025-09-21 17:52:59.477175+02
428	14	training_schedule	pending	1	2025-09-28 17:52:59+02	\N	{}	\N	f	2025-09-21 17:53:00.481932+02	2025-09-21 17:53:00.481932+02
429	14	material_delivery	pending	1	2025-10-01 17:53:00+02	\N	{}	\N	f	2025-09-21 17:53:01.506478+02	2025-09-21 17:53:01.506478+02
430	14	agent_paperwork	pending	1	2025-10-05 17:53:01+02	\N	{}	\N	f	2025-09-21 17:53:02.509168+02	2025-09-21 17:53:02.509168+02
431	14	supervisor_approval	pending	1	2025-09-23 17:53:02+02	\N	{}	\N	f	2025-09-21 17:53:03.520876+02	2025-09-21 17:53:03.520876+02
433	13	load_learners	pending	1	2025-09-24 17:53:08+02	\N	{}	\N	f	2025-09-21 17:53:09.573004+02	2025-09-21 17:53:09.573004+02
434	13	agent_order	pending	1	2025-09-26 17:53:09+02	\N	{}	\N	f	2025-09-21 17:53:10.579654+02	2025-09-21 17:53:10.579654+02
435	13	training_schedule	pending	1	2025-09-28 17:53:10+02	\N	{}	\N	f	2025-09-21 17:53:11.586893+02	2025-09-21 17:53:11.586893+02
436	13	material_delivery	pending	1	2025-10-01 17:53:11+02	\N	{}	\N	f	2025-09-21 17:53:12.587028+02	2025-09-21 17:53:12.587028+02
437	13	agent_paperwork	pending	1	2025-10-05 17:53:12+02	\N	{}	\N	f	2025-09-21 17:53:13.610979+02	2025-09-21 17:53:13.610979+02
438	13	supervisor_approval	pending	1	2025-09-23 17:53:13+02	\N	{}	\N	f	2025-09-21 17:53:14.603558+02	2025-09-21 17:53:14.603558+02
440	10	load_learners	pending	1	2025-09-24 17:53:19+02	\N	{}	\N	f	2025-09-21 17:53:20.659525+02	2025-09-21 17:53:20.659525+02
441	10	agent_order	pending	1	2025-09-26 17:53:20+02	\N	{}	\N	f	2025-09-21 17:53:21.66802+02	2025-09-21 17:53:21.66802+02
442	10	training_schedule	pending	1	2025-09-28 17:53:21+02	\N	{}	\N	f	2025-09-21 17:53:22.661552+02	2025-09-21 17:53:22.661552+02
443	10	material_delivery	pending	1	2025-10-01 17:53:22+02	\N	{}	\N	f	2025-09-21 17:53:23.662675+02	2025-09-21 17:53:23.662675+02
444	10	agent_paperwork	pending	1	2025-10-05 17:53:23+02	\N	{}	\N	f	2025-09-21 17:53:24.659601+02	2025-09-21 17:53:24.659601+02
445	10	supervisor_approval	pending	1	2025-09-23 17:53:24+02	\N	{}	\N	f	2025-09-21 17:53:25.65259+02	2025-09-21 17:53:25.65259+02
447	9	load_learners	pending	1	2025-09-24 17:53:30+02	\N	{}	\N	f	2025-09-21 17:53:31.76473+02	2025-09-21 17:53:31.76473+02
448	9	agent_order	pending	1	2025-09-26 17:53:32+02	\N	{}	\N	f	2025-09-21 17:53:32.759715+02	2025-09-21 17:53:32.759715+02
449	9	training_schedule	pending	1	2025-09-28 17:53:33+02	\N	{}	\N	f	2025-09-21 17:53:33.763838+02	2025-09-21 17:53:33.763838+02
450	9	material_delivery	pending	1	2025-10-01 17:53:34+02	\N	{}	\N	f	2025-09-21 17:53:34.777073+02	2025-09-21 17:53:34.777073+02
451	9	agent_paperwork	pending	1	2025-10-05 17:53:35+02	\N	{}	\N	f	2025-09-21 17:53:35.788599+02	2025-09-21 17:53:35.788599+02
452	9	supervisor_approval	pending	1	2025-09-23 17:53:36+02	\N	{}	\N	f	2025-09-21 17:53:36.787045+02	2025-09-21 17:53:36.787045+02
454	7	load_learners	pending	1	2025-09-24 17:53:42+02	\N	{}	\N	f	2025-09-21 17:53:42.835548+02	2025-09-21 17:53:42.835548+02
455	7	agent_order	pending	1	2025-09-26 17:53:43+02	\N	{}	\N	f	2025-09-21 17:53:43.842622+02	2025-09-21 17:53:43.842622+02
456	7	training_schedule	pending	1	2025-09-28 17:53:44+02	\N	{}	\N	f	2025-09-21 17:53:44.862631+02	2025-09-21 17:53:44.862631+02
457	7	material_delivery	pending	1	2025-10-01 17:53:45+02	\N	{}	\N	f	2025-09-21 17:53:45.859555+02	2025-09-21 17:53:45.859555+02
458	7	agent_paperwork	pending	1	2025-10-05 17:53:46+02	\N	{}	\N	f	2025-09-21 17:53:46.859594+02	2025-09-21 17:53:46.859594+02
459	7	supervisor_approval	pending	1	2025-09-23 17:53:47+02	\N	{}	\N	f	2025-09-21 17:53:47.85507+02	2025-09-21 17:53:47.85507+02
461	6	load_learners	pending	1	2025-09-24 17:53:53+02	\N	{}	\N	f	2025-09-21 17:53:53.899081+02	2025-09-21 17:53:53.899081+02
462	6	agent_order	pending	1	2025-09-26 17:53:54+02	\N	{}	\N	f	2025-09-21 17:53:54.898495+02	2025-09-21 17:53:54.898495+02
463	6	training_schedule	pending	1	2025-09-28 17:53:55+02	\N	{}	\N	f	2025-09-21 17:53:55.911816+02	2025-09-21 17:53:55.911816+02
464	6	material_delivery	pending	1	2025-10-01 17:53:56+02	\N	{}	\N	f	2025-09-21 17:53:56.938138+02	2025-09-21 17:53:56.938138+02
465	6	agent_paperwork	pending	1	2025-10-05 17:53:57+02	\N	{}	\N	f	2025-09-21 17:53:57.960992+02	2025-09-21 17:53:57.960992+02
466	6	supervisor_approval	pending	1	2025-09-23 17:53:58+02	\N	{}	\N	f	2025-09-21 17:53:58.961262+02	2025-09-21 17:53:58.961262+02
468	5	load_learners	pending	1	2025-09-24 17:54:04+02	\N	{}	\N	f	2025-09-21 17:54:04.977114+02	2025-09-21 17:54:04.977114+02
469	5	agent_order	pending	1	2025-09-26 17:54:05+02	\N	{}	\N	f	2025-09-21 17:54:06.014094+02	2025-09-21 17:54:06.014094+02
470	5	training_schedule	pending	1	2025-09-28 17:54:06+02	\N	{}	\N	f	2025-09-21 17:54:07.009232+02	2025-09-21 17:54:07.009232+02
471	5	material_delivery	pending	1	2025-10-01 17:54:07+02	\N	{}	\N	f	2025-09-21 17:54:08.009786+02	2025-09-21 17:54:08.009786+02
439	13	class_created	open	2	\N	\N	{"site_id": 0, "client_id": 13, "synced_at": "2025-09-22 16:21:51", "class_code": "", "class_type": "Community", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 2}	\N	f	2025-09-21 17:53:15.614269+02	2025-09-22 16:21:51.409302+02
446	10	class_created	open	2	\N	\N	{"site_id": 0, "client_id": 10, "synced_at": "2025-09-22 16:21:51", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 2}	\N	f	2025-09-21 17:53:26.672022+02	2025-09-22 16:21:51.942399+02
467	6	class_created	open	11	\N	\N	{"site_id": 0, "client_id": 6, "synced_at": "2025-09-22 16:21:53", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 11}	\N	f	2025-09-21 17:53:59.970717+02	2025-09-22 16:21:53.538777+02
460	7	class_created	open	2	\N	\N	{"site_id": 0, "client_id": 7, "synced_at": "2025-09-22 16:21:52", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 2}	\N	f	2025-09-21 17:53:48.856502+02	2025-09-22 16:21:53.007869+02
425	15	class_created	open	11	\N	\N	{"site_id": 0, "client_id": 15, "synced_at": "2025-09-22 16:21:50", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 11}	\N	f	2025-09-21 17:52:53.451076+02	2025-09-22 16:21:50.342865+02
432	14	class_created	open	6	\N	\N	{"site_id": 0, "client_id": 14, "synced_at": "2025-09-22 16:21:50", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 6}	\N	f	2025-09-21 17:53:04.525047+02	2025-09-22 16:21:50.881868+02
453	9	class_created	open	11	\N	\N	{"site_id": 0, "client_id": 9, "synced_at": "2025-09-22 16:21:52", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 11}	\N	f	2025-09-21 17:53:37.797677+02	2025-09-22 16:21:52.472685+02
473	5	supervisor_approval	pending	1	2025-09-23 17:54:09+02	\N	{}	\N	f	2025-09-21 17:54:10.016063+02	2025-09-21 17:54:10.016063+02
475	3	load_learners	pending	1	2025-09-24 17:54:15+02	\N	{}	\N	f	2025-09-21 17:54:16.027071+02	2025-09-21 17:54:16.027071+02
476	3	agent_order	pending	1	2025-09-26 17:54:16+02	\N	{}	\N	f	2025-09-21 17:54:17.016735+02	2025-09-21 17:54:17.016735+02
477	3	training_schedule	pending	1	2025-09-28 17:54:17+02	\N	{}	\N	f	2025-09-21 17:54:18.020113+02	2025-09-21 17:54:18.020113+02
478	3	material_delivery	pending	1	2025-10-01 17:54:18+02	\N	{}	\N	f	2025-09-21 17:54:19.016686+02	2025-09-21 17:54:19.016686+02
479	3	agent_paperwork	pending	1	2025-10-05 17:54:19+02	\N	{}	\N	f	2025-09-21 17:54:20.03835+02	2025-09-21 17:54:20.03835+02
480	3	supervisor_approval	pending	1	2025-09-23 17:54:20+02	\N	{}	\N	f	2025-09-21 17:54:21.037168+02	2025-09-21 17:54:21.037168+02
482	1	load_learners	pending	1	2025-09-24 17:54:26+02	\N	{}	\N	f	2025-09-21 17:54:27.108737+02	2025-09-21 17:54:27.108737+02
483	1	agent_order	pending	1	2025-09-26 17:54:27+02	\N	{}	\N	f	2025-09-21 17:54:28.121719+02	2025-09-21 17:54:28.121719+02
484	1	training_schedule	pending	1	2025-09-28 17:54:28+02	\N	{}	\N	f	2025-09-21 17:54:29.123673+02	2025-09-21 17:54:29.123673+02
485	1	material_delivery	pending	1	2025-10-01 17:54:29+02	\N	{}	\N	f	2025-09-21 17:54:30.126677+02	2025-09-21 17:54:30.126677+02
486	1	agent_paperwork	pending	1	2025-10-05 17:54:30+02	\N	{}	\N	f	2025-09-21 17:54:31.121704+02	2025-09-21 17:54:31.121704+02
487	1	supervisor_approval	pending	1	2025-09-23 17:54:31+02	\N	{}	\N	f	2025-09-21 17:54:32.118614+02	2025-09-21 17:54:32.118614+02
474	5	class_created	open	6	\N	\N	{"site_id": 0, "client_id": 5, "synced_at": "2025-09-22 16:21:53", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 6}	\N	f	2025-09-21 17:54:11.023037+02	2025-09-22 16:21:54.068769+02
285	40	class_created	open	1	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:39", "class_code": "2-AET-COMM_NUM-2025-07-02-16-08", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 14:10:34", "class_updated_at": "2025-07-02 14:10:34", "responsible_user_id": 1}	\N	f	2025-09-21 17:49:11.782015+02	2025-09-22 16:21:39.731269+02
208	51	class_created	open	1	\N	\N	{"site_id": 18, "client_id": 5, "synced_at": "2025-09-22 16:21:33", "class_code": "5-BA2-BA2LP2-2025-09-21-15-42", "class_type": "BA2", "synced_source": "dashboard_status_sync", "class_created_at": "2025-09-21 13:44:15", "class_updated_at": "2025-09-22 10:44:10", "responsible_user_id": 1}	\N	f	2025-09-21 17:47:10.035663+02	2025-09-22 16:21:33.847544+02
481	3	class_created	open	11	\N	\N	{"site_id": 0, "client_id": 3, "synced_at": "2025-09-22 16:21:54", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 11}	\N	f	2025-09-21 17:54:22.044122+02	2025-09-22 16:21:54.597004+02
313	36	class_created	open	4	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:41", "class_code": "2-AET-COMM_NUM-2025-07-01-14-40", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 12:43:11", "class_updated_at": "2025-07-01 12:43:11", "responsible_user_id": 4}	\N	f	2025-09-21 17:49:56.609485+02	2025-09-22 16:21:41.863791+02
369	28	class_created	open	3	\N	\N	{"site_id": 0, "client_id": 14, "synced_at": "2025-09-22 16:21:45", "class_code": "14-REALLL-RLC-2025-06-03-15-52", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-03 15:06:33", "class_updated_at": "2025-06-03 15:06:33", "responsible_user_id": 3}	\N	f	2025-09-21 17:51:25.098987+02	2025-09-22 16:21:46.117499+02
488	1	class_created	open	2	\N	\N	{"site_id": 0, "client_id": 1, "synced_at": "2025-09-22 16:21:54", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": 2}	\N	f	2025-09-21 17:54:33.137214+02	2025-09-22 16:21:55.122031+02
257	44	class_created	open	2	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:37", "class_code": "2-AET-COMM_NUM-2025-07-02-18-06", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 16:08:00", "class_updated_at": "2025-07-02 16:08:00", "responsible_user_id": 2}	\N	f	2025-09-21 17:48:27.615158+02	2025-09-22 16:21:37.598076+02
418	20	class_created	open	8	\N	\N	{"site_id": 0, "client_id": 11, "synced_at": "2025-09-22 16:21:49", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 16:19:46", "class_updated_at": "2025-05-28 16:19:46", "responsible_user_id": 8}	\N	f	2025-09-21 17:52:42.364147+02	2025-09-22 16:21:49.819673+02
327	34	class_created	open	1	\N	\N	{"site_id": 23, "client_id": 2, "synced_at": "2025-09-22 16:21:42", "class_code": "2-AET-COMM_NUM-2025-06-30-13-07", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 11:09:39", "class_updated_at": "2025-06-30 11:09:39", "responsible_user_id": 1}	\N	f	2025-09-21 17:50:18.764641+02	2025-09-22 16:21:42.915642+02
\.


--
-- TOC entry 4204 (class 0 OID 17588)
-- Dependencies: 293
-- Data for Name: events_log; Type: TABLE DATA; Schema: wecoza_events; Owner: John
--

COPY wecoza_events.events_log (id, event_name, event_payload, class_id, actor_id, idempotency_key, processed, occurred_at, processed_at, created_at) FROM stdin;
1	class.created	{"event": "class.created", "actor_id": "1", "class_id": "51", "metadata": {"site_id": "18", "client_id": "5", "synced_at": "2025-09-21 17:47:01", "class_code": "5-BA2-BA2LP2-2025-09-21-15-42", "class_type": "BA2", "synced_source": "dashboard_status_sync", "class_created_at": "2025-09-21 13:44:15", "class_updated_at": "2025-09-21 15:19:37", "responsible_user_id": "1"}, "occurred_at": "2025-09-21 13:44:15", "idempotency_key": "class.created:sync:51"}	51	1	class.created:sync:51	t	2025-09-21 15:44:15+02	2025-09-21 17:47:12+02	2025-09-21 17:47:02.513456+02
2	class.created	{"event": "class.created", "actor_id": "5", "class_id": "50", "metadata": {"site_id": "2", "client_id": "5", "synced_at": "2025-09-21 17:47:12", "class_code": "5-GETC-NL4-2025-07-18-12-41", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-18 10:43:53", "class_updated_at": "2025-07-18 10:43:53", "responsible_user_id": "5"}, "occurred_at": "2025-07-18 10:43:53", "idempotency_key": "class.created:sync:50"}	50	5	class.created:sync:50	t	2025-07-18 12:43:53+02	2025-09-21 17:47:23+02	2025-09-21 17:47:13.584615+02
3	class.created	{"event": "class.created", "actor_id": "4", "class_id": "49", "metadata": {"site_id": "6", "client_id": "2", "synced_at": "2025-09-21 17:47:23", "class_code": "2-BA2-BA2LP1-2025-07-15-19-26", "class_type": "BA2", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-15 17:29:08", "class_updated_at": "2025-07-15 17:29:08", "responsible_user_id": "4"}, "occurred_at": "2025-07-15 17:29:08", "idempotency_key": "class.created:sync:49"}	49	4	class.created:sync:49	t	2025-07-15 19:29:08+02	2025-09-21 17:47:34+02	2025-09-21 17:47:24.694141+02
4	class.created	{"event": "class.created", "actor_id": "4", "class_id": "48", "metadata": {"site_id": "6", "client_id": "2", "synced_at": "2025-09-21 17:47:35", "class_code": "2-SKILL-WALK-2025-07-07-21-10", "class_type": "SKILL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-07 19:13:18", "class_updated_at": "2025-07-15 14:16:50", "responsible_user_id": "4"}, "occurred_at": "2025-07-07 19:13:18", "idempotency_key": "class.created:sync:48"}	48	4	class.created:sync:48	t	2025-07-07 21:13:18+02	2025-09-21 17:47:45+02	2025-09-21 17:47:35.84434+02
5	class.created	{"event": "class.created", "actor_id": "4", "class_id": "47", "metadata": {"site_id": "15", "client_id": "5", "synced_at": "2025-09-21 17:47:46", "class_code": "5-GETC-CL4-2025-07-03-10-07", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-03 08:10:27", "class_updated_at": "2025-07-03 08:10:27", "responsible_user_id": "4"}, "occurred_at": "2025-07-03 08:10:27", "idempotency_key": "class.created:sync:47"}	47	4	class.created:sync:47	t	2025-07-03 10:10:27+02	2025-09-21 17:47:56+02	2025-09-21 17:47:46.905315+02
6	class.created	{"event": "class.created", "actor_id": "2", "class_id": "46", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:47:57", "class_code": "2-AET-COMM_NUM-2025-07-02-19-56", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 17:57:27", "class_updated_at": "2025-07-02 17:57:27", "responsible_user_id": "2"}, "occurred_at": "2025-07-02 17:57:27", "idempotency_key": "class.created:sync:46"}	46	2	class.created:sync:46	t	2025-07-02 19:57:27+02	2025-09-21 17:48:07+02	2025-09-21 17:47:57.978777+02
7	class.created	{"event": "class.created", "actor_id": "3", "class_id": "45", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:08", "class_code": "2-AET-COMM_NUM-2025-07-02-18-40", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 16:43:35", "class_updated_at": "2025-07-02 16:43:35", "responsible_user_id": "3"}, "occurred_at": "2025-07-02 16:43:35", "idempotency_key": "class.created:sync:45"}	45	3	class.created:sync:45	t	2025-07-02 18:43:35+02	2025-09-21 17:48:18+02	2025-09-21 17:48:09.03919+02
8	class.created	{"event": "class.created", "actor_id": "2", "class_id": "44", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:19", "class_code": "2-AET-COMM_NUM-2025-07-02-18-06", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 16:08:00", "class_updated_at": "2025-07-02 16:08:00", "responsible_user_id": "2"}, "occurred_at": "2025-07-02 16:08:00", "idempotency_key": "class.created:sync:44"}	44	2	class.created:sync:44	t	2025-07-02 18:08:00+02	2025-09-21 17:48:29+02	2025-09-21 17:48:20.064744+02
9	class.created	{"event": "class.created", "actor_id": "2", "class_id": "43", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:30", "class_code": "2-AET-COMM_NUM-2025-07-02-17-48", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 15:51:48", "class_updated_at": "2025-07-02 15:51:48", "responsible_user_id": "2"}, "occurred_at": "2025-07-02 15:51:48", "idempotency_key": "class.created:sync:43"}	43	2	class.created:sync:43	t	2025-07-02 17:51:48+02	2025-09-21 17:48:40+02	2025-09-21 17:48:31.142769+02
10	class.created	{"event": "class.created", "actor_id": "2", "class_id": "42", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:41", "class_code": "2-AET-COMM_NUM-2025-07-02-17-24", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 15:27:34", "class_updated_at": "2025-07-02 15:27:34", "responsible_user_id": "2"}, "occurred_at": "2025-07-02 15:27:34", "idempotency_key": "class.created:sync:42"}	42	2	class.created:sync:42	t	2025-07-02 17:27:34+02	2025-09-21 17:48:51+02	2025-09-21 17:48:42.137189+02
11	class.created	{"event": "class.created", "actor_id": "2", "class_id": "41", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:52", "class_code": "2-AET-COMM_NUM-2025-07-02-16-54", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 14:58:13", "class_updated_at": "2025-07-02 14:58:13", "responsible_user_id": "2"}, "occurred_at": "2025-07-02 14:58:13", "idempotency_key": "class.created:sync:41"}	41	2	class.created:sync:41	t	2025-07-02 16:58:13+02	2025-09-21 17:49:02+02	2025-09-21 17:48:53.214471+02
12	class.created	{"event": "class.created", "actor_id": "1", "class_id": "40", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:03", "class_code": "2-AET-COMM_NUM-2025-07-02-16-08", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 14:10:34", "class_updated_at": "2025-07-02 14:10:34", "responsible_user_id": "1"}, "occurred_at": "2025-07-02 14:10:34", "idempotency_key": "class.created:sync:40"}	40	1	class.created:sync:40	t	2025-07-02 16:10:34+02	2025-09-21 17:49:14+02	2025-09-21 17:49:04.250356+02
13	class.created	{"event": "class.created", "actor_id": "5", "class_id": "39", "metadata": {"site_id": "8", "client_id": "2", "synced_at": "2025-09-21 17:49:14", "class_code": "2-AET-COMM_NUM-2025-07-01-15-41", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 13:43:01", "class_updated_at": "2025-07-01 13:43:01", "responsible_user_id": "5"}, "occurred_at": "2025-07-01 13:43:01", "idempotency_key": "class.created:sync:39"}	39	5	class.created:sync:39	t	2025-07-01 15:43:01+02	2025-09-21 17:49:25+02	2025-09-21 17:49:15.322335+02
14	class.created	{"event": "class.created", "actor_id": "5", "class_id": "38", "metadata": {"site_id": "21", "client_id": "5", "synced_at": "2025-09-21 17:49:26", "class_code": "5-AET-COMM_NUM-2025-07-01-15-15", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 13:18:50", "class_updated_at": "2025-07-01 13:18:50", "responsible_user_id": "5"}, "occurred_at": "2025-07-01 13:18:50", "idempotency_key": "class.created:sync:38"}	38	5	class.created:sync:38	t	2025-07-01 15:18:50+02	2025-09-21 17:49:36+02	2025-09-21 17:49:26.923629+02
15	class.created	{"event": "class.created", "actor_id": "4", "class_id": "37", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:37", "class_code": "2-AET-COMM_NUM-2025-07-01-14-55", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 12:57:57", "class_updated_at": "2025-07-01 12:57:57", "responsible_user_id": "4"}, "occurred_at": "2025-07-01 12:57:57", "idempotency_key": "class.created:sync:37"}	37	4	class.created:sync:37	t	2025-07-01 14:57:57+02	2025-09-21 17:49:47+02	2025-09-21 17:49:37.991313+02
16	class.created	{"event": "class.created", "actor_id": "4", "class_id": "36", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:48", "class_code": "2-AET-COMM_NUM-2025-07-01-14-40", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 12:43:11", "class_updated_at": "2025-07-01 12:43:11", "responsible_user_id": "4"}, "occurred_at": "2025-07-01 12:43:11", "idempotency_key": "class.created:sync:36"}	36	4	class.created:sync:36	t	2025-07-01 14:43:11+02	2025-09-21 17:49:58+02	2025-09-21 17:49:49.07334+02
17	class.created	{"event": "class.created", "actor_id": "2", "class_id": "35", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:59", "class_code": "2-AET-COMM_NUM-2025-06-30-19-32", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 17:34:38", "class_updated_at": "2025-06-30 17:34:38", "responsible_user_id": "2"}, "occurred_at": "2025-06-30 17:34:38", "idempotency_key": "class.created:sync:35"}	35	2	class.created:sync:35	t	2025-06-30 19:34:38+02	2025-09-21 17:50:09+02	2025-09-21 17:50:00.100972+02
18	class.created	{"event": "class.created", "actor_id": "1", "class_id": "34", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:50:10", "class_code": "2-AET-COMM_NUM-2025-06-30-13-07", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 11:09:39", "class_updated_at": "2025-06-30 11:09:39", "responsible_user_id": "1"}, "occurred_at": "2025-06-30 11:09:39", "idempotency_key": "class.created:sync:34"}	34	1	class.created:sync:34	t	2025-06-30 13:09:39+02	2025-09-21 17:50:21+02	2025-09-21 17:50:11.213023+02
19	class.created	{"event": "class.created", "actor_id": "1", "class_id": "33", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:50:21", "class_code": "2-AET-COMM_NUM-2025-06-30-12-19", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 10:21:40", "class_updated_at": "2025-06-30 10:21:40", "responsible_user_id": "1"}, "occurred_at": "2025-06-30 10:21:40", "idempotency_key": "class.created:sync:33"}	33	1	class.created:sync:33	t	2025-06-30 12:21:40+02	2025-09-21 17:50:32+02	2025-09-21 17:50:22.31555+02
20	class.created	{"event": "class.created", "actor_id": "4", "class_id": "32", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:50:32", "class_code": "14-REALLL-RLC-2025-06-04-20-45", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 18:47:47", "class_updated_at": "2025-06-04 18:47:47", "responsible_user_id": "4"}, "occurred_at": "2025-06-04 18:47:47", "idempotency_key": "class.created:sync:32"}	32	4	class.created:sync:32	t	2025-06-04 20:47:47+02	2025-09-21 17:50:43+02	2025-09-21 17:50:33.353776+02
21	class.created	{"event": "class.created", "actor_id": "3", "class_id": "31", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:50:43", "class_code": "11-AET-COMM_NUM-2025-06-04-19-50", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:52:43", "class_updated_at": "2025-06-04 17:52:43", "responsible_user_id": "3"}, "occurred_at": "2025-06-04 17:52:43", "idempotency_key": "class.created:sync:31"}	31	3	class.created:sync:31	t	2025-06-04 19:52:43+02	2025-09-21 17:50:54+02	2025-09-21 17:50:44.426798+02
22	class.created	{"event": "class.created", "actor_id": "1", "class_id": "30", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:50:54", "class_code": "14-GETC-SMME4-2025-06-04-19-39", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:41:53", "class_updated_at": "2025-06-04 17:41:53", "responsible_user_id": "1"}, "occurred_at": "2025-06-04 17:41:53", "idempotency_key": "class.created:sync:30"}	30	1	class.created:sync:30	t	2025-06-04 19:41:53+02	2025-09-21 17:51:05+02	2025-09-21 17:50:55.468482+02
23	class.created	{"event": "class.created", "actor_id": "11", "class_id": "29", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:05", "class_code": "14-GETC-SMME4-2025-06-04-19-18", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:21:05", "class_updated_at": "2025-06-04 17:21:05", "responsible_user_id": "11"}, "occurred_at": "2025-06-04 17:21:05", "idempotency_key": "class.created:sync:29"}	29	11	class.created:sync:29	t	2025-06-04 19:21:05+02	2025-09-21 17:51:16+02	2025-09-21 17:51:06.540515+02
24	class.created	{"event": "class.created", "actor_id": "3", "class_id": "28", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:16", "class_code": "14-REALLL-RLC-2025-06-03-15-52", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-03 15:06:33", "class_updated_at": "2025-06-03 15:06:33", "responsible_user_id": "3"}, "occurred_at": "2025-06-03 15:06:33", "idempotency_key": "class.created:sync:28"}	28	3	class.created:sync:28	t	2025-06-03 17:06:33+02	2025-09-21 17:51:27+02	2025-09-21 17:51:17.591384+02
25	class.created	{"event": "class.created", "actor_id": "3", "class_id": "27", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:27", "class_code": "14-REALLL-RLC-2025-06-03-15-52", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-03 14:00:15", "class_updated_at": "2025-06-03 14:00:15", "responsible_user_id": "3"}, "occurred_at": "2025-06-03 14:00:15", "idempotency_key": "class.created:sync:27"}	27	3	class.created:sync:27	t	2025-06-03 16:00:15+02	2025-09-21 17:51:38+02	2025-09-21 17:51:28.6141+02
26	class.created	{"event": "class.created", "actor_id": "5", "class_id": "26", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:38", "class_code": "AET-COMM_NUM-2025-2506021457", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-02 13:02:38", "class_updated_at": "2025-06-02 13:02:38", "responsible_user_id": "5"}, "occurred_at": "2025-06-02 13:02:38", "idempotency_key": "class.created:sync:26"}	26	5	class.created:sync:26	t	2025-06-02 15:02:38+02	2025-09-21 17:51:49+02	2025-09-21 17:51:39.621917+02
27	class.created	{"event": "class.created", "actor_id": "3", "class_id": "24", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:51:49", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-29 09:20:59", "class_updated_at": "2025-05-29 09:20:59", "responsible_user_id": "3"}, "occurred_at": "2025-05-29 09:20:59", "idempotency_key": "class.created:sync:24"}	24	3	class.created:sync:24	t	2025-05-29 11:20:59+02	2025-09-21 17:52:00+02	2025-09-21 17:51:50.670505+02
28	class.created	{"event": "class.created", "actor_id": "8", "class_id": "23", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:00", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-29 07:12:33", "class_updated_at": "2025-05-29 07:12:33", "responsible_user_id": "8"}, "occurred_at": "2025-05-29 07:12:33", "idempotency_key": "class.created:sync:23"}	23	8	class.created:sync:23	t	2025-05-29 09:12:33+02	2025-09-21 17:52:11+02	2025-09-21 17:52:01.718147+02
29	class.created	{"event": "class.created", "actor_id": "8", "class_id": "22", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:12", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 17:50:04", "class_updated_at": "2025-05-28 17:50:04", "responsible_user_id": "8"}, "occurred_at": "2025-05-28 17:50:04", "idempotency_key": "class.created:sync:22"}	22	8	class.created:sync:22	t	2025-05-28 19:50:04+02	2025-09-21 17:52:22+02	2025-09-21 17:52:12.789305+02
30	class.created	{"event": "class.created", "actor_id": "1", "class_id": "21", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:23", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 17:30:59", "class_updated_at": "2025-05-28 17:30:59", "responsible_user_id": "1"}, "occurred_at": "2025-05-28 17:30:59", "idempotency_key": "class.created:sync:21"}	21	1	class.created:sync:21	t	2025-05-28 19:30:59+02	2025-09-21 17:52:33+02	2025-09-21 17:52:23.827949+02
31	class.created	{"event": "class.created", "actor_id": "8", "class_id": "20", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:34", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 16:19:46", "class_updated_at": "2025-05-28 16:19:46", "responsible_user_id": "8"}, "occurred_at": "2025-05-28 16:19:46", "idempotency_key": "class.created:sync:20"}	20	8	class.created:sync:20	t	2025-05-28 18:19:46+02	2025-09-21 17:52:44+02	2025-09-21 17:52:34.841596+02
32	class.created	{"event": "class.created", "actor_id": "11", "class_id": "15", "metadata": {"site_id": "0", "client_id": "15", "synced_at": "2025-09-21 17:52:45", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:15"}	15	11	class.created:sync:15	t	2024-10-17 15:21:57+02	2025-09-21 17:52:55+02	2025-09-21 17:52:45.898501+02
33	class.created	{"event": "class.created", "actor_id": "6", "class_id": "14", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:52:56", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "6"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:14"}	14	6	class.created:sync:14	t	2024-10-17 15:21:57+02	2025-09-21 17:53:06+02	2025-09-21 17:52:56.950978+02
34	class.created	{"event": "class.created", "actor_id": "2", "class_id": "13", "metadata": {"site_id": "0", "client_id": "13", "synced_at": "2025-09-21 17:53:07", "class_code": "", "class_type": "Community", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:13"}	13	2	class.created:sync:13	t	2024-10-17 15:21:57+02	2025-09-21 17:53:17+02	2025-09-21 17:53:08.065478+02
35	class.created	{"event": "class.created", "actor_id": "2", "class_id": "10", "metadata": {"site_id": "0", "client_id": "10", "synced_at": "2025-09-21 17:53:18", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:10"}	10	2	class.created:sync:10	t	2024-10-17 15:21:57+02	2025-09-21 17:53:28+02	2025-09-21 17:53:19.151608+02
36	class.created	{"event": "class.created", "actor_id": "11", "class_id": "9", "metadata": {"site_id": "0", "client_id": "9", "synced_at": "2025-09-21 17:53:29", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:9"}	9	11	class.created:sync:9	t	2024-10-17 15:21:57+02	2025-09-21 17:53:40+02	2025-09-21 17:53:30.239673+02
37	class.created	{"event": "class.created", "actor_id": "2", "class_id": "7", "metadata": {"site_id": "0", "client_id": "7", "synced_at": "2025-09-21 17:53:40", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:7"}	7	2	class.created:sync:7	t	2024-10-17 15:21:57+02	2025-09-21 17:53:51+02	2025-09-21 17:53:41.329822+02
38	class.created	{"event": "class.created", "actor_id": "11", "class_id": "6", "metadata": {"site_id": "0", "client_id": "6", "synced_at": "2025-09-21 17:53:51", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:6"}	6	11	class.created:sync:6	t	2024-10-17 15:21:57+02	2025-09-21 17:54:02+02	2025-09-21 17:53:52.403193+02
39	class.created	{"event": "class.created", "actor_id": "6", "class_id": "5", "metadata": {"site_id": "0", "client_id": "5", "synced_at": "2025-09-21 17:54:02", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "6"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:5"}	5	6	class.created:sync:5	t	2024-10-17 15:21:57+02	2025-09-21 17:54:13+02	2025-09-21 17:54:03.484193+02
40	class.created	{"event": "class.created", "actor_id": "11", "class_id": "3", "metadata": {"site_id": "0", "client_id": "3", "synced_at": "2025-09-21 17:54:13", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:3"}	3	11	class.created:sync:3	t	2024-10-17 15:21:57+02	2025-09-21 17:54:24+02	2025-09-21 17:54:14.532286+02
41	class.created	{"event": "class.created", "actor_id": "2", "class_id": "1", "metadata": {"site_id": "0", "client_id": "1", "synced_at": "2025-09-21 17:54:24", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:1"}	1	2	class.created:sync:1	t	2024-10-17 15:21:57+02	2025-09-21 17:54:35+02	2025-09-21 17:54:25.598399+02
\.


--
-- TOC entry 4206 (class 0 OID 17598)
-- Dependencies: 295
-- Data for Name: notification_queue; Type: TABLE DATA; Schema: wecoza_events; Owner: John
--

COPY wecoza_events.notification_queue (id, event_name, idempotency_key, recipient_email, recipient_name, channel, template_name, payload, status, attempts, max_attempts, last_error, scheduled_at, sent_at, created_at, updated_at) FROM stdin;
1	class.created	class.created:sync:51_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "51", "metadata": {"site_id": "18", "client_id": "5", "synced_at": "2025-09-21 17:47:01", "class_code": "5-BA2-BA2LP2-2025-09-21-15-42", "class_type": "BA2", "synced_source": "dashboard_status_sync", "class_created_at": "2025-09-21 13:44:15", "class_updated_at": "2025-09-21 15:19:37", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-09-21 13:44:15", "idempotency_key": "class.created:sync:51"}	pending	0	3	\N	2025-09-21 17:47:11+02	\N	2025-09-21 17:47:11.566204+02	2025-09-21 17:47:11.566204+02
2	class.created	class.created:sync:51_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "51", "metadata": {"site_id": "18", "client_id": "5", "synced_at": "2025-09-21 17:47:01", "class_code": "5-BA2-BA2LP2-2025-09-21-15-42", "class_type": "BA2", "synced_source": "dashboard_status_sync", "class_created_at": "2025-09-21 13:44:15", "class_updated_at": "2025-09-21 15:19:37", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-09-21 13:44:15", "idempotency_key": "class.created:sync:51"}	pending	0	3	\N	2025-09-21 17:47:11+02	\N	2025-09-21 17:47:12.062139+02	2025-09-21 17:47:12.062139+02
3	class.created	class.created:sync:50_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "5", "class_id": "50", "metadata": {"site_id": "2", "client_id": "5", "synced_at": "2025-09-21 17:47:12", "class_code": "5-GETC-NL4-2025-07-18-12-41", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-18 10:43:53", "class_updated_at": "2025-07-18 10:43:53", "responsible_user_id": "5"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-18 10:43:53", "idempotency_key": "class.created:sync:50"}	pending	0	3	\N	2025-09-21 17:47:22+02	\N	2025-09-21 17:47:22.680749+02	2025-09-21 17:47:22.680749+02
4	class.created	class.created:sync:50_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "5", "class_id": "50", "metadata": {"site_id": "2", "client_id": "5", "synced_at": "2025-09-21 17:47:12", "class_code": "5-GETC-NL4-2025-07-18-12-41", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-18 10:43:53", "class_updated_at": "2025-07-18 10:43:53", "responsible_user_id": "5"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-18 10:43:53", "idempotency_key": "class.created:sync:50"}	pending	0	3	\N	2025-09-21 17:47:22+02	\N	2025-09-21 17:47:23.191897+02	2025-09-21 17:47:23.191897+02
5	class.created	class.created:sync:49_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "49", "metadata": {"site_id": "6", "client_id": "2", "synced_at": "2025-09-21 17:47:23", "class_code": "2-BA2-BA2LP1-2025-07-15-19-26", "class_type": "BA2", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-15 17:29:08", "class_updated_at": "2025-07-15 17:29:08", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-15 17:29:08", "idempotency_key": "class.created:sync:49"}	pending	0	3	\N	2025-09-21 17:47:33+02	\N	2025-09-21 17:47:33.843858+02	2025-09-21 17:47:33.843858+02
6	class.created	class.created:sync:49_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "49", "metadata": {"site_id": "6", "client_id": "2", "synced_at": "2025-09-21 17:47:23", "class_code": "2-BA2-BA2LP1-2025-07-15-19-26", "class_type": "BA2", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-15 17:29:08", "class_updated_at": "2025-07-15 17:29:08", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-15 17:29:08", "idempotency_key": "class.created:sync:49"}	pending	0	3	\N	2025-09-21 17:47:34+02	\N	2025-09-21 17:47:34.344725+02	2025-09-21 17:47:34.344725+02
7	class.created	class.created:sync:48_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "48", "metadata": {"site_id": "6", "client_id": "2", "synced_at": "2025-09-21 17:47:35", "class_code": "2-SKILL-WALK-2025-07-07-21-10", "class_type": "SKILL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-07 19:13:18", "class_updated_at": "2025-07-15 14:16:50", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-07 19:13:18", "idempotency_key": "class.created:sync:48"}	pending	0	3	\N	2025-09-21 17:47:44+02	\N	2025-09-21 17:47:44.8746+02	2025-09-21 17:47:44.8746+02
8	class.created	class.created:sync:48_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "48", "metadata": {"site_id": "6", "client_id": "2", "synced_at": "2025-09-21 17:47:35", "class_code": "2-SKILL-WALK-2025-07-07-21-10", "class_type": "SKILL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-07 19:13:18", "class_updated_at": "2025-07-15 14:16:50", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-07 19:13:18", "idempotency_key": "class.created:sync:48"}	pending	0	3	\N	2025-09-21 17:47:45+02	\N	2025-09-21 17:47:45.373199+02	2025-09-21 17:47:45.373199+02
9	class.created	class.created:sync:47_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "47", "metadata": {"site_id": "15", "client_id": "5", "synced_at": "2025-09-21 17:47:46", "class_code": "5-GETC-CL4-2025-07-03-10-07", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-03 08:10:27", "class_updated_at": "2025-07-03 08:10:27", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-03 08:10:27", "idempotency_key": "class.created:sync:47"}	pending	0	3	\N	2025-09-21 17:47:55+02	\N	2025-09-21 17:47:55.986456+02	2025-09-21 17:47:55.986456+02
10	class.created	class.created:sync:47_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "47", "metadata": {"site_id": "15", "client_id": "5", "synced_at": "2025-09-21 17:47:46", "class_code": "5-GETC-CL4-2025-07-03-10-07", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-03 08:10:27", "class_updated_at": "2025-07-03 08:10:27", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-03 08:10:27", "idempotency_key": "class.created:sync:47"}	pending	0	3	\N	2025-09-21 17:47:56+02	\N	2025-09-21 17:47:56.483146+02	2025-09-21 17:47:56.483146+02
11	class.created	class.created:sync:46_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "46", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:47:57", "class_code": "2-AET-COMM_NUM-2025-07-02-19-56", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 17:57:27", "class_updated_at": "2025-07-02 17:57:27", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 17:57:27", "idempotency_key": "class.created:sync:46"}	pending	0	3	\N	2025-09-21 17:48:06+02	\N	2025-09-21 17:48:07.019666+02	2025-09-21 17:48:07.019666+02
12	class.created	class.created:sync:46_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "46", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:47:57", "class_code": "2-AET-COMM_NUM-2025-07-02-19-56", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 17:57:27", "class_updated_at": "2025-07-02 17:57:27", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 17:57:27", "idempotency_key": "class.created:sync:46"}	pending	0	3	\N	2025-09-21 17:48:07+02	\N	2025-09-21 17:48:07.525669+02	2025-09-21 17:48:07.525669+02
13	class.created	class.created:sync:45_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "45", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:08", "class_code": "2-AET-COMM_NUM-2025-07-02-18-40", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 16:43:35", "class_updated_at": "2025-07-02 16:43:35", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 16:43:35", "idempotency_key": "class.created:sync:45"}	pending	0	3	\N	2025-09-21 17:48:17+02	\N	2025-09-21 17:48:18.064671+02	2025-09-21 17:48:18.064671+02
14	class.created	class.created:sync:45_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "45", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:08", "class_code": "2-AET-COMM_NUM-2025-07-02-18-40", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 16:43:35", "class_updated_at": "2025-07-02 16:43:35", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 16:43:35", "idempotency_key": "class.created:sync:45"}	pending	0	3	\N	2025-09-21 17:48:18+02	\N	2025-09-21 17:48:18.564737+02	2025-09-21 17:48:18.564737+02
15	class.created	class.created:sync:44_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "44", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:19", "class_code": "2-AET-COMM_NUM-2025-07-02-18-06", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 16:08:00", "class_updated_at": "2025-07-02 16:08:00", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 16:08:00", "idempotency_key": "class.created:sync:44"}	pending	0	3	\N	2025-09-21 17:48:28+02	\N	2025-09-21 17:48:29.134689+02	2025-09-21 17:48:29.134689+02
16	class.created	class.created:sync:44_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "44", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:19", "class_code": "2-AET-COMM_NUM-2025-07-02-18-06", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 16:08:00", "class_updated_at": "2025-07-02 16:08:00", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 16:08:00", "idempotency_key": "class.created:sync:44"}	pending	0	3	\N	2025-09-21 17:48:29+02	\N	2025-09-21 17:48:29.637288+02	2025-09-21 17:48:29.637288+02
17	class.created	class.created:sync:43_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "43", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:30", "class_code": "2-AET-COMM_NUM-2025-07-02-17-48", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 15:51:48", "class_updated_at": "2025-07-02 15:51:48", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 15:51:48", "idempotency_key": "class.created:sync:43"}	pending	0	3	\N	2025-09-21 17:48:39+02	\N	2025-09-21 17:48:40.135311+02	2025-09-21 17:48:40.135311+02
18	class.created	class.created:sync:43_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "43", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:30", "class_code": "2-AET-COMM_NUM-2025-07-02-17-48", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 15:51:48", "class_updated_at": "2025-07-02 15:51:48", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 15:51:48", "idempotency_key": "class.created:sync:43"}	pending	0	3	\N	2025-09-21 17:48:40+02	\N	2025-09-21 17:48:40.635607+02	2025-09-21 17:48:40.635607+02
19	class.created	class.created:sync:42_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "42", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:41", "class_code": "2-AET-COMM_NUM-2025-07-02-17-24", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 15:27:34", "class_updated_at": "2025-07-02 15:27:34", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 15:27:34", "idempotency_key": "class.created:sync:42"}	pending	0	3	\N	2025-09-21 17:48:50+02	\N	2025-09-21 17:48:51.200764+02	2025-09-21 17:48:51.200764+02
20	class.created	class.created:sync:42_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "42", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:41", "class_code": "2-AET-COMM_NUM-2025-07-02-17-24", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 15:27:34", "class_updated_at": "2025-07-02 15:27:34", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 15:27:34", "idempotency_key": "class.created:sync:42"}	pending	0	3	\N	2025-09-21 17:48:51+02	\N	2025-09-21 17:48:51.710372+02	2025-09-21 17:48:51.710372+02
21	class.created	class.created:sync:41_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "41", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:52", "class_code": "2-AET-COMM_NUM-2025-07-02-16-54", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 14:58:13", "class_updated_at": "2025-07-02 14:58:13", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 14:58:13", "idempotency_key": "class.created:sync:41"}	pending	0	3	\N	2025-09-21 17:49:01+02	\N	2025-09-21 17:49:02.221363+02	2025-09-21 17:49:02.221363+02
22	class.created	class.created:sync:41_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "41", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:48:52", "class_code": "2-AET-COMM_NUM-2025-07-02-16-54", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 14:58:13", "class_updated_at": "2025-07-02 14:58:13", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 14:58:13", "idempotency_key": "class.created:sync:41"}	pending	0	3	\N	2025-09-21 17:49:02+02	\N	2025-09-21 17:49:02.718238+02	2025-09-21 17:49:02.718238+02
23	class.created	class.created:sync:40_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "40", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:03", "class_code": "2-AET-COMM_NUM-2025-07-02-16-08", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 14:10:34", "class_updated_at": "2025-07-02 14:10:34", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 14:10:34", "idempotency_key": "class.created:sync:40"}	pending	0	3	\N	2025-09-21 17:49:13+02	\N	2025-09-21 17:49:13.291249+02	2025-09-21 17:49:13.291249+02
24	class.created	class.created:sync:40_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "40", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:03", "class_code": "2-AET-COMM_NUM-2025-07-02-16-08", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-02 14:10:34", "class_updated_at": "2025-07-02 14:10:34", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-02 14:10:34", "idempotency_key": "class.created:sync:40"}	pending	0	3	\N	2025-09-21 17:49:13+02	\N	2025-09-21 17:49:13.794495+02	2025-09-21 17:49:13.794495+02
25	class.created	class.created:sync:39_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "5", "class_id": "39", "metadata": {"site_id": "8", "client_id": "2", "synced_at": "2025-09-21 17:49:14", "class_code": "2-AET-COMM_NUM-2025-07-01-15-41", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 13:43:01", "class_updated_at": "2025-07-01 13:43:01", "responsible_user_id": "5"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-01 13:43:01", "idempotency_key": "class.created:sync:39"}	pending	0	3	\N	2025-09-21 17:49:24+02	\N	2025-09-21 17:49:24.499238+02	2025-09-21 17:49:24.499238+02
26	class.created	class.created:sync:39_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "5", "class_id": "39", "metadata": {"site_id": "8", "client_id": "2", "synced_at": "2025-09-21 17:49:14", "class_code": "2-AET-COMM_NUM-2025-07-01-15-41", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 13:43:01", "class_updated_at": "2025-07-01 13:43:01", "responsible_user_id": "5"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-01 13:43:01", "idempotency_key": "class.created:sync:39"}	pending	0	3	\N	2025-09-21 17:49:24+02	\N	2025-09-21 17:49:24.995235+02	2025-09-21 17:49:24.995235+02
27	class.created	class.created:sync:38_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "5", "class_id": "38", "metadata": {"site_id": "21", "client_id": "5", "synced_at": "2025-09-21 17:49:26", "class_code": "5-AET-COMM_NUM-2025-07-01-15-15", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 13:18:50", "class_updated_at": "2025-07-01 13:18:50", "responsible_user_id": "5"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-01 13:18:50", "idempotency_key": "class.created:sync:38"}	pending	0	3	\N	2025-09-21 17:49:35+02	\N	2025-09-21 17:49:35.985787+02	2025-09-21 17:49:35.985787+02
28	class.created	class.created:sync:38_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "5", "class_id": "38", "metadata": {"site_id": "21", "client_id": "5", "synced_at": "2025-09-21 17:49:26", "class_code": "5-AET-COMM_NUM-2025-07-01-15-15", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 13:18:50", "class_updated_at": "2025-07-01 13:18:50", "responsible_user_id": "5"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-01 13:18:50", "idempotency_key": "class.created:sync:38"}	pending	0	3	\N	2025-09-21 17:49:36+02	\N	2025-09-21 17:49:36.491811+02	2025-09-21 17:49:36.491811+02
29	class.created	class.created:sync:37_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "37", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:37", "class_code": "2-AET-COMM_NUM-2025-07-01-14-55", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 12:57:57", "class_updated_at": "2025-07-01 12:57:57", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-01 12:57:57", "idempotency_key": "class.created:sync:37"}	pending	0	3	\N	2025-09-21 17:49:46+02	\N	2025-09-21 17:49:47.04599+02	2025-09-21 17:49:47.04599+02
30	class.created	class.created:sync:37_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "37", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:37", "class_code": "2-AET-COMM_NUM-2025-07-01-14-55", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 12:57:57", "class_updated_at": "2025-07-01 12:57:57", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-01 12:57:57", "idempotency_key": "class.created:sync:37"}	pending	0	3	\N	2025-09-21 17:49:47+02	\N	2025-09-21 17:49:47.548386+02	2025-09-21 17:49:47.548386+02
31	class.created	class.created:sync:36_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "36", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:48", "class_code": "2-AET-COMM_NUM-2025-07-01-14-40", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 12:43:11", "class_updated_at": "2025-07-01 12:43:11", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-01 12:43:11", "idempotency_key": "class.created:sync:36"}	pending	0	3	\N	2025-09-21 17:49:57+02	\N	2025-09-21 17:49:58.106756+02	2025-09-21 17:49:58.106756+02
32	class.created	class.created:sync:36_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "36", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:48", "class_code": "2-AET-COMM_NUM-2025-07-01-14-40", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-07-01 12:43:11", "class_updated_at": "2025-07-01 12:43:11", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-07-01 12:43:11", "idempotency_key": "class.created:sync:36"}	pending	0	3	\N	2025-09-21 17:49:58+02	\N	2025-09-21 17:49:58.606325+02	2025-09-21 17:49:58.606325+02
33	class.created	class.created:sync:35_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "35", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:59", "class_code": "2-AET-COMM_NUM-2025-06-30-19-32", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 17:34:38", "class_updated_at": "2025-06-30 17:34:38", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-30 17:34:38", "idempotency_key": "class.created:sync:35"}	pending	0	3	\N	2025-09-21 17:50:08+02	\N	2025-09-21 17:50:09.15509+02	2025-09-21 17:50:09.15509+02
34	class.created	class.created:sync:35_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "35", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:49:59", "class_code": "2-AET-COMM_NUM-2025-06-30-19-32", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 17:34:38", "class_updated_at": "2025-06-30 17:34:38", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-30 17:34:38", "idempotency_key": "class.created:sync:35"}	pending	0	3	\N	2025-09-21 17:50:09+02	\N	2025-09-21 17:50:09.678014+02	2025-09-21 17:50:09.678014+02
35	class.created	class.created:sync:34_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "34", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:50:10", "class_code": "2-AET-COMM_NUM-2025-06-30-13-07", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 11:09:39", "class_updated_at": "2025-06-30 11:09:39", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-30 11:09:39", "idempotency_key": "class.created:sync:34"}	pending	0	3	\N	2025-09-21 17:50:20+02	\N	2025-09-21 17:50:20.279345+02	2025-09-21 17:50:20.279345+02
36	class.created	class.created:sync:34_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "34", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:50:10", "class_code": "2-AET-COMM_NUM-2025-06-30-13-07", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 11:09:39", "class_updated_at": "2025-06-30 11:09:39", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-30 11:09:39", "idempotency_key": "class.created:sync:34"}	pending	0	3	\N	2025-09-21 17:50:20+02	\N	2025-09-21 17:50:20.791296+02	2025-09-21 17:50:20.791296+02
37	class.created	class.created:sync:33_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "33", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:50:21", "class_code": "2-AET-COMM_NUM-2025-06-30-12-19", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 10:21:40", "class_updated_at": "2025-06-30 10:21:40", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-30 10:21:40", "idempotency_key": "class.created:sync:33"}	pending	0	3	\N	2025-09-21 17:50:31+02	\N	2025-09-21 17:50:31.332314+02	2025-09-21 17:50:31.332314+02
38	class.created	class.created:sync:33_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "33", "metadata": {"site_id": "23", "client_id": "2", "synced_at": "2025-09-21 17:50:21", "class_code": "2-AET-COMM_NUM-2025-06-30-12-19", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-30 10:21:40", "class_updated_at": "2025-06-30 10:21:40", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-30 10:21:40", "idempotency_key": "class.created:sync:33"}	pending	0	3	\N	2025-09-21 17:50:31+02	\N	2025-09-21 17:50:31.831572+02	2025-09-21 17:50:31.831572+02
39	class.created	class.created:sync:32_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "32", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:50:32", "class_code": "14-REALLL-RLC-2025-06-04-20-45", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 18:47:47", "class_updated_at": "2025-06-04 18:47:47", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-04 18:47:47", "idempotency_key": "class.created:sync:32"}	pending	0	3	\N	2025-09-21 17:50:42+02	\N	2025-09-21 17:50:42.419848+02	2025-09-21 17:50:42.419848+02
40	class.created	class.created:sync:32_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "4", "class_id": "32", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:50:32", "class_code": "14-REALLL-RLC-2025-06-04-20-45", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 18:47:47", "class_updated_at": "2025-06-04 18:47:47", "responsible_user_id": "4"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-04 18:47:47", "idempotency_key": "class.created:sync:32"}	pending	0	3	\N	2025-09-21 17:50:42+02	\N	2025-09-21 17:50:42.932839+02	2025-09-21 17:50:42.932839+02
41	class.created	class.created:sync:31_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "31", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:50:43", "class_code": "11-AET-COMM_NUM-2025-06-04-19-50", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:52:43", "class_updated_at": "2025-06-04 17:52:43", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-04 17:52:43", "idempotency_key": "class.created:sync:31"}	pending	0	3	\N	2025-09-21 17:50:53+02	\N	2025-09-21 17:50:53.451516+02	2025-09-21 17:50:53.451516+02
42	class.created	class.created:sync:31_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "31", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:50:43", "class_code": "11-AET-COMM_NUM-2025-06-04-19-50", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:52:43", "class_updated_at": "2025-06-04 17:52:43", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-04 17:52:43", "idempotency_key": "class.created:sync:31"}	pending	0	3	\N	2025-09-21 17:50:53+02	\N	2025-09-21 17:50:53.967829+02	2025-09-21 17:50:53.967829+02
43	class.created	class.created:sync:30_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "30", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:50:54", "class_code": "14-GETC-SMME4-2025-06-04-19-39", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:41:53", "class_updated_at": "2025-06-04 17:41:53", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-04 17:41:53", "idempotency_key": "class.created:sync:30"}	pending	0	3	\N	2025-09-21 17:51:04+02	\N	2025-09-21 17:51:04.520863+02	2025-09-21 17:51:04.520863+02
44	class.created	class.created:sync:30_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "30", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:50:54", "class_code": "14-GETC-SMME4-2025-06-04-19-39", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:41:53", "class_updated_at": "2025-06-04 17:41:53", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-04 17:41:53", "idempotency_key": "class.created:sync:30"}	pending	0	3	\N	2025-09-21 17:51:04+02	\N	2025-09-21 17:51:05.018093+02	2025-09-21 17:51:05.018093+02
45	class.created	class.created:sync:29_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "29", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:05", "class_code": "14-GETC-SMME4-2025-06-04-19-18", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:21:05", "class_updated_at": "2025-06-04 17:21:05", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-04 17:21:05", "idempotency_key": "class.created:sync:29"}	pending	0	3	\N	2025-09-21 17:51:15+02	\N	2025-09-21 17:51:15.579912+02	2025-09-21 17:51:15.579912+02
46	class.created	class.created:sync:29_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "29", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:05", "class_code": "14-GETC-SMME4-2025-06-04-19-18", "class_type": "GETC", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-04 17:21:05", "class_updated_at": "2025-06-04 17:21:05", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-04 17:21:05", "idempotency_key": "class.created:sync:29"}	pending	0	3	\N	2025-09-21 17:51:15+02	\N	2025-09-21 17:51:16.083848+02	2025-09-21 17:51:16.083848+02
47	class.created	class.created:sync:28_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "28", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:16", "class_code": "14-REALLL-RLC-2025-06-03-15-52", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-03 15:06:33", "class_updated_at": "2025-06-03 15:06:33", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-03 15:06:33", "idempotency_key": "class.created:sync:28"}	pending	0	3	\N	2025-09-21 17:51:26+02	\N	2025-09-21 17:51:26.603943+02	2025-09-21 17:51:26.603943+02
48	class.created	class.created:sync:28_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "28", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:16", "class_code": "14-REALLL-RLC-2025-06-03-15-52", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-03 15:06:33", "class_updated_at": "2025-06-03 15:06:33", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-03 15:06:33", "idempotency_key": "class.created:sync:28"}	pending	0	3	\N	2025-09-21 17:51:26+02	\N	2025-09-21 17:51:27.111405+02	2025-09-21 17:51:27.111405+02
49	class.created	class.created:sync:27_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "27", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:27", "class_code": "14-REALLL-RLC-2025-06-03-15-52", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-03 14:00:15", "class_updated_at": "2025-06-03 14:00:15", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-03 14:00:15", "idempotency_key": "class.created:sync:27"}	pending	0	3	\N	2025-09-21 17:51:37+02	\N	2025-09-21 17:51:37.61881+02	2025-09-21 17:51:37.61881+02
50	class.created	class.created:sync:27_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "27", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:27", "class_code": "14-REALLL-RLC-2025-06-03-15-52", "class_type": "REALLL", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-03 14:00:15", "class_updated_at": "2025-06-03 14:00:15", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-03 14:00:15", "idempotency_key": "class.created:sync:27"}	pending	0	3	\N	2025-09-21 17:51:37+02	\N	2025-09-21 17:51:38.125218+02	2025-09-21 17:51:38.125218+02
51	class.created	class.created:sync:26_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "5", "class_id": "26", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:38", "class_code": "AET-COMM_NUM-2025-2506021457", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-02 13:02:38", "class_updated_at": "2025-06-02 13:02:38", "responsible_user_id": "5"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-02 13:02:38", "idempotency_key": "class.created:sync:26"}	pending	0	3	\N	2025-09-21 17:51:48+02	\N	2025-09-21 17:51:48.66192+02	2025-09-21 17:51:48.66192+02
52	class.created	class.created:sync:26_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "5", "class_id": "26", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:51:38", "class_code": "AET-COMM_NUM-2025-2506021457", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-06-02 13:02:38", "class_updated_at": "2025-06-02 13:02:38", "responsible_user_id": "5"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-06-02 13:02:38", "idempotency_key": "class.created:sync:26"}	pending	0	3	\N	2025-09-21 17:51:48+02	\N	2025-09-21 17:51:49.169681+02	2025-09-21 17:51:49.169681+02
53	class.created	class.created:sync:24_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "24", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:51:49", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-29 09:20:59", "class_updated_at": "2025-05-29 09:20:59", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-29 09:20:59", "idempotency_key": "class.created:sync:24"}	pending	0	3	\N	2025-09-21 17:51:59+02	\N	2025-09-21 17:51:59.708115+02	2025-09-21 17:51:59.708115+02
54	class.created	class.created:sync:24_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "3", "class_id": "24", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:51:49", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-29 09:20:59", "class_updated_at": "2025-05-29 09:20:59", "responsible_user_id": "3"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-29 09:20:59", "idempotency_key": "class.created:sync:24"}	pending	0	3	\N	2025-09-21 17:51:59+02	\N	2025-09-21 17:52:00.208783+02	2025-09-21 17:52:00.208783+02
55	class.created	class.created:sync:23_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "8", "class_id": "23", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:00", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-29 07:12:33", "class_updated_at": "2025-05-29 07:12:33", "responsible_user_id": "8"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-29 07:12:33", "idempotency_key": "class.created:sync:23"}	pending	0	3	\N	2025-09-21 17:52:10+02	\N	2025-09-21 17:52:10.776153+02	2025-09-21 17:52:10.776153+02
56	class.created	class.created:sync:23_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "8", "class_id": "23", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:00", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-29 07:12:33", "class_updated_at": "2025-05-29 07:12:33", "responsible_user_id": "8"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-29 07:12:33", "idempotency_key": "class.created:sync:23"}	pending	0	3	\N	2025-09-21 17:52:11+02	\N	2025-09-21 17:52:11.286424+02	2025-09-21 17:52:11.286424+02
57	class.created	class.created:sync:22_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "8", "class_id": "22", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:12", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 17:50:04", "class_updated_at": "2025-05-28 17:50:04", "responsible_user_id": "8"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-28 17:50:04", "idempotency_key": "class.created:sync:22"}	pending	0	3	\N	2025-09-21 17:52:21+02	\N	2025-09-21 17:52:21.812314+02	2025-09-21 17:52:21.812314+02
58	class.created	class.created:sync:22_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "8", "class_id": "22", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:12", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 17:50:04", "class_updated_at": "2025-05-28 17:50:04", "responsible_user_id": "8"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-28 17:50:04", "idempotency_key": "class.created:sync:22"}	pending	0	3	\N	2025-09-21 17:52:22+02	\N	2025-09-21 17:52:22.325155+02	2025-09-21 17:52:22.325155+02
59	class.created	class.created:sync:21_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "21", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:23", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 17:30:59", "class_updated_at": "2025-05-28 17:30:59", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-28 17:30:59", "idempotency_key": "class.created:sync:21"}	pending	0	3	\N	2025-09-21 17:52:32+02	\N	2025-09-21 17:52:32.820137+02	2025-09-21 17:52:32.820137+02
60	class.created	class.created:sync:21_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "1", "class_id": "21", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:23", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 17:30:59", "class_updated_at": "2025-05-28 17:30:59", "responsible_user_id": "1"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-28 17:30:59", "idempotency_key": "class.created:sync:21"}	pending	0	3	\N	2025-09-21 17:52:33+02	\N	2025-09-21 17:52:33.319999+02	2025-09-21 17:52:33.319999+02
61	class.created	class.created:sync:20_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "8", "class_id": "20", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:34", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 16:19:46", "class_updated_at": "2025-05-28 16:19:46", "responsible_user_id": "8"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-28 16:19:46", "idempotency_key": "class.created:sync:20"}	pending	0	3	\N	2025-09-21 17:52:43+02	\N	2025-09-21 17:52:43.876791+02	2025-09-21 17:52:43.876791+02
62	class.created	class.created:sync:20_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "8", "class_id": "20", "metadata": {"site_id": "0", "client_id": "11", "synced_at": "2025-09-21 17:52:34", "class_code": "AET-BOTH-2025", "class_type": "AET", "synced_source": "dashboard_status_sync", "class_created_at": "2025-05-28 16:19:46", "class_updated_at": "2025-05-28 16:19:46", "responsible_user_id": "8"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2025-05-28 16:19:46", "idempotency_key": "class.created:sync:20"}	pending	0	3	\N	2025-09-21 17:52:44+02	\N	2025-09-21 17:52:44.377745+02	2025-09-21 17:52:44.377745+02
63	class.created	class.created:sync:15_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "15", "metadata": {"site_id": "0", "client_id": "15", "synced_at": "2025-09-21 17:52:45", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:15"}	pending	0	3	\N	2025-09-21 17:52:54+02	\N	2025-09-21 17:52:54.94565+02	2025-09-21 17:52:54.94565+02
64	class.created	class.created:sync:15_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "15", "metadata": {"site_id": "0", "client_id": "15", "synced_at": "2025-09-21 17:52:45", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:15"}	pending	0	3	\N	2025-09-21 17:52:55+02	\N	2025-09-21 17:52:55.444137+02	2025-09-21 17:52:55.444137+02
65	class.created	class.created:sync:14_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "6", "class_id": "14", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:52:56", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "6"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:14"}	pending	0	3	\N	2025-09-21 17:53:05+02	\N	2025-09-21 17:53:06.02567+02	2025-09-21 17:53:06.02567+02
66	class.created	class.created:sync:14_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "6", "class_id": "14", "metadata": {"site_id": "0", "client_id": "14", "synced_at": "2025-09-21 17:52:56", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "6"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:14"}	pending	0	3	\N	2025-09-21 17:53:06+02	\N	2025-09-21 17:53:06.543543+02	2025-09-21 17:53:06.543543+02
67	class.created	class.created:sync:13_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "13", "metadata": {"site_id": "0", "client_id": "13", "synced_at": "2025-09-21 17:53:07", "class_code": "", "class_type": "Community", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:13"}	pending	0	3	\N	2025-09-21 17:53:16+02	\N	2025-09-21 17:53:17.119417+02	2025-09-21 17:53:17.119417+02
68	class.created	class.created:sync:13_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "13", "metadata": {"site_id": "0", "client_id": "13", "synced_at": "2025-09-21 17:53:07", "class_code": "", "class_type": "Community", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:13"}	pending	0	3	\N	2025-09-21 17:53:17+02	\N	2025-09-21 17:53:17.638658+02	2025-09-21 17:53:17.638658+02
69	class.created	class.created:sync:10_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "10", "metadata": {"site_id": "0", "client_id": "10", "synced_at": "2025-09-21 17:53:18", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:10"}	pending	0	3	\N	2025-09-21 17:53:27+02	\N	2025-09-21 17:53:28.226001+02	2025-09-21 17:53:28.226001+02
70	class.created	class.created:sync:10_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "10", "metadata": {"site_id": "0", "client_id": "10", "synced_at": "2025-09-21 17:53:18", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:10"}	pending	0	3	\N	2025-09-21 17:53:28+02	\N	2025-09-21 17:53:28.734219+02	2025-09-21 17:53:28.734219+02
71	class.created	class.created:sync:9_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "9", "metadata": {"site_id": "0", "client_id": "9", "synced_at": "2025-09-21 17:53:29", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:9"}	pending	0	3	\N	2025-09-21 17:53:39+02	\N	2025-09-21 17:53:39.319691+02	2025-09-21 17:53:39.319691+02
72	class.created	class.created:sync:9_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "9", "metadata": {"site_id": "0", "client_id": "9", "synced_at": "2025-09-21 17:53:29", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:9"}	pending	0	3	\N	2025-09-21 17:53:39+02	\N	2025-09-21 17:53:39.822101+02	2025-09-21 17:53:39.822101+02
73	class.created	class.created:sync:7_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "7", "metadata": {"site_id": "0", "client_id": "7", "synced_at": "2025-09-21 17:53:40", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:7"}	pending	0	3	\N	2025-09-21 17:53:50+02	\N	2025-09-21 17:53:50.359027+02	2025-09-21 17:53:50.359027+02
74	class.created	class.created:sync:7_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "7", "metadata": {"site_id": "0", "client_id": "7", "synced_at": "2025-09-21 17:53:40", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:7"}	pending	0	3	\N	2025-09-21 17:53:50+02	\N	2025-09-21 17:53:50.865105+02	2025-09-21 17:53:50.865105+02
75	class.created	class.created:sync:6_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "6", "metadata": {"site_id": "0", "client_id": "6", "synced_at": "2025-09-21 17:53:51", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:6"}	pending	0	3	\N	2025-09-21 17:54:01+02	\N	2025-09-21 17:54:01.47766+02	2025-09-21 17:54:01.47766+02
76	class.created	class.created:sync:6_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "6", "metadata": {"site_id": "0", "client_id": "6", "synced_at": "2025-09-21 17:53:51", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:6"}	pending	0	3	\N	2025-09-21 17:54:01+02	\N	2025-09-21 17:54:01.975812+02	2025-09-21 17:54:01.975812+02
77	class.created	class.created:sync:5_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "6", "class_id": "5", "metadata": {"site_id": "0", "client_id": "5", "synced_at": "2025-09-21 17:54:02", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "6"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:5"}	pending	0	3	\N	2025-09-21 17:54:12+02	\N	2025-09-21 17:54:12.536622+02	2025-09-21 17:54:12.536622+02
78	class.created	class.created:sync:5_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "6", "class_id": "5", "metadata": {"site_id": "0", "client_id": "5", "synced_at": "2025-09-21 17:54:02", "class_code": "", "class_type": "Specialized", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "6"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:5"}	pending	0	3	\N	2025-09-21 17:54:12+02	\N	2025-09-21 17:54:13.038358+02	2025-09-21 17:54:13.038358+02
79	class.created	class.created:sync:3_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "3", "metadata": {"site_id": "0", "client_id": "3", "synced_at": "2025-09-21 17:54:13", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:3"}	pending	0	3	\N	2025-09-21 17:54:23+02	\N	2025-09-21 17:54:23.567131+02	2025-09-21 17:54:23.567131+02
80	class.created	class.created:sync:3_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "11", "class_id": "3", "metadata": {"site_id": "0", "client_id": "3", "synced_at": "2025-09-21 17:54:13", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "11"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:3"}	pending	0	3	\N	2025-09-21 17:54:23+02	\N	2025-09-21 17:54:24.068105+02	2025-09-21 17:54:24.068105+02
81	class.created	class.created:sync:1_info@devai.co.za_email	info@devai.co.za	Koos Kombuis	email	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "1", "metadata": {"site_id": "0", "client_id": "1", "synced_at": "2025-09-21 17:54:24", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:1"}	pending	0	3	\N	2025-09-21 17:54:34+02	\N	2025-09-21 17:54:34.646751+02	2025-09-21 17:54:34.646751+02
82	class.created	class.created:sync:1_info@devai.co.za_dashboard	info@devai.co.za	Koos Kombuis	dashboard	class_created_supervisor	{"event": "class.created", "actor_id": "2", "class_id": "1", "metadata": {"site_id": "0", "client_id": "1", "synced_at": "2025-09-21 17:54:24", "class_code": "", "class_type": "Corporate", "synced_source": "dashboard_status_sync", "class_created_at": "2024-10-17 13:21:57", "class_updated_at": "2024-10-17 13:21:57", "responsible_user_id": "2"}, "recipient": {"name": "Koos Kombuis", "email": "info@devai.co.za", "supervisor_id": "1"}, "occurred_at": "2024-10-17 13:21:57", "idempotency_key": "class.created:sync:1"}	pending	0	3	\N	2025-09-21 17:54:34+02	\N	2025-09-21 17:54:35.147754+02	2025-09-21 17:54:35.147754+02
\.


--
-- TOC entry 4208 (class 0 OID 17612)
-- Dependencies: 297
-- Data for Name: supervisors; Type: TABLE DATA; Schema: wecoza_events; Owner: John
--

COPY wecoza_events.supervisors (id, name, email, phone, role, client_assignments, site_assignments, is_default, is_active, created_at, updated_at) FROM stdin;
1	Koos Kombuis	info@devai.co.za	\N	supervisor	[]	[]	t	t	2025-09-21 16:35:58+02	2025-09-21 16:35:58+02
\.


--
-- TOC entry 4613 (class 0 OID 0)
-- Dependencies: 218
-- Name: agent_absences_absence_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.agent_absences_absence_id_seq', 20, true);


--
-- TOC entry 4614 (class 0 OID 0)
-- Dependencies: 311
-- Name: agent_meta_meta_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.agent_meta_meta_id_seq', 1, false);


--
-- TOC entry 4615 (class 0 OID 0)
-- Dependencies: 220
-- Name: agent_notes_note_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.agent_notes_note_id_seq', 20, true);


--
-- TOC entry 4616 (class 0 OID 0)
-- Dependencies: 222
-- Name: agent_orders_order_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.agent_orders_order_id_seq', 15, true);


--
-- TOC entry 4617 (class 0 OID 0)
-- Dependencies: 224
-- Name: agent_replacements_replacement_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.agent_replacements_replacement_id_seq', 43, true);


--
-- TOC entry 4618 (class 0 OID 0)
-- Dependencies: 226
-- Name: agents_agent_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.agents_agent_id_seq', 33, true);


--
-- TOC entry 4619 (class 0 OID 0)
-- Dependencies: 228
-- Name: attendance_registers_register_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.attendance_registers_register_id_seq', 30, true);


--
-- TOC entry 4620 (class 0 OID 0)
-- Dependencies: 309
-- Name: class_events_event_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.class_events_event_id_seq', 15, true);


--
-- TOC entry 4621 (class 0 OID 0)
-- Dependencies: 231
-- Name: class_material_tracking_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.class_material_tracking_id_seq', 4, true);


--
-- TOC entry 4622 (class 0 OID 0)
-- Dependencies: 233
-- Name: class_notes_note_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.class_notes_note_id_seq', 15, true);


--
-- TOC entry 4623 (class 0 OID 0)
-- Dependencies: 235
-- Name: class_schedules_schedule_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.class_schedules_schedule_id_seq', 42, true);


--
-- TOC entry 4624 (class 0 OID 0)
-- Dependencies: 301
-- Name: class_type_subjects_class_type_subject_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.class_type_subjects_class_type_subject_id_seq', 50, true);


--
-- TOC entry 4625 (class 0 OID 0)
-- Dependencies: 299
-- Name: class_types_class_type_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.class_types_class_type_id_seq', 10, true);


--
-- TOC entry 4626 (class 0 OID 0)
-- Dependencies: 238
-- Name: classes_class_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.classes_class_id_seq', 58, true);


--
-- TOC entry 4627 (class 0 OID 0)
-- Dependencies: 240
-- Name: client_communications_communication_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.client_communications_communication_id_seq', 23, true);


--
-- TOC entry 4628 (class 0 OID 0)
-- Dependencies: 242
-- Name: clients_client_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.clients_client_id_seq', 26, true);


--
-- TOC entry 4629 (class 0 OID 0)
-- Dependencies: 244
-- Name: collections_collection_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.collections_collection_id_seq', 15, true);


--
-- TOC entry 4630 (class 0 OID 0)
-- Dependencies: 246
-- Name: deliveries_delivery_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.deliveries_delivery_id_seq', 15, true);


--
-- TOC entry 4631 (class 0 OID 0)
-- Dependencies: 248
-- Name: employers_employer_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.employers_employer_id_seq', 15, true);


--
-- TOC entry 4632 (class 0 OID 0)
-- Dependencies: 250
-- Name: exam_results_result_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.exam_results_result_id_seq', 15, true);


--
-- TOC entry 4633 (class 0 OID 0)
-- Dependencies: 252
-- Name: exams_exam_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.exams_exam_id_seq', 15, true);


--
-- TOC entry 4634 (class 0 OID 0)
-- Dependencies: 254
-- Name: files_file_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.files_file_id_seq', 15, true);


--
-- TOC entry 4635 (class 0 OID 0)
-- Dependencies: 256
-- Name: history_history_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.history_history_id_seq', 15, true);


--
-- TOC entry 4636 (class 0 OID 0)
-- Dependencies: 305
-- Name: learner_hours_log_log_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.learner_hours_log_log_id_seq', 1, false);


--
-- TOC entry 4637 (class 0 OID 0)
-- Dependencies: 303
-- Name: learner_lp_tracking_tracking_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.learner_lp_tracking_tracking_id_seq', 2, true);


--
-- TOC entry 4638 (class 0 OID 0)
-- Dependencies: 260
-- Name: learner_portfolios_portfolio_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.learner_portfolios_portfolio_id_seq', 16, true);


--
-- TOC entry 4639 (class 0 OID 0)
-- Dependencies: 307
-- Name: learner_progression_portfolios_file_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.learner_progression_portfolios_file_id_seq', 1, false);


--
-- TOC entry 4640 (class 0 OID 0)
-- Dependencies: 263
-- Name: learner_progressions_progression_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.learner_progressions_progression_id_seq', 15, true);


--
-- TOC entry 4641 (class 0 OID 0)
-- Dependencies: 265
-- Name: learner_qualifications_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.learner_qualifications_id_seq', 5, true);


--
-- TOC entry 4642 (class 0 OID 0)
-- Dependencies: 313
-- Name: learner_sponsors_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.learner_sponsors_id_seq', 1, true);


--
-- TOC entry 4643 (class 0 OID 0)
-- Dependencies: 267
-- Name: learners_learner_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.learners_learner_id_seq', 37, true);


--
-- TOC entry 4644 (class 0 OID 0)
-- Dependencies: 269
-- Name: locations_location_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.locations_location_id_seq', 18, true);


--
-- TOC entry 4645 (class 0 OID 0)
-- Dependencies: 271
-- Name: products_product_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.products_product_id_seq', 15, true);


--
-- TOC entry 4646 (class 0 OID 0)
-- Dependencies: 273
-- Name: progress_reports_report_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.progress_reports_report_id_seq', 16, true);


--
-- TOC entry 4647 (class 0 OID 0)
-- Dependencies: 275
-- Name: qa_visits_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.qa_visits_id_seq', 12, true);


--
-- TOC entry 4648 (class 0 OID 0)
-- Dependencies: 276
-- Name: qa_visits_id_seq1; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.qa_visits_id_seq1', 20, true);


--
-- TOC entry 4649 (class 0 OID 0)
-- Dependencies: 280
-- Name: sites_site_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.sites_site_id_seq', 45, true);


--
-- TOC entry 4650 (class 0 OID 0)
-- Dependencies: 282
-- Name: user_permissions_permission_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.user_permissions_permission_id_seq', 54, true);


--
-- TOC entry 4651 (class 0 OID 0)
-- Dependencies: 284
-- Name: user_roles_role_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.user_roles_role_id_seq', 16, true);


--
-- TOC entry 4652 (class 0 OID 0)
-- Dependencies: 286
-- Name: users_user_id_seq; Type: SEQUENCE SET; Schema: public; Owner: John
--

SELECT pg_catalog.setval('public.users_user_id_seq', 16, true);


--
-- TOC entry 4653 (class 0 OID 0)
-- Dependencies: 290
-- Name: audit_log_id_seq; Type: SEQUENCE SET; Schema: wecoza_events; Owner: John
--

SELECT pg_catalog.setval('wecoza_events.audit_log_id_seq', 14, true);


--
-- TOC entry 4654 (class 0 OID 0)
-- Dependencies: 292
-- Name: dashboard_status_id_seq; Type: SEQUENCE SET; Schema: wecoza_events; Owner: John
--

SELECT pg_catalog.setval('wecoza_events.dashboard_status_id_seq', 488, true);


--
-- TOC entry 4655 (class 0 OID 0)
-- Dependencies: 294
-- Name: events_log_id_seq; Type: SEQUENCE SET; Schema: wecoza_events; Owner: John
--

SELECT pg_catalog.setval('wecoza_events.events_log_id_seq', 41, true);


--
-- TOC entry 4656 (class 0 OID 0)
-- Dependencies: 296
-- Name: notification_queue_id_seq; Type: SEQUENCE SET; Schema: wecoza_events; Owner: John
--

SELECT pg_catalog.setval('wecoza_events.notification_queue_id_seq', 82, true);


--
-- TOC entry 4657 (class 0 OID 0)
-- Dependencies: 298
-- Name: supervisors_id_seq; Type: SEQUENCE SET; Schema: wecoza_events; Owner: John
--

SELECT pg_catalog.setval('wecoza_events.supervisors_id_seq', 1, true);


--
-- TOC entry 3681 (class 2606 OID 17688)
-- Name: agent_absences agent_absences_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_absences
    ADD CONSTRAINT agent_absences_pkey PRIMARY KEY (absence_id);


--
-- TOC entry 3901 (class 2606 OID 24752)
-- Name: agent_meta agent_meta_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_meta
    ADD CONSTRAINT agent_meta_pkey PRIMARY KEY (meta_id);


--
-- TOC entry 3903 (class 2606 OID 24754)
-- Name: agent_meta agent_meta_unique; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_meta
    ADD CONSTRAINT agent_meta_unique UNIQUE (agent_id, meta_key);


--
-- TOC entry 3685 (class 2606 OID 17690)
-- Name: agent_notes agent_notes_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_notes
    ADD CONSTRAINT agent_notes_pkey PRIMARY KEY (note_id);


--
-- TOC entry 3688 (class 2606 OID 17692)
-- Name: agent_orders agent_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_orders
    ADD CONSTRAINT agent_orders_pkey PRIMARY KEY (order_id);


--
-- TOC entry 3690 (class 2606 OID 17694)
-- Name: agent_replacements agent_replacements_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_replacements
    ADD CONSTRAINT agent_replacements_pkey PRIMARY KEY (replacement_id);


--
-- TOC entry 3692 (class 2606 OID 17696)
-- Name: agents agents_email_unique; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_email_unique UNIQUE (email_address);


--
-- TOC entry 3694 (class 2606 OID 17698)
-- Name: agents agents_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_pkey PRIMARY KEY (agent_id);


--
-- TOC entry 3696 (class 2606 OID 17700)
-- Name: agents agents_sa_id_unique; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_sa_id_unique UNIQUE (sa_id_no);


--
-- TOC entry 3717 (class 2606 OID 17702)
-- Name: attendance_registers attendance_registers_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.attendance_registers
    ADD CONSTRAINT attendance_registers_pkey PRIMARY KEY (register_id);


--
-- TOC entry 3719 (class 2606 OID 17704)
-- Name: class_agents class_agents_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_agents
    ADD CONSTRAINT class_agents_pkey PRIMARY KEY (class_id, agent_id, start_date);


--
-- TOC entry 3894 (class 2606 OID 24730)
-- Name: class_events class_events_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_events
    ADD CONSTRAINT class_events_pkey PRIMARY KEY (event_id);


--
-- TOC entry 3721 (class 2606 OID 17708)
-- Name: class_material_tracking class_material_tracking_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_material_tracking
    ADD CONSTRAINT class_material_tracking_pkey PRIMARY KEY (id);


--
-- TOC entry 3730 (class 2606 OID 17710)
-- Name: class_notes class_notes_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_notes
    ADD CONSTRAINT class_notes_pkey PRIMARY KEY (note_id);


--
-- TOC entry 3732 (class 2606 OID 17712)
-- Name: class_schedules class_schedules_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_schedules
    ADD CONSTRAINT class_schedules_pkey PRIMARY KEY (schedule_id);


--
-- TOC entry 3734 (class 2606 OID 17714)
-- Name: class_subjects class_subjects_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_subjects
    ADD CONSTRAINT class_subjects_pkey PRIMARY KEY (class_id, product_id);


--
-- TOC entry 3870 (class 2606 OID 24612)
-- Name: class_type_subjects class_type_subjects_class_type_id_subject_code_key; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_type_subjects
    ADD CONSTRAINT class_type_subjects_class_type_id_subject_code_key UNIQUE (class_type_id, subject_code);


--
-- TOC entry 3872 (class 2606 OID 24610)
-- Name: class_type_subjects class_type_subjects_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_type_subjects
    ADD CONSTRAINT class_type_subjects_pkey PRIMARY KEY (class_type_subject_id);


--
-- TOC entry 3863 (class 2606 OID 24596)
-- Name: class_types class_types_class_type_code_key; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_types
    ADD CONSTRAINT class_types_class_type_code_key UNIQUE (class_type_code);


--
-- TOC entry 3865 (class 2606 OID 24594)
-- Name: class_types class_types_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_types
    ADD CONSTRAINT class_types_pkey PRIMARY KEY (class_type_id);


--
-- TOC entry 3736 (class 2606 OID 17716)
-- Name: classes classes_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT classes_pkey PRIMARY KEY (class_id);


--
-- TOC entry 3746 (class 2606 OID 17718)
-- Name: client_communications client_communications_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.client_communications
    ADD CONSTRAINT client_communications_pkey PRIMARY KEY (communication_id);


--
-- TOC entry 3749 (class 2606 OID 17720)
-- Name: clients clients_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_pkey PRIMARY KEY (client_id);


--
-- TOC entry 3756 (class 2606 OID 17722)
-- Name: collections collections_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.collections
    ADD CONSTRAINT collections_pkey PRIMARY KEY (collection_id);


--
-- TOC entry 3758 (class 2606 OID 17724)
-- Name: deliveries deliveries_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.deliveries
    ADD CONSTRAINT deliveries_pkey PRIMARY KEY (delivery_id);


--
-- TOC entry 3760 (class 2606 OID 17726)
-- Name: employers employers_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.employers
    ADD CONSTRAINT employers_pkey PRIMARY KEY (employer_id);


--
-- TOC entry 3762 (class 2606 OID 17728)
-- Name: exam_results exam_results_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.exam_results
    ADD CONSTRAINT exam_results_pkey PRIMARY KEY (result_id);


--
-- TOC entry 3764 (class 2606 OID 17730)
-- Name: exams exams_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.exams
    ADD CONSTRAINT exams_pkey PRIMARY KEY (exam_id);


--
-- TOC entry 3766 (class 2606 OID 17732)
-- Name: files files_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.files
    ADD CONSTRAINT files_pkey PRIMARY KEY (file_id);


--
-- TOC entry 3768 (class 2606 OID 17734)
-- Name: history history_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.history
    ADD CONSTRAINT history_pkey PRIMARY KEY (history_id);


--
-- TOC entry 3889 (class 2606 OID 24667)
-- Name: learner_hours_log learner_hours_log_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_hours_log
    ADD CONSTRAINT learner_hours_log_pkey PRIMARY KEY (log_id);


--
-- TOC entry 3884 (class 2606 OID 24638)
-- Name: learner_lp_tracking learner_lp_tracking_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_lp_tracking
    ADD CONSTRAINT learner_lp_tracking_pkey PRIMARY KEY (tracking_id);


--
-- TOC entry 3776 (class 2606 OID 17736)
-- Name: learner_placement_level learner_placement_level_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_placement_level
    ADD CONSTRAINT learner_placement_level_pkey PRIMARY KEY (placement_level_id);


--
-- TOC entry 3778 (class 2606 OID 17738)
-- Name: learner_portfolios learner_portfolios_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_portfolios
    ADD CONSTRAINT learner_portfolios_pkey PRIMARY KEY (portfolio_id);


--
-- TOC entry 3780 (class 2606 OID 17740)
-- Name: learner_products learner_products_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_products
    ADD CONSTRAINT learner_products_pkey PRIMARY KEY (learner_id, product_id);


--
-- TOC entry 3892 (class 2606 OID 24697)
-- Name: learner_progression_portfolios learner_progression_portfolios_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_progression_portfolios
    ADD CONSTRAINT learner_progression_portfolios_pkey PRIMARY KEY (file_id);


--
-- TOC entry 3782 (class 2606 OID 17742)
-- Name: learner_progressions learner_progressions_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_progressions
    ADD CONSTRAINT learner_progressions_pkey PRIMARY KEY (progression_id);


--
-- TOC entry 3784 (class 2606 OID 17744)
-- Name: learner_qualifications learner_qualifications_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_qualifications
    ADD CONSTRAINT learner_qualifications_pkey PRIMARY KEY (id);


--
-- TOC entry 3908 (class 2606 OID 24770)
-- Name: learner_sponsors learner_sponsors_learner_id_employer_id_key; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_sponsors
    ADD CONSTRAINT learner_sponsors_learner_id_employer_id_key UNIQUE (learner_id, employer_id);


--
-- TOC entry 3910 (class 2606 OID 24768)
-- Name: learner_sponsors learner_sponsors_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_sponsors
    ADD CONSTRAINT learner_sponsors_pkey PRIMARY KEY (id);


--
-- TOC entry 3786 (class 2606 OID 17746)
-- Name: learners learners_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT learners_pkey PRIMARY KEY (id);


--
-- TOC entry 3788 (class 2606 OID 17748)
-- Name: locations locations_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.locations
    ADD CONSTRAINT locations_pkey PRIMARY KEY (location_id);


--
-- TOC entry 3790 (class 2606 OID 17750)
-- Name: products products_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_pkey PRIMARY KEY (product_id);


--
-- TOC entry 3792 (class 2606 OID 17752)
-- Name: progress_reports progress_reports_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.progress_reports
    ADD CONSTRAINT progress_reports_pkey PRIMARY KEY (report_id);


--
-- TOC entry 3774 (class 2606 OID 17754)
-- Name: latest_document qa_visits_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.latest_document
    ADD CONSTRAINT qa_visits_pkey PRIMARY KEY (id);


--
-- TOC entry 3794 (class 2606 OID 17756)
-- Name: qa_visits qa_visits_pkey1; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.qa_visits
    ADD CONSTRAINT qa_visits_pkey1 PRIMARY KEY (id);


--
-- TOC entry 3807 (class 2606 OID 17758)
-- Name: sites sites_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT sites_pkey PRIMARY KEY (site_id);


--
-- TOC entry 3728 (class 2606 OID 17760)
-- Name: class_material_tracking unique_class_notification_type; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_material_tracking
    ADD CONSTRAINT unique_class_notification_type UNIQUE (class_id, notification_type);


--
-- TOC entry 3810 (class 2606 OID 17762)
-- Name: user_permissions user_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.user_permissions
    ADD CONSTRAINT user_permissions_pkey PRIMARY KEY (permission_id);


--
-- TOC entry 3812 (class 2606 OID 17764)
-- Name: user_roles user_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.user_roles
    ADD CONSTRAINT user_roles_pkey PRIMARY KEY (role_id);


--
-- TOC entry 3814 (class 2606 OID 17766)
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- TOC entry 3816 (class 2606 OID 17768)
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (user_id);


--
-- TOC entry 3818 (class 2606 OID 17770)
-- Name: audit_log audit_log_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.audit_log
    ADD CONSTRAINT audit_log_pkey PRIMARY KEY (id);


--
-- TOC entry 3825 (class 2606 OID 17772)
-- Name: dashboard_status dashboard_status_class_id_task_type_key; Type: CONSTRAINT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.dashboard_status
    ADD CONSTRAINT dashboard_status_class_id_task_type_key UNIQUE (class_id, task_type);


--
-- TOC entry 3827 (class 2606 OID 17774)
-- Name: dashboard_status dashboard_status_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.dashboard_status
    ADD CONSTRAINT dashboard_status_pkey PRIMARY KEY (id);


--
-- TOC entry 3834 (class 2606 OID 17776)
-- Name: events_log events_log_idempotency_key_key; Type: CONSTRAINT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.events_log
    ADD CONSTRAINT events_log_idempotency_key_key UNIQUE (idempotency_key);


--
-- TOC entry 3836 (class 2606 OID 17778)
-- Name: events_log events_log_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.events_log
    ADD CONSTRAINT events_log_pkey PRIMARY KEY (id);


--
-- TOC entry 3850 (class 2606 OID 17780)
-- Name: notification_queue notification_queue_idempotency_key_key; Type: CONSTRAINT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.notification_queue
    ADD CONSTRAINT notification_queue_idempotency_key_key UNIQUE (idempotency_key);


--
-- TOC entry 3852 (class 2606 OID 17782)
-- Name: notification_queue notification_queue_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.notification_queue
    ADD CONSTRAINT notification_queue_pkey PRIMARY KEY (id);


--
-- TOC entry 3859 (class 2606 OID 17784)
-- Name: supervisors supervisors_email_key; Type: CONSTRAINT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.supervisors
    ADD CONSTRAINT supervisors_email_key UNIQUE (email);


--
-- TOC entry 3861 (class 2606 OID 17786)
-- Name: supervisors supervisors_pkey; Type: CONSTRAINT; Schema: wecoza_events; Owner: John
--

ALTER TABLE ONLY wecoza_events.supervisors
    ADD CONSTRAINT supervisors_pkey PRIMARY KEY (id);


--
-- TOC entry 3682 (class 1259 OID 17787)
-- Name: idx_agent_absences_agent_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agent_absences_agent_id ON public.agent_absences USING btree (agent_id);


--
-- TOC entry 3683 (class 1259 OID 17788)
-- Name: idx_agent_absences_date; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agent_absences_date ON public.agent_absences USING btree (absence_date);


--
-- TOC entry 3904 (class 1259 OID 24760)
-- Name: idx_agent_meta_agent_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agent_meta_agent_id ON public.agent_meta USING btree (agent_id);


--
-- TOC entry 3686 (class 1259 OID 17789)
-- Name: idx_agent_notes_agent_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agent_notes_agent_id ON public.agent_notes USING btree (agent_id);


--
-- TOC entry 3697 (class 1259 OID 17790)
-- Name: idx_agents_city; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_city ON public.agents USING btree (city);


--
-- TOC entry 3698 (class 1259 OID 17791)
-- Name: idx_agents_city_province; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_city_province ON public.agents USING btree (city, province);


--
-- TOC entry 3699 (class 1259 OID 17792)
-- Name: idx_agents_created_at; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_created_at ON public.agents USING btree (created_at);


--
-- TOC entry 3700 (class 1259 OID 17793)
-- Name: idx_agents_email; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_email ON public.agents USING btree (email_address);


--
-- TOC entry 3701 (class 1259 OID 17794)
-- Name: idx_agents_email_address; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_email_address ON public.agents USING btree (email_address);


--
-- TOC entry 3702 (class 1259 OID 17795)
-- Name: idx_agents_email_unique; Type: INDEX; Schema: public; Owner: John
--

CREATE UNIQUE INDEX idx_agents_email_unique ON public.agents USING btree (email_address) WHERE ((status)::text <> 'deleted'::text);


--
-- TOC entry 3703 (class 1259 OID 17796)
-- Name: idx_agents_first_name; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_first_name ON public.agents USING btree (first_name);


--
-- TOC entry 3704 (class 1259 OID 17797)
-- Name: idx_agents_phone; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_phone ON public.agents USING btree (tel_number);


--
-- TOC entry 3705 (class 1259 OID 17798)
-- Name: idx_agents_province; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_province ON public.agents USING btree (province);


--
-- TOC entry 3706 (class 1259 OID 17799)
-- Name: idx_agents_sa_id_no; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_sa_id_no ON public.agents USING btree (sa_id_no);


--
-- TOC entry 3707 (class 1259 OID 17800)
-- Name: idx_agents_sa_id_unique; Type: INDEX; Schema: public; Owner: John
--

CREATE UNIQUE INDEX idx_agents_sa_id_unique ON public.agents USING btree (sa_id_no) WHERE ((sa_id_no IS NOT NULL) AND ((sa_id_no)::text <> ''::text) AND ((status)::text <> 'deleted'::text));


--
-- TOC entry 3708 (class 1259 OID 17801)
-- Name: idx_agents_sace; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_sace ON public.agents USING btree (sace_number) WHERE ((sace_number IS NOT NULL) AND ((sace_number)::text <> ''::text));


--
-- TOC entry 3709 (class 1259 OID 17802)
-- Name: idx_agents_search; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_search ON public.agents USING btree (surname, first_name, email_address);


--
-- TOC entry 3710 (class 1259 OID 17803)
-- Name: idx_agents_status; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_status ON public.agents USING btree (status);


--
-- TOC entry 3711 (class 1259 OID 17804)
-- Name: idx_agents_status_created; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_status_created ON public.agents USING btree (status, created_at DESC);


--
-- TOC entry 3712 (class 1259 OID 17805)
-- Name: idx_agents_surname; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_surname ON public.agents USING btree (surname);


--
-- TOC entry 3713 (class 1259 OID 17806)
-- Name: idx_agents_tel_number; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_tel_number ON public.agents USING btree (tel_number);


--
-- TOC entry 3714 (class 1259 OID 17807)
-- Name: idx_agents_updated_at; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_updated_at ON public.agents USING btree (updated_at);


--
-- TOC entry 3715 (class 1259 OID 17808)
-- Name: idx_agents_working_areas; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_agents_working_areas ON public.agents USING btree (preferred_working_area_1, preferred_working_area_2, preferred_working_area_3);


--
-- TOC entry 3895 (class 1259 OID 24733)
-- Name: idx_class_events_created_at; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_class_events_created_at ON public.class_events USING btree (created_at DESC);


--
-- TOC entry 3896 (class 1259 OID 24731)
-- Name: idx_class_events_entity; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_class_events_entity ON public.class_events USING btree (entity_type, entity_id);


--
-- TOC entry 3897 (class 1259 OID 24732)
-- Name: idx_class_events_status; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_class_events_status ON public.class_events USING btree (notification_status) WHERE ((notification_status)::text = ANY ((ARRAY['pending'::character varying, 'enriching'::character varying, 'sending'::character varying])::text[]));


--
-- TOC entry 3898 (class 1259 OID 24735)
-- Name: idx_class_events_unread; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_class_events_unread ON public.class_events USING btree (created_at DESC) WHERE (viewed_at IS NULL);


--
-- TOC entry 3899 (class 1259 OID 24734)
-- Name: idx_class_events_user_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_class_events_user_id ON public.class_events USING btree (user_id) WHERE (user_id IS NOT NULL);


--
-- TOC entry 3866 (class 1259 OID 24598)
-- Name: idx_class_types_active; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_class_types_active ON public.class_types USING btree (is_active);


--
-- TOC entry 3867 (class 1259 OID 24597)
-- Name: idx_class_types_code; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_class_types_code ON public.class_types USING btree (class_type_code);


--
-- TOC entry 3868 (class 1259 OID 24599)
-- Name: idx_class_types_display_order; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_class_types_display_order ON public.class_types USING btree (display_order);


--
-- TOC entry 3737 (class 1259 OID 17812)
-- Name: idx_classes_backup_agent_ids; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_classes_backup_agent_ids ON public.classes USING gin (backup_agent_ids);


--
-- TOC entry 3738 (class 1259 OID 17813)
-- Name: idx_classes_class_agent; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_classes_class_agent ON public.classes USING btree (class_agent);


--
-- TOC entry 3739 (class 1259 OID 17814)
-- Name: idx_classes_class_code; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_classes_class_code ON public.classes USING btree (class_code);


--
-- TOC entry 3740 (class 1259 OID 17815)
-- Name: idx_classes_class_subject; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_classes_class_subject ON public.classes USING btree (class_subject);


--
-- TOC entry 3741 (class 1259 OID 17816)
-- Name: idx_classes_exam_learners; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_classes_exam_learners ON public.classes USING gin (exam_learners);


--
-- TOC entry 3742 (class 1259 OID 17817)
-- Name: idx_classes_learner_ids; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_classes_learner_ids ON public.classes USING gin (learner_ids);


--
-- TOC entry 3743 (class 1259 OID 17818)
-- Name: idx_classes_schedule_data; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_classes_schedule_data ON public.classes USING gin (schedule_data);


--
-- TOC entry 3744 (class 1259 OID 17819)
-- Name: idx_classes_site_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_classes_site_id ON public.classes USING btree (site_id);


--
-- TOC entry 3750 (class 1259 OID 17820)
-- Name: idx_clients_client_name; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_clients_client_name ON public.clients USING btree (client_name);


--
-- TOC entry 3751 (class 1259 OID 17821)
-- Name: idx_clients_contact_email; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_clients_contact_email ON public.clients USING btree (contact_person_email) WHERE (contact_person_email IS NOT NULL);


--
-- TOC entry 3752 (class 1259 OID 17822)
-- Name: idx_clients_contact_email_lower; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_clients_contact_email_lower ON public.clients USING btree (lower((contact_person_email)::text)) WHERE (contact_person_email IS NOT NULL);


--
-- TOC entry 3753 (class 1259 OID 24742)
-- Name: idx_clients_deleted_at; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_clients_deleted_at ON public.clients USING btree (deleted_at) WHERE (deleted_at IS NOT NULL);


--
-- TOC entry 3873 (class 1259 OID 24620)
-- Name: idx_cts_active; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_cts_active ON public.class_type_subjects USING btree (is_active);


--
-- TOC entry 3874 (class 1259 OID 24619)
-- Name: idx_cts_code; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_cts_code ON public.class_type_subjects USING btree (subject_code);


--
-- TOC entry 3875 (class 1259 OID 24621)
-- Name: idx_cts_display; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_cts_display ON public.class_type_subjects USING btree (class_type_id, display_order);


--
-- TOC entry 3876 (class 1259 OID 24618)
-- Name: idx_cts_type_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_cts_type_id ON public.class_type_subjects USING btree (class_type_id);


--
-- TOC entry 3885 (class 1259 OID 24709)
-- Name: idx_hours_log_date; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_hours_log_date ON public.learner_hours_log USING btree (log_date);


--
-- TOC entry 3886 (class 1259 OID 24708)
-- Name: idx_hours_log_learner; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_hours_log_learner ON public.learner_hours_log USING btree (learner_id);


--
-- TOC entry 3887 (class 1259 OID 24710)
-- Name: idx_hours_log_tracking; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_hours_log_tracking ON public.learner_hours_log USING btree (tracking_id);


--
-- TOC entry 3905 (class 1259 OID 24782)
-- Name: idx_learner_sponsors_employer_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_learner_sponsors_employer_id ON public.learner_sponsors USING btree (employer_id);


--
-- TOC entry 3906 (class 1259 OID 24781)
-- Name: idx_learner_sponsors_learner_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_learner_sponsors_learner_id ON public.learner_sponsors USING btree (learner_id);


--
-- TOC entry 3877 (class 1259 OID 24705)
-- Name: idx_lp_tracking_class; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_lp_tracking_class ON public.learner_lp_tracking USING btree (class_id);


--
-- TOC entry 3878 (class 1259 OID 24707)
-- Name: idx_lp_tracking_completion; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_lp_tracking_completion ON public.learner_lp_tracking USING btree (completion_date);


--
-- TOC entry 3879 (class 1259 OID 24703)
-- Name: idx_lp_tracking_learner; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_lp_tracking_learner ON public.learner_lp_tracking USING btree (learner_id);


--
-- TOC entry 3880 (class 1259 OID 24704)
-- Name: idx_lp_tracking_product; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_lp_tracking_product ON public.learner_lp_tracking USING btree (product_id);


--
-- TOC entry 3881 (class 1259 OID 24706)
-- Name: idx_lp_tracking_status; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_lp_tracking_status ON public.learner_lp_tracking USING btree (status);


--
-- TOC entry 3722 (class 1259 OID 17823)
-- Name: idx_material_tracking_class_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_material_tracking_class_id ON public.class_material_tracking USING btree (class_id);


--
-- TOC entry 3723 (class 1259 OID 17824)
-- Name: idx_material_tracking_class_type_status; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_material_tracking_class_type_status ON public.class_material_tracking USING btree (class_id, notification_type, delivery_status);


--
-- TOC entry 3724 (class 1259 OID 17825)
-- Name: idx_material_tracking_sent_at; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_material_tracking_sent_at ON public.class_material_tracking USING btree (notification_sent_at);


--
-- TOC entry 3725 (class 1259 OID 17826)
-- Name: idx_material_tracking_status; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_material_tracking_status ON public.class_material_tracking USING btree (delivery_status);


--
-- TOC entry 3726 (class 1259 OID 17827)
-- Name: idx_material_tracking_type; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_material_tracking_type ON public.class_material_tracking USING btree (notification_type);


--
-- TOC entry 3882 (class 1259 OID 24654)
-- Name: idx_one_active_lp_per_learner; Type: INDEX; Schema: public; Owner: John
--

CREATE UNIQUE INDEX idx_one_active_lp_per_learner ON public.learner_lp_tracking USING btree (learner_id) WHERE ((status)::text = 'in_progress'::text);


--
-- TOC entry 3890 (class 1259 OID 24711)
-- Name: idx_progression_portfolios_tracking; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_progression_portfolios_tracking ON public.learner_progression_portfolios USING btree (tracking_id);


--
-- TOC entry 3769 (class 1259 OID 17828)
-- Name: idx_qa_visits_class_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_qa_visits_class_id ON public.latest_document USING btree (class_id);


--
-- TOC entry 3770 (class 1259 OID 17829)
-- Name: idx_qa_visits_officer_name; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_qa_visits_officer_name ON public.latest_document USING btree (officer_name);


--
-- TOC entry 3771 (class 1259 OID 17830)
-- Name: idx_qa_visits_visit_date; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_qa_visits_visit_date ON public.latest_document USING btree (visit_date);


--
-- TOC entry 3772 (class 1259 OID 17831)
-- Name: idx_qa_visits_visit_type; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_qa_visits_visit_type ON public.latest_document USING btree (visit_type);


--
-- TOC entry 3795 (class 1259 OID 17832)
-- Name: idx_sites_client_hierarchy; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_sites_client_hierarchy ON public.sites USING btree (client_id, parent_site_id);


--
-- TOC entry 3796 (class 1259 OID 17833)
-- Name: idx_sites_client_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_sites_client_id ON public.sites USING btree (client_id);


--
-- TOC entry 3797 (class 1259 OID 17834)
-- Name: idx_sites_client_place; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_sites_client_place ON public.sites USING btree (client_id, place_id);


--
-- TOC entry 3798 (class 1259 OID 17835)
-- Name: idx_sites_created_at; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_sites_created_at ON public.sites USING btree (created_at);


--
-- TOC entry 3799 (class 1259 OID 17836)
-- Name: idx_sites_place_lookup; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_sites_place_lookup ON public.sites USING btree (place_id);


--
-- TOC entry 3800 (class 1259 OID 17837)
-- Name: idx_sites_site_name; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_sites_site_name ON public.sites USING btree (site_name);


--
-- TOC entry 3801 (class 1259 OID 17838)
-- Name: idx_sites_site_name_lower; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX idx_sites_site_name_lower ON public.sites USING btree (lower((site_name)::text));


--
-- TOC entry 3802 (class 1259 OID 17839)
-- Name: idxu_sites_client_head_site_name_ci; Type: INDEX; Schema: public; Owner: John
--

CREATE UNIQUE INDEX idxu_sites_client_head_site_name_ci ON public.sites USING btree (client_id, lower((site_name)::text)) WHERE (parent_site_id IS NULL);


--
-- TOC entry 4658 (class 0 OID 0)
-- Dependencies: 3802
-- Name: INDEX idxu_sites_client_head_site_name_ci; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON INDEX public.idxu_sites_client_head_site_name_ci IS 'Ensures unique (case-insensitive) head site names within each client (parent_site_id IS NULL).';


--
-- TOC entry 3803 (class 1259 OID 17840)
-- Name: idxu_sites_parent_site_name_ci; Type: INDEX; Schema: public; Owner: John
--

CREATE UNIQUE INDEX idxu_sites_parent_site_name_ci ON public.sites USING btree (parent_site_id, lower((site_name)::text)) WHERE (parent_site_id IS NOT NULL);


--
-- TOC entry 4659 (class 0 OID 0)
-- Dependencies: 3803
-- Name: INDEX idxu_sites_parent_site_name_ci; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON INDEX public.idxu_sites_parent_site_name_ci IS 'Ensures unique (case-insensitive) sub-site names within each parent site (parent_site_id IS NOT NULL).';


--
-- TOC entry 3754 (class 1259 OID 17841)
-- Name: ix_clients_main_client_id; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX ix_clients_main_client_id ON public.clients USING btree (main_client_id);


--
-- TOC entry 3747 (class 1259 OID 17842)
-- Name: ix_comm_site; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX ix_comm_site ON public.client_communications USING btree (site_id);


--
-- TOC entry 3804 (class 1259 OID 17843)
-- Name: ix_sites_parent; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX ix_sites_parent ON public.sites USING btree (parent_site_id);


--
-- TOC entry 3805 (class 1259 OID 17844)
-- Name: ix_sites_place; Type: INDEX; Schema: public; Owner: John
--

CREATE INDEX ix_sites_place ON public.sites USING btree (place_id);


--
-- TOC entry 3808 (class 1259 OID 17845)
-- Name: uq_sites_client_lowername; Type: INDEX; Schema: public; Owner: John
--

CREATE UNIQUE INDEX uq_sites_client_lowername ON public.sites USING btree (client_id, lower((site_name)::text));


--
-- TOC entry 3819 (class 1259 OID 17846)
-- Name: idx_audit_log_action; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_audit_log_action ON wecoza_events.audit_log USING btree (action);


--
-- TOC entry 3820 (class 1259 OID 17847)
-- Name: idx_audit_log_context; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_audit_log_context ON wecoza_events.audit_log USING gin (context);


--
-- TOC entry 3821 (class 1259 OID 17848)
-- Name: idx_audit_log_created_at; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_audit_log_created_at ON wecoza_events.audit_log USING btree (created_at);


--
-- TOC entry 3822 (class 1259 OID 17849)
-- Name: idx_audit_log_level; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_audit_log_level ON wecoza_events.audit_log USING btree (level);


--
-- TOC entry 3823 (class 1259 OID 17850)
-- Name: idx_audit_log_user_id; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_audit_log_user_id ON wecoza_events.audit_log USING btree (user_id);


--
-- TOC entry 3828 (class 1259 OID 17851)
-- Name: idx_dashboard_status_class_id; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_dashboard_status_class_id ON wecoza_events.dashboard_status USING btree (class_id);


--
-- TOC entry 3829 (class 1259 OID 17852)
-- Name: idx_dashboard_status_due_date; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_dashboard_status_due_date ON wecoza_events.dashboard_status USING btree (due_date);


--
-- TOC entry 3830 (class 1259 OID 17853)
-- Name: idx_dashboard_status_responsible_user_id; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_dashboard_status_responsible_user_id ON wecoza_events.dashboard_status USING btree (responsible_user_id);


--
-- TOC entry 3831 (class 1259 OID 17854)
-- Name: idx_dashboard_status_task_status; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_dashboard_status_task_status ON wecoza_events.dashboard_status USING btree (task_status);


--
-- TOC entry 3832 (class 1259 OID 17855)
-- Name: idx_dashboard_status_task_type; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_dashboard_status_task_type ON wecoza_events.dashboard_status USING btree (task_type);


--
-- TOC entry 3837 (class 1259 OID 17856)
-- Name: idx_events_log_actor_id; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_events_log_actor_id ON wecoza_events.events_log USING btree (actor_id);


--
-- TOC entry 3838 (class 1259 OID 17857)
-- Name: idx_events_log_class_id; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_events_log_class_id ON wecoza_events.events_log USING btree (class_id);


--
-- TOC entry 3839 (class 1259 OID 17858)
-- Name: idx_events_log_event_name; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_events_log_event_name ON wecoza_events.events_log USING btree (event_name);


--
-- TOC entry 3840 (class 1259 OID 17859)
-- Name: idx_events_log_occurred_at; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_events_log_occurred_at ON wecoza_events.events_log USING btree (occurred_at);


--
-- TOC entry 3841 (class 1259 OID 17860)
-- Name: idx_events_log_payload; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_events_log_payload ON wecoza_events.events_log USING gin (event_payload);


--
-- TOC entry 3842 (class 1259 OID 17861)
-- Name: idx_events_log_processed; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_events_log_processed ON wecoza_events.events_log USING btree (processed);


--
-- TOC entry 3843 (class 1259 OID 17862)
-- Name: idx_notification_queue_channel; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_notification_queue_channel ON wecoza_events.notification_queue USING btree (channel);


--
-- TOC entry 3844 (class 1259 OID 17863)
-- Name: idx_notification_queue_event_name; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_notification_queue_event_name ON wecoza_events.notification_queue USING btree (event_name);


--
-- TOC entry 3845 (class 1259 OID 17864)
-- Name: idx_notification_queue_recipient_email; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_notification_queue_recipient_email ON wecoza_events.notification_queue USING btree (recipient_email);


--
-- TOC entry 3846 (class 1259 OID 17865)
-- Name: idx_notification_queue_scheduled_at; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_notification_queue_scheduled_at ON wecoza_events.notification_queue USING btree (scheduled_at);


--
-- TOC entry 3847 (class 1259 OID 17866)
-- Name: idx_notification_queue_status; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_notification_queue_status ON wecoza_events.notification_queue USING btree (status);


--
-- TOC entry 3848 (class 1259 OID 17867)
-- Name: idx_notification_queue_template_name; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_notification_queue_template_name ON wecoza_events.notification_queue USING btree (template_name);


--
-- TOC entry 3853 (class 1259 OID 17868)
-- Name: idx_supervisors_client_assignments; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_supervisors_client_assignments ON wecoza_events.supervisors USING gin (client_assignments);


--
-- TOC entry 3854 (class 1259 OID 17869)
-- Name: idx_supervisors_email; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_supervisors_email ON wecoza_events.supervisors USING btree (email);


--
-- TOC entry 3855 (class 1259 OID 17870)
-- Name: idx_supervisors_is_active; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_supervisors_is_active ON wecoza_events.supervisors USING btree (is_active);


--
-- TOC entry 3856 (class 1259 OID 17871)
-- Name: idx_supervisors_is_default; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_supervisors_is_default ON wecoza_events.supervisors USING btree (is_default);


--
-- TOC entry 3857 (class 1259 OID 17872)
-- Name: idx_supervisors_site_assignments; Type: INDEX; Schema: wecoza_events; Owner: John
--

CREATE INDEX idx_supervisors_site_assignments ON wecoza_events.supervisors USING gin (site_assignments);


--
-- TOC entry 3981 (class 2620 OID 17874)
-- Name: sites trg_sites_same_client; Type: TRIGGER; Schema: public; Owner: John
--

CREATE TRIGGER trg_sites_same_client BEFORE INSERT OR UPDATE OF client_id, parent_site_id ON public.sites FOR EACH ROW EXECUTE FUNCTION public.fn_sites_same_client();


--
-- TOC entry 3979 (class 2620 OID 17875)
-- Name: agents update_agents_updated_at; Type: TRIGGER; Schema: public; Owner: John
--

CREATE TRIGGER update_agents_updated_at BEFORE UPDATE ON public.agents FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- TOC entry 3980 (class 2620 OID 17876)
-- Name: class_material_tracking update_material_tracking_updated_at; Type: TRIGGER; Schema: public; Owner: John
--

CREATE TRIGGER update_material_tracking_updated_at BEFORE UPDATE ON public.class_material_tracking FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- TOC entry 3982 (class 2620 OID 17877)
-- Name: dashboard_status update_dashboard_status_updated_at; Type: TRIGGER; Schema: wecoza_events; Owner: John
--

CREATE TRIGGER update_dashboard_status_updated_at BEFORE UPDATE ON wecoza_events.dashboard_status FOR EACH ROW EXECUTE FUNCTION wecoza_events.update_updated_at_column();


--
-- TOC entry 3983 (class 2620 OID 17878)
-- Name: notification_queue update_notification_queue_updated_at; Type: TRIGGER; Schema: wecoza_events; Owner: John
--

CREATE TRIGGER update_notification_queue_updated_at BEFORE UPDATE ON wecoza_events.notification_queue FOR EACH ROW EXECUTE FUNCTION wecoza_events.update_updated_at_column();


--
-- TOC entry 3984 (class 2620 OID 17879)
-- Name: supervisors update_supervisors_updated_at; Type: TRIGGER; Schema: wecoza_events; Owner: John
--

CREATE TRIGGER update_supervisors_updated_at BEFORE UPDATE ON wecoza_events.supervisors FOR EACH ROW EXECUTE FUNCTION wecoza_events.update_updated_at_column();


--
-- TOC entry 3911 (class 2606 OID 17880)
-- Name: agent_absences agent_absences_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_absences
    ADD CONSTRAINT agent_absences_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 3912 (class 2606 OID 17885)
-- Name: agent_absences agent_absences_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_absences
    ADD CONSTRAINT agent_absences_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3976 (class 2606 OID 24755)
-- Name: agent_meta agent_meta_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_meta
    ADD CONSTRAINT agent_meta_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id) ON DELETE CASCADE;


--
-- TOC entry 3913 (class 2606 OID 17890)
-- Name: agent_notes agent_notes_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_notes
    ADD CONSTRAINT agent_notes_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 3914 (class 2606 OID 17895)
-- Name: agent_orders agent_orders_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_orders
    ADD CONSTRAINT agent_orders_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 3915 (class 2606 OID 17900)
-- Name: agent_orders agent_orders_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_orders
    ADD CONSTRAINT agent_orders_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3916 (class 2606 OID 17905)
-- Name: agent_replacements agent_replacements_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_replacements
    ADD CONSTRAINT agent_replacements_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3917 (class 2606 OID 17910)
-- Name: agent_replacements agent_replacements_original_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_replacements
    ADD CONSTRAINT agent_replacements_original_agent_id_fkey FOREIGN KEY (original_agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 3918 (class 2606 OID 17915)
-- Name: agent_replacements agent_replacements_replacement_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agent_replacements
    ADD CONSTRAINT agent_replacements_replacement_agent_id_fkey FOREIGN KEY (replacement_agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 3919 (class 2606 OID 17920)
-- Name: agents agents_preferred_working_area_1_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_preferred_working_area_1_fkey FOREIGN KEY (preferred_working_area_1) REFERENCES public.locations(location_id);


--
-- TOC entry 3920 (class 2606 OID 17925)
-- Name: agents agents_preferred_working_area_2_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_preferred_working_area_2_fkey FOREIGN KEY (preferred_working_area_2) REFERENCES public.locations(location_id);


--
-- TOC entry 3921 (class 2606 OID 17930)
-- Name: agents agents_preferred_working_area_3_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.agents
    ADD CONSTRAINT agents_preferred_working_area_3_fkey FOREIGN KEY (preferred_working_area_3) REFERENCES public.locations(location_id);


--
-- TOC entry 3922 (class 2606 OID 17935)
-- Name: attendance_registers attendance_registers_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.attendance_registers
    ADD CONSTRAINT attendance_registers_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 3923 (class 2606 OID 17940)
-- Name: attendance_registers attendance_registers_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.attendance_registers
    ADD CONSTRAINT attendance_registers_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3924 (class 2606 OID 17945)
-- Name: class_agents class_agents_agent_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_agents
    ADD CONSTRAINT class_agents_agent_id_fkey FOREIGN KEY (agent_id) REFERENCES public.agents(agent_id);


--
-- TOC entry 3925 (class 2606 OID 17950)
-- Name: class_agents class_agents_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_agents
    ADD CONSTRAINT class_agents_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3927 (class 2606 OID 17960)
-- Name: class_notes class_notes_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_notes
    ADD CONSTRAINT class_notes_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3928 (class 2606 OID 17965)
-- Name: class_schedules class_schedules_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_schedules
    ADD CONSTRAINT class_schedules_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3929 (class 2606 OID 17970)
-- Name: class_subjects class_subjects_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_subjects
    ADD CONSTRAINT class_subjects_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3930 (class 2606 OID 17975)
-- Name: class_subjects class_subjects_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_subjects
    ADD CONSTRAINT class_subjects_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 3967 (class 2606 OID 24613)
-- Name: class_type_subjects class_type_subjects_class_type_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_type_subjects
    ADD CONSTRAINT class_type_subjects_class_type_id_fkey FOREIGN KEY (class_type_id) REFERENCES public.class_types(class_type_id) ON DELETE CASCADE;


--
-- TOC entry 3931 (class 2606 OID 17980)
-- Name: classes classes_client_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT classes_client_id_fkey FOREIGN KEY (client_id) REFERENCES public.clients(client_id);


--
-- TOC entry 3932 (class 2606 OID 17985)
-- Name: classes classes_project_supervisor_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT classes_project_supervisor_id_fkey FOREIGN KEY (project_supervisor_id) REFERENCES public.users(user_id);


--
-- TOC entry 3935 (class 2606 OID 17990)
-- Name: client_communications client_communications_client_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.client_communications
    ADD CONSTRAINT client_communications_client_id_fkey FOREIGN KEY (client_id) REFERENCES public.clients(client_id);


--
-- TOC entry 3936 (class 2606 OID 17995)
-- Name: client_communications client_communications_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.client_communications
    ADD CONSTRAINT client_communications_site_id_fkey FOREIGN KEY (site_id) REFERENCES public.sites(site_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 3937 (class 2606 OID 18000)
-- Name: client_communications client_communications_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.client_communications
    ADD CONSTRAINT client_communications_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- TOC entry 3938 (class 2606 OID 18005)
-- Name: clients clients_main_client_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.clients
    ADD CONSTRAINT clients_main_client_id_fkey FOREIGN KEY (main_client_id) REFERENCES public.clients(client_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 3939 (class 2606 OID 18010)
-- Name: collections collections_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.collections
    ADD CONSTRAINT collections_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3940 (class 2606 OID 18015)
-- Name: deliveries deliveries_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.deliveries
    ADD CONSTRAINT deliveries_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3941 (class 2606 OID 18020)
-- Name: exam_results exam_results_exam_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.exam_results
    ADD CONSTRAINT exam_results_exam_id_fkey FOREIGN KEY (exam_id) REFERENCES public.exams(exam_id);


--
-- TOC entry 3942 (class 2606 OID 18025)
-- Name: exam_results exam_results_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.exam_results
    ADD CONSTRAINT exam_results_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 3943 (class 2606 OID 18030)
-- Name: exams exams_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.exams
    ADD CONSTRAINT exams_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 3944 (class 2606 OID 18035)
-- Name: exams exams_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.exams
    ADD CONSTRAINT exams_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 3926 (class 2606 OID 18040)
-- Name: class_material_tracking fk_class_material_tracking_class; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.class_material_tracking
    ADD CONSTRAINT fk_class_material_tracking_class FOREIGN KEY (class_id) REFERENCES public.classes(class_id) ON DELETE CASCADE;


--
-- TOC entry 3933 (class 2606 OID 18045)
-- Name: classes fk_classes_agent; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT fk_classes_agent FOREIGN KEY (class_agent) REFERENCES public.agents(agent_id) ON UPDATE CASCADE ON DELETE SET NULL;


--
-- TOC entry 3934 (class 2606 OID 18050)
-- Name: classes fk_classes_site; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.classes
    ADD CONSTRAINT fk_classes_site FOREIGN KEY (site_id) REFERENCES public.sites(site_id) ON DELETE SET NULL;


--
-- TOC entry 3953 (class 2606 OID 18055)
-- Name: learners fk_highest_qualification; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT fk_highest_qualification FOREIGN KEY (highest_qualification) REFERENCES public.learner_qualifications(id);


--
-- TOC entry 4660 (class 0 OID 0)
-- Dependencies: 3953
-- Name: CONSTRAINT fk_highest_qualification ON learners; Type: COMMENT; Schema: public; Owner: John
--

COMMENT ON CONSTRAINT fk_highest_qualification ON public.learners IS 'Ensures that highest_qualification in learners references a valid id in learner_qualifications.';


--
-- TOC entry 3971 (class 2606 OID 24678)
-- Name: learner_hours_log fk_hours_log_class; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_hours_log
    ADD CONSTRAINT fk_hours_log_class FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3972 (class 2606 OID 24668)
-- Name: learner_hours_log fk_hours_log_learner; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_hours_log
    ADD CONSTRAINT fk_hours_log_learner FOREIGN KEY (learner_id) REFERENCES public.learners(id) ON DELETE CASCADE;


--
-- TOC entry 3973 (class 2606 OID 24673)
-- Name: learner_hours_log fk_hours_log_product; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_hours_log
    ADD CONSTRAINT fk_hours_log_product FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 3974 (class 2606 OID 24683)
-- Name: learner_hours_log fk_hours_log_tracking; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_hours_log
    ADD CONSTRAINT fk_hours_log_tracking FOREIGN KEY (tracking_id) REFERENCES public.learner_lp_tracking(tracking_id) ON DELETE SET NULL;


--
-- TOC entry 3968 (class 2606 OID 24649)
-- Name: learner_lp_tracking fk_lp_tracking_class; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_lp_tracking
    ADD CONSTRAINT fk_lp_tracking_class FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3969 (class 2606 OID 24639)
-- Name: learner_lp_tracking fk_lp_tracking_learner; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_lp_tracking
    ADD CONSTRAINT fk_lp_tracking_learner FOREIGN KEY (learner_id) REFERENCES public.learners(id) ON DELETE CASCADE;


--
-- TOC entry 3970 (class 2606 OID 24644)
-- Name: learner_lp_tracking fk_lp_tracking_product; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_lp_tracking
    ADD CONSTRAINT fk_lp_tracking_product FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 3954 (class 2606 OID 18060)
-- Name: learners fk_placement_level; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT fk_placement_level FOREIGN KEY (numeracy_level) REFERENCES public.learner_placement_level(placement_level_id) ON UPDATE CASCADE;


--
-- TOC entry 3975 (class 2606 OID 24698)
-- Name: learner_progression_portfolios fk_portfolio_tracking; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_progression_portfolios
    ADD CONSTRAINT fk_portfolio_tracking FOREIGN KEY (tracking_id) REFERENCES public.learner_lp_tracking(tracking_id) ON DELETE CASCADE;


--
-- TOC entry 3946 (class 2606 OID 18065)
-- Name: latest_document fk_qa_visits_class; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.latest_document
    ADD CONSTRAINT fk_qa_visits_class FOREIGN KEY (class_id) REFERENCES public.classes(class_id) ON DELETE CASCADE;


--
-- TOC entry 3962 (class 2606 OID 18070)
-- Name: qa_visits fk_qa_visits_class; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.qa_visits
    ADD CONSTRAINT fk_qa_visits_class FOREIGN KEY (class_id) REFERENCES public.classes(class_id) ON DELETE CASCADE;


--
-- TOC entry 3963 (class 2606 OID 18075)
-- Name: sites fk_sites_client; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT fk_sites_client FOREIGN KEY (client_id) REFERENCES public.clients(client_id) ON DELETE CASCADE;


--
-- TOC entry 3945 (class 2606 OID 18080)
-- Name: history history_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.history
    ADD CONSTRAINT history_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- TOC entry 3947 (class 2606 OID 18085)
-- Name: learner_portfolios learner_portfolios_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_portfolios
    ADD CONSTRAINT learner_portfolios_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- TOC entry 3948 (class 2606 OID 18090)
-- Name: learner_products learner_products_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_products
    ADD CONSTRAINT learner_products_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 3949 (class 2606 OID 18095)
-- Name: learner_products learner_products_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_products
    ADD CONSTRAINT learner_products_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 3950 (class 2606 OID 18100)
-- Name: learner_progressions learner_progressions_from_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_progressions
    ADD CONSTRAINT learner_progressions_from_product_id_fkey FOREIGN KEY (from_product_id) REFERENCES public.products(product_id);


--
-- TOC entry 3951 (class 2606 OID 18105)
-- Name: learner_progressions learner_progressions_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_progressions
    ADD CONSTRAINT learner_progressions_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 3952 (class 2606 OID 18110)
-- Name: learner_progressions learner_progressions_to_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_progressions
    ADD CONSTRAINT learner_progressions_to_product_id_fkey FOREIGN KEY (to_product_id) REFERENCES public.products(product_id);


--
-- TOC entry 3977 (class 2606 OID 24776)
-- Name: learner_sponsors learner_sponsors_employer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_sponsors
    ADD CONSTRAINT learner_sponsors_employer_id_fkey FOREIGN KEY (employer_id) REFERENCES public.employers(employer_id) ON DELETE CASCADE;


--
-- TOC entry 3978 (class 2606 OID 24771)
-- Name: learner_sponsors learner_sponsors_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learner_sponsors
    ADD CONSTRAINT learner_sponsors_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id) ON DELETE CASCADE;


--
-- TOC entry 3955 (class 2606 OID 18115)
-- Name: learners learners_city_town_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT learners_city_town_id_fkey FOREIGN KEY (city_town_id) REFERENCES public.locations(location_id);


--
-- TOC entry 3956 (class 2606 OID 18120)
-- Name: learners learners_employer_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT learners_employer_id_fkey FOREIGN KEY (employer_id) REFERENCES public.employers(employer_id);


--
-- TOC entry 3957 (class 2606 OID 18125)
-- Name: learners learners_province_region_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.learners
    ADD CONSTRAINT learners_province_region_id_fkey FOREIGN KEY (province_region_id) REFERENCES public.locations(location_id);


--
-- TOC entry 3958 (class 2606 OID 18130)
-- Name: products products_parent_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.products
    ADD CONSTRAINT products_parent_product_id_fkey FOREIGN KEY (parent_product_id) REFERENCES public.products(product_id);


--
-- TOC entry 3959 (class 2606 OID 18135)
-- Name: progress_reports progress_reports_class_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.progress_reports
    ADD CONSTRAINT progress_reports_class_id_fkey FOREIGN KEY (class_id) REFERENCES public.classes(class_id);


--
-- TOC entry 3960 (class 2606 OID 18140)
-- Name: progress_reports progress_reports_learner_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.progress_reports
    ADD CONSTRAINT progress_reports_learner_id_fkey FOREIGN KEY (learner_id) REFERENCES public.learners(id);


--
-- TOC entry 3961 (class 2606 OID 18145)
-- Name: progress_reports progress_reports_product_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.progress_reports
    ADD CONSTRAINT progress_reports_product_id_fkey FOREIGN KEY (product_id) REFERENCES public.products(product_id);


--
-- TOC entry 3964 (class 2606 OID 18150)
-- Name: sites sites_parent_site_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT sites_parent_site_id_fkey FOREIGN KEY (parent_site_id) REFERENCES public.sites(site_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- TOC entry 3965 (class 2606 OID 18155)
-- Name: sites sites_place_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.sites
    ADD CONSTRAINT sites_place_id_fkey FOREIGN KEY (place_id) REFERENCES public.locations(location_id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- TOC entry 3966 (class 2606 OID 18160)
-- Name: user_permissions user_permissions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: John
--

ALTER TABLE ONLY public.user_permissions
    ADD CONSTRAINT user_permissions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(user_id);


--
-- TOC entry 4232 (class 0 OID 0)
-- Dependencies: 7
-- Name: SCHEMA public; Type: ACL; Schema: -; Owner: pg_database_owner
--

REVOKE USAGE ON SCHEMA public FROM PUBLIC;


-- Completed on 2026-02-16 13:34:23 SAST

--
-- PostgreSQL database dump complete
--

\unrestrict YZKLCwJqIHseTelKkfqY8aaQpcxUFHmFjPIRt5B8diuvrRX6GQPMqqaSrR9l8wW

