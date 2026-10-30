const BRANCH_UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

function readLatLon(entry) {
  if (!entry || typeof entry !== 'object') return { lat: null, lon: null };
  const lat = entry.latitude ?? entry.lat;
  const lon = entry.longitude ?? entry.lon ?? entry.lng;
  return {
    lat: typeof lat === 'number' ? lat : null,
    lon: typeof lon === 'number' ? lon : null,
  };
}

function branchLabels(raw) {
  const schoolName = [raw.school_name, raw.schoolName].find((value) => typeof value === 'string' && value.trim());
  const branchName = [raw.name, raw.label].find((value) => typeof value === 'string' && value.trim());
  const shortName = [raw.short_name, raw.shortName, raw.code].find((value) => typeof value === 'string' && value.trim());
  const schoolTitle = schoolName?.trim() || null;
  const title = schoolTitle
    || (branchName && branchName.trim())
    || (shortName && shortName.trim())
    || '';
  let code = shortName && shortName.trim() !== title ? shortName.trim() : null;
  if (!code && branchName && branchName.trim() !== title && schoolTitle) {
    code = branchName.trim();
  }
  return { schoolTitle, title, code };
}

/**
 * @param {Record<string, unknown>|null|undefined} raw
 * @param {string|number|null|undefined} fallbackId
 */
/**
 * @param {Record<string, unknown>|null|undefined} raw
 * @param {string|number|null|undefined} fallbackId
 * @param {{ requireCoords?: boolean }} [options]
 */
export function normalizeCheckInBranch(raw, fallbackId = null, options = {}) {
  if (!raw || typeof raw !== 'object') return null;
  const requireCoords = options.requireCoords !== false;
  const { lat, lon } = readLatLon(raw);
  if (requireCoords && (lat == null || lon == null)) return null;
  const id = raw.uuid ?? raw.id ?? fallbackId;
  const tolerance = raw.gps_tolerance_meters ?? raw.gpsToleranceMeters;
  const { schoolTitle, title, code } = branchLabels(raw);
  if (!title && lat == null) return null;
  const addressRaw = [raw.address_detail, raw.addressDetail, raw.address].find(
    (value) => typeof value === 'string' && value.trim(),
  );
  const address = addressRaw?.trim() || null;

  return {
    id: id != null ? String(id) : (lat != null ? `${lat},${lon}` : 'profile'),
    name: title || 'Cơ sở chấm công',
    schoolName: schoolTitle,
    code,
    address,
    lat,
    lon,
    gps_tolerance_meters: typeof tolerance === 'number' ? tolerance : null,
  };
}

/** UUID chi nhánh HRM — không gửi id nội bộ (`profile`, `default`). */
export function isCheckInBranchUuid(value) {
  return typeof value === 'string' && BRANCH_UUID_RE.test(value);
}

/**
 * Cơ sở HRM đã khớp từ hồ sơ (`workplace`, mã, hậu tố `(MÃ)`).
 * Có thể không có tọa độ khi chi nhánh chỉ bật WiFi.
 *
 * @param {Record<string, unknown>|null|undefined} context
 */
export function profileCheckInBranch(context) {
  if (!context || typeof context !== 'object') return null;
  const meta = context.branch && typeof context.branch === 'object' ? context.branch : null;
  if (!meta) return null;
  const top = readLatLon(context);
  const own = readLatLon(meta);
  const lat = top.lat ?? own.lat;
  const lon = top.lon ?? own.lon;
  const tolerance = meta.gps_tolerance_meters ?? context.gps_tolerance_meters;
  const row = normalizeCheckInBranch(
    {
      ...meta,
      latitude: lat,
      longitude: lon,
      gps_tolerance_meters: typeof tolerance === 'number' ? tolerance : null,
    },
    meta.uuid ?? 'profile',
    { requireCoords: false },
  );
  if (!row) return null;
  return { ...row, workplaceMatched: true };
}

/**
 * Danh sách chi nhánh active, bật GPS, có tọa độ — dùng khi hồ sơ không khớp.
 *
 * @param {Record<string, unknown>|null|undefined} context
 */
