-- Chay bang: bash lab.sh sql ; \i database/queries.sql
-- Cac truy van KIEM TRA, khong can ALTER/DELETE.
SELECT 'problems' AS entity, COUNT(*) AS total FROM problems;
SELECT 'test_cases' AS entity, COUNT(*) AS total FROM test_cases;
SELECT s.id, s.user_id, s.problem_id,s.problem_version_id,s.suite_version_id,
       s.language,s.idempotency_key,j.generation,j.verdict,j.status
FROM submissions s LEFT JOIN judging_runs j ON j.id=s.latest_run_id
ORDER BY s.id DESC LIMIT 15;
-- LEFT JOIN giu ca lop chua co nguoi hoc va so luot nop bang 0.
SELECT c.id AS classroom_id,c.name,COUNT(DISTINCT m.user_id) AS student_count,
       COUNT(DISTINCT s.id) AS submission_count
FROM classrooms c LEFT JOIN class_memberships m ON m.classroom_id=c.id
LEFT JOIN submissions s ON s.user_id=m.user_id
GROUP BY c.id,c.name ORDER BY c.id;
-- Moi request_key cua mot user chi co mot submission.
SELECT user_id,idempotency_key,COUNT(*) AS n FROM submissions
GROUP BY user_id,idempotency_key HAVING COUNT(*)>1;
-- Chieu lich su chay, khong doc input/output kin.
SELECT s.id AS submission_id,j.id AS run_id,j.generation,sv.version_no AS suite_version,
       j.status,j.verdict,j.passed_count,j.total_count
FROM submissions s JOIN judging_runs j ON j.submission_id=s.id
JOIN test_suite_versions sv ON sv.id=j.suite_version_id
ORDER BY s.id DESC,j.generation DESC LIMIT 40;
