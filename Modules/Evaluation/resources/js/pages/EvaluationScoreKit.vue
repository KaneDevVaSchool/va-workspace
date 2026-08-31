<script setup>
//
// manager/evaluation-score-kit — engine chấm điểm theo phòng ban.
// Cách 1 (base_adjust): đếm số việc — mọi việc tính giống nhau.
// Cách 2 (weighted_task): hiệu suất việc — chuẩn × độ khó × tiến độ × chất lượng.
//
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from "vue";
import { onBeforeRouteLeave } from "vue-router";
import AppIcon from "@/components/AppIcon.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";
import PageHeader from "@/components/PageHeader.vue";
import { showClientToast } from "@/lib/clientToast";
import { useAuthStore } from "@modules/Identity/resources/js/stores/auth.js";

const CLASSIFICATION_LEVEL_MIN = 2;
const CLASSIFICATION_LEVEL_MAX = 12;

const MODES = [
    {
        id: "base_adjust",
        icon: "hash",
        title: "Đếm số việc",
        lead: "Theo công việc · mỗi việc tính giống nhau",
    },
    {
        id: "weighted_task",
        icon: "layers",
        title: "Hiệu suất việc",
        lead: "Chuẩn theo độ khó · thực theo hạn và chất lượng",
    },
];

const SCALE_TABS = [
    { id: "weight", title: "Độ khó" },
    { id: "progress", title: "Tiến độ" },
    { id: "quality", title: "Chất lượng" },
];

const DEFAULT_BASE_LEVELS = [
    { code: "XS", label: "Xuất sắc", score: 110, sort_order: 0 },
    { code: "T", label: "Tốt", score: 100, sort_order: 1 },
    { code: "K", label: "Khá", score: 90, sort_order: 2 },
    { code: "D", label: "Đạt", score: 80, sort_order: 3 },
    { code: "CD", label: "Chưa đạt", score: 0, sort_order: 4 },
];

const DEFAULT_WEIGHT_LEVELS = [
    { code: "RK", label: "Rất khó", score: 1.5 },
    { code: "KH", label: "Khó", score: 1.2 },
    { code: "TB", label: "Trung bình", score: 1 },
    { code: "DE", label: "Dễ", score: 0.85 },
];

const DEFAULT_PROGRESS_LEVELS = [
    { code: "S20", label: "Sớm ≥20%", score: 1.1 },
    { code: "S5", label: "Sớm dưới 20%", score: 1.05 },
    { code: "DH", label: "Đúng hạn", score: 1 },
    { code: "T2", label: "Trễ 1–2 ngày", score: 0.9 },
    { code: "T5", label: "Trễ 3–5 ngày", score: 0.75 },
    { code: "T6", label: "Trễ hơn 5 ngày", score: 0.5 },
];

const DEFAULT_QUALITY_LEVELS = [
    { code: "XS", label: "Xuất sắc", score: 1 },
    { code: "DAT", label: "Đạt", score: 1 },
    { code: "CS", label: "Cần sửa", score: 0.8 },
    { code: "KD", label: "Không đạt", score: 0.5 },
];

const DEFAULT_PERFORMANCE_LEVELS = [
    { code: "VTK", label: "Vượt kỳ vọng", score: 110 },
    { code: "XS", label: "Xuất sắc", score: 100 },
    { code: "T", label: "Tốt", score: 90 },
    { code: "D", label: "Đạt", score: 80 },
    { code: "CC", label: "Cần cải thiện", score: 70 },
    { code: "KD", label: "Không đạt", score: 0 },
];

const DEFAULT_FORMULA = {
    base: "on",
    done: "add",
    undone: "add",
    weight: "on",
    project: "off",
    progress: "on",
    quality: "on",
    contrib: "off",
    lock_difficulty: "on",
};

const WEIGHT_CASES = [
    {
        id: "on_time",
        task: "Việc khó, đúng hạn, đạt",
        note: "Chuẩn 120 · thực 120 · hiệu suất 100%",
        weightIndex: 1,
        progressIndex: 2,
        qualityIndex: 1,
    },
    {
        id: "late_fix",
        task: "Việc khó, trễ 4 ngày, phải sửa",
        note: "Chuẩn 120 · thực 72 · hiệu suất 60%",
        weightIndex: 1,
        progressIndex: 4,
        qualityIndex: 2,
    },
];

const TERM_OPS = ["add", "sub", "off"];
const CHANGE_FIELD_LABELS = {
    mode: "cách tính điểm",
    base_score: "điểm khởi đầu",
    points_per_completed_task: "điểm việc hoàn thành",
    points_per_incomplete_task: "điểm việc chưa hoàn thành",
    task_base_score: "điểm cơ bản mỗi việc",
    quality_bonus_percent: "bonus chất lượng xuất sắc",
    formula: "công thức",
    classification_criterion_id: "tiêu chí nguồn thang xếp loại",
    difficulty_criterion_id: "tiêu chí nguồn thang độ khó",
    progress_criterion_id: "tiêu chí nguồn thang tiến độ",
    quality_criterion_id: "tiêu chí nguồn thang chất lượng",
    classification_use_default: "mặc định thang xếp loại",
    difficulty_use_default: "mặc định thang độ khó",
    progress_use_default: "mặc định thang tiến độ",
    quality_use_default: "mặc định thang chất lượng",
    base_adjust_levels: "thang xếp loại",
    weighted_task_levels: "thang độ khó",
    progress_levels: "thang tiến độ",
    quality_levels: "thang chất lượng",
    performance_levels: "thang xếp loại",
};

const auth = useAuthStore();

const loading = ref(false);
const hydrating = ref(true);
const reloading = ref(false);
const savingKit = ref(false);
const savedFlash = ref(false);
const dirty = ref(false);
const resetOpen = ref(false);
const changesDialogOpen = ref(false);
const changesDialogKind = ref("save");
const modeConfirmOpen = ref(false);
let pendingLeaveNavigation = null;
const pendingMode = ref(null);
const viewMode = ref("base_adjust");
const savedSnapshot = ref(null);
const allCriteria = ref([]);

const kit = reactive({
    id: null,
    mode: null,
    base_score: 100,
    task_base_score: 100,
    quality_bonus_percent: 5,
    points_per_completed_task: 0,
    points_per_incomplete_task: 0,
    use_project_importance: false,
    classification_criterion_id: null,
    difficulty_criterion_id: null,
    progress_criterion_id: null,
    quality_criterion_id: null,
    classification_use_default: false,
    difficulty_use_default: false,
    progress_use_default: false,
    quality_use_default: false,
    formula: cloneFormula(null),
    base_adjust_levels: cloneClassificationLevels(null),
    weighted_task_levels: cloneLevels(null, DEFAULT_WEIGHT_LEVELS),
    progress_levels: cloneLevels(null, DEFAULT_PROGRESS_LEVELS),
    quality_levels: cloneLevels(null, DEFAULT_QUALITY_LEVELS),
    performance_levels: cloneLevels(null, DEFAULT_PERFORMANCE_LEVELS),
});

const demoCompleted = ref(8);
const demoIncomplete = ref(2);
const demoWeightIndex = ref(1);
const demoProgressIndex = ref(2);
const demoQualityIndex = ref(1);
const demoCaseId = ref("on_time");
const scaleView = ref("weight");

let flashTimer = null;
let editVersion = 0;

const hasDepartment = computed(() => Boolean(auth.user?.department?.id));
const canManage = computed(() => auth.can("evaluation.manage_department"));
const departmentName = computed(
    () => auth.user?.department?.name || "Chưa gắn phòng ban",
);
const currentModeTitle = computed(
    () =>
        MODES.find((mode) => mode.id === viewMode.value)?.title ||
        "cách tính hiện tại",
);
const changedFieldLabels = computed(() => {
    if (!savedSnapshot.value) return [];
    const current = kitPayload();

    return Object.entries(CHANGE_FIELD_LABELS)
        .filter(
            ([field]) =>
                JSON.stringify(current[field]) !==
                JSON.stringify(savedSnapshot.value[field]),
        )
        .map(([, label]) => label);
});
const changeSummaryText = computed(() => {
    if (!changedFieldLabels.value.length) return "các nội dung vừa chỉnh";
    return changedFieldLabels.value.join(", ");
});
const changedFieldCount = computed(() => changedFieldLabels.value.length);
const savedModeTitle = computed(() => {
    const modeId = kit.mode || viewMode.value;
    return (
        MODES.find((mode) => mode.id === modeId)?.title || currentModeTitle.value
    );
});

const classificationLevels = computed(() =>
    [...kit.base_adjust_levels].sort(
        (a, b) => Number(b.score) - Number(a.score),
    ),
);
const scaleCriteria = computed(() =>
    allCriteria.value.filter(
        (criterion) =>
            criterion?.type === "scale" &&
            criterion?.is_active &&
            Array.isArray(criterion?.levels) &&
            criterion.levels.length,
    ),
);
const classificationCriteria = computed(() =>
    scaleCriteria.value.filter((criterion) => criterion.levels.length >= 2),
);
const classificationFromCriterion = computed(() =>
    Boolean(
        kit.classification_criterion_id || kit.classification_use_default,
    ),
);

const doneCount = computed(() => Math.max(0, Number(demoCompleted.value) || 0));
const undoneCount = computed(() =>
    Math.max(0, Number(demoIncomplete.value) || 0),
);
const donePts = computed(() => Number(kit.points_per_completed_task) || 0);
const undonePts = computed(() => Number(kit.points_per_incomplete_task) || 0);

const demoDoneDelta = computed(() =>
    applyOp(0, kit.formula.done, round2(donePts.value * doneCount.value)),
);

const demoUndoneDelta = computed(() =>
    applyOp(0, kit.formula.undone, round2(undonePts.value * undoneCount.value)),
);

const demoTotal = computed(() => {
    let total = kit.formula.base === "on" ? Number(kit.base_score) || 0 : 0;
    total = applyOp(total, kit.formula.done, donePts.value * doneCount.value);
    total = applyOp(
        total,
        kit.formula.undone,
        undonePts.value * undoneCount.value,
    );
    return round2(total);
});

const demoRank = computed(() => rankFor(demoTotal.value));

const demoWeight = computed(
    () =>
        kit.weighted_task_levels[demoWeightIndex.value] ??
        kit.weighted_task_levels[1] ??
        kit.weighted_task_levels[0],
);

const demoProgress = computed(
    () =>
        kit.progress_levels[demoProgressIndex.value] ??
        kit.progress_levels[0],
);

const demoQuality = computed(
    () =>
        kit.quality_levels[demoQualityIndex.value] ??
        kit.quality_levels[1] ??
        kit.quality_levels[0],
);

const demoWeightFactor = computed(() =>
    kit.formula.weight === "on" ? Number(demoWeight.value?.score) || 1 : 1,
);

const demoProgressFactor = computed(() =>
    kit.formula.progress === "on" ? Number(demoProgress.value?.score) || 1 : 1,
);

const demoQualityFactor = computed(() =>
    kit.formula.quality === "on" ? Number(demoQuality.value?.score) || 1 : 1,
);

const demoTaskBase = computed(() => Number(kit.task_base_score) || 0);

const demoStandard = computed(() =>
    round2(demoTaskBase.value * demoWeightFactor.value),
);

const demoActual = computed(() =>
    round2(
        demoStandard.value * demoProgressFactor.value * demoQualityFactor.value,
    ),
);

const demoTaskPercent = computed(() => {
    if (demoStandard.value <= 0) return 0;
    return round2((demoActual.value / demoStandard.value) * 100);
});

const demoQualityBonus = computed(() => {
    if (kit.formula.quality !== "on") return 0;
    const code = String(demoQuality.value?.code ?? "").toUpperCase();
    if (code !== "XS" && demoQualityIndex.value !== 0) return 0;
    return Math.max(0, Number(kit.quality_bonus_percent) || 0);
});

const demoRatedPercent = computed(() =>
    round2(demoTaskPercent.value + demoQualityBonus.value),
);

const activeWeightCase = computed(
    () => WEIGHT_CASES.find((item) => item.id === demoCaseId.value) ?? null,
);

const demoTaskRank = computed(() => rankPercent(demoRatedPercent.value));

const activeScale = computed(() => {
    if (scaleView.value === "progress") {
        return {
            key: "progress",
            title: "Thang tiến độ",
            lead: "Các mức lấy từ tiêu chí tiến độ của phòng ban.",
            levels: kit.progress_levels,
            scoreMin: 0.01,
            scoreMax: 9.99,
            scoreStep: 0.05,
            highlight: demoProgressIndex.value,
            criterionIdKey: "progress_criterion_id",
            useDefaultKey: "progress_use_default",
        };
    }
    if (scaleView.value === "quality") {
        return {
            key: "quality",
            title: "Thang chất lượng",
            lead: "Các mức lấy từ tiêu chí chất lượng của phòng ban.",
            levels: kit.quality_levels,
            scoreMin: 0.01,
            scoreMax: 9.99,
            scoreStep: 0.05,
            highlight: demoQualityIndex.value,
            criterionIdKey: "quality_criterion_id",
            useDefaultKey: "quality_use_default",
        };
    }
    return {
        key: "weight",
        title: "Thang độ khó",
        lead: "Các mức lấy từ tiêu chí độ khó của phòng ban.",
        levels: kit.weighted_task_levels,
        scoreMin: 0.01,
        scoreMax: 9.99,
        scoreStep: 0.05,
        highlight: demoWeightIndex.value,
        criterionIdKey: "difficulty_criterion_id",
        useDefaultKey: "difficulty_use_default",
    };
});
const activeScaleFromCriterion = computed(() =>
    Boolean(
        kit[activeScale.value.criterionIdKey] ||
            kit[activeScale.value.useDefaultKey],
    ),
);

const baseFormulaMethod = computed(() => {
    const parts = [];
    if (kit.formula.base === "on") {
        parts.push("điểm khởi đầu");
    }
    if (kit.formula.done !== "off") {
        appendNamedPart(
            parts,
            kit.formula.done,
            "số việc đã xong × điểm mỗi việc đã xong",
        );
    }
    if (kit.formula.undone !== "off") {
        appendNamedPart(
            parts,
            kit.formula.undone,
            "số việc chưa xong × điểm mỗi việc chưa xong",
        );
    }
    if (!parts.length) {
        return "Chưa chọn hạng mục nào. Bấm dòng chữ trên từng ô bên dưới để đưa vào công thức.";
    }
    return `Tổng điểm = ${parts.join(" ")}`;
});

