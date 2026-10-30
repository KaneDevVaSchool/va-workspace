<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AppIcon from '@/components/AppIcon.vue';
import AttendanceGlyph from './AttendanceGlyph.vue';
import { formatBranchAddress, googleMapsEmbedUrl, googleMapsPointUrl } from '../lib/employeeAttendanceGeo';

const props = defineProps({
  open: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  direction: { type: String, default: 'in' },
  timeLabel: { type: String, default: '' },
  dateLabel: { type: String, default: '' },
  methodLabel: { type: String, default: 'Chấm bằng nút' },
  branchName: { type: String, default: '' },
  branchSchoolName: { type: String, default: '' },
  branchCode: { type: String, default: '' },
  branchAddress: { type: String, default: '' },
  deviceCoords: { type: String, default: '' },
  accuracyMeters: { type: Number, default: null },
  locationState: { type: String, default: 'unknown' },
  distanceMeters: { type: Number, default: null },
  toleranceMeters: { type: Number, default: null },
  gpsEnforced: { type: Boolean, default: false },
  mapLat: { type: Number, default: null },
  mapLon: { type: Number, default: null },
  geoPending: { type: Boolean, default: false },
  branchOptions: { type: Array, default: () => [] },
  activeBranchId: { type: String, default: '' },
  confirmBlocked: { type: Boolean, default: false },
  confirmHint: { type: String, default: '' },
});

const emit = defineEmits(['update:open', 'confirm', 'select-branch']);

const branchPickerOpen = ref(false);

const canPickBranch = computed(() => props.branchOptions.length > 1);

const branchKicker = computed(() => (props.branchSchoolName ? 'Tên trường' : 'Chi nhánh'));

const leaving = computed(() => props.direction === 'out');

const title = computed(() => (
  leaving.value ? 'Xác nhận chấm ra buổi chiều' : 'Xác nhận chấm vào buổi sáng'
));

const sessionBadge = computed(() => (
  leaving.value ? 'Ra buổi chiều' : 'Vào buổi sáng'
));

const mapEmbedSrc = computed(() => {
  if (typeof props.mapLat !== 'number' || typeof props.mapLon !== 'number') return '';
  return googleMapsEmbedUrl(props.mapLat, props.mapLon, 18);
});

const mapsOpenUrl = computed(() => {
  if (typeof props.mapLat !== 'number' || typeof props.mapLon !== 'number') return '';
  return googleMapsPointUrl(props.mapLat, props.mapLon);
});

const locationLine = computed(() => {
  if (props.geoPending) return 'Đang cập nhật vị trí thiết bị…';
  if (!props.deviceCoords) return 'Chưa có tọa độ thiết bị.';
  const accuracy = props.accuracyMeters > 0 ? ` · lệch khoảng ${Math.round(props.accuracyMeters)} m` : '';
  return `Vị trí thiết bị: ${props.deviceCoords}${accuracy}`;
});

const zoneLine = computed(() => {
  if (!props.gpsEnforced) {
    return props.deviceCoords ? 'GPS không bắt buộc — vẫn ghi nhận vị trí nếu có.' : 'GPS không bắt buộc cho cơ sở này.';
  }
  if (props.locationState === 'inside') {
    const dist = props.distanceMeters;
    const tol = props.toleranceMeters;
    if (dist != null && tol != null) {
      return `Trong vùng chấm công · cách điểm ${dist} m / dung sai ${tol} m`;
    }
    return 'Trong vùng chấm công.';
  }
  if (props.locationState === 'outside') {
    const dist = props.distanceMeters;
    const tol = props.toleranceMeters;
    if (dist != null && tol != null) {
      return `Ngoài vùng · cách điểm ${dist} m (dung sai ${tol} m)`;
    }
    return 'Ngoài vùng chấm công.';
  }
  return 'Chưa đối chiếu được với vùng cơ sở.';
});

const zoneTone = computed(() => {
  if (!props.gpsEnforced) return 'info';
  if (props.locationState === 'inside') return 'ok';
  if (props.locationState === 'outside') return 'warn';
  return 'info';
});

function close() {
  if (props.loading) return;
  emit('update:open', false);
}

function confirm() {
  if (props.loading || props.confirmBlocked) return;
  emit('confirm');
}

function toggleBranchPicker() {
  if (!canPickBranch.value) return;
  branchPickerOpen.value = !branchPickerOpen.value;
}

function pickBranch(branch) {
  emit('select-branch', branch);
  branchPickerOpen.value = false;
}

function onKeydown(e) {
  if (e.key === 'Escape') close();
}

watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      document.addEventListener('keydown', onKeydown);
    } else {
      document.removeEventListener('keydown', onKeydown);
      branchPickerOpen.value = false;
    }
  },
);

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="att-punch-confirm-fade">
      <div v-if="open" class="att-punch-confirm" role="presentation" @mousedown.self="close">
        <div
          class="att-punch-confirm__panel"
          role="alertdialog"
          aria-modal="true"
          :aria-label="title"
        >
          <header class="att-punch-confirm__head">
            <div class="att-punch-confirm__head-text">
              <span
                class="att-punch-confirm__session"
                :class="leaving ? 'att-punch-confirm__session--indigo' : 'att-punch-confirm__session--amber'"
              >
                {{ sessionBadge }}
              </span>
              <h2 class="att-punch-confirm__title">{{ title }}</h2>
              <p class="att-punch-confirm__lead">
                Ghi nhận lúc <strong>{{ timeLabel }}</strong>
                <span v-if="dateLabel"> · {{ dateLabel }}</span>
              </p>
            </div>
            <button
              type="button"
              class="att-punch-confirm__close"
              aria-label="Đóng"
              :disabled="loading"
              @click="close"
            >
              <AppIcon name="close" :size="20" />
            </button>
          </header>

          <div class="att-punch-confirm__body hide-scrollbar">
            <section class="att-punch-confirm__map-wrap" aria-label="Bản đồ vị trí hiện tại">
              <iframe
                v-if="mapEmbedSrc"
                :key="mapEmbedSrc"
                class="att-punch-confirm__map"
                :src="mapEmbedSrc"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                title="Vị trí thiết bị trên Google Maps"
                allowfullscreen
              />
              <div v-else class="att-punch-confirm__map-placeholder">
                <AttendanceGlyph name="pin" :size="32" />
                <p v-if="geoPending">Đang lấy vị trí để hiển thị bản đồ…</p>
                <p v-else>Bật định vị để xem bản đồ vị trí của bạn.</p>
              </div>
              <a
                v-if="mapsOpenUrl"
                class="att-punch-confirm__maps-link"
                :href="mapsOpenUrl"
                target="_blank"
                rel="noopener noreferrer"
              >
                Mở Google Maps
              </a>
            </section>

            <div v-if="canPickBranch" class="att-punch-confirm__branch-row">
              <div class="att-punch-confirm__branch-summary">
                <span class="att-punch-confirm__branch-kicker">{{ branchKicker }}</span>
                <strong>{{ branchName || '—' }}</strong>
                <small v-if="branchCode">{{ branchCode }}</small>
              </div>
              <button
                type="button"
                class="att-punch-confirm__change"
                :aria-expanded="branchPickerOpen"
                @click="toggleBranchPicker"
              >
                Đổi
              </button>
            </div>

            <ul
              v-if="branchPickerOpen && canPickBranch"
              class="att-punch-confirm__branch-list"
              role="listbox"
              aria-label="Chọn chi nhánh"
            >
              <li v-for="branch in branchOptions" :key="branch.id" role="presentation">
                <button
                  type="button"
                  role="option"
                  class="att-punch-confirm__branch-option"
                  :aria-selected="branch.id === activeBranchId"
                  @click="pickBranch(branch)"
                >
                  <span>{{ branch.name }}</span>
                  <small v-if="branch.code">{{ branch.code }}</small>
                  <small v-if="formatBranchAddress(branch)" class="att-punch-confirm__branch-address">{{ formatBranchAddress(branch) }}</small>
                </button>
              </li>
            </ul>

            <dl class="att-punch-confirm__facts">
              <div v-if="!canPickBranch" class="att-punch-confirm__fact">
                <dt>{{ branchKicker }}</dt>
                <dd>
                  <strong>{{ branchName || '—' }}</strong>
                  <small v-if="branchCode">{{ branchCode }}</small>
                </dd>
              </div>
              <div class="att-punch-confirm__fact">
                <dt>Địa chỉ</dt>
                <dd>{{ branchAddress || '—' }}</dd>
              </div>
              <div class="att-punch-confirm__fact">
                <dt>Hình thức</dt>
                <dd>{{ methodLabel }}</dd>
              </div>
              <div class="att-punch-confirm__fact">
                <dt>Giờ ghi nhận</dt>
                <dd>{{ timeLabel || '—' }}</dd>
              </div>
            </dl>

            <p class="att-punch-confirm__device">{{ locationLine }}</p>

            <p
              class="att-punch-confirm__zone"
              :class="`att-punch-confirm__zone--${zoneTone}`"
            >
              <AppIcon
                :name="zoneTone === 'ok' ? 'check' : (zoneTone === 'warn' ? 'alertTriangle' : 'info')"
                :size="16"
              />
              <span>{{ zoneLine }}</span>
            </p>

            <p v-if="confirmBlocked && confirmHint" class="att-punch-confirm__blocked">
              <AppIcon name="alertTriangle" :size="16" />
              <span>{{ confirmHint }}</span>
            </p>
          </div>

          <footer class="att-punch-confirm__actions">
            <button
              type="button"
              class="att-punch-confirm__btn att-punch-confirm__btn--ghost"
              :disabled="loading"
              @click="close"
            >
              Huỷ
            </button>
            <button
              type="button"
              class="att-punch-confirm__btn att-punch-confirm__btn--primary"
              :disabled="loading || confirmBlocked"
              @click="confirm"
            >
              {{ loading ? 'Đang gửi…' : 'Xác nhận chấm công' }}
            </button>
          </footer>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.att-punch-confirm {
  position: fixed;
  inset: 0;
  z-index: 420;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0.75rem;
  background: color-mix(in srgb, #000000 48%, transparent);
}

.att-punch-confirm__panel {
  display: flex;
  flex-direction: column;
  width: calc(100vw - 1.25rem);
  max-width: 30rem;
  max-height: calc(100vh - 1.25rem);
  overflow: hidden;
  border-radius: 22px;
  background: var(--color-surface);
  box-shadow: 0 24px 48px color-mix(in srgb, var(--color-tertiary-900) 22%, transparent);
}

.att-punch-confirm__head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  flex-shrink: 0;
  padding: var(--space-4) var(--space-4) var(--space-3);
  border-bottom: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
}

