/** Khóa khử trùng picker — khớp DepartmentRepository::pickerDedupeKey (PHP). */
export function departmentPickerKey(department) {
  const hrm = String(department?.hrm_code ?? department?.external_code ?? '')
    .trim()
    .toLowerCase();
  if (hrm !== '') {
    const companyId = department?.company_id ?? 0;
    return `${companyId}\0${hrm}`;
  }
  return `uuid:${department?.hrm_org_unit_uuid ?? department?.id ?? ''}`;
}

/** Nhãn mã hiển thị: mã HRM · mã công ty (phân biệt BGH/THPT trùng giữa các công ty). */
export function formatDepartmentMeta(department) {
  const hrm = String(
    department?.hrm_code ?? department?.external_code ?? department?.code ?? '',
  ).trim();
  const company = String(department?.company_code ?? '').trim();
  if (hrm && company) {
    return `${hrm} · ${company}`;
  }
  return hrm;
}

/**
 * Gom id đã chọn trùng (company_id + mã HRM) — giữ id lớn nhất.
 *
 * @param {number[]} ids
 * @param {Array<{ id: number|string, hrm_code?: string, external_code?: string, company_id?: number|null }>} departments
 */
export function collapseDuplicateDepartmentIds(ids, departments) {
  const byId = new Map(departments.map((d) => [String(d.id), d]));
  /** @type {Map<string, number>} */
  const best = new Map();

  for (const rawId of ids) {
    const department = byId.get(String(rawId));
    if (!department) {
      continue;
    }
    const key = departmentPickerKey(department);
    const id = Number(department.id);
    const prev = best.get(key);
    if (prev === undefined || id > prev) {
      best.set(key, id);
    }
  }

  return [...best.values()];
}
