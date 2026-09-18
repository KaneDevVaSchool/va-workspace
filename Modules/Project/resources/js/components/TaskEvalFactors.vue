<script setup>
import AppIcon from '@/components/AppIcon.vue';
import GuideHelp from '@/components/GuideHelp.vue';
import { SCORE_FACTOR_GUIDES, previewProgressLevel } from '@/constants/scoreFactorGuides.js';
import { computed } from 'vue';

const props = defineProps({
  task: { type: Object, required: true },
  difficultyLabel: { type: String, default: '' },
  difficultyLocked: { type: Boolean, default: false },
  progressLevels: { type: Array, default: () => [] },
  canApprove: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false },
  weighted: { type: Boolean, default: false },
});

const emit = defineEmits(['evaluate', 'edit-difficulty']);

const qualityLabel = computed(() => props.task?.task_score?.rating_result || '');
const qualityPassed = computed(() => props.task?.task_score?.is_passed);
const progressPreview = computed(() => previewProgressLevel(props.task, props.progressLevels));
const progressPercent = computed(() => {
  if (props.task?.progress_percent == null) return null;
  const n = Number(props.task.progress_percent);
  return Number.isNaN(n) ? null : Math.max(0, Math.min(100, Math.round(n)));
});

function qualityTone() {
  if (qualityPassed.value === false) return 'danger';
  if (qualityPassed.value === true) return 'success';
  return qualityLabel.value ? 'gold' : 'neutral';
}
</script>

<template>
  <section class="eval-factors" :class="{ 'eval-factors--off': !weighted }">
    <header class="eval-factors__head">
      <h3 class="eval-factors__title">Ba thước chấm điểm</h3>
      <GuideHelp :guide="SCORE_FACTOR_GUIDES.kitWeight" tone="secondary" />
    </header>
    <p v-if="!weighted" class="eval-factors__note">
      Phòng ban đang đếm số việc — ba thước này chưa nhân vào điểm. Vẫn có thể ghi để làm việc hàng ngày.
    </p>
    <div class="eval-factors__grid">
      <article class="eval-factors__card eval-factors__card--gold">
        <div class="eval-factors__card-head">
          <span class="eval-factors__icon" aria-hidden="true">
            <AppIcon name="layers" :size="15" :stroke-width="1.75" />
          </span>
          <h4 class="eval-factors__name">Độ khó</h4>
          <GuideHelp :guide="SCORE_FACTOR_GUIDES.difficulty" tone="gold" />
        </div>
        <p class="eval-factors__value">{{ difficultyLabel || 'Chưa chọn' }}</p>
        <p class="eval-factors__hint">
          {{ difficultyLocked ? 'Đã khóa sau khi giao việc.' : 'Chọn lúc giao việc.' }}
        </p>
        <button
          v-if="canEdit && !difficultyLocked"
          type="button"
          class="eval-factors__action"
          @click="emit('edit-difficulty')"
        >
          Đổi mức
        </button>
      </article>

      <article class="eval-factors__card eval-factors__card--teal">
        <div class="eval-factors__card-head">
          <span class="eval-factors__icon" aria-hidden="true">
            <AppIcon name="trendingUp" :size="15" :stroke-width="1.75" />
          </span>
          <h4 class="eval-factors__name">Tiến độ</h4>
          <GuideHelp :guide="SCORE_FACTOR_GUIDES.progress" tone="secondary" />
        </div>
        <p class="eval-factors__value">
          <template v-if="progressPercent != null">{{ progressPercent }}% làm việc</template>
          <template v-else>Chưa nhập %</template>
        </p>
        <p v-if="progressPreview" class="eval-factors__chip" :class="`eval-factors__chip--${progressPreview.tone}`">
          {{ progressPreview.label }}
        </p>
        <p class="eval-factors__hint">{{ progressPreview?.hint || 'Máy xếp sớm / đúng hạn / trễ từ ngày hạn và ngày xong thật.' }}</p>
      </article>

      <article class="eval-factors__card eval-factors__card--done">
        <div class="eval-factors__card-head">
          <span class="eval-factors__icon" aria-hidden="true">
            <AppIcon name="starFilled" :size="15" :stroke-width="1.75" />
          </span>
          <h4 class="eval-factors__name">Chất lượng</h4>
          <GuideHelp :guide="SCORE_FACTOR_GUIDES.quality" tone="gold" />
        </div>
        <p class="eval-factors__value">{{ qualityLabel || 'Chưa chấm' }}</p>
        <p class="eval-factors__chip" :class="`eval-factors__chip--${qualityTone()}`">
          {{ qualityPassed === false ? 'Không đạt' : qualityPassed === true ? 'Đạt' : 'Chưa chọn Đạt / Không đạt' }}
        </p>
        <button
          v-if="canApprove"
          type="button"
          class="eval-factors__action"
          @click="emit('evaluate')"
        >
          {{ qualityLabel ? 'Sửa đánh giá' : 'Chấm chất lượng' }}
        </button>
      </article>
    </div>
  </section>