const baseFormulaApplied = computed(() => {
    const parts = [];
    if (kit.formula.base === "on") {
        parts.push(`${formatPlain(kit.base_score)} (điểm khởi đầu)`);
    }
    if (kit.formula.done !== "off") {
        appendNamedPart(
            parts,
            kit.formula.done,
            `${doneCount.value} việc đã xong × ${formatSigned(donePts.value)} điểm = ${formatSigned(demoDoneDelta.value)}`,
        );
    }
    if (kit.formula.undone !== "off") {
        appendNamedPart(
            parts,
            kit.formula.undone,
            `${undoneCount.value} việc chưa xong × ${formatSigned(undonePts.value)} điểm = ${formatSigned(demoUndoneDelta.value)}`,
        );
    }
    if (!parts.length) {
        return "";
    }
    const rank = demoRank.value;
    const rankText = rank
        ? `xếp loại ${rank.label} (từ ${formatPlain(rank.score)} điểm)`
        : "chưa đạt mức nào trên thang của phòng";
    return `Áp dụng số đang set: ${parts.join(" ")} = ${formatPlain(demoTotal.value)} điểm → ${rankText}`;
});

const weightFormulaMethod = computed(() => {
    const standardBits = ["điểm cơ bản"];
    if (kit.formula.weight === "on") {
        standardBits.push("× độ khó");
    }
    const actualBits = ["điểm chuẩn"];
    if (kit.formula.progress === "on") {
        actualBits.push("× tiến độ");
    }
    if (kit.formula.quality === "on") {
        actualBits.push("× chất lượng");
    }
    return `Điểm chuẩn = ${standardBits.join(" ")}. Điểm thực = ${actualBits.join(" ")}. Hiệu suất = Σ điểm thực / Σ điểm chuẩn × 100%. Độ khó là trọng số khối lượng. Xuất sắc cộng bonus riêng, không nhân vào chuẩn.`;
});

const weightFormulaApplied = computed(() => {
    const stdLabel = demoWeight.value?.label
        ? demoWeight.value.label.toLowerCase()
        : "độ khó";
    const bonusText = demoQualityBonus.value
        ? ` + ${formatPlain(demoQualityBonus.value)}% bonus xuất sắc = ${formatPlain(demoRatedPercent.value)}%`
        : "";
    const rank = demoTaskRank.value;
    const rankText = rank
        ? `xếp loại ${rank.label.toLowerCase()} (từ ${formatPlain(rank.score)}%)`
        : "chưa đạt mức nào trên thang hiệu suất";
    return `Một việc: chuẩn ${formatPlain(demoStandard.value)} (${formatPlain(demoTaskBase.value)} × ${formatPlain(demoWeightFactor.value)} ${stdLabel}) → thực ${formatPlain(demoActual.value)} → ${formatPlain(demoTaskPercent.value)}%${bonusText} → ${rankText}`;
});

watch(
    () => kit.mode,
    (mode) => {
        if (mode) viewMode.value = mode;
    },
);

function cloneFormula(raw) {
    const src = raw && typeof raw === "object" ? raw : {};
    return {
        base: src.base === "off" ? "off" : DEFAULT_FORMULA.base,
        done: TERM_OPS.includes(src.done) ? src.done : DEFAULT_FORMULA.done,
        undone: TERM_OPS.includes(src.undone)
            ? src.undone
            : DEFAULT_FORMULA.undone,
        weight: src.weight === "off" ? "off" : DEFAULT_FORMULA.weight,
        project: src.project === "off" ? "off" : DEFAULT_FORMULA.project,
        progress: src.progress === "off" ? "off" : DEFAULT_FORMULA.progress,
        quality: src.quality === "off" ? "off" : DEFAULT_FORMULA.quality,
        contrib: src.contrib === "on" ? "on" : DEFAULT_FORMULA.contrib,
        lock_difficulty:
            src.lock_difficulty === "off"
                ? "off"
                : DEFAULT_FORMULA.lock_difficulty,
    };
}

function applyOp(total, op, value) {
    if (op === "off") return total;
    if (op === "sub") return round2(total - value);
    return round2(total + value);
}

function appendNamedPart(parts, op, text) {
    const word = op === "sub" ? "trừ" : "cộng";
    const group = `(${text})`;
    if (!parts.length) {
        parts.push(op === "sub" ? `${word} ${group}` : group);
        return;
    }
    parts.push(word, group);
}

function formulaOpLabel(key) {
    const op = kit.formula[key];
    if (op === "add") return "Cộng";
    if (op === "sub") return "Trừ";
    if (op === "off") return "Tắt";
    if (
        key === "weight" ||
        key === "project" ||
        key === "progress" ||
        key === "quality"
    )
        return "Nhân";
    return "Dùng";
}

function cycleTermOp(key) {
    if (!canManage.value) return;
    const current = kit.formula[key];
    const index = TERM_OPS.indexOf(current);
    kit.formula[key] = TERM_OPS[(index + 1) % TERM_OPS.length];
    scheduleSaveKit();
}

function cycleOnOff(key) {
    if (!canManage.value) return;
    kit.formula[key] = kit.formula[key] === "on" ? "off" : "on";
    if (key === "project") {
        kit.use_project_importance = kit.formula.project === "on";
    }
    scheduleSaveKit();
}

function cloneClassificationLevels(rows) {
    const source =
        Array.isArray(rows) && rows.length >= CLASSIFICATION_LEVEL_MIN
            ? rows
            : DEFAULT_BASE_LEVELS;
    return source.slice(0, CLASSIFICATION_LEVEL_MAX).map((row, index) => ({
        code: String(row?.code ?? ""),
        label: String(row?.label ?? `Mức ${index + 1}`),
        score: Number(row?.score ?? 0),
        sort_order: Number.isFinite(Number(row?.sort_order))
            ? Number(row.sort_order)
            : index,
    }));
}

function cloneLevels(rows, fallback) {
    const source = Array.isArray(rows) && rows.length ? rows : fallback;
    return source.map((row, index) => ({
        code: String(row?.code ?? fallback[index]?.code ?? ""),
        label: String(
            row?.label ?? fallback[index]?.label ?? `Mức ${index + 1}`,
        ),
        score: Number(row?.score ?? fallback[index]?.score ?? 1),
    }));
}

function criterionById(id) {
    if (id == null || id === "") return null;
    return (
        scaleCriteria.value.find(
            (criterion) => String(criterion.id) === String(id),
        ) ?? null
    );
}

function levelsFromCriterion(criterion, withSort = false) {
    if (!criterion || !Array.isArray(criterion.levels)) return [];
    return criterion.levels.map((level, index) => {
        const row = {
            code: String(level?.code ?? ""),
            label: String(level?.label ?? `Mức ${index + 1}`),
            score: Number(level?.score ?? 0),
        };
        if (withSort) row.sort_order = index;
        return row;
    });
}

function applyClassificationCriterion(value) {
    const criterion = criterionById(value);
    kit.classification_criterion_id = criterion ? Number(criterion.id) : null;
    kit.classification_use_default = false;
    if (criterion) {
        const levels = levelsFromCriterion(criterion, true);
        kit.base_adjust_levels = cloneClassificationLevels(levels);
        kit.performance_levels = cloneLevels(
            levels,
            DEFAULT_PERFORMANCE_LEVELS,
        );
    } else {
        kit.base_adjust_levels = cloneClassificationLevels(
            levelsFromCriterion(
                criterionById(kit.classification_criterion_id),
                true,
            ),
        );
        kit.performance_levels = cloneLevels(null, DEFAULT_PERFORMANCE_LEVELS);
    }
    scheduleSaveKit();
}

function restoreClassificationDefaults() {
    if (!canManage.value) return;
    kit.classification_criterion_id = null;
    kit.classification_use_default = true;
    kit.base_adjust_levels = cloneClassificationLevels(null);
    kit.performance_levels = cloneLevels(null, DEFAULT_PERFORMANCE_LEVELS);
    scheduleSaveKit();
}

function applyActiveScaleCriterion(value) {
    const criterion = criterionById(value);
    const config = activeScale.value;
    kit[config.criterionIdKey] = criterion ? Number(criterion.id) : null;
    kit[config.useDefaultKey] = false;
    const levels = criterion ? levelsFromCriterion(criterion) : null;

    if (config.key === "progress") {
        kit.progress_levels = cloneLevels(levels, DEFAULT_PROGRESS_LEVELS);
        demoProgressIndex.value = Math.min(
            demoProgressIndex.value,
            kit.progress_levels.length - 1,
        );
    } else if (config.key === "quality") {
        kit.quality_levels = cloneLevels(levels, DEFAULT_QUALITY_LEVELS);
        demoQualityIndex.value = Math.min(
            demoQualityIndex.value,
            kit.quality_levels.length - 1,
        );
    } else {
        kit.weighted_task_levels = cloneLevels(
            levels,
            DEFAULT_WEIGHT_LEVELS,
        );
        demoWeightIndex.value = Math.min(
            demoWeightIndex.value,
            kit.weighted_task_levels.length - 1,
        );
    }
    scheduleSaveKit();
}

function restoreActiveScaleDefaults() {
    if (!canManage.value) return;
    const config = activeScale.value;
    kit[config.criterionIdKey] = null;
    kit[config.useDefaultKey] = true;

    if (config.key === "progress") {
        kit.progress_levels = cloneLevels(null, DEFAULT_PROGRESS_LEVELS);
        demoProgressIndex.value = 2;
    } else if (config.key === "quality") {
        kit.quality_levels = cloneLevels(null, DEFAULT_QUALITY_LEVELS);
        demoQualityIndex.value = 1;
    } else {
        kit.weighted_task_levels = cloneLevels(null, DEFAULT_WEIGHT_LEVELS);
        demoWeightIndex.value = 1;
    }
    scheduleSaveKit();
}

function reindexClassification() {
    kit.base_adjust_levels.forEach((level, index) => {
        level.sort_order = index;
    });
}

function addClassificationLevel() {
    if (!canManage.value) return;
    if (kit.base_adjust_levels.length >= CLASSIFICATION_LEVEL_MAX) return;
    kit.base_adjust_levels.push({
        code: "",
        label: `Mức ${kit.base_adjust_levels.length + 1}`,
        score: 0,
        sort_order: kit.base_adjust_levels.length,
    });
    scheduleSaveKit();
}

function removeClassificationLevel(index) {
    if (!canManage.value) return;
    if (kit.base_adjust_levels.length <= CLASSIFICATION_LEVEL_MIN) return;
    kit.base_adjust_levels.splice(index, 1);
    reindexClassification();
    scheduleSaveKit();
}

function moveClassificationLevel(index, delta) {
    if (!canManage.value) return;
    const next = index + delta;
    if (next < 0 || next >= kit.base_adjust_levels.length) return;
    const rows = kit.base_adjust_levels;
    const [row] = rows.splice(index, 1);
    rows.splice(next, 0, row);
    reindexClassification();
    scheduleSaveKit();
}

function rankPercent(percent) {
    const ranks = [...kit.performance_levels].sort(
        (a, b) => Number(b.score) - Number(a.score),
    );
    for (const level of ranks) {
        if (percent >= Number(level.score)) return level;
    }
    return null;
}

function applyKit(data) {
    kit.id = data?.id ?? null;
    kit.mode = data?.mode ?? null;
    kit.base_score = data?.base_score ?? 100;
    kit.task_base_score = data?.task_base_score ?? 100;
    kit.quality_bonus_percent = data?.quality_bonus_percent ?? 5;
    kit.points_per_completed_task = data?.points_per_completed_task ?? 0;
    kit.points_per_incomplete_task = data?.points_per_incomplete_task ?? 0;
    kit.formula = cloneFormula(data?.formula);
    if (!data?.formula && data?.use_project_importance === false) {
        kit.formula.project = "off";
    }
    kit.use_project_importance = kit.formula.project === "on";
    kit.classification_criterion_id =
        data?.classification_criterion_id ?? null;
    kit.difficulty_criterion_id = data?.difficulty_criterion_id ?? null;
    kit.progress_criterion_id = data?.progress_criterion_id ?? null;
    kit.quality_criterion_id = data?.quality_criterion_id ?? null;
    kit.classification_use_default =
        data?.classification_use_default === true;
    kit.difficulty_use_default = data?.difficulty_use_default === true;
    kit.progress_use_default = data?.progress_use_default === true;
    kit.quality_use_default = data?.quality_use_default === true;
    kit.base_adjust_levels = cloneClassificationLevels(
        data?.base_adjust_levels,
    );
    kit.weighted_task_levels = cloneLevels(
        data?.weighted_task_levels,
        DEFAULT_WEIGHT_LEVELS,
    );
    kit.progress_levels = cloneLevels(
        data?.progress_levels,
        DEFAULT_PROGRESS_LEVELS,
    );
    kit.quality_levels = cloneLevels(
        data?.quality_levels,
        DEFAULT_QUALITY_LEVELS,
    );
    kit.performance_levels = cloneLevels(
        data?.performance_levels,
        DEFAULT_PERFORMANCE_LEVELS,
    );
    demoWeightIndex.value = Math.min(
        demoWeightIndex.value,
        Math.max(0, kit.weighted_task_levels.length - 1),
    );
    demoProgressIndex.value = Math.min(
        demoProgressIndex.value,
        Math.max(0, kit.progress_levels.length - 1),
    );
    demoQualityIndex.value = Math.min(
        demoQualityIndex.value,
        Math.max(0, kit.quality_levels.length - 1),
    );
    if (kit.mode) viewMode.value = kit.mode;
    savedSnapshot.value = kitPayload();
}

