import assert from 'node:assert/strict';
import test from 'node:test';
import {
  branchUuidForPunch,
  effectiveCheckInContext,
  formatBranchAddress,
  normalizeCheckInBranch,
  resolveActiveCheckInBranch,
} from '../../Modules/Attendance/resources/js/lib/employeeAttendanceGeo.js';

const profileId = '22222222-2222-4222-8222-222222222222';
const otherId = '11111111-1111-4111-8111-111111111111';

function context() {
  return {
    workplace_label: 'Cơ sở hồ sơ',
    gps_enforced: true,
    wifi_enforced: true,
    latitude: 10.762622,
    longitude: 106.660172,
    gps_tolerance_meters: 80,
    branch: {
      uuid: profileId,
      name: 'Cơ sở hồ sơ',
      school_name: 'Trường hồ sơ',
      code: 'HS',
    },
    branches: [
      {
        uuid: profileId,
        name: 'Cơ sở hồ sơ',
        latitude: 10.762622,
        longitude: 106.660172,
        gps_tolerance_meters: 80,
      },
      {
        uuid: otherId,
        name: 'Cơ sở gần máy',
        school_name: 'Trường gần',
        latitude: 10.8,
        longitude: 106.8,
        gps_tolerance_meters: 50,
      },
    ],
  };
}

test('hồ sơ khớp thì không đổi sang chi nhánh GPS gần hơn', () => {
  const resolved = resolveActiveCheckInBranch(context(), {
    coords: { lat: 10.8, lon: 106.8 },
  });

  assert.equal(resolved.explicit, false);
  assert.equal(resolved.branch.id, profileId);
  assert.equal(resolved.branch.schoolName, 'Trường hồ sơ');
  assert.equal(branchUuidForPunch(context(), resolved.branch), null);

  const zone = effectiveCheckInContext(context(), resolved.branch);
  assert.equal(zone.latitude, 10.762622);
  assert.equal(zone.gps_enforced, true);
  assert.equal(zone.wifi_enforced, true);
});

test('chọn tường minh một chi nhánh khác thì đối chiếu GPS của chi nhánh đó', () => {
  const resolved = resolveActiveCheckInBranch(context(), {
    selectedId: otherId,
    coords: { lat: 10.762622, lon: 106.660172 },
  });

  assert.equal(resolved.explicit, true);
  assert.equal(resolved.branch.name, 'Trường gần');
  assert.equal(resolved.branch.code, 'Cơ sở gần máy');
  assert.equal(branchUuidForPunch(context(), resolved.branch), otherId);

  const zone = effectiveCheckInContext(context(), resolved.branch);
  assert.equal(zone.latitude, 10.8);
  assert.equal(zone.longitude, 106.8);
  assert.equal(zone.gps_tolerance_meters, 50);
  assert.equal(zone.gps_enforced, true);
});

test('hồ sơ không khớp thì gợi ý chi nhánh GPS gần nhất và gửi uuid', () => {
  const raw = context();
  raw.branch = null;
  raw.gps_enforced = false;
  raw.latitude = null;
  raw.longitude = null;
  raw.gps_tolerance_meters = null;

  const resolved = resolveActiveCheckInBranch(raw, {
    coords: { lat: 10.8, lon: 106.8 },
  });

  assert.equal(resolved.branch.id, otherId);
  assert.equal(branchUuidForPunch(raw, resolved.branch), otherId);

  const zone = effectiveCheckInContext(raw, resolved.branch);
  assert.equal(zone.gps_enforced, true);
  assert.equal(zone.gps_tolerance_meters, 50);
});

test('formatBranchAddress lấy địa chỉ từ payload HRM', () => {
  const row = normalizeCheckInBranch(
    {
      uuid: profileId,
      name: 'Cơ sở',
      latitude: 10,
      longitude: 106,
      address_detail: '11 D3, Phường 25, Quận Bình Thạnh, TP.HCM',
    },
    profileId,
  );
  assert.equal(formatBranchAddress(row), '11 D3, Phường 25, Quận Bình Thạnh, TP.HCM');
  assert.equal(formatBranchAddress({ address: '  ' }), '');
});