export function gpsBranchOptions(context) {
  if (!context) return [];
  const lists = [
    context.branches,
    context.check_in_branches,
    context.branch_options,
    context.available_branches,
    context.nearby_branches,
  ];
  for (const list of lists) {
    if (!Array.isArray(list) || list.length === 0) continue;
    const rows = list
      .map((item, index) => normalizeCheckInBranch(item, item?.uuid ?? index))
      .filter(Boolean);
    if (rows.length) return rows;
  }
  return [];
}

/**
 * @param {Record<string, unknown>|null|undefined} context
 * @returns {Array<{ id: string, name: string, lat: number, lon: number }>}
 */
export function branchesFromCheckInContext(context) {
  const listed = gpsBranchOptions(context);
  if (listed.length) return listed;

  const profile = profileCheckInBranch(context);
  if (profile?.lat != null && profile?.lon != null) return [profile];

  const { lat, lon } = readLatLon(context);
  if (lat == null || lon == null) return [];

  const branchMeta = context.branch && typeof context.branch === 'object' ? context.branch : {};
  const merged = normalizeCheckInBranch(
    {
      ...branchMeta,
      name: branchMeta.name ?? context.workplace_label,
      latitude: lat,
      longitude: lon,
      gps_tolerance_meters: context.gps_tolerance_meters,
    },
    branchMeta.uuid ?? 'default',
  );

  return merged ? [merged] : [];
}

/**
 * Cơ sở đang chấm: hồ sơ khớp thì giữ nguyên; không khớp thì gợi ý GPS gần nhất.
 * `selectedId` là lựa chọn tường minh của nhân viên.
 *
 * @param {Record<string, unknown>|null|undefined} context
 * @param {{ selectedId?: string|null, coords?: { lat: number, lon: number }|null }} [choice]
 */
export function resolveActiveCheckInBranch(context, choice = {}) {
  const options = gpsBranchOptions(context);
  const selectedId = choice.selectedId != null ? String(choice.selectedId) : '';
  if (selectedId) {
    const picked = options.find((item) => item.id === selectedId);
    if (picked) return { branch: picked, explicit: true };
  }

  const profile = profileCheckInBranch(context);
  if (profile) return { branch: profile, explicit: false };

  if (!options.length) return { branch: null, explicit: false };
  const nearest = choice.coords
    ? (nearestCheckInBranch(options, choice.coords) ?? options[0])
    : options[0];
  return { branch: nearest, explicit: false };
}

/**
 * Chỉ gửi `branch_uuid` khi cơ sở đang chấm khác cơ sở hồ sơ.
 * Bỏ qua thì HRM tự khớp workplace và cờ GPS/WiFi của chi nhánh đó.
 *
 * @param {Record<string, unknown>|null|undefined} context
 * @param {{ id?: string }|null|undefined} activeBranch
 */
export function branchUuidForPunch(context, activeBranch) {
  const id = activeBranch?.id != null ? String(activeBranch.id) : '';
  if (!isCheckInBranchUuid(id)) return null;
  const profile = profileCheckInBranch(context);
  if (profile && id === String(profile.id)) return null;
  return id;
}

/**
 * Ngữ cảnh đối chiếu GPS. Chi nhánh hồ sơ giữ payload HRM.
 * Chi nhánh chọn từ `branches[]` thì bắt GPS theo tọa độ của dòng đó.
 *
 * @param {Record<string, unknown>|null|undefined} context
 * @param {{ id?: string, lat?: number|null, lon?: number|null, gps_tolerance_meters?: number|null }|null|undefined} activeBranch
 */
export function effectiveCheckInContext(context, activeBranch) {
  if (!context || !activeBranch) return context;
  const profile = profileCheckInBranch(context);
  if (profile && String(activeBranch.id) === String(profile.id)) return context;
  if (typeof activeBranch.lat !== 'number' || typeof activeBranch.lon !== 'number') return context;
  return contextWithBranchCenter(context, activeBranch, { enforceGps: true });
}