function resetCurrentMode() {
    if (!canManage.value) return;
    kit.mode = viewMode.value;

    if (viewMode.value === "base_adjust") {
        kit.classification_use_default = true;
        kit.classification_criterion_id = null;
        kit.base_score = 100;
        kit.points_per_completed_task = 0;
        kit.points_per_incomplete_task = 0;
        kit.formula.base = DEFAULT_FORMULA.base;
        kit.formula.done = DEFAULT_FORMULA.done;
        kit.formula.undone = DEFAULT_FORMULA.undone;
        kit.base_adjust_levels = cloneClassificationLevels(null);
        demoCompleted.value = 8;
        demoIncomplete.value = 2;
    } else {
        kit.classification_use_default = true;
        kit.difficulty_use_default = true;
        kit.progress_use_default = true;
        kit.quality_use_default = true;
        kit.classification_criterion_id = null;
        kit.difficulty_criterion_id = null;
        kit.progress_criterion_id = null;
        kit.quality_criterion_id = null;
        kit.task_base_score = 100;
        kit.quality_bonus_percent = 5;
        kit.formula.weight = DEFAULT_FORMULA.weight;
        kit.formula.progress = DEFAULT_FORMULA.progress;
        kit.formula.quality = DEFAULT_FORMULA.quality;
        kit.formula.contrib = DEFAULT_FORMULA.contrib;
        kit.formula.lock_difficulty = DEFAULT_FORMULA.lock_difficulty;
        kit.formula.project = DEFAULT_FORMULA.project;
        kit.use_project_importance = false;
        kit.weighted_task_levels = cloneLevels(
            levelsFromCriterion(
                criterionById(kit.difficulty_criterion_id),
            ),
            DEFAULT_WEIGHT_LEVELS,
        );
        kit.progress_levels = cloneLevels(
            levelsFromCriterion(criterionById(kit.progress_criterion_id)),
            DEFAULT_PROGRESS_LEVELS,
        );
        kit.quality_levels = cloneLevels(
            levelsFromCriterion(criterionById(kit.quality_criterion_id)),
            DEFAULT_QUALITY_LEVELS,
        );
        kit.performance_levels = cloneLevels(
            levelsFromCriterion(
                criterionById(kit.classification_criterion_id),
            ),
            DEFAULT_PERFORMANCE_LEVELS,
        );
        demoWeightIndex.value = 1;
        demoProgressIndex.value = 2;
        demoQualityIndex.value = 1;
        demoCaseId.value = "on_time";
        scaleView.value = "weight";
    }
}

function applyWeightCase(item) {
    demoCaseId.value = item.id;
    demoWeightIndex.value = item.weightIndex;
    demoProgressIndex.value = item.progressIndex;
    demoQualityIndex.value = item.qualityIndex;
}

function selectDemoWeight(idx) {
    demoWeightIndex.value = idx;
    syncWeightCase();
}

function selectDemoProgress(idx) {
    demoProgressIndex.value = idx;
    syncWeightCase();
}

function selectDemoQuality(idx) {
    demoQualityIndex.value = idx;
    syncWeightCase();
}

function syncWeightCase() {
    const match = WEIGHT_CASES.find(
        (item) =>
            item.weightIndex === demoWeightIndex.value &&
            item.progressIndex === demoProgressIndex.value &&
            item.qualityIndex === demoQualityIndex.value,
    );
    demoCaseId.value = match?.id ?? null;
}

function round2(n) {
    return Math.round(n * 100) / 100;
}

function rankFor(score) {
    for (const level of classificationLevels.value) {
        if (score >= Number(level.score)) return level;
    }
    return null;
}

function isDemoRank(level) {
    if (!demoRank.value) return false;
    return (
        Number(demoRank.value.score) === Number(level.score) &&
        String(demoRank.value.label) === String(level.label)
    );
}

function isTaskRank(level) {
    if (!demoTaskRank.value) return false;
    return (
        Number(demoTaskRank.value.score) === Number(level.score) &&
        String(demoTaskRank.value.label) === String(level.label)
    );
}

function formatPlain(score) {
    const n = Number(score);
    if (!Number.isFinite(n)) return "0";
    return Number.isInteger(n) ? String(n) : String(round2(n));
}

function formatSigned(score) {
    const n = Number(score);
    if (!Number.isFinite(n)) return "0";
    const abs = Number.isInteger(n)
        ? String(Math.abs(n))
        : String(Math.abs(round2(n)));
    if (n > 0) return `+${abs}`;
    if (n < 0) return `−${abs}`;
    return abs;
}

function scoreTone(score) {
    const n = Number(score);
    if (n > 0) return "pos";
    if (n < 0) return "neg";
    return "zero";
}

function pointPhrase(score) {
    const n = Number(score);
    if (!Number.isFinite(n) || n === 0) return "Không cộng không trừ";
    if (n > 0) return `Cộng ${formatPlain(n)} điểm`;
    return `Trừ ${formatPlain(Math.abs(n))} điểm`;
}

function clampNumber(value, min, max) {
    const n = Number(value);
    if (!Number.isFinite(n)) return min;
    return Math.min(max, Math.max(min, n));
}

function bumpKit(key, delta, min, max) {
    if (!canManage.value) return;
    kit[key] = clampNumber((Number(kit[key]) || 0) + delta, min, max);
    scheduleSaveKit();
}

function bumpLevelScore(levels, index, delta, min, max) {
    if (!canManage.value) return;
    const row = levels[index];
    if (!row) return;
    row.score = clampNumber((Number(row.score) || 0) + delta, min, max);
    scheduleSaveKit();
}

function bumpDemo(key, delta) {
    const target = key === "done" ? demoCompleted : demoIncomplete;
    target.value = clampNumber((Number(target.value) || 0) + delta, 0, 999);
}

function bumpActiveScale(index, delta) {
    bumpLevelScore(
        activeScale.value.levels,
        index,
        delta,
        activeScale.value.scoreMin,
        activeScale.value.scoreMax,
    );
}

async function load() {
    if (!hasDepartment.value) {
        applyKit(null);
        hydrating.value = false;
        return;
    }
    loading.value = true;
    hydrating.value = true;
    try {
        const { data } = await window.axios.get("/api/evaluation/score-kit");
        allCriteria.value = data.criteria ?? [];
        applyKit(data.kit);
    } catch (err) {
        showClientToast(
            "error",
            err?.response?.data?.message || "Không tải được khung chấm điểm.",
        );
    } finally {
        loading.value = false;
        hydrating.value = false;
    }
}

function mapLevels(levels, withSort = false) {
    return levels.map((level, index) => {
        const row = {
            code: String(level.code ?? "").trim(),
            label: String(level.label ?? "").trim(),
            score: Number(level.score) || 0,
        };
        if (withSort) {
            row.sort_order = Number.isFinite(Number(level.sort_order))
                ? Number(level.sort_order)
                : index;
        }
        return row;
    });
}

function kitPayload() {
    return {
        mode: kit.mode,
        base_score: Number(kit.base_score) || 0,
        task_base_score: Number(kit.task_base_score) || 0,
        quality_bonus_percent: Number(kit.quality_bonus_percent) || 0,
        points_per_completed_task: Number(kit.points_per_completed_task) || 0,
        points_per_incomplete_task: Number(kit.points_per_incomplete_task) || 0,
        use_project_importance: kit.formula.project === "on",
        classification_criterion_id: kit.classification_criterion_id,
        difficulty_criterion_id: kit.difficulty_criterion_id,
        progress_criterion_id: kit.progress_criterion_id,
        quality_criterion_id: kit.quality_criterion_id,
        classification_use_default: kit.classification_use_default,
        difficulty_use_default: kit.difficulty_use_default,
        progress_use_default: kit.progress_use_default,
        quality_use_default: kit.quality_use_default,
        formula: {
            base: kit.formula.base,
            done: kit.formula.done,
            undone: kit.formula.undone,
            weight: kit.formula.weight,
            project: kit.formula.project,
            progress: kit.formula.progress,
            quality: kit.formula.quality,
            contrib: kit.formula.contrib,
            lock_difficulty: kit.formula.lock_difficulty,
        },
        base_adjust_levels: mapLevels(kit.base_adjust_levels, true),
        weighted_task_levels: mapLevels(kit.weighted_task_levels),
        progress_levels: mapLevels(kit.progress_levels),
        quality_levels: mapLevels(kit.quality_levels),
        performance_levels: mapLevels(kit.performance_levels),
    };
}

function flashSaved() {
    savedFlash.value = true;
    clearTimeout(flashTimer);
    flashTimer = setTimeout(() => {
        savedFlash.value = false;
    }, 1600);
}

async function saveKit({ context = "manual" } = {}) {
    if (!canManage.value || hydrating.value) return;
    if (savingKit.value) return false;

    const savingVersion = editVersion;
    savingKit.value = true;
    try {
        const { data } = await window.axios.put("/api/evaluation/score-kit", {
            ...kitPayload(),
            change_context: context,
        });
        if (savingVersion === editVersion) {
            applyKit(data.kit);
            dirty.value = false;
            flashSaved();
            showClientToast("success", "Đã lưu cách tính điểm.");
        }
        return true;
    } catch (err) {
        const first = err?.response?.data?.errors
            ? Object.values(err.response.data.errors).flat()[0]
            : null;
        showClientToast(
            "error",
            first ||
                err?.response?.data?.message ||
                "Không lưu được khung chấm điểm.",
        );
        return false;
    } finally {
        savingKit.value = false;
    }
}

function scheduleSaveKit() {
    if (hydrating.value || !canManage.value) return;
    editVersion += 1;
    dirty.value = true;
    savedFlash.value = false;
}

function saveNow() {
    if (!dirty.value || savingKit.value) return;
    changesDialogKind.value = "save";
    changesDialogOpen.value = true;
}

function closeChangesDialog() {
    if (savingKit.value) return;
    changesDialogOpen.value = false;
    if (changesDialogKind.value === "leave" && pendingLeaveNavigation) {
        pendingLeaveNavigation.resolve(false);
        pendingLeaveNavigation = null;
    }
}

function onChangesDialogKeydown(event) {
    if (event.key === "Escape") closeChangesDialog();
}

async function confirmChangesSave() {
    const context =
        changesDialogKind.value === "leave" ? "leave_save" : "manual";
    const ok = await saveKit({ context });
    if (!ok) return;
    changesDialogOpen.value = false;
    if (pendingLeaveNavigation) {
        pendingLeaveNavigation.resolve(true);
        pendingLeaveNavigation = null;
    }
}

function confirmChangesDiscard() {
    if (changesDialogKind.value !== "leave" || savingKit.value) return;
    dirty.value = false;
    changesDialogOpen.value = false;
    if (pendingLeaveNavigation) {
        pendingLeaveNavigation.resolve(true);
        pendingLeaveNavigation = null;
    }
}

async function confirmReset() {
    resetCurrentMode();
    resetOpen.value = false;
    scheduleSaveKit();
    await saveKit({ context: "reset" });
}

function selectMode(id) {
    if (hydrating.value || id === kit.mode) {
        viewMode.value = id;
        return;
    }
    if (!canManage.value) {
        viewMode.value = id;
        return;
    }
    pendingMode.value = id;
    modeConfirmOpen.value = true;
}

async function confirmModeChange() {
    const nextMode = pendingMode.value;
    if (!nextMode) return;

    modeConfirmOpen.value = false;
    pendingMode.value = null;
    viewMode.value = nextMode;
    kit.mode = nextMode;
    scheduleSaveKit();
    await saveKit({ context: "mode_change" });
}

async function reload() {
    if (reloading.value) return;
    reloading.value = true;
    try {
        await load();
    } finally {
        reloading.value = false;
    }
}

watch(changesDialogOpen, (open) => {
    if (open) {
        document.addEventListener("keydown", onChangesDialogKeydown);
    } else {
        document.removeEventListener("keydown", onChangesDialogKeydown);
    }
});

onMounted(() => {
    load();
    window.addEventListener("beforeunload", warnUnsavedBeforeUnload);
});

onBeforeUnmount(() => {
    clearTimeout(flashTimer);
    document.removeEventListener("keydown", onChangesDialogKeydown);
    window.removeEventListener("beforeunload", warnUnsavedBeforeUnload);
    if (pendingLeaveNavigation) {
        pendingLeaveNavigation.resolve(false);
        pendingLeaveNavigation = null;
    }
});

function warnUnsavedBeforeUnload(event) {
    if (!dirty.value || !canManage.value) return;
    event.preventDefault();
    event.returnValue = "";
}

onBeforeRouteLeave(() => {
    if (!dirty.value || !canManage.value) return true;
    if (changesDialogOpen.value && changesDialogKind.value === "leave") {
        return false;
    }
    return new Promise((resolve) => {
        pendingLeaveNavigation = { resolve };
        changesDialogKind.value = "leave";
        changesDialogOpen.value = true;
    });
});
</script>

