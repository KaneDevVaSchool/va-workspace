-- Xem lệch UUID (company_email)
SELECT u.id, u.email, u.hrm_employee_uuid AS ws_uuid, e.uuid AS hrm_uuid, e.full_name
FROM va_workspace_users u
INNER JOIN va_hrm_employees e ON LOWER(TRIM(u.email)) = LOWER(TRIM(e.company_email))
WHERE u.hrm_employee_uuid IS NULL OR u.hrm_employee_uuid <> e.uuid;

-- Xem lệch (personal_email, chưa khớp company)
SELECT u.id, u.email, u.hrm_employee_uuid AS ws_uuid, e.uuid AS hrm_uuid, e.full_name
FROM va_workspace_users u
INNER JOIN va_hrm_employees e ON LOWER(TRIM(u.email)) = LOWER(TRIM(e.personal_email))
LEFT JOIN va_hrm_employees e2 ON LOWER(TRIM(u.email)) = LOWER(TRIM(e2.company_email))
WHERE e2.id IS NULL
  AND (u.hrm_employee_uuid IS NULL OR u.hrm_employee_uuid <> e.uuid);
