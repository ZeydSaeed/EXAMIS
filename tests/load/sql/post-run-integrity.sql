-- Integrity checks after a k6 run on the synthetic test database (examis_perf).
-- Each statement returns one row: a check name and its number of violations.
-- Every number must be 0. Portable SQL: the same file runs on SQLite in Pest
-- (tests/Feature/QualityEngineering/LoadJourneyContractTest.php) and on
-- PostgreSQL after a run:
--
--   psql -h 127.0.0.1 -p <perf port> -d examis_perf -v ON_ERROR_STOP=1 -f tests/load/sql/post-run-integrity.sql
--
-- k6 cannot see the database, so these numbers, not HTTP statuses, decide
-- whether a run lost or duplicated anything.

SELECT 'duplicate_reference_numbers' AS check_name, COUNT(*) AS violations FROM (
    SELECT reference_number FROM applications
    WHERE reference_number IS NOT NULL
    GROUP BY reference_number HAVING COUNT(*) > 1
) AS d;

SELECT 'duplicate_applications_per_account_and_period' AS check_name, COUNT(*) AS violations FROM (
    SELECT user_id, exam_period_id FROM applications
    GROUP BY user_id, exam_period_id HAVING COUNT(*) > 1
) AS d;

SELECT 'submitted_without_reference' AS check_name, COUNT(*) AS violations
FROM applications
WHERE status <> 'draft' AND status <> 'withdrawn' AND reference_number IS NULL;

SELECT 'submission_events_not_exactly_one' AS check_name, COUNT(*) AS violations
FROM applications a
WHERE a.reference_number IS NOT NULL
  AND (SELECT COUNT(*) FROM application_events e
       WHERE e.application_id = a.id AND e.type = 'status_changed' AND e.to_status = 'submitted') <> 1;

SELECT 'period_counter_mismatch' AS check_name, COUNT(*) AS violations
FROM exam_periods p
WHERE p.last_sequence <> (SELECT COUNT(*) FROM applications a
                          WHERE a.exam_period_id = p.id AND a.reference_number IS NOT NULL);

SELECT 'more_than_one_current_document_per_type' AS check_name, COUNT(*) AS violations FROM (
    SELECT application_id, document_type_id FROM application_documents
    WHERE replaced_at IS NULL
    GROUP BY application_id, document_type_id HAVING COUNT(*) > 1
) AS d;

SELECT 'submitted_without_current_document' AS check_name, COUNT(*) AS violations
FROM applications a
WHERE a.reference_number IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM application_documents d
                  WHERE d.application_id = a.id AND d.replaced_at IS NULL);

-- Synthetic accounts only: k6 and PerfSeeder give every student a phone
-- starting 0000, which no real Iraqi number does.
SELECT 'non_synthetic_applicants' AS check_name, COUNT(*) AS violations
FROM users
WHERE account_type = 'applicant' AND phone IS NOT NULL AND phone NOT LIKE '0000%';