/**
 * @param {Array<{ lat: number, lon: number }>} branches
 * @param {{ lat: number, lon: number }} coords
 */
export function nearestCheckInBranch(branches, coords) {
  if (!branches?.length || !coords) return branches?.[0] ?? null;
  let best = branches[0];
  let bestDist = distanceMeters(coords.lat, coords.lon, best.lat, best.lon);
  for (let i = 1; i < branches.length; i += 1) {
    const candidate = branches[i];
    const dist = distanceMeters(coords.lat, coords.lon, candidate.lat, candidate.lon);
    if (dist < bestDist) {
      best = candidate;
      bestDist = dist;
    }
  }
  return best;
}

export function googleMapsEmbedUrl(latitude, longitude, zoom = 17) {
  const params = new URLSearchParams({
    q: `${latitude},${longitude}`,
    z: String(zoom),
    hl: 'vi',
    output: 'embed',
  });
  return `https://www.google.com/maps?${params.toString()}`;
}

export function googleMapsPointUrl(latitude, longitude) {
  const query = `${latitude},${longitude}`;
  return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
}

/** @param {{ lat: number, lon: number }} branch */
export function formatBranchCoordinates(branch) {
  if (!branch || typeof branch.lat !== 'number' || typeof branch.lon !== 'number') return '—';
  const lat = branch.lat.toFixed(6);
  const lon = branch.lon.toFixed(6);
  return `${lat}, ${lon}`;
}

/** Địa chỉ cơ sở từ HRM — hiển thị thay cho lat/lon trên màn chấm công. */
export function formatBranchAddress(branch) {
  if (!branch) return '';
  const text = typeof branch.address === 'string' ? branch.address.trim() : '';
  return text;
}

/**
 * @param {Record<string, unknown>|null|undefined} context
 * @param {{ lat: number, lon: number }} branch
 */
export function contextWithBranchCenter(context, branch, options = {}) {
  if (!context || !branch) return context;
  const tolerance = branch.gps_tolerance_meters ?? context.gps_tolerance_meters ?? 100;
  return {
    ...context,
    latitude: branch.lat,
    longitude: branch.lon,
    gps_tolerance_meters: tolerance,
    gps_enforced: options.enforceGps ? true : Boolean(context.gps_enforced),
  };
}

/** Khoảng cách haversine (mét) — khớp EmployeeAttendanceGeo trên server. */
export function distanceMeters(lat1, lon1, lat2, lon2) {
  const earthRadius = 6_371_000;
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLon = ((lon2 - lon1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) ** 2
    + Math.cos((lat1 * Math.PI) / 180)
      * Math.cos((lat2 * Math.PI) / 180)
      * Math.sin(dLon / 2) ** 2;

  return 2 * earthRadius * Math.asin(Math.min(1, Math.sqrt(a)));
}

/**
 * @param {Record<string, unknown>|null|undefined} context
 * @param {{ lat: number, lon: number, accuracy?: number }|null|undefined} coords
 */
export function evaluateCheckInLocation(context, coords) {
  if (!context?.gps_enforced) {
    return { state: 'not_required', distanceMeters: null, toleranceMeters: null };
  }

  const centerLat = context.latitude;
  const centerLon = context.longitude;
  const tolerance = context.gps_tolerance_meters ?? 100;

  if (typeof centerLat !== 'number' || typeof centerLon !== 'number') {
    return { state: 'not_required', distanceMeters: null, toleranceMeters: null };
  }

  if (!coords || typeof coords.lat !== 'number' || typeof coords.lon !== 'number') {
    return { state: 'unknown', distanceMeters: null, toleranceMeters: tolerance };
  }

  const distance = distanceMeters(coords.lat, coords.lon, centerLat, centerLon);
  const buffer = coords.accuracy > 0 ? Math.min(50, coords.accuracy) : 0;
  const within = distance <= tolerance + buffer;

  return {
    state: within ? 'inside' : 'outside',
    distanceMeters: Math.round(distance),
    toleranceMeters: tolerance,
  };
}