<template>
    <section class="kit-wrap">
        <PageHeader
            title="Khung chấm điểm"
            :subtitle="departmentName"
            icon="layers"
            :breadcrumbs="[
                { label: 'Trang chủ', to: { name: 'home' } },
                { label: 'Khung chấm điểm' },
            ]"
        >
            <template #actions>
                <button
                    v-if="canManage && hasDepartment"
                    type="button"
                    class="kit-header-btn"
                    :disabled="loading || savingKit"
                    @click="resetOpen = true"
                >
                    <AppIcon name="refresh" :size="15" :stroke-width="1.75" />
                    Mặc định
                </button>
                <button
                    type="button"
                    class="kit-header-btn"
                    :disabled="reloading || savingKit || dirty"
                    @click="reload"
                >
                    <AppIcon
                        name="refresh"
                        :size="16"
                        :class="{ 'kit-header-btn__spin': reloading }"
                    />
                    Làm mới
                </button>
            </template>
        </PageHeader>

        <nav
            v-if="hasDepartment && !loading"
            class="kit-tabs hide-scrollbar"
            aria-label="Cách tính điểm"
        >
            <button
                v-for="mode in MODES"
                :key="mode.id"
                type="button"
                class="kit-tab"
                :class="{ 'kit-tab--on': viewMode === mode.id }"
                :aria-current="viewMode === mode.id ? 'page' : undefined"
                @click="selectMode(mode.id)"
            >
                <AppIcon :name="mode.icon" :size="15" :stroke-width="1.75" />
                <span class="kit-tab__copy">
                    <span class="kit-tab__title">{{ mode.title }}</span>
                    <span class="kit-tab__lead">{{ mode.lead }}</span>
                </span>
            </button>
        </nav>

        <div class="kit-page hide-scrollbar">
            <p v-if="!hasDepartment" class="kit-banner">
                Tài khoản chưa gắn phòng ban — không thể cấu hình khung chấm
                điểm.
            </p>

            <p v-else-if="loading" class="kit-banner">
                Đang tải khung chấm điểm…
            </p>

            <template v-else>
                <section
                    class="kit-formula"
                    :class="
                        viewMode === 'base_adjust'
                            ? 'kit-formula--count'
                            : 'kit-formula--weight'
                    "
                    aria-label="Công thức tính điểm"
                >
                    <h2 class="kit-formula__title">Công thức</h2>
                    <div class="kit-formula__body" aria-live="polite">
                        <p class="kit-formula__method">
                            {{
                                viewMode === "base_adjust"
                                    ? baseFormulaMethod
                                    : weightFormulaMethod
                            }}
                        </p>
                        <p
                            v-if="
                                viewMode === 'base_adjust'
                                    ? baseFormulaApplied
                                    : weightFormulaApplied
                            "
                            class="kit-formula__applied"
                        >
                            {{
                                viewMode === "base_adjust"
                                    ? baseFormulaApplied
                                    : weightFormulaApplied
                            }}
                        </p>
                    </div>
                </section>

                <div v-if="viewMode === 'base_adjust'" class="kit-split">
                    <section class="kit-col" aria-label="Điểm theo việc">
                        <div class="kit-tiles">
                            <div class="kit-tile kit-tile--base">
                                <label
                                    class="kit-tile__name"
                                    for="kit-base-score"
                                    >Điểm khởi đầu</label
                                >
                                <button
                                    type="button"
                                    class="kit-tile__phrase kit-tile__phrase--btn"
                                    :disabled="!canManage"
                                    @click="cycleOnOff('base')"
                                >
                                    {{
                                        kit.formula.base === "on"
                                            ? "Có trong công thức"
                                            : "Không tính vào công thức"
                                    }}
                                </button>
                                <div class="kit-stepper">
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Giảm điểm khởi đầu"
                                        @click="
                                            bumpKit('base_score', -1, 0, 9999)
                                        "
                                    >
                                        <AppIcon
                                            name="minus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                    <input
                                        id="kit-base-score"
                                        v-model.number="kit.base_score"
                                        type="number"
                                        min="0"
                                        max="9999"
                                        step="1"
                                        placeholder="VD: 100"
                                        class="kit-stepper__input"
                                        :disabled="!canManage"
                                        @input="scheduleSaveKit"
                                    />
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Tăng điểm khởi đầu"
                                        @click="
                                            bumpKit('base_score', 1, 0, 9999)
                                        "
                                    >
                                        <AppIcon
                                            name="plus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                </div>
                            </div>
                            <div class="kit-tile kit-tile--done">
                                <label class="kit-tile__name" for="kit-done"
                                    >Mỗi việc hoàn thành</label
                                >
                                <button
                                    type="button"
                                    class="kit-tile__phrase kit-tile__phrase--btn"
                                    :disabled="!canManage"
                                    @click="cycleTermOp('done')"
                                >
                                    {{
                                        pointPhrase(
                                            kit.points_per_completed_task,
                                        )
                                    }}
                                    · {{ formulaOpLabel("done").toLowerCase() }}
                                </button>
                                <div class="kit-stepper">
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Giảm điểm mỗi việc hoàn thành"
                                        @click="
                                            bumpKit(
                                                'points_per_completed_task',
                                                -0.5,
                                                -999,
                                                999,
                                            )
                                        "
                                    >
                                        <AppIcon
                                            name="minus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                    <input
                                        id="kit-done"
                                        v-model.number="
                                            kit.points_per_completed_task
                                        "
                                        type="number"
                                        min="-999"
                                        max="999"
                                        step="0.5"
                                        placeholder="VD: 1"
                                        class="kit-stepper__input"
                                        :disabled="!canManage"
                                        @input="scheduleSaveKit"
                                    />
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Tăng điểm mỗi việc hoàn thành"
                                        @click="
                                            bumpKit(
                                                'points_per_completed_task',
                                                0.5,
                                                -999,
                                                999,
                                            )
                                        "
                                    >
                                        <AppIcon
                                            name="plus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                </div>
                            </div>
                            <div class="kit-tile kit-tile--undone">
                                <label class="kit-tile__name" for="kit-undone"
                                    >Mỗi việc chưa hoàn thành</label
                                >
                                <button
                                    type="button"
                                    class="kit-tile__phrase kit-tile__phrase--btn"
                                    :disabled="!canManage"
                                    @click="cycleTermOp('undone')"
                                >
                                    {{
                                        pointPhrase(
                                            kit.points_per_incomplete_task,
                                        )
                                    }}
                                    ·
                                    {{ formulaOpLabel("undone").toLowerCase() }}
                                </button>
                                <div class="kit-stepper">
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Giảm điểm mỗi việc chưa hoàn thành"
                                        @click="
                                            bumpKit(
                                                'points_per_incomplete_task',
                                                -0.5,
                                                -999,
                                                999,
                                            )
                                        "
                                    >
                                        <AppIcon
                                            name="minus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                    <input
                                        id="kit-undone"
                                        v-model.number="
                                            kit.points_per_incomplete_task
                                        "
                                        type="number"
                                        min="-999"
                                        max="999"
                                        step="0.5"
                                        placeholder="VD: -1"
                                        class="kit-stepper__input"
                                        :disabled="!canManage"
                                        @input="scheduleSaveKit"
                                    />
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Tăng điểm mỗi việc chưa hoàn thành"
                                        @click="
                                            bumpKit(
                                                'points_per_incomplete_task',
                                                0.5,
                                                -999,
                                                999,
                                            )
                                        "
                                    >
                                        <AppIcon
                                            name="plus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="kit-panel kit-panel--story">
                            <div class="kit-panel__head">
                                <h2 class="kit-panel__title">Xem thử</h2>
                                <p class="kit-panel__lead">
                                    Đổi số việc — tổng và xếp loại đổi theo
                                </p>
                            </div>
                            <div class="kit-demo">
                                <label
                                    class="kit-demo__field kit-demo__field--done"
                                    for="kit-demo-done"
                                >
                                    <span class="kit-demo__label"
                                        >Việc đã xong</span
                                    >
                                    <div class="kit-stepper">
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            aria-label="Giảm số việc hoàn thành"
                                            @click="bumpDemo('done', -1)"
                                        >
                                            <AppIcon
                                                name="minus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                        <input
                                            id="kit-demo-done"
                                            v-model.number="demoCompleted"
                                            type="number"
                                            min="0"
                                            max="999"
                                            step="1"
                                            placeholder="VD: 8"
                                            class="kit-stepper__input"
                                        />
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            aria-label="Tăng số việc hoàn thành"
                                            @click="bumpDemo('done', 1)"
                                        >
                                            <AppIcon
                                                name="plus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                    </div>
                                </label>
                                <label
                                    class="kit-demo__field kit-demo__field--undone"
                                    for="kit-demo-undone"
                                >
                                    <span class="kit-demo__label"
                                        >Việc chưa xong</span
                                    >
                                    <div class="kit-stepper">
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            aria-label="Giảm số việc chưa hoàn thành"
                                            @click="bumpDemo('undone', -1)"
                                        >
                                            <AppIcon
                                                name="minus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                        <input
                                            id="kit-demo-undone"
                                            v-model.number="demoIncomplete"
                                            type="number"
                                            min="0"
                                            max="999"
                                            step="1"
                                            placeholder="VD: 2"
                                            class="kit-stepper__input"
                                        />
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            aria-label="Tăng số việc chưa hoàn thành"
                                            @click="bumpDemo('undone', 1)"
                                        >
                                            <AppIcon
                                                name="plus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                    </div>
                                </label>
                            </div>
                            <ul class="kit-story">
                                <li
                                    v-if="kit.formula.base === 'on'"
                                    class="kit-story__row"
                                >
                                    <span>Bắt đầu</span>
                                    <span
                                        class="kit-story__v kit-story__v--base"
                                        >{{ formatPlain(kit.base_score) }}</span
                                    >
                                </li>
                                <li
                                    v-if="kit.formula.done !== 'off'"
                                    class="kit-story__row"
                                >
                                    <span
                                        >{{ doneCount }} việc xong ·
                                        {{
                                            formulaOpLabel("done").toLowerCase()
                                        }}</span
                                    >
                                    <span
                                        class="kit-story__v"
                                        :class="`kit-story__v--${scoreTone(demoDoneDelta)}`"
                                        >{{ formatSigned(demoDoneDelta) }}</span
                                    >
                                </li>
                                <li
                                    v-if="kit.formula.undone !== 'off'"
                                    class="kit-story__row"
                                >
                                    <span
                                        >{{ undoneCount }} việc chưa xong ·
                                        {{
                                            formulaOpLabel(
                                                "undone",
                                            ).toLowerCase()
                                        }}</span
                                    >
                                    <span
                                        class="kit-story__v"
                                        :class="`kit-story__v--${scoreTone(demoUndoneDelta)}`"
                                        >{{
                                            formatSigned(demoUndoneDelta)
                                        }}</span
                                    >
                                </li>
                                <li
                                    v-if="
                                        kit.formula.base === 'off' &&
                                        kit.formula.done === 'off' &&
                                        kit.formula.undone === 'off'
                                    "
                                    class="kit-story__row"
                                >
                                    <span>Chưa chọn hạng mục nào</span>
                                    <span
                                        class="kit-story__v kit-story__v--zero"
                                        >0</span
                                    >
                                </li>
                            </ul>
                            <div class="kit-result">
                                <span class="kit-result__score"
                                    >{{ formatPlain(demoTotal) }} điểm</span
                                >
                                <span class="kit-result__rank">{{
                                    demoRank?.label || "Chưa đạt mức nào"
                                }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="kit-col" aria-label="Thang xếp loại">
                        <div class="kit-panel">
                            <div
                                class="kit-panel__head kit-panel__head--scale"
                            >
                                <div class="kit-panel__heading">
                                    <h2 class="kit-panel__title">
                                        Thang xếp loại của phòng
                                    </h2>
                                    <div class="kit-source">
                                        <select
                                            class="kit-source__select"
                                            :value="
                                                kit.classification_criterion_id ??
                                                ''
                                            "
                                            :disabled="!canManage"
                                            aria-label="Tiêu chí nguồn thang xếp loại"
                                            @change="
                                                applyClassificationCriterion(
                                                    $event.target.value,
                                                )
                                            "
                                        >
                                            <option value="" disabled>
                                                Mặc định hệ thống
                                            </option>
                                            <option
                                                v-for="criterion in classificationCriteria"
                                                :key="`classification-${criterion.id}`"
                                                :value="criterion.id"
                                            >
                                                {{ criterion.name }}
                                            </option>
                                        </select>
                                        <div class="kit-source__actions">
                                            <a
                                                class="kit-source__action"
                                                href="/manager/evaluation"
                                                >Mở Tiêu chí</a
                                            >
                                            <button
                                                type="button"
                                                class="kit-source__action kit-source__action--reset"
                                                :disabled="
                                                    !canManage ||
                                                    kit.classification_use_default
                                                "
                                                @click="
                                                    restoreClassificationDefaults
                                                "
                                            >
                                                <AppIcon
                                                    name="refresh"
                                                    :size="13"
                                                    :stroke-width="1.75"
                                                />
                                                Khôi phục mặc định
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <button
                                    v-if="
                                        canManage &&
                                        !classificationFromCriterion
                                    "
                                    type="button"
                                    class="kit-header-btn"
                                    :disabled="
                                        kit.base_adjust_levels.length >=
                                        CLASSIFICATION_LEVEL_MAX
                                    "
                                    @click="addClassificationLevel"
                                >
                                    <AppIcon
                                        name="plus"
                                        :size="14"
                                        :stroke-width="2"
                                    />
                                    Thêm mức
                                </button>
                            </div>
                            <div class="kit-levels kit-levels--class">
                                <div class="kit-levels__head">
                                    <span>Mã</span>
                                    <span>Tên mức</span>
                                    <span>Từ điểm</span>
                                    <span />
                                </div>
                                <div
                                    v-for="(
                                        level, idx
                                    ) in kit.base_adjust_levels"
                                    :key="`base-${idx}`"
                                    class="kit-levels__row"
                                    :class="{
                                        'kit-levels__row--on':
                                            isDemoRank(level),
                                    }"
                                >
                                    <input
                                        v-model="level.code"
                                        type="text"
                                        maxlength="8"
                                        placeholder="VD: XS"
                                        class="kit-field"
                                        :disabled="
                                            !canManage ||
                                            classificationFromCriterion
                                        "
                                        :aria-label="`Mã mức ${idx + 1}`"
                                        @input="scheduleSaveKit"
                                    />
                                    <input
                                        v-model="level.label"
                                        type="text"
                                        maxlength="80"
                                        placeholder="VD: Xuất sắc"
                                        class="kit-field"
                                        :disabled="
                                            !canManage ||
                                            classificationFromCriterion
                                        "
                                        :aria-label="`Tên mức ${idx + 1}`"
                                        @input="scheduleSaveKit"
                                    />
                                    <div class="kit-stepper">
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            :disabled="
                                                !canManage ||
                                                classificationFromCriterion
                                            "
                                            :aria-label="`Giảm mốc ${level.label}`"
                                            @click="
                                                bumpLevelScore(
                                                    kit.base_adjust_levels,
                                                    idx,
                                                    -1,
                                                    0,
                                                    9999,
                                                )
                                            "
                                        >
                                            <AppIcon
                                                name="minus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                        <input
                                            v-model.number="level.score"
                                            type="number"
                                            min="0"
                                            max="9999"
                                            step="1"
                                            placeholder="VD: 100"
                                            class="kit-stepper__input"
                                            :disabled="
                                                !canManage ||
                                                classificationFromCriterion
                                            "
                                            :aria-label="`Mốc điểm ${level.label}`"
                                            @input="scheduleSaveKit"
                                        />
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            :disabled="
                                                !canManage ||
                                                classificationFromCriterion
                                            "
                                            :aria-label="`Tăng mốc ${level.label}`"
                                            @click="
                                                bumpLevelScore(
                                                    kit.base_adjust_levels,
                                                    idx,
                                                    1,
                                                    0,
                                                    9999,
                                                )
                                            "
                                        >
                                            <AppIcon
                                                name="plus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                    </div>
                                    <div class="kit-levels__ops">
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            :disabled="
                                                !canManage ||
                                                classificationFromCriterion ||
                                                idx === 0
                                            "
                                            :aria-label="`Đưa ${level.label || 'mức'} lên`"
                                            @click="
                                                moveClassificationLevel(idx, -1)
                                            "
                                        >
                                            <AppIcon
                                                name="chevronsUp"
                                                :size="13"
                                                :stroke-width="2"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            :disabled="
                                                !canManage ||
                                                classificationFromCriterion ||
                                                idx ===
                                                    kit.base_adjust_levels
                                                        .length -
                                                        1
                                            "
                                            :aria-label="`Đưa ${level.label || 'mức'} xuống`"
                                            @click="
                                                moveClassificationLevel(idx, 1)
                                            "
                                        >
                                            <AppIcon
                                                name="chevronsDown"
                                                :size="13"
                                                :stroke-width="2"
                                            />
                                        </button>
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            :disabled="
                                                !canManage ||
                                                classificationFromCriterion ||
                                                kit.base_adjust_levels.length <=
                                                    CLASSIFICATION_LEVEL_MIN
                                            "
                                            :aria-label="`Xóa ${level.label || 'mức'}`"
                                            @click="
                                                removeClassificationLevel(idx)
                                            "
                                        >
                                            <AppIcon
                                                name="trash"
                                                :size="13"
                                                :stroke-width="2"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div v-else class="kit-split kit-split--task">
                    <section class="kit-col" aria-label="Cách tính điểm việc">
                        <div class="kit-tiles kit-tiles--quad">
                            <div class="kit-tile kit-tile--base">
                                <label
                                    class="kit-tile__name"
                                    for="kit-task-base"
                                    >Điểm cơ bản</label
                                >
                                <p class="kit-tile__phrase">
                                    Gốc để tính điểm chuẩn
                                </p>
                                <div class="kit-stepper">
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Giảm điểm cơ bản"
                                        @click="
                                            bumpKit(
                                                'task_base_score',
                                                -1,
                                                0,
                                                9999,
                                            )
                                        "
                                    >
                                        <AppIcon
                                            name="minus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                    <input
                                        id="kit-task-base"
                                        v-model.number="kit.task_base_score"
                                        type="number"
                                        min="0"
                                        max="9999"
                                        step="1"
                                        placeholder="VD: 100"
                                        class="kit-stepper__input"
                                        :disabled="!canManage"
                                        @input="scheduleSaveKit"
                                    />
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Tăng điểm cơ bản"
                                        @click="
                                            bumpKit(
                                                'task_base_score',
                                                1,
                                                0,
                                                9999,
                                            )
                                        "
                                    >
                                        <AppIcon
                                            name="plus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="kit-tile kit-tile--switch"
                                :class="
                                    kit.formula.weight === 'on'
                                        ? 'kit-tile--gold'
                                        : 'kit-tile--off'
                                "
                                role="switch"
                                :aria-checked="
                                    kit.formula.weight === 'on'
                                        ? 'true'
                                        : 'false'
                                "
                                aria-labelledby="kit-weight-label"
                                :disabled="!canManage"
                                @click="cycleOnOff('weight')"
                            >
                                <span class="kit-tile__copy">
                                    <span
                                        class="kit-tile__name"
                                        id="kit-weight-label"
                                        >Độ khó → điểm chuẩn</span
                                    >
                                    <span class="kit-tile__phrase">
                                        {{
                                            kit.formula.weight === "on"
                                                ? "Chuẩn hóa khối lượng, không thưởng"
                                                : "Không tính vào điểm chuẩn"
                                        }}
                                    </span>
                                </span>
                                <span
                                    class="kit-switch"
                                    :class="{
                                        'kit-switch--on':
                                            kit.formula.weight === 'on',
                                    }"
                                    aria-hidden="true"
                                >
                                    <span class="kit-switch__thumb" />
                                </span>
                            </button>
                            <button
                                type="button"
                                class="kit-tile kit-tile--switch"
                                :class="
                                    kit.formula.progress === 'on'
                                        ? 'kit-tile--teal'
                                        : 'kit-tile--off'
                                "
                                role="switch"
                                :aria-checked="
                                    kit.formula.progress === 'on'
                                        ? 'true'
                                        : 'false'
                                "
                                aria-labelledby="kit-progress-label"
                                :disabled="!canManage"
                                @click="cycleOnOff('progress')"
                            >
                                <span class="kit-tile__copy">
                                    <span
                                        class="kit-tile__name"
                                        id="kit-progress-label"
                                        >Tiến độ → điểm thực</span
                                    >
                                    <span class="kit-tile__phrase">
                                        {{
                                            kit.formula.progress === "on"
                                                ? "Mức hoàn thành đúng hạn / sớm / trễ"
                                                : "Không tính vào điểm thực"
                                        }}
                                    </span>
                                </span>
                                <span
                                    class="kit-switch kit-switch--teal"
                                    :class="{
                                        'kit-switch--on':
                                            kit.formula.progress === 'on',
                                    }"
                                    aria-hidden="true"
                                >
                                    <span class="kit-switch__thumb" />
                                </span>
                            </button>
                            <button
                                type="button"
                                class="kit-tile kit-tile--switch"
                                :class="
                                    kit.formula.quality === 'on'
                                        ? 'kit-tile--done'
                                        : 'kit-tile--off'
                                "
                                role="switch"
                                :aria-checked="
                                    kit.formula.quality === 'on'
                                        ? 'true'
                                        : 'false'
                                "
                                aria-labelledby="kit-quality-label"
                                :disabled="!canManage"
                                @click="cycleOnOff('quality')"
                            >
                                <span class="kit-tile__copy">
                                    <span
                                        class="kit-tile__name"
                                        id="kit-quality-label"
                                        >Chất lượng → điểm thực</span
                                    >
                                    <span class="kit-tile__phrase">
                                        {{
                                            kit.formula.quality === "on"
                                                ? "Xuất sắc = Đạt ×1, bonus cộng riêng"
                                                : "Không tính vào điểm thực"
                                        }}
                                    </span>
                                </span>
                                <span
                                    class="kit-switch kit-switch--done"
                                    :class="{
                                        'kit-switch--on':
                                            kit.formula.quality === 'on',
                                    }"
                                    aria-hidden="true"
                                >
                                    <span class="kit-switch__thumb" />
                                </span>
                            </button>
                        </div>

                        <button
                            type="button"
                            class="kit-contrib"
                            :class="{
                                'kit-contrib--on':
                                    kit.formula.lock_difficulty === 'on',
                            }"
                            role="switch"
                            :aria-checked="
                                kit.formula.lock_difficulty === 'on'
                                    ? 'true'
                                    : 'false'
                            "
                            :disabled="!canManage"
                            @click="cycleOnOff('lock_difficulty')"
                        >
                            <span class="kit-contrib__copy">
                                <span class="kit-contrib__name"
                                    >Khóa độ khó sau khi giao việc</span
                                >
                                <span class="kit-contrib__phrase">
                                    {{
                                        kit.formula.lock_difficulty === "on"
                                            ? "Người giao đề xuất · quản lý xác nhận · không sửa sau khi việc xong"
                                            : "Được sửa độ khó bất kỳ lúc nào — dễ đội điểm sau khi hoàn thành"
                                    }}
                                </span>
                            </span>
                            <span
                                class="kit-switch kit-switch--teal"
                                :class="{
                                    'kit-switch--on':
                                        kit.formula.lock_difficulty === 'on',
                                }"
                                aria-hidden="true"
                            >
                                <span class="kit-switch__thumb" />
                            </span>
                        </button>

                        <div class="kit-panel kit-panel--story">
                            <div class="kit-panel__head">
                                <h2 class="kit-panel__title">
                                    Case study · Một việc
                                </h2>
                                <p class="kit-panel__lead">
                                    Chọn tình huống, rồi đổi mức để xem điểm
                                </p>
                            </div>

                            <div
                                class="kit-cases"
                                role="radiogroup"
                                aria-label="Chọn việc mẫu"
                            >
                                <button
                                    v-for="item in WEIGHT_CASES"
                                    :key="item.id"
                                    type="button"
                                    class="kit-case"
                                    :class="{
                                        'kit-case--on': demoCaseId === item.id,
                                    }"
                                    role="radio"
                                    :aria-checked="
                                        demoCaseId === item.id
                                            ? 'true'
                                            : 'false'
                                    "
                                    @click="applyWeightCase(item)"
                                >
                                    <span class="kit-case__task">{{
                                        item.task
                                    }}</span>
                                    <span class="kit-case__project">{{
                                        item.note
                                    }}</span>
                                </button>
                            </div>

                            <div class="kit-study">
                                <div
                                    v-if="kit.formula.weight === 'on'"
                                    class="kit-study__group"
                                >
                                    <p
                                        class="kit-study__label"
                                        id="kit-study-weight"
                                    >
                                        Độ khó
                                    </p>
                                    <div
                                        class="kit-picks"
                                        role="radiogroup"
                                        aria-labelledby="kit-study-weight"
                                    >
                                        <button
                                            v-for="(
                                                level, idx
                                            ) in kit.weighted_task_levels"
                                            :key="`pick-w-${idx}`"
                                            type="button"
                                            class="kit-pick"
                                            :class="{
                                                'kit-pick--on':
                                                    demoWeightIndex === idx,
                                            }"
                                            role="radio"
                                            :aria-checked="
                                                demoWeightIndex === idx
                                                    ? 'true'
                                                    : 'false'
                                            "
                                            @click="selectDemoWeight(idx)"
                                        >
                                            <span class="kit-pick__name">{{
                                                level.label
                                            }}</span>
                                            <span class="kit-pick__score"
                                                >×{{
                                                    formatPlain(level.score)
                                                }}</span
                                            >
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="kit.formula.progress === 'on'"
                                    class="kit-study__group"
                                >
                                    <p
                                        class="kit-study__label"
                                        id="kit-study-progress"
                                    >
                                        Tiến độ
                                    </p>
                                    <div
                                        class="kit-picks kit-picks--project kit-picks--six"
                                        role="radiogroup"
                                        aria-labelledby="kit-study-progress"
                                    >
                                        <button
                                            v-for="(
                                                level, idx
                                            ) in kit.progress_levels"
                                            :key="`pick-p-${idx}`"
                                            type="button"
                                            class="kit-pick"
                                            :class="{
                                                'kit-pick--on':
                                                    demoProgressIndex === idx,
                                            }"
                                            role="radio"
                                            :aria-checked="
                                                demoProgressIndex === idx
                                                    ? 'true'
                                                    : 'false'
                                            "
                                            @click="selectDemoProgress(idx)"
                                        >
                                            <span class="kit-pick__name">{{
                                                level.label
                                            }}</span>
                                            <span class="kit-pick__score"
                                                >×{{
                                                    formatPlain(level.score)
                                                }}</span
                                            >
                                        </button>
                                    </div>
                                </div>

                                <div
                                    v-if="kit.formula.quality === 'on'"
                                    class="kit-study__group"
                                >
                                    <p
                                        class="kit-study__label"
                                        id="kit-study-quality"
                                    >
                                        Chất lượng
                                    </p>
                                    <div
                                        class="kit-picks kit-picks--quality"
                                        role="radiogroup"
                                        aria-labelledby="kit-study-quality"
                                    >
                                        <button
                                            v-for="(
                                                level, idx
                                            ) in kit.quality_levels"
                                            :key="`pick-q-${idx}`"
                                            type="button"
                                            class="kit-pick"
                                            :class="{
                                                'kit-pick--on':
                                                    demoQualityIndex === idx,
                                            }"
                                            role="radio"
                                            :aria-checked="
                                                demoQualityIndex === idx
                                                    ? 'true'
                                                    : 'false'
                                            "
                                            @click="selectDemoQuality(idx)"
                                        >
                                            <span class="kit-pick__name">{{
                                                level.label
                                            }}</span>
                                            <span class="kit-pick__score"
                                                >×{{
                                                    formatPlain(level.score)
                                                }}</span
                                            >
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="kit-eq" aria-live="polite">
                                <span class="kit-eq__term kit-eq__term--gold">
                                    <span class="kit-eq__n">{{
                                        formatPlain(demoStandard)
                                    }}</span>
                                    <span class="kit-eq__k"
                                        >chuẩn ·
                                        {{ formatPlain(demoTaskBase) }} ×
                                        {{
                                            formatPlain(demoWeightFactor)
                                        }}</span
                                    >
                                </span>
                                <span class="kit-eq__op">→</span>
                                <span class="kit-eq__term kit-eq__term--teal">
                                    <span class="kit-eq__n">{{
                                        formatPlain(demoActual)
                                    }}</span>
                                    <span class="kit-eq__k"
                                        >thực · ×{{
                                            formatPlain(demoProgressFactor)
                                        }}
                                        ×{{
                                            formatPlain(demoQualityFactor)
                                        }}</span
                                    >
                                </span>
                                <span class="kit-eq__op">=</span>
                                <span class="kit-eq__term kit-eq__term--out">
                                    <span class="kit-eq__n"
                                        >{{
                                            formatPlain(demoTaskPercent)
                                        }}%</span
                                    >
                                    <span class="kit-eq__k">
                                        {{ formatPlain(demoActual) }} /
                                        {{ formatPlain(demoStandard) }}
                                        <template v-if="demoQualityBonus">
                                            · +{{
                                                formatPlain(demoQualityBonus)
                                            }}% bonus →
                                            {{
                                                formatPlain(demoRatedPercent)
                                            }}%</template
                                        >
                                        ·
                                        {{
                                            demoTaskRank?.label ||
                                            "chưa xếp loại"
                                        }}
                                    </span>
                                </span>
                            </div>
                        </div>
                    </section>

                    <section
                        class="kit-col"
                        aria-label="Thang xếp loại và hệ số"
                    >
                        <div class="kit-panel">
                            <div
                                class="kit-panel__head kit-panel__head--scale"
                            >
                                <div class="kit-panel__heading">
                                    <h2 class="kit-panel__title">
                                        Thang xếp loại của phòng
                                    </h2>
                                    <div class="kit-source">
                                        <select
                                            class="kit-source__select"
                                            :value="
                                                kit.classification_criterion_id ??
                                                ''
                                            "
                                            :disabled="!canManage"
                                            aria-label="Tiêu chí nguồn thang xếp loại"
                                            @change="
                                                applyClassificationCriterion(
                                                    $event.target.value,
                                                )
                                            "
                                        >
                                            <option value="" disabled>
                                                Mặc định hệ thống
                                            </option>
                                            <option
                                                v-for="criterion in classificationCriteria"
                                                :key="`performance-${criterion.id}`"
                                                :value="criterion.id"
                                            >
                                                {{ criterion.name }}
                                            </option>
                                        </select>
                                        <div class="kit-source__actions">
                                            <a
                                                class="kit-source__action"
                                                href="/manager/evaluation"
                                                >Mở Tiêu chí</a
                                            >
                                            <button
                                                type="button"
                                                class="kit-source__action kit-source__action--reset"
                                                :disabled="
                                                    !canManage ||
                                                    kit.classification_use_default
                                                "
                                                @click="
                                                    restoreClassificationDefaults
                                                "
                                            >
                                                <AppIcon
                                                    name="refresh"
                                                    :size="13"
                                                    :stroke-width="1.75"
                                                />
                                                Khôi phục mặc định
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="kit-levels">
                                <div class="kit-levels__head">
                                    <span>Mã</span>
                                    <span>Tên mức</span>
                                    <span>Từ %</span>
                                </div>
                                <div
                                    v-for="(
                                        level, idx
                                    ) in kit.performance_levels"
                                    :key="`perf-${idx}`"
                                    class="kit-levels__row"
                                    :class="{
                                        'kit-levels__row--on':
                                            isTaskRank(level),
                                    }"
                                >
                                    <input
                                        v-model="level.code"
                                        type="text"
                                        maxlength="8"
                                        placeholder="VD: XS"
                                        class="kit-field"
                                        :disabled="
                                            !canManage ||
                                            classificationFromCriterion
                                        "
                                        :aria-label="`Mã xếp loại ${idx + 1}`"
                                        @input="scheduleSaveKit"
                                    />
                                    <input
                                        v-model="level.label"
                                        type="text"
                                        maxlength="80"
                                        placeholder="VD: Xuất sắc"
                                        class="kit-field"
                                        :disabled="
                                            !canManage ||
                                            classificationFromCriterion
                                        "
                                        :aria-label="`Tên xếp loại ${idx + 1}`"
                                        @input="scheduleSaveKit"
                                    />
                                    <div class="kit-stepper">
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            :disabled="
                                                !canManage ||
                                                classificationFromCriterion
                                            "
                                            :aria-label="`Giảm mốc ${level.label}`"
                                            @click="
                                                bumpLevelScore(
                                                    kit.performance_levels,
                                                    idx,
                                                    -1,
                                                    0,
                                                    9999,
                                                )
                                            "
                                        >
                                            <AppIcon
                                                name="minus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                        <input
                                            v-model.number="level.score"
                                            type="number"
                                            min="0"
                                            max="9999"
                                            step="1"
                                            placeholder="VD: 100"
                                            class="kit-stepper__input"
                                            :disabled="
                                                !canManage ||
                                                classificationFromCriterion
                                            "
                                            :aria-label="`Mốc phần trăm ${level.label}`"
                                            @input="scheduleSaveKit"
                                        />
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            :disabled="
                                                !canManage ||
                                                classificationFromCriterion
                                            "
                                            :aria-label="`Tăng mốc ${level.label}`"
                                            @click="
                                                bumpLevelScore(
                                                    kit.performance_levels,
                                                    idx,
                                                    1,
                                                    0,
                                                    9999,
                                                )
                                            "
                                        >
                                            <AppIcon
                                                name="plus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="kit-panel kit-panel--secondary">
                            <div
                                class="kit-scale-tabs"
                                role="tablist"
                                aria-label="Chọn thang hệ số để sửa"
                            >
                                <button
                                    v-for="tab in SCALE_TABS"
                                    :key="tab.id"
                                    type="button"
                                    class="kit-scale-tab"
                                    :class="{
                                        'kit-scale-tab--on':
                                            scaleView === tab.id,
                                    }"
                                    role="tab"
                                    :aria-selected="
                                        scaleView === tab.id ? 'true' : 'false'
                                    "
                                    @click="scaleView = tab.id"
                                >
                                    {{ tab.title }}
                                </button>
                            </div>
                            <div
                                class="kit-panel__head kit-panel__head--scale"
                            >
                                <div class="kit-panel__heading">
                                    <h2 class="kit-panel__title">
                                        {{ activeScale.title }}
                                    </h2>
                                    <div class="kit-source">
                                        <select
                                            class="kit-source__select"
                                            :value="
                                                kit[
                                                    activeScale.criterionIdKey
                                                ] ?? ''
                                            "
                                            :disabled="!canManage"
                                            :aria-label="`Tiêu chí nguồn ${activeScale.title}`"
                                            @change="
                                                applyActiveScaleCriterion(
                                                    $event.target.value,
                                                )
                                            "
                                        >
                                            <option value="" disabled>
                                                Mặc định hệ thống
                                            </option>
                                            <option
                                                v-for="criterion in scaleCriteria"
                                                :key="`${activeScale.key}-${criterion.id}`"
                                                :value="criterion.id"
                                            >
                                                {{ criterion.name }}
                                            </option>
                                        </select>
                                        <div class="kit-source__actions">
                                            <a
                                                class="kit-source__action"
                                                href="/manager/evaluation"
                                                >Mở Tiêu chí</a
                                            >
                                            <button
                                                type="button"
                                                class="kit-source__action kit-source__action--reset"
                                                :disabled="
                                                    !canManage ||
                                                    kit[
                                                        activeScale
                                                            .useDefaultKey
                                                    ]
                                                "
                                                @click="
                                                    restoreActiveScaleDefaults
                                                "
                                            >
                                                <AppIcon
                                                    name="refresh"
                                                    :size="13"
                                                    :stroke-width="1.75"
                                                />
                                                Khôi phục mặc định
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <label
                                v-if="scaleView === 'quality'"
                                class="kit-bonus"
                                for="kit-quality-bonus"
                            >
                                <span class="kit-bonus__name"
                                    >Bonus xuất sắc</span
                                >
                                <div class="kit-stepper">
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Giảm bonus xuất sắc"
                                        @click="
                                            bumpKit(
                                                'quality_bonus_percent',
                                                -1,
                                                0,
                                                100,
                                            )
                                        "
                                    >
                                        <AppIcon
                                            name="minus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                    <input
                                        id="kit-quality-bonus"
                                        v-model.number="
                                            kit.quality_bonus_percent
                                        "
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="1"
                                        placeholder="VD: 5"
                                        class="kit-stepper__input"
                                        :disabled="!canManage"
                                        @input="scheduleSaveKit"
                                    />
                                    <button
                                        type="button"
                                        class="kit-stepper__btn"
                                        :disabled="!canManage"
                                        aria-label="Tăng bonus xuất sắc"
                                        @click="
                                            bumpKit(
                                                'quality_bonus_percent',
                                                1,
                                                0,
                                                100,
                                            )
                                        "
                                    >
                                        <AppIcon
                                            name="plus"
                                            :size="14"
                                            :stroke-width="2"
                                        />
                                    </button>
                                </div>
                            </label>
                            <div class="kit-levels">
                                <div class="kit-levels__head">
                                    <span>Mã</span>
                                    <span>Tên mức</span>
                                    <span>Hệ số</span>
                                </div>
                                <div
                                    v-for="(level, idx) in activeScale.levels"
                                    :key="`${activeScale.key}-${idx}`"
                                    class="kit-levels__row"
                                    :class="{
                                        'kit-levels__row--weight':
                                            activeScale.highlight === idx,
                                    }"
                                >
                                    <input
                                        v-model="level.code"
                                        type="text"
                                        maxlength="8"
                                        placeholder="VD: KH"
                                        class="kit-field"
                                        :disabled="
                                            !canManage ||
                                            activeScaleFromCriterion
                                        "
                                        :aria-label="`Mã mức ${idx + 1}`"
                                        @input="scheduleSaveKit"
                                    />
                                    <input
                                        v-model="level.label"
                                        type="text"
                                        maxlength="80"
                                        placeholder="VD: Khó"
                                        class="kit-field"
                                        :disabled="
                                            !canManage ||
                                            activeScaleFromCriterion
                                        "
                                        :aria-label="`Tên mức ${idx + 1}`"
                                        @input="scheduleSaveKit"
                                    />
                                    <div class="kit-stepper">
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            :disabled="
                                                !canManage ||
                                                activeScaleFromCriterion
                                            "
                                            :aria-label="`Giảm ${level.label}`"
                                            @click="
                                                bumpActiveScale(
                                                    idx,
                                                    -activeScale.scoreStep,
                                                )
                                            "
                                        >
                                            <AppIcon
                                                name="minus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                        <input
                                            v-model.number="level.score"
                                            type="number"
                                            :min="activeScale.scoreMin"
                                            :max="activeScale.scoreMax"
                                            :step="activeScale.scoreStep"
                                            placeholder="VD: 1.2"
                                            class="kit-stepper__input"
                                            :disabled="
                                                !canManage ||
                                                activeScaleFromCriterion
                                            "
                                            :aria-label="`Hệ số ${level.label}`"
                                            @input="scheduleSaveKit"
                                        />
                                        <button
                                            type="button"
                                            class="kit-stepper__btn"
                                            :disabled="
                                                !canManage ||
                                                activeScaleFromCriterion
                                            "
                                            :aria-label="`Tăng ${level.label}`"
                                            @click="
                                                bumpActiveScale(
                                                    idx,
                                                    activeScale.scoreStep,
                                                )
                                            "
                                        >
                                            <AppIcon
                                                name="plus"
                                                :size="14"
                                                :stroke-width="2"
                                            />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </template>
        </div>

        <footer
            v-if="canManage && hasDepartment && !loading"
            class="kit-dock"
            :class="{ 'kit-dock--dirty': dirty && !savingKit }"
        >
            <div class="kit-save-state" aria-live="polite">
                <template v-if="savingKit">Đang lưu…</template>
                <template v-else-if="dirty">
                    <span class="kit-save-state__lead"
                        >Có thay đổi chưa lưu — bấm Lưu để áp dụng cho phòng
                        ban</span
                    >
                    <span class="kit-save-state__detail">{{
                        changeSummaryText
                    }}</span>
                    <span class="kit-save-state__note"
                        >Rời trang khi chưa lưu sẽ hỏi xác nhận.</span
                    >
                </template>
                <template v-else-if="savedFlash">Đã lưu</template>
            </div>
            <button
                type="button"
                class="kit-header-btn kit-header-btn--primary"
                :disabled="!dirty || savingKit"
                @click="saveNow"
            >
                <AppIcon name="check" :size="15" :stroke-width="2" />
                Lưu
            </button>
        </footer>

        <ConfirmDialog
            v-model:open="resetOpen"
            title="Khôi phục thiết lập mặc định?"
            :description="`Các giá trị của cách “${currentModeTitle}” sẽ được đưa về mặc định và lưu ngay.`"
            confirm-label="Khôi phục"
            :loading="savingKit"
            @confirm="confirmReset"
        />

        <Teleport to="body">
            <Transition name="kit-changes-fade">
                <div
                    v-if="changesDialogOpen"
                    class="kit-changes-dialog"
                    role="presentation"
                    @mousedown.self="closeChangesDialog"
                >
                    <div
                        class="kit-changes-dialog__panel"
                        role="alertdialog"
                        aria-modal="true"
                        :aria-label="
                            changesDialogKind === 'leave'
                                ? 'Rời trang khi chưa lưu'
                                : 'Lưu các thay đổi'
                        "
                    >
                        <header class="kit-changes-dialog__head">
                            <h2 class="kit-changes-dialog__title">
                                {{
                                    changesDialogKind === "leave"
                                        ? "Rời trang khi chưa lưu?"
                                        : "Lưu các thay đổi?"
                                }}
                            </h2>
                            <p class="kit-changes-dialog__lead">
                                {{
                                    changesDialogKind === "leave"
                                        ? "Bản nháp sẽ mất nếu bạn rời mà không lưu."
                                        : "Xác nhận trước khi áp dụng khung chấm điểm cho phòng ban."
                                }}
                            </p>
                        </header>

                        <div class="kit-changes-dialog__body hide-scrollbar">
                            <dl class="kit-changes-dialog__grid">
                                <div class="kit-changes-dialog__field">
                                    <dt>Phòng ban</dt>
                                    <dd>{{ departmentName }}</dd>
                                </div>
                                <div class="kit-changes-dialog__field">
                                    <dt>Cách tính</dt>
                                    <dd>{{ savedModeTitle }}</dd>
                                </div>
                                <div class="kit-changes-dialog__field">
                                    <dt>Số mục thay đổi</dt>
                                    <dd>
                                        {{
                                            changedFieldCount ||
                                            "chưa phân loại"
                                        }}
                                    </dd>
                                </div>
                                <div
                                    class="kit-changes-dialog__field kit-changes-dialog__field--wide"
                                >
                                    <dt>Nội dung thay đổi</dt>
                                    <dd>
                                        <ul
                                            v-if="changedFieldLabels.length"
                                            class="kit-changes-dialog__list"
                                        >
                                            <li
                                                v-for="(
                                                    label, idx
                                                ) in changedFieldLabels"
                                                :key="`chg-${idx}`"
                                            >
                                                {{ label }}
                                            </li>
                                        </ul>
                                        <span v-else>{{
                                            changeSummaryText
                                        }}</span>
                                    </dd>
                                </div>
                                <div
                                    class="kit-changes-dialog__field kit-changes-dialog__field--wide"
                                >
                                    <dt>Nhật ký hoạt động</dt>
                                    <dd>
                                        Hệ thống ghi chi tiết trước / sau cho
                                        từng mục khi lưu.
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <footer class="kit-changes-dialog__actions">
                            <button
                                type="button"
                                class="kit-changes-dialog__btn kit-changes-dialog__btn--ghost"
                                :disabled="savingKit"
                                @click="closeChangesDialog"
                            >
                                {{
                                    changesDialogKind === "leave"
                                        ? "Ở lại"
                                        : "Huỷ"
                                }}
                            </button>
                            <button
                                v-if="changesDialogKind === 'leave'"
                                type="button"
                                class="kit-changes-dialog__btn kit-changes-dialog__btn--danger"
                                :disabled="savingKit"
                                @click="confirmChangesDiscard"
                            >
                                Rời và bỏ thay đổi
                            </button>
                            <button
                                type="button"
                                class="kit-changes-dialog__btn kit-changes-dialog__btn--primary"
                                :disabled="savingKit"
                                @click="confirmChangesSave"
                            >
                                {{
                                    savingKit
                                        ? "Đang lưu…"
                                        : changesDialogKind === "leave"
                                          ? "Lưu rồi rời"
                                          : "Lưu thay đổi"
                                }}
                            </button>
                        </footer>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <ConfirmDialog
            v-model:open="modeConfirmOpen"
            title="Đổi cách tính điểm?"
            :description="
                dirty
                    ? `Cách tính mới cùng bản nháp (${changeSummaryText}) sẽ được áp dụng cho phòng ban và ghi nhật ký chi tiết.`
                    : 'Cách tính mới sẽ được áp dụng cho phòng ban và ghi vào nhật ký hoạt động.'
            "
            confirm-label="Đổi cách tính"
            :loading="savingKit"
            @confirm="confirmModeChange"
        />
    </section>