</template>

<style scoped>
.eval-factors {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}

.eval-factors__head {
  display: flex;
  align-items: center;
  gap: 0.15rem;
}

.eval-factors__title {
  margin: 0;
  font-size: 0.9375rem;
  font-weight: 650;
}

.eval-factors__note {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.8125rem;
}

.eval-factors__grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
}

.eval-factors__card {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 0;
  padding: var(--space-4);
  padding-left: calc(var(--space-2) + 3px + var(--space-4));
  border-radius: var(--radius-md);
  background: var(--color-surface);
  box-shadow: var(--shadow-sm);
}

.eval-factors__card::before {
  content: '';
  position: absolute;
  top: var(--space-2);
  bottom: var(--space-2);
  left: var(--space-2);
  width: 3px;
  border-radius: 0;
  background: var(--color-border);
}

.eval-factors__card--gold::before { background: var(--color-gold); }
.eval-factors__card--teal::before { background: var(--color-secondary); }
.eval-factors__card--done::before { background: var(--color-success); }

.eval-factors__card-head {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}

.eval-factors__icon {
  display: inline-flex;
  color: var(--color-text-muted);
}

.eval-factors__name {
  margin: 0;
  font-size: 0.8125rem;
  font-weight: 650;
}

.eval-factors__value {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 650;
  line-height: 1.3;
}

.eval-factors__hint {
  margin: 0;
  color: var(--color-text-muted);
  font-size: 0.75rem;
  line-height: 1.4;
}

.eval-factors__chip {
  margin: 0;
  align-self: flex-start;
  padding: 0.1rem 0.45rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-style: italic;
}

.eval-factors__chip--success { background: var(--color-success-tint-bg); color: var(--color-success); }
.eval-factors__chip--danger { background: var(--color-danger-tint-bg); color: var(--color-danger); }
.eval-factors__chip--gold { background: var(--color-gold-50); color: var(--color-gold); }
.eval-factors__chip--info { background: var(--color-tertiary-surface); color: var(--color-tertiary); }
.eval-factors__chip--warning { background: var(--color-gold-50); color: var(--color-gold); }
.eval-factors__chip--neutral { background: var(--color-surface-muted); color: var(--color-text-muted); }

.eval-factors__action {
  margin-top: 0.25rem;
  align-self: flex-start;
  padding: 0;
  border: none;
  background: none;
  color: var(--color-primary);
  font-family: inherit;
  font-size: 0.8125rem;
  font-weight: 650;
  cursor: pointer;
}

.eval-factors__action:hover,
.eval-factors__action:focus-visible {
  text-decoration: underline;
  outline: none;
}

@media (max-width: 860px) {
  .eval-factors__grid {
    grid-template-columns: 1fr;
  }
}
</style>
