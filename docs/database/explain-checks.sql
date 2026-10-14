-- EXAMIS: query plans to capture on a staging copy (>= 100k synthetic applications).
-- Run as the application role. Record results in docs/database/SCHEMA.md.

-- Admin queue: period + status, ordered by submission time.
EXPLAIN (ANALYZE, BUFFERS)
SELECT * FROM applications
WHERE status <> 'draft' AND exam_period_id = 1 AND status = 'submitted'
ORDER BY submitted_at IS NULL, submitted_at, id
LIMIT 26 OFFSET 0;

-- Reviewer visibility scope.
EXPLAIN (ANALYZE, BUFFERS)
SELECT * FROM applications
WHERE status <> 'draft'
  AND (assigned_to = 42 OR (assigned_to IS NULL AND status IN ('submitted', 'resubmitted')))
ORDER BY submitted_at IS NULL, submitted_at, id
LIMIT 26;

-- Exact searches.
EXPLAIN (ANALYZE, BUFFERS) SELECT * FROM applications WHERE reference_number = 'EX2027-0012345';
EXPLAIN (ANALYZE, BUFFERS) SELECT * FROM applications WHERE national_id_number = '199912345678';

-- Prefix name search (needs text_pattern_ops index, see SCHEMA.md).
EXPLAIN (ANALYZE, BUFFERS) SELECT * FROM applications WHERE full_name_ar LIKE 'محمد%' LIMIT 26;

-- Dashboard aggregate.
EXPLAIN (ANALYZE, BUFFERS)
SELECT status, count(*) FROM applications WHERE status <> 'draft' GROUP BY status;

-- Report breakdown.
EXPLAIN (ANALYZE, BUFFERS)
SELECT governorate_code, count(*) FROM applications
WHERE exam_period_id = 1 AND status <> 'draft' GROUP BY governorate_code ORDER BY 2 DESC;

-- Audit viewer (cursor pagination).
EXPLAIN (ANALYZE, BUFFERS)
SELECT * FROM audit_events WHERE action LIKE 'application.%' AND id < 1000000 ORDER BY id DESC LIMIT 51;

-- Lock monitoring during load tests.
SELECT pid, wait_event_type, wait_event, state, now() - xact_start AS xact_age, left(query, 80)
FROM pg_stat_activity WHERE datname = current_database() AND state <> 'idle' ORDER BY xact_age DESC NULLS LAST;