</template>

<style scoped>
.kit-wrap {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.kit-header-btn {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    height: 2rem;
    padding: 0 0.75rem;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-text);
    font-family: var(--font-family-base);
    font-size: 0.8125rem;
    font-weight: 400;
    box-shadow:
        inset 0 0 0 1px var(--color-border),
        var(--shadow-sm);
    cursor: pointer;
}

.kit-header-btn:hover:not(:disabled) {
    background: var(--color-surface-muted);
}

.kit-header-btn--primary {
    background: var(--color-primary);
    color: var(--color-on-primary);
    box-shadow: none;
}

.kit-header-btn--primary:hover:not(:disabled) {
    background: var(--color-primary-hover);
}

.kit-header-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.kit-header-btn__spin {
    animation: kit-spin 0.8s linear infinite;
}

.kit-dock {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    min-height: 3rem;
    padding: var(--space-2) var(--space-4);
    background: var(--color-surface);
    box-shadow: 0 -1px 0
        color-mix(in srgb, var(--color-border) 75%, transparent);
}

.kit-dock--dirty {
    position: relative;
    padding-left: calc(var(--space-4) + 3px + var(--space-2));
    background: var(--color-gold-surface);
    box-shadow:
        0 -1px 0 var(--color-gold),
        inset 0 1px 0 color-mix(in srgb, var(--color-gold) 35%, transparent);
}