.att-punch-confirm__head-text {
  flex: 1;
  min-width: 0;
}

.att-punch-confirm__session {
  display: inline-flex;
  margin-bottom: 6px;
  padding: 3px 10px;
  border-radius: var(--radius-full);
  font-size: 0.68rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.att-punch-confirm__session--amber {
  background: color-mix(in srgb, var(--color-gold-100) 88%, white);
  color: var(--color-gold-800);
}

.att-punch-confirm__session--indigo {
  background: color-mix(in srgb, var(--color-secondary-100) 90%, white);
  color: var(--color-secondary-800);
}

.att-punch-confirm__title {
  margin: 0;
  font-size: 1.12rem;
  font-weight: 800;
  letter-spacing: -0.02em;
  color: var(--color-tertiary-900);
  line-height: 1.25;
}

.att-punch-confirm__lead {
  margin: 6px 0 0;
  font-size: 0.8125rem;
  color: var(--color-text-muted);
  line-height: 1.4;
}

.att-punch-confirm__lead strong {
  color: var(--color-text);
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

.att-punch-confirm__close {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 2.25rem;
  height: 2.25rem;
  border: 0;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-tertiary-50) 80%, var(--color-surface));
  color: var(--color-text-muted);
  cursor: pointer;
}

.att-punch-confirm__body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: var(--space-3) var(--space-4);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.att-punch-confirm__branch-row {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  padding: var(--space-3);
  border-radius: 16px;
  background: color-mix(in srgb, var(--color-tertiary-50) 50%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-border) 65%, transparent);
}

.att-punch-confirm__branch-summary {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.att-punch-confirm__branch-kicker {
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.att-punch-confirm__branch-summary strong {
  font-size: 0.9375rem;
  font-weight: 800;
  line-height: 1.25;
}

.att-punch-confirm__branch-summary small {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--color-tertiary-800);
}

.att-punch-confirm__change {
  flex-shrink: 0;
  align-self: center;
  border: 1px solid color-mix(in srgb, var(--color-tertiary-400) 45%, var(--color-border));
  border-radius: var(--radius-full);
  padding: 6px 14px;
  background: var(--color-surface);
  color: var(--color-tertiary-800);
  font: inherit;
  font-size: 0.75rem;
  font-weight: 800;
  cursor: pointer;
}

.att-punch-confirm__branch-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.att-punch-confirm__branch-option {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  width: 100%;
  padding: var(--space-2) var(--space-3);
  border: 1px solid color-mix(in srgb, var(--color-border) 80%, transparent);
  border-radius: 12px;
  background: var(--color-surface);
  color: var(--color-text);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.att-punch-confirm__branch-option[aria-selected='true'] {
  border-color: color-mix(in srgb, var(--color-tertiary-400) 55%, var(--color-border));
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-tertiary-200) 75%, transparent);
}

.att-punch-confirm__branch-address {
  color: var(--color-text-muted);
  font-size: 0.72rem;
  line-height: 1.35;
}

.att-punch-confirm__blocked {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 0;
  padding: var(--space-3);
  border-radius: 14px;
  background: color-mix(in srgb, var(--color-gold-200) 45%, white);
  color: var(--color-umber-900);
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.4;
}

