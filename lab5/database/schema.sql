-- De tai 20 - PostgreSQL 14+; CHI su dung CSDL moi, khong chay tren CSDL CommunityPass hien huu.
BEGIN;
CREATE TABLE IF NOT EXISTS users (
 id BIGSERIAL PRIMARY KEY, username VARCHAR(40) NOT NULL UNIQUE,
 password_hash TEXT NOT NULL, display_name VARCHAR(120) NOT NULL,
 role VARCHAR(12) NOT NULL CHECK(role IN ('student','teacher','admin')),
 created_at TIMESTAMPTZ NOT NULL DEFAULT now());
CREATE TABLE IF NOT EXISTS classrooms (
 id BIGSERIAL PRIMARY KEY, name VARCHAR(120) NOT NULL,
 teacher_id BIGINT NOT NULL REFERENCES users(id));
CREATE TABLE IF NOT EXISTS class_memberships (
 classroom_id BIGINT NOT NULL REFERENCES classrooms(id),
 user_id BIGINT NOT NULL REFERENCES users(id),
 PRIMARY KEY(classroom_id,user_id));
CREATE TABLE IF NOT EXISTS topics (id BIGSERIAL PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS levels (id BIGSERIAL PRIMARY KEY, name VARCHAR(60) NOT NULL UNIQUE);
CREATE TABLE IF NOT EXISTS problems (
 id BIGSERIAL PRIMARY KEY, topic_id BIGINT NOT NULL REFERENCES topics(id),
 level_id BIGINT NOT NULL REFERENCES levels(id),slug VARCHAR(120) NOT NULL UNIQUE,
 title VARCHAR(160) NOT NULL, published BOOLEAN NOT NULL DEFAULT false,
 current_version_id BIGINT, current_suite_id BIGINT,
 created_by BIGINT NOT NULL REFERENCES users(id),created_at TIMESTAMPTZ NOT NULL DEFAULT now());
CREATE TABLE IF NOT EXISTS problem_versions (
 id BIGSERIAL PRIMARY KEY,problem_id BIGINT NOT NULL REFERENCES problems(id),
 version_no INT NOT NULL CHECK(version_no > 0), statement TEXT NOT NULL,
 input_desc TEXT NOT NULL,output_desc TEXT NOT NULL,
 sample_input TEXT NOT NULL,sample_output TEXT NOT NULL,
 time_limit_ms INT NOT NULL CHECK(time_limit_ms BETWEEN 100 AND 5000),
 memory_limit_mb INT NOT NULL CHECK(memory_limit_mb BETWEEN 32 AND 256),
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(),UNIQUE(problem_id,version_no),
 UNIQUE(id,problem_id));
CREATE TABLE IF NOT EXISTS test_suite_versions (
 id BIGSERIAL PRIMARY KEY,problem_id BIGINT NOT NULL REFERENCES problems(id),
 problem_version_id BIGINT NOT NULL,
 version_no INT NOT NULL CHECK(version_no > 0),
 created_by BIGINT NOT NULL REFERENCES users(id),
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 UNIQUE(problem_id,version_no),UNIQUE(id,problem_id),
 FOREIGN KEY (problem_version_id,problem_id) REFERENCES problem_versions(id,problem_id));
CREATE TABLE IF NOT EXISTS test_cases (
 id BIGSERIAL PRIMARY KEY,suite_version_id BIGINT NOT NULL REFERENCES test_suite_versions(id),
 position INT NOT NULL CHECK(position > 0),input_data TEXT NOT NULL,
 expected_output TEXT NOT NULL,is_public BOOLEAN NOT NULL DEFAULT false,
 UNIQUE(suite_version_id,position));
ALTER TABLE problems DROP CONSTRAINT IF EXISTS fk_current_problem_version;
ALTER TABLE problems ADD CONSTRAINT fk_current_problem_version FOREIGN KEY(current_version_id,id) REFERENCES problem_versions(id,problem_id);
ALTER TABLE problems DROP CONSTRAINT IF EXISTS fk_current_suite_version;
ALTER TABLE problems ADD CONSTRAINT fk_current_suite_version FOREIGN KEY(current_suite_id,id) REFERENCES test_suite_versions(id,problem_id);
CREATE TABLE IF NOT EXISTS drafts (
 user_id BIGINT NOT NULL REFERENCES users(id),problem_id BIGINT NOT NULL REFERENCES problems(id),
 language VARCHAR(10) NOT NULL CHECK(language IN ('python','cpp')),
 code TEXT NOT NULL CHECK(octet_length(code)<=65536),updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 PRIMARY KEY(user_id,problem_id,language));
CREATE TABLE IF NOT EXISTS submissions (
 id BIGSERIAL PRIMARY KEY,user_id BIGINT NOT NULL REFERENCES users(id),
 problem_id BIGINT NOT NULL REFERENCES problems(id),
 problem_version_id BIGINT NOT NULL REFERENCES problem_versions(id),
 suite_version_id BIGINT NOT NULL REFERENCES test_suite_versions(id),
 language VARCHAR(10) NOT NULL CHECK(language IN ('python','cpp')),
 source_code TEXT NOT NULL CHECK(length(trim(source_code))>0 AND octet_length(source_code)<=65536),
 idempotency_key UUID NOT NULL,latest_run_id BIGINT,created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
 UNIQUE(user_id,idempotency_key));
CREATE TABLE IF NOT EXISTS judging_runs (
 id BIGSERIAL PRIMARY KEY,submission_id BIGINT NOT NULL REFERENCES submissions(id),
 suite_version_id BIGINT NOT NULL REFERENCES test_suite_versions(id),
 generation INT NOT NULL CHECK(generation>0),
 status VARCHAR(16) NOT NULL DEFAULT 'queued' CHECK(status IN ('queued','running','done','system_error')),
 verdict VARCHAR(20) CHECK(verdict IN ('AC','WA','CE','RE','TLE','SYSTEM_ERROR')),
 passed_count INT NOT NULL DEFAULT 0,total_count INT NOT NULL DEFAULT 0,
 lease_token TEXT,lease_until TIMESTAMPTZ,attempts INT NOT NULL DEFAULT 0,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now(),finished_at TIMESTAMPTZ,
 UNIQUE(submission_id,generation));
ALTER TABLE submissions DROP CONSTRAINT IF EXISTS fk_latest_run;
ALTER TABLE submissions ADD CONSTRAINT fk_latest_run FOREIGN KEY(latest_run_id) REFERENCES judging_runs(id);
CREATE TABLE IF NOT EXISTS case_results (
 id BIGSERIAL PRIMARY KEY,judging_run_id BIGINT NOT NULL REFERENCES judging_runs(id),
 test_case_id BIGINT NOT NULL REFERENCES test_cases(id),
 verdict VARCHAR(20) NOT NULL CHECK(verdict IN ('AC','WA','CE','RE','TLE')),
 elapsed_ms INT NOT NULL CHECK(elapsed_ms>=0),UNIQUE(judging_run_id,test_case_id));
CREATE TABLE IF NOT EXISTS trial_runs (
 id BIGSERIAL PRIMARY KEY,user_id BIGINT NOT NULL REFERENCES users(id),
 problem_id BIGINT NOT NULL REFERENCES problems(id),problem_version_id BIGINT NOT NULL REFERENCES problem_versions(id),
 language VARCHAR(10) NOT NULL CHECK(language IN ('python','cpp')),
 source_code TEXT NOT NULL CHECK(octet_length(source_code)<=65536),
 stdin_data TEXT NOT NULL CHECK(octet_length(stdin_data)<=16384),
 status VARCHAR(16) NOT NULL DEFAULT 'queued' CHECK(status IN ('queued','running','done','system_error')),
 result_kind VARCHAR(20),stdout_data TEXT,stderr_data TEXT,elapsed_ms INT,
 lease_token TEXT,lease_until TIMESTAMPTZ,attempts INT NOT NULL DEFAULT 0,
 created_at TIMESTAMPTZ NOT NULL DEFAULT now());
CREATE INDEX IF NOT EXISTS idx_trial_claim ON trial_runs(status,lease_until,id);
CREATE INDEX IF NOT EXISTS idx_trial_owner ON trial_runs(user_id,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_runs_claim ON judging_runs(status,lease_until,id);
CREATE INDEX IF NOT EXISTS idx_submission_user ON submissions(user_id,created_at DESC);
CREATE INDEX IF NOT EXISTS idx_submission_problem ON submissions(problem_id);
COMMIT;
