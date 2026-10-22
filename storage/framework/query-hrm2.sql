SELECT id, uuid, full_name, work_email FROM va_hrm_employees WHERE work_email LIKE '%khoana%' OR uuid = '8f97e220-a3eb-44e7-b53e-0cc0e332f03d' LIMIT 5;
SELECT id, name FROM va_hrm_org_units WHERE id = 39;
SELECT COUNT(*) AS cnt FROM va_hrm_employee_assignments WHERE org_unit_id = 39 AND is_primary = 1;