.att-punch-confirm__map-wrap {
  position: relative;
  flex-shrink: 0;
  min-height: min(58vh, 20rem);
  border-radius: 18px;
  overflow: hidden;
  background: var(--color-tertiary-50);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-border) 70%, transparent);
}

.att-punch-confirm__map {
  display: block;
  width: 100%;
  height: min(58vh, 20rem);
  border: 0;
}

.att-punch-confirm__map-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  min-height: min(58vh, 20rem);
  padding: var(--space-4);
  text-align: center;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
  font-weight: 600;
}

.att-punch-confirm__maps-link {
  position: absolute;
  right: 10px;
  bottom: 10px;
  padding: 6px 12px;
  border-radius: var(--radius-full);
  background: color-mix(in srgb, var(--color-surface) 92%, white);
  box-shadow: 0 4px 14px color-mix(in srgb, var(--color-tertiary-900) 18%, transparent);
  color: var(--color-tertiary-800);
  font-size: 0.72rem;
  font-weight: 800;
  text-decoration: none;
}

.att-punch-confirm__facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-2) var(--space-3);
  margin: 0;
}

.att-punch-confirm__fact {
  margin: 0;
  padding: var(--space-2) var(--space-3);
  border-radius: 14px;
  background: color-mix(in srgb, var(--color-tertiary-50) 55%, var(--color-surface));
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-border) 60%, transparent);
}

.att-punch-confirm__fact dt {
  margin: 0 0 4px;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: var(--color-text-muted);
}

.att-punch-confirm__fact dt::after {
  content: ':';
}

.att-punch-confirm__fact dd {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-text);
  line-height: 1.35;
}

.att-punch-confirm__fact dd strong {
  display: block;
  font-weight: 800;
  font-size: 0.875rem;
}

.att-punch-confirm__fact dd small {
  display: block;
  margin-top: 2px;
  color: var(--color-tertiary-800);
  font-size: 0.72rem;
  font-weight: 700;
}

.att-punch-confirm__device {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--color-text-muted);
  font-variant-numeric: tabular-nums;
  line-height: 1.4;
}

.att-punch-confirm__zone {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin: 0;
  padding: var(--space-3);
  border-radius: 14px;
  font-size: 0.8125rem;
  font-weight: 600;
  line-height: 1.4;
}

.att-punch-confirm__zone--ok {
  background: var(--color-success-tint-bg);
  color: var(--color-success-tint-fg);
}

.att-punch-confirm__zone--warn {
  background: color-mix(in srgb, var(--color-gold-200) 55%, white);
  color: var(--color-umber-900);
}

.att-punch-confirm__zone--info {
  background: var(--color-info-tint-bg);
  color: var(--color-info-tint-fg);
}

.att-punch-confirm__actions {
  flex-shrink: 0;
  display: grid;
  grid-template-columns: 1fr 1.2fr;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4) var(--space-4);
  border-top: 1px solid color-mix(in srgb, var(--color-border) 65%, transparent);
}

.att-punch-confirm__btn {
  min-height: 3rem;
  border: 0;
  border-radius: var(--radius-full);
  font: inherit;
  font-size: 0.875rem;
  font-weight: 800;
  cursor: pointer;
}

.att-punch-confirm__btn--ghost {
  background: color-mix(in srgb, var(--color-tertiary-50) 70%, var(--color-surface));
  color: var(--color-text);
  box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--color-border) 80%, transparent);
}

.att-punch-confirm__btn--primary {
  background: linear-gradient(180deg, var(--color-tertiary-500), var(--color-tertiary-700));
  color: var(--color-on-tertiary);
  box-shadow: 0 10px 20px color-mix(in srgb, var(--color-tertiary-700) 28%, transparent);
}

.att-punch-confirm__btn:disabled {
  opacity: 0.55;
  cursor: default;
}

.att-punch-confirm-fade-enter-active,
.att-punch-confirm-fade-leave-active {
  transition: opacity 0.18s ease;
}

.att-punch-confirm-fade-enter-active .att-punch-confirm__panel,
.att-punch-confirm-fade-leave-active .att-punch-confirm__panel {
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.att-punch-confirm-fade-enter-from,
.att-punch-confirm-fade-leave-to {
  opacity: 0;
}

.att-punch-confirm-fade-enter-from .att-punch-confirm__panel,
.att-punch-confirm-fade-leave-to .att-punch-confirm__panel {
  transform: translateY(12px) scale(0.98);
  opacity: 0;
}

@media (min-width: 480px) {
  .att-punch-confirm__map,
  .att-punch-confirm__map-wrap,
  .att-punch-confirm__map-placeholder {
    min-height: 20rem;
    height: 20rem;
  }
}
</style>