.kit-dock--dirty::before {
    content: "";
    position: absolute;
    top: var(--space-2);
    bottom: var(--space-2);
    left: var(--space-2);
    width: 3px;
    background: var(--color-gold);
}

.kit-dock--dirty .kit-save-state__lead {
    color: var(--color-gold-800);
    font-size: 0.875rem;
}

.kit-dock--dirty .kit-save-state__note {
    display: block;
    margin-top: 0.2rem;
    color: var(--color-gold-800);
    font-size: 0.75rem;
    font-style: italic;
    opacity: 0.9;
}

.kit-save-state {
    min-width: 0;
    flex: 1;
    min-height: 1.25rem;
    color: var(--color-text-muted);
    font-size: 0.8125rem;
    font-weight: 400;
}

.kit-save-state__lead {
    display: block;
    color: var(--color-gold-800);
}

.kit-save-state__detail {
    display: block;
    margin-top: 0.15rem;
    font-style: italic;
    line-height: 1.35;
}

.kit-save-state--pending {
    color: var(--color-gold-800);
}

.kit-save-state--saved {
    color: var(--color-success);
}

@keyframes kit-spin {
    to {
        transform: rotate(360deg);
    }
}

.kit-tabs {
    flex-shrink: 0;
    display: flex;
    align-items: stretch;
    gap: var(--space-1);
    min-height: 2.75rem;
    padding: 0 var(--space-4);
    box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.kit-tab {
    display: inline-flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    gap: var(--space-2);
    min-width: 0;
    padding: 0.45rem var(--space-3);
    border: none;
    background: transparent;
    color: var(--color-text-muted);
    font-family: var(--font-family-base);
    text-align: left;
    cursor: pointer;
    box-shadow: 0 2px 0 transparent;
}

.kit-tab:hover {
    color: var(--color-text);
}

.kit-tab--on {
    color: var(--color-primary);
    box-shadow: 0 2px 0 var(--color-primary);
}

.kit-tab__copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.05rem;
}

