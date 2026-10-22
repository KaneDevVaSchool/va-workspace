UPDATE va_workspace_users
SET hrm_employee_uuid = 'dda2aa55-9766-440f-b04c-523f18e2a64b'
WHERE email = 'khoana@hcm.vaschools.edu.vn'
  AND hrm_employee_uuid = '8f97e220-a3eb-44e7-b53e-0cc0e332f03d';

SELECT id, email, hrm_employee_uuid FROM va_workspace_users WHERE email = 'khoana@hcm.vaschools.edu.vn';
