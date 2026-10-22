SELECT e.id, e.uuid, e.full_name, a.org_unit_id, a.company_id
FROM va_hrm_employees e
LEFT JOIN va_hrm_employee_assignments a ON a.employee_id = e.id AND a.is_primary = 1
WHERE e.uuid IN (
  '8f97e220-a3eb-44e7-b53e-0cc0e332f03d',
  'aa999990-fc51-45c8-9783-ad144b2db663'
);

SELECT employee_id, work_date, check_in_time, check_out_time
FROM va_hrm_attendance_workspace_punches
WHERE work_date = '2026-10-22';