.kit-tab__title {
    font-size: 0.8125rem;
    font-weight: 400;
}

.kit-tab__lead {
    font-size: 0.6875rem;
    font-weight: 400;
    opacity: 0.8;
}

.kit-page {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow-x: hidden;
    overflow-y: auto;
    padding: var(--space-3) var(--space-4) var(--space-4);
}

.kit-banner {
    margin: 0;
    padding: var(--space-4);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    color: var(--color-text-muted);
    font-size: 0.8125rem;
    font-weight: 400;
    line-height: 1.45;
    box-shadow: var(--shadow-sm);
}

.kit-changes-dialog {
    position: fixed;
    inset: 0;
    z-index: 300;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-5);
    background: color-mix(in srgb, #000000 45%, transparent);
}

.kit-changes-dialog__panel {
    display: flex;
    flex-direction: column;
    width: min(44rem, calc(100vw - 2.5rem));
    max-height: calc(100vh - 2.5rem);
    overflow: hidden;
    border-radius: var(--radius-lg);
    background: var(--color-surface);
    box-shadow: var(--shadow-lg);
}

.kit-changes-dialog__head {
    flex-shrink: 0;
    padding: var(--space-4) var(--space-5) var(--space-3);
    box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.kit-changes-dialog__title {
    margin: 0;
    font-size: 1.0625rem;
    font-weight: 700;
    color: var(--color-text);
}

.kit-changes-dialog__lead {
    margin: var(--space-2) 0 0;
    color: var(--color-text-muted);
    font-size: 0.8125rem;
    font-weight: 400;
    line-height: 1.45;
}

.kit-changes-dialog__body {
    flex: 1;
    min-height: 0;
    overflow: auto;
    padding: var(--space-4) var(--space-5);
}

.kit-changes-dialog__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: var(--space-3) var(--space-4);
    margin: 0;
}

.kit-changes-dialog__field {
    min-width: 0;
    margin: 0;
}

.kit-changes-dialog__field--wide {
    grid-column: 1 / -1;
}

.kit-changes-dialog__field dt {
    margin: 0 0 0.2rem;
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-weight: 400;
}

.kit-changes-dialog__field dt::after {
    content: ":";
}

.kit-changes-dialog__field dd {
    margin: 0;
    color: var(--color-text);
    font-size: 0.8125rem;
    font-style: italic;
    font-weight: 400;
    line-height: 1.45;
}

.kit-changes-dialog__list {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.35rem var(--space-3);
}

.kit-changes-dialog__list li {
    min-width: 0;
}

.kit-changes-dialog__actions {
    flex-shrink: 0;
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-5) var(--space-4);
    box-shadow: 0 -1px 0 color-mix(in srgb, var(--color-border) 75%, transparent);
}

.kit-changes-dialog__btn {
    padding: var(--space-2) var(--space-4);
    border: 1px solid transparent;
    border-radius: var(--radius-md);
    font-family: var(--font-family-base);
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
}

.kit-changes-dialog__btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.kit-changes-dialog__btn--ghost {
    border-color: var(--color-border);
    background: var(--color-surface);
    color: var(--color-text);
}

.kit-changes-dialog__btn--ghost:hover:not(:disabled) {
    background: var(--color-surface-muted);
}

.kit-changes-dialog__btn--primary {
    background: var(--color-primary);
    color: var(--color-on-primary);
}

.kit-changes-dialog__btn--primary:hover:not(:disabled) {
    background: var(--color-primary-hover);
}

.kit-changes-dialog__btn--danger {
    background: var(--color-danger);
    color: var(--color-on-primary);
}

.kit-changes-dialog__btn--danger:hover:not(:disabled) {
    filter: brightness(0.95);
}

.kit-changes-fade-enter-active,
.kit-changes-fade-leave-active {
    transition: opacity 0.15s ease;
}

.kit-changes-fade-enter-from,
.kit-changes-fade-leave-to {
    opacity: 0;
}

.kit-formula {
    position: relative;
    flex-shrink: 0;
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: start;
    gap: 0.2rem 0.75rem;
    margin-bottom: var(--space-3);
    padding: var(--space-3);
    padding-left: calc(var(--space-2) + 3px + var(--space-3));
    border-radius: var(--radius-md);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
}

.kit-formula::before {
    content: "";
    position: absolute;
    top: var(--space-2);
    bottom: var(--space-2);
    left: var(--space-2);
    width: 3px;
    border-radius: 0;
    background: var(--color-primary);
}

.kit-formula--weight::before {
    background: var(--color-secondary);
}

.kit-formula__title {
    margin: 0;
    padding-top: 0.1rem;
    color: var(--color-text-muted);
    font-size: 0.8125rem;
    font-weight: 400;
}

.kit-formula__title::after {
    content: ":";
}

.kit-formula__body {
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}

.kit-formula__method,
.kit-formula__applied {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 400;
    line-height: 1.45;
}

.kit-formula__method {
    color: var(--color-text);
}

.kit-formula__applied {
    color: var(--color-text-muted);
    font-style: italic;
    font-variant-numeric: tabular-nums;
}

.kit-split {
    flex: 0 0 auto;
    min-height: 0;
    display: grid;
    grid-template-columns: minmax(0, 11fr) minmax(0, 9fr);
    align-items: start;
    gap: 0.625rem;
}

.kit-split--task {
    grid-template-columns: minmax(0, 13fr) minmax(0, 7fr);
}

.kit-col {
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
}

.kit-tiles {
    flex-shrink: 0;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.625rem;
}

.kit-tiles--pair,
.kit-tiles--quad {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.kit-tile {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
    padding: var(--space-3);
    padding-left: calc(var(--space-2) + 3px + var(--space-3));
    border-radius: var(--radius-md);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
}

.kit-tile::before {
    content: "";
    position: absolute;
    top: var(--space-2);
    bottom: var(--space-2);
    left: var(--space-2);
    width: 3px;
    border-radius: 0;
}

.kit-tile--base {
    background: var(--color-gold-surface);
}

.kit-tile--base::before {
    background: var(--color-gold);
}

.kit-tile--done {
    background: var(--color-success-tint-bg);
}

.kit-tile--done::before {
    background: var(--color-success);
}

.kit-tile--undone {
    background: var(--color-danger-tint-bg);
}

.kit-tile--undone::before {
    background: var(--color-danger);
}

.kit-tile--gold {
    background: var(--color-gold-surface);
}

.kit-tile--gold::before {
    background: var(--color-gold);
}

.kit-tile--teal {
    background: var(--color-secondary-surface);
}

.kit-tile--teal::before {
    background: var(--color-secondary);
}

.kit-tile--off {
    background: var(--color-surface);
}

.kit-tile--off::before {
    background: var(--color-border);
}

.kit-tile--off .kit-tile__name {
    color: var(--color-text-muted);
}

.kit-tile--switch {
    width: 100%;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    border: none;
    color: inherit;
    font-family: var(--font-family-base);
    text-align: left;
    cursor: pointer;
}

.kit-tile--switch:hover:not(:disabled).kit-tile--gold {
    background: var(--color-gold-surface-strong);
}

.kit-tile--switch:hover:not(:disabled).kit-tile--teal {
    background: var(--color-secondary-surface-strong);
}

.kit-tile--switch:hover:not(:disabled).kit-tile--off {
    background: var(--color-surface-muted);
}

.kit-tile--switch:disabled {
    cursor: not-allowed;
}

.kit-tile--switch:focus-visible {
    outline: none;
    box-shadow:
        var(--shadow-sm),
        inset 0 0 0 1.5px var(--color-primary);
}

.kit-tile__copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.2rem;
}

.kit-tile__name {
    margin: 0;
    color: var(--color-text);
    font-size: 0.8125rem;
    font-weight: 400;
}

.kit-tile__phrase {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-weight: 400;
    font-style: italic;
}

.kit-tile__phrase--btn {
    padding: 0;
    border: none;
    background: transparent;
    font-family: inherit;
    text-align: left;
    cursor: pointer;
}

.kit-tile__phrase--btn:hover:not(:disabled) {
    color: var(--color-text);
}

.kit-tile__phrase--btn:disabled {
    cursor: not-allowed;
}

.kit-stepper {
    display: grid;
    grid-template-columns: 1.75rem minmax(0, 1fr) 1.75rem;
    align-items: center;
    gap: 0.35rem;
}

.kit-stepper__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    padding: 0;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-text);
    box-shadow: inset 0 0 0 1px var(--color-border);
    cursor: pointer;
}

.kit-stepper__btn:hover:not(:disabled) {
    background: var(--color-surface-muted);
}

.kit-stepper__btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.kit-stepper__input,
.kit-field {
    width: 100%;
    height: 2rem;
    padding: 0 0.4rem;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-text);
    font-family: var(--font-family-base);
    font-size: 0.8125rem;
    font-weight: 400;
    box-shadow: inset 0 0 0 1px var(--color-border);
}

.kit-stepper__input {
    font-variant-numeric: tabular-nums;
    text-align: center;
    appearance: textfield;
}

.kit-stepper__input::-webkit-outer-spin-button,
.kit-stepper__input::-webkit-inner-spin-button {
    margin: 0;
    appearance: none;
}

.kit-stepper__input:focus,
.kit-field:focus {
    outline: none;
    box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.kit-stepper__input::placeholder,
.kit-field::placeholder {
    color: var(--color-text-muted);
    opacity: 0.72;
}

.kit-stepper__input:disabled,
.kit-field:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.kit-panel {
    position: relative;
    flex: 0 0 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
    padding: var(--space-3);
    padding-left: calc(var(--space-2) + 3px + var(--space-3));
    border-radius: var(--radius-md);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}

.kit-panel::before {
    content: "";
    position: absolute;
    top: var(--space-2);
    bottom: var(--space-2);
    left: var(--space-2);
    width: 3px;
    border-radius: 0;
    background: var(--color-primary);
}

.kit-panel--story::before {
    background: var(--color-tertiary);
}

.kit-panel--secondary::before {
    background: var(--color-secondary);
}

.kit-panel__head {
    flex-shrink: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2);
}

.kit-panel__head--scale {
    flex-wrap: nowrap;
    align-items: flex-start;
}

.kit-panel__heading {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 0.4rem;
}

.kit-panel__title {
    margin: 0;
    font-size: 0.8125rem;
    font-weight: 400;
}

.kit-panel__lead {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-weight: 400;
}

.kit-panel__head--scale .kit-panel__title {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.25;
}

.kit-source {
    display: grid;
    grid-template-columns: minmax(9rem, 1fr) auto;
    width: 100%;
    min-width: 0;
    align-items: center;
    gap: 0.4rem;
}

.kit-source__select {
    min-width: 0;
    height: 2rem;
    padding: 0 var(--space-2);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    color: var(--color-text);
    font: inherit;
    font-size: 0.75rem;
    text-overflow: ellipsis;
}

.kit-source__select:focus {
    outline: none;
    box-shadow: inset 0 0 0 1px var(--color-primary);
}

.kit-source__select:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

.kit-source__actions {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
}

