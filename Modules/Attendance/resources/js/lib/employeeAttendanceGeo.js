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
