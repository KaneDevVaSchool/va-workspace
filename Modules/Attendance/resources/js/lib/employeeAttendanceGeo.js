function readLatLon(entry) {
  if (!entry || typeof entry !== 'object') return { lat: null, lon: null };
  const lat = entry.latitude ?? entry.lat;
  const lon = entry.longitude ?? entry.lon ?? entry.lng;
  return {
    lat: typeof lat === 'number' ? lat : null,
    lon: typeof lon === 'number' ? lon : null,
  };
}

/**
 * @param {Record<string, unknown>|null|undefined} raw
 * @param {string|number|null|undefined} fallbackId
 */
export function normalizeCheckInBranch(raw, fallbackId = null) {
  if (!raw || typeof raw !== 'object') return null;
  const { lat, lon } = readLatLon(raw);
  if (lat == null || lon == null) return null;
  const id = raw.uuid ?? raw.id ?? fallbackId;
  const tolerance = raw.gps_tolerance_meters ?? raw.gpsToleranceMeters;
  const fullName = [raw.name, raw.label].find((value) => typeof value === 'string' && value.trim());
  const shortName = [raw.short_name, raw.shortName].find((value) => typeof value === 'string' && value.trim());
  const title = (fullName && fullName.trim()) || (shortName && shortName.trim()) || 'Cơ sở chấm công';
  const code = shortName && shortName.trim() !== title ? shortName.trim() : null;

  return {
    id: id != null ? String(id) : `${lat},${lon}`,
    name: title,
    code,
    lat,
    lon,
    gps_tolerance_meters: typeof tolerance === 'number' ? tolerance : null,
  };
}

/**
 * @param {Record<string, unknown>|null|undefined} context
 * @returns {Array<{ id: string, name: string, lat: number, lon: number }>}
 */
export function branchesFromCheckInContext(context) {
  if (!context) return [];

  if (Array.isArray(context.branches) && context.branches.length > 0) {
    const rows = context.branches
      .map((item, index) => normalizeCheckInBranch(item, item?.uuid ?? index))
      .filter(Boolean);
    if (rows.length) return rows;
  }

  const legacyLists = [
    context.check_in_branches,
    context.branch_options,
    context.available_branches,
    context.nearby_branches,
  ];
  for (const list of legacyLists) {
    if (!Array.isArray(list) || list.length === 0) continue;
    const rows = list
      .map((item, index) => normalizeCheckInBranch(item, item?.uuid ?? index))
      .filter(Boolean);
    if (rows.length) return rows;
  }

  const { lat, lon } = readLatLon(context);
  if (lat == null || lon == null) return [];

  const branchMeta = context.branch && typeof context.branch === 'object' ? context.branch : {};
  const merged = normalizeCheckInBranch(
    {
      ...branchMeta,
      name: branchMeta.name ?? context.workplace_label,
      latitude: lat,
      longitude: lon,
    },
    'default',
  );

  return merged ? [merged] : [];
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

/** @param {{ lat: number, lon: number }} branch */
export function formatBranchCoordinates(branch) {
  if (!branch) return '—';
  const lat = branch.lat.toFixed(6);
  const lon = branch.lon.toFixed(6);
  return `${lat}, ${lon}`;
}

/**
 * @param {Record<string, unknown>|null|undefined} context
 * @param {{ lat: number, lon: number }} branch
 */
export function contextWithBranchCenter(context, branch) {
  if (!context || !branch) return context;
  const tolerance = branch.gps_tolerance_meters ?? context.gps_tolerance_meters ?? 100;
  return {
    ...context,
    latitude: branch.lat,
    longitude: branch.lon,
    gps_tolerance_meters: tolerance,
    gps_enforced: context.gps_enforced ?? true,
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