.kit-source__action {
    display: inline-flex;
    height: 2rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    padding: 0 0.6rem;
    border: 0;
    border-radius: var(--radius-sm);
    background: var(--color-primary-surface);
    color: var(--color-primary);
    font-family: var(--font-family-base);
    font-size: 0.75rem;
    line-height: 1;
    text-decoration: none;
    white-space: nowrap;
    cursor: pointer;
}

.kit-source__action:hover:not(:disabled) {
    background: color-mix(
        in srgb,
        var(--color-primary) 12%,
        var(--color-surface)
    );
}

.kit-source__action--reset {
    background: var(--color-surface-muted);
    color: var(--color-text);
    box-shadow: inset 0 0 0 1px var(--color-border);
}

.kit-source__action:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.kit-demo {
    flex-shrink: 0;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.625rem;
}

.kit-demo__field {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.35rem;
    padding: var(--space-2);
    border-radius: var(--radius-sm);
}

.kit-demo__field--done {
    background: var(--color-success-tint-bg);
}

.kit-demo__field--undone {
    background: var(--color-danger-tint-bg);
}

.kit-demo__label {
    font-size: 0.75rem;
    font-weight: 400;
}

.kit-story {
    flex: 0 0 auto;
    min-height: 0;
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
}

.kit-story__row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: var(--space-2);
    padding: 0.4rem 0;
    color: var(--color-text);
    font-size: 0.8125rem;
    font-weight: 400;
    box-shadow: 0 1px 0 color-mix(in srgb, var(--color-border) 55%, transparent);
}

.kit-story__row:last-child {
    box-shadow: none;
}

.kit-story__v {
    font-variant-numeric: tabular-nums;
}

.kit-story__v--base {
    color: var(--color-gold-700);
}

.kit-story__v--pos {
    color: var(--color-success-tint-fg);
}

.kit-story__v--neg {
    color: var(--color-danger-tint-fg);
}

.kit-story__v--zero {
    color: var(--color-text-muted);
}

.kit-result {
    flex-shrink: 0;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-2);
    padding: 0.55rem 0.75rem;
    border-radius: var(--radius-sm);
    background: var(--color-primary-surface);
}

.kit-result__score {
    color: var(--color-primary);
    font-size: 0.9375rem;
    font-variant-numeric: tabular-nums;
    font-weight: 400;
}

.kit-result__rank {
    color: var(--color-gold-800);
    font-size: 0.8125rem;
    font-weight: 400;
}

.kit-levels {
    flex: 0 0 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
}

.kit-levels__head,
.kit-levels__row {
    display: grid;
    grid-template-columns: 3.75rem minmax(0, 1fr) 8rem;
    gap: var(--space-2);
    align-items: center;
}

.kit-levels--class .kit-levels__head,
.kit-levels--class .kit-levels__row {
    grid-template-columns: 3.75rem minmax(0, 1fr) 8rem 6.25rem;
}

.kit-levels__ops {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.2rem;
}

.kit-levels__head {
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-weight: 400;
}

.kit-levels__row {
    min-height: 2.5rem;
    padding: 0.25rem 0.35rem;
    border-radius: var(--radius-sm);
    background: var(--color-surface-muted);
}

.kit-levels__row--on {
    background: var(--color-gold-surface);
    box-shadow: inset 0 0 0 1px var(--color-gold);
}

.kit-levels__row--weight {
    background: var(--color-secondary-surface);
    box-shadow: inset 0 0 0 1px var(--color-secondary);
}

.kit-cases {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.kit-case {
    position: relative;
    display: flex;
    min-width: 0;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: space-between;
    gap: 0.2rem var(--space-2);
    padding: 0.5rem 0.65rem;
    padding-left: calc(var(--space-2) + 3px + 0.55rem);
    border: none;
    border-radius: var(--radius-sm);
    background: var(--color-surface-muted);
    color: var(--color-text);
    font-family: var(--font-family-base);
    text-align: left;
    cursor: pointer;
}

.kit-case::before {
    content: "";
    position: absolute;
    top: var(--space-2);
    bottom: var(--space-2);
    left: var(--space-2);
    width: 3px;
    border-radius: 0;
    background: var(--color-border);
}

.kit-case:hover:not(.kit-case--on) {
    background: var(--color-tertiary-surface);
}

.kit-case--on {
    background: var(--color-tertiary-surface);
}

.kit-case--on::before {
    background: var(--color-tertiary);
}

.kit-case:focus-visible {
    outline: none;
    box-shadow: inset 0 0 0 1.5px var(--color-tertiary);
}

.kit-case__task {
    font-size: 0.75rem;
    font-weight: 400;
    line-height: 1.35;
}

.kit-case__project {
    color: var(--color-text-muted);
    font-size: 0.6875rem;
    font-style: italic;
    line-height: 1.3;
}

.kit-study {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
}

.kit-study__group {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.4rem;
}

.kit-study__label {
    margin: 0;
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-weight: 400;
}

.kit-study__label::after {
    content: ":";
}

.kit-contrib {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
    width: 100%;
    padding: var(--space-3);
    padding-left: calc(var(--space-2) + 3px + var(--space-3));
    border: none;
    border-radius: var(--radius-md);
    background: var(--color-surface);
    color: inherit;
    font-family: var(--font-family-base);
    text-align: left;
    cursor: pointer;
    box-shadow: var(--shadow-sm);
    position: relative;
}

.kit-contrib::before {
    content: "";
    position: absolute;
    top: var(--space-2);
    bottom: var(--space-2);
    left: var(--space-2);
    width: 3px;
    border-radius: 0;
    background: var(--color-border);
}

.kit-contrib--on {
    background: var(--color-secondary-surface);
}

.kit-contrib--on::before {
    background: var(--color-secondary);
}

.kit-contrib:disabled {
    cursor: not-allowed;
}

.kit-contrib:hover:not(:disabled) {
    background: var(--color-surface-muted);
}

.kit-contrib--on:hover:not(:disabled) {
    background: var(--color-secondary-surface-strong);
}

.kit-contrib__copy {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 0.2rem;
}

.kit-contrib__name {
    font-size: 0.8125rem;
    font-weight: 400;
}

.kit-contrib__phrase {
    color: var(--color-text-muted);
    font-size: 0.75rem;
    font-style: italic;
    line-height: 1.35;
}

.kit-scale-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.kit-scale-tab {
    padding: 0.35rem 0.65rem;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
    font-family: var(--font-family-base);
    font-size: 0.75rem;
    font-weight: 400;
    cursor: pointer;
}

.kit-scale-tab--on {
    background: var(--color-secondary);
    color: var(--color-on-secondary);
}

.kit-scale-tab:focus-visible {
    outline: none;
    box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.kit-bonus {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 8rem;
    align-items: center;
    gap: var(--space-2);
}

.kit-bonus__name {
    color: var(--color-text-muted);
    font-size: 0.75rem;
}

.kit-bonus__name::after {
    content: ":";
}

.kit-study .kit-picks {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.35rem;
}

.kit-study .kit-pick {
    flex: 1 1 0;
    min-width: 0;
    min-height: 2.75rem;
    padding: 0.3rem 0.15rem;
}

.kit-study .kit-pick__name {
    font-size: 0.625rem;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.kit-study .kit-pick__score {
    font-size: 0.6875rem;
}

.kit-panel--story .kit-eq {
    display: flex;
    flex-wrap: nowrap;
    align-items: stretch;
    gap: 0.35rem;
}

.kit-panel--story .kit-eq__term {
    flex: 1 1 0;
    min-width: 0;
    max-width: none;
}

.kit-panel--story .kit-eq__term--out {
    flex: 1.15 1 0;
}

.kit-panel--story .kit-eq__op {
    flex: 0 0 auto;
    align-self: center;
}

.kit-panel--story .kit-eq__k {
    font-size: 0.625rem;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

.kit-picks {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0.4rem;
}

.kit-picks--six {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.kit-pick {
    display: flex;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.15rem;
    min-height: 3.15rem;
    padding: 0.4rem 0.25rem;
    border: none;
    border-radius: var(--radius-sm);
    background: var(--color-gold-surface);
    color: var(--color-gold-800);
    font-family: var(--font-family-base);
    font-weight: 400;
    cursor: pointer;
}

.kit-picks--project .kit-pick {
    background: var(--color-secondary-surface);
    color: var(--color-secondary-800);
}

.kit-picks--quality .kit-pick {
    background: var(--color-success-tint-bg);
    color: var(--color-success-tint-fg);
}

.kit-pick:hover:not(.kit-pick--on) {
    background: var(--color-gold-surface-strong);
}

.kit-picks--project .kit-pick:hover:not(.kit-pick--on) {
    background: var(--color-secondary-surface-strong);
}

.kit-picks--quality .kit-pick:hover:not(.kit-pick--on) {
    background: color-mix(
        in srgb,
        var(--color-success-tint-bg) 70%,
        var(--color-success)
    );
}

.kit-pick--on {
    background: var(--color-gold);
    color: var(--color-surface);
}

.kit-picks--project .kit-pick--on {
    background: var(--color-secondary);
    color: var(--color-on-secondary);
}

.kit-picks--quality .kit-pick--on {
    background: var(--color-success);
    color: var(--color-surface);
}

.kit-pick--mute {
    background: var(--color-surface-muted);
    color: var(--color-text-muted);
}

.kit-pick--mute.kit-pick--on {
    background: var(--color-text-muted);
    color: var(--color-surface);
}

.kit-pick:focus-visible {
    outline: none;
    box-shadow: inset 0 0 0 1.5px var(--color-primary);
}

.kit-pick__name {
    font-size: 0.6875rem;
    line-height: 1.25;
    text-align: center;
}

.kit-pick__score {
    font-size: 0.75rem;
    font-variant-numeric: tabular-nums;
}

.kit-eq {
    display: grid;
    grid-template-columns:
        minmax(4.25rem, 1fr) auto minmax(4.25rem, 1fr) auto
        minmax(6rem, 1.4fr);
    align-items: stretch;
    gap: 0.4rem;
    padding: 0.5rem;
    border-radius: var(--radius-sm);
    background: var(--color-surface-muted);
}

.kit-eq__term {
    display: flex;
    min-width: 4.25rem;
    max-width: 100%;
    flex: 1 1 4.25rem;
    flex-direction: column;
    justify-content: center;
    padding: 0.4rem 0.55rem;
    border-radius: var(--radius-sm);
    background: var(--color-surface);
}

.kit-eq__n {
    color: var(--color-text);
    font-size: 0.9375rem;
    font-variant-numeric: tabular-nums;
    line-height: 1.25;
}

.kit-eq__k {
    color: var(--color-text-muted);
    font-size: 0.6875rem;
    font-style: italic;
    line-height: 1.3;
}

.kit-eq__op {
    align-self: center;
    color: var(--color-text-muted);
    font-size: 0.9375rem;
    font-weight: 400;
}

.kit-eq__term--gold {
    background: var(--color-gold-surface);
}

.kit-eq__term--gold .kit-eq__n {
    color: var(--color-gold-800);
}

.kit-eq__term--teal {
    background: var(--color-secondary-surface);
}

.kit-eq__term--teal .kit-eq__n {
    color: var(--color-secondary-800);
}

.kit-eq__term--quality {
    background: var(--color-success-tint-bg);
}

.kit-eq__term--quality .kit-eq__n {
    color: var(--color-success-tint-fg);
}

.kit-eq__term--out {
    flex: 1.4 1 6rem;
    background: var(--color-primary-surface);
}

.kit-eq__term--out .kit-eq__n {
    color: var(--color-primary);
}

.kit-switch {
    position: relative;
    flex-shrink: 0;
    width: 2.5rem;
    height: 1.375rem;
    padding: 0;
    border: none;
    border-radius: var(--radius-full);
    background: color-mix(
        in srgb,
        var(--color-text-muted) 35%,
        var(--color-surface-muted)
    );
    pointer-events: none;
}

.kit-switch--on {
    background: var(--color-gold);
}

.kit-switch--teal.kit-switch--on {
    background: var(--color-secondary);
}

.kit-switch--done.kit-switch--on {
    background: var(--color-success);
}

.kit-switch__thumb {
    position: absolute;
    top: 0.125rem;
    left: 0.125rem;
    width: 1.125rem;
    height: 1.125rem;
    border-radius: var(--radius-full);
    background: var(--color-surface);
    box-shadow: var(--shadow-sm);
    transition: transform 0.15s ease;
}

.kit-switch--on .kit-switch__thumb {
    transform: translateX(1.125rem);
}

@media (max-width: 960px) {
    .kit-formula {
        grid-template-columns: minmax(0, 1fr);
    }

    .kit-split,
    .kit-tiles,
    .kit-demo {
        grid-template-columns: minmax(0, 1fr);
    }

    .kit-tiles--pair,
    .kit-tiles--quad {
        grid-template-columns: minmax(0, 1fr);
    }

    .kit-page {
        overflow-x: hidden;
        overflow-y: auto;
    }

    .kit-split,
    .kit-col {
        overflow: visible;
    }
}

@media (max-width: 640px) {
    .kit-changes-dialog__grid,
    .kit-changes-dialog__list {
        grid-template-columns: minmax(0, 1fr);
    }

    .kit-tabs {
        padding: 0 var(--space-3);
    }

    .kit-page {
        padding: var(--space-3);
    }

    .kit-dock {
        padding: var(--space-2) var(--space-3);
    }

    .kit-panel__head--scale {
        flex-direction: column;
    }

    .kit-panel__head--scale > .kit-header-btn {
        align-self: flex-end;
    }

    .kit-source {
        grid-template-columns: minmax(0, 1fr);
    }

    .kit-source__actions {
        justify-content: flex-end;
    }

    .kit-levels__head,
    .kit-levels__row {
        grid-template-columns: 3.25rem minmax(0, 1fr);
    }

    .kit-levels--class .kit-levels__head,
    .kit-levels--class .kit-levels__row {
        grid-template-columns: 3.25rem minmax(0, 1fr);
    }

    .kit-levels__row .kit-stepper,
    .kit-levels__ops {
        grid-column: 1 / -1;
    }

    .kit-levels__ops {
        justify-content: flex-start;
    }

    .kit-picks {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .kit-study .kit-picks {
        display: flex;
        flex-wrap: nowrap;
    }
}

@media (max-width: 1200px) and (min-width: 641px) {
    .kit-picks {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .kit-study .kit-picks {
        display: flex;
        flex-wrap: nowrap;
    }
}

@media (prefers-reduced-motion: reduce) {
    .kit-header-btn__spin,
    .kit-switch__thumb {
        animation: none;
        transition: none;
    }
}
</style>
