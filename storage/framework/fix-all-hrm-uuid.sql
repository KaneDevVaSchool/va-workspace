USE `va-workspace-stag-db`;

UPDATE va_workspace_users u
INNER JOIN `va-hrm-prod-db`.va_hrm_employees e ON LOWER(TRIM(u.email)) = LOWER(TRIM(e.company_email))
SET u.hrm_employee_uuid = e.uuid
WHERE u.hrm_employee_uuid IS NULL OR u.hrm_employee_uuid <> e.uuid;

UPDATE va_workspace_users u
INNER JOIN `va-hrm-prod-db`.va_hrm_employees e ON LOWER(TRIM(u.email)) = LOWER(TRIM(e.personal_email))
LEFT JOIN `va-hrm-prod-db`.va_hrm_employees e2 ON LOWER(TRIM(u.email)) = LOWER(TRIM(e2.company_email))
SET u.hrm_employee_uuid = e.uuid
WHERE e2.id IS NULL
  AND (u.hrm_employee_uuid IS NULL OR u.hrm_employee_uuid <> e.uuid);

SELECT COUNT(*) AS orphan_uuid FROM va_workspace_users u
WHERE u.hrm_employee_uuid IS NOT NULL AND u.hrm_employee_uuid <> ''
  AND NOT EXISTS (SELECT 1 FROM `va-hrm-prod-db`.va_hrm_employees e WHERE e.uuid = u.hrm_employee_uuid);

SELECT id, email, hrm_employee_uuid FROM va_workspace_users
WHERE status = 'active' AND (hrm_employee_uuid IS NULL OR hrm_employee_uuid = '');
