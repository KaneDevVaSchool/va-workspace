SELECT e.id, e.uuid, e.full_name, e.company_email, e.personal_email,
       a.org_unit_id, a.company_id, ou.name AS org_unit_name
FROM va_hrm_employees e
LEFT JOIN va_hrm_employee_assignments a ON a.employee_id = e.id AND a.is_primary = 1
LEFT JOIN va_hrm_org_units ou ON ou.id = a.org_unit_id
WHERE e.uuid = '8f97e220-a3eb-44e7-b53e-0cc0e332f03d'
   OR e.company_email = 'khoana@hcm.vaschools.edu.vn'
   OR e.personal_email = 'khoana@hcm.vaschools.edu.vn';

SELECT p.employee_id, e.uuid, e.full_name, p.work_date, p.check_in_time, p.check_out_time, p.source
FROM va_hrm_attendance_workspace_punches p
JOIN va_hrm_employees e ON e.id = p.employee_id
WHERE e.company_email = 'khoana@hcm.vaschools.edu.vn'
   OR e.personal_email = 'khoana@hcm.vaschools.edu.vn'
   OR e.uuid = '8f97e220-a3eb-44e7-b53e-0cc0e332f03d'
ORDER BY p.work_date DESC
LIMIT 5;
