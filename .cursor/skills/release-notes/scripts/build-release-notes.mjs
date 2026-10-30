/**
 * Ghi release/ từ git log. Chạy tại gốc repo:
 *   node .cursor/skills/release-notes/scripts/build-release-notes.mjs
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const releaseDir = path.join(root, 'release');
const PRODUCT = 'VA Workspace';

function gitExe() {
  const candidates = [
    'C:\\ServBay\\packages\\git\\cmd\\git.exe',
    'C:\\Program Files\\Git\\cmd\\git.exe',
  ];
  for (const file of candidates) {
    if (fs.existsSync(file)) return file;
  }
  return 'git';
}

const git = gitExe();

function gitOut(args) {
  return execFileSync(git, args, { cwd: root, encoding: 'utf8' }).replace(/^\uFEFF/, '');
}

const branch = gitOut(['branch', '--show-current']).trim() || '(detached)';
const raw = gitOut(['log', '--date=format:%Y-%m-%d %H:%M', '--name-only', '--pretty=format:---%ad|%h|%s']);

const AREA_LABEL = {
  Attendance: 'Chấm công',
  Chat: 'Chat',
  Social: 'Bảng tin',
  Identity: 'Định danh và phân quyền',
  Evaluation: 'Đánh giá',
  Report: 'Báo cáo',
  Project: 'Dự án và công việc',
  WorkspaceConfig: 'Cấu hình workspace',
  DonTu: 'Đơn từ',
  Credential: 'Hồ sơ đăng nhập',
  Dashboard: 'Tổng quan',
  FeatureRequest: 'Đề xuất',
};

function areaOf(file) {
  const mod = file.match(/^Modules\/([^/]+)\//);
  if (mod) return mod[1];
  if (file.startsWith('release/') || file.startsWith('docs/') || file.endsWith('.md')) return 'docs';
  if (file.startsWith('tests/')) return 'tests';
  if (/^(database|app|routes|config|bootstrap)\//.test(file)) return 'platform';
  if (file.startsWith('resources/')) return 'shell';
  if (file.startsWith('.cursor/') || file.startsWith('scripts/') || file.startsWith('.github/')) return 'tooling';
  return 'other';
}

function areaLabel(area) {
  return AREA_LABEL[area]
    || { docs: 'Tài liệu', tests: 'Kiểm thử', platform: 'Nền tảng', shell: 'Giao diện gốc', tooling: 'Tooling' }[area]
    || 'Khác';
}

function primaryArea(files) {
  const counts = new Map();
  const source = files.some((file) => areaOf(file) !== 'tests')
    ? files.filter((file) => areaOf(file) !== 'tests')
    : files;
  for (const file of source) {
    const area = areaOf(file);
    counts.set(area, (counts.get(area) || 0) + 1);
  }
  let best = 'other';
  let n = 0;
  for (const [area, count] of counts) {
    if (count > n) {
      best = area;
      n = count;
    }
  }
  return best;
}

function isJunk(subject) {
  const text = stripPrefix(subject);
  if (text.length <= 4) return true;
  if (/^(a+|s+|d+|e+|n+|m+|k+|f+|ư+|g+|h+|j+|q+|r+|x+|z+|b+|c+|updates?|updatings?|updating|upda|update|updates it|updaets|updatte.*)$/i.test(text)) return true;
  if (/^docs:\s*updates\b/i.test(text)) return true;
  if (/^updates?\s+\d/i.test(text)) return true;
  const words = text.split(/\s+/);
  if (/^(updates?|updatings?)\b/i.test(text) && words.length <= 4 && !/[àáạảãăâêôơưđ]/i.test(text)) return true;
  return false;
}

function shortFile(file) {
  const parts = file.split('/');
  if (parts[0] === 'Modules' && parts.length >= 2) {
    return `${parts[1]}/${parts[parts.length - 1]}`;
  }
  return parts.slice(-2).join('/');
}

function explain(commit, featureTitle) {
  if (!isJunk(commit.subject)) return plainSubject(commit.subject);
  const screens = screensOf(commit.files);
  const where = screens.length ? ` Màn: ${screens.join(', ')}.` : '';
  return `Sửa tiếp cùng việc.${where}`;
}

function stripPrefix(subject) {
  return subject
    .trim()
    .replace(/^(feat|fix|docs|chore|refactor|test|style|perf|build|ci)(\([^)]+\))?!?:\s*/i, '')
    .replace(/\.$/, '');
}

function plainSubject(subject) {
  const text = stripPrefix(subject);
  if (!text) return 'Không có câu mô tả';
  return text.charAt(0).toUpperCase() + text.slice(1);
}

function displayTitle(feature) {
  if (feature.title.startsWith('Chỉnh ')) return `Sửa tiếp phần ${areaLabel(feature.area)} (chưa ghi câu mô tả)`;
  return plainSubject(feature.title);
}

const SCREEN_RULES = [
  [/EmployeeAttendance|check-in-context|AttendancePunch/i, 'Chấm công nhân viên'],
  [/EmployeeRequests\.vue|EmployeeLeaveRequest|EmployeeLeaveForm|leave-requests/i, 'Lịch sử'],
  [/EmployeeLeave\.vue|EmployeeLeaveBalance|leave-balance/i, 'Nghỉ phép'],
  [/EmployeeChat|Modules\/Chat\//i, 'Chat'],
  [/SocialGroup/i, 'Nhóm'],
  [/Modules\/Social\/|SocialPost|SocialModeration/i, 'Bảng tin'],
  [/PermissionMatrix|ActivityLog/i, 'Quyền và nhật ký'],
  [/Login\.vue|AuthCallback/i, 'Đăng nhập'],
  [/EvaluationScoreKit|WorkspaceConfigEvaluation|evaluation-templates/i, 'Tiêu chí đánh giá'],
  [/Modules\/Report\/|ReportList|ReportCreate/i, 'Báo cáo'],
  [/TaskList|TaskCreate|ProjectList|Modules\/Project\//i, 'Công việc và dự án'],
  [/Modules\/DonTu\/|DonTuStatus/i, 'Đơn từ'],
  [/Modules\/Credential\//i, 'Hồ sơ đăng nhập'],
  [/EmployeeProfile/i, 'Hồ sơ cá nhân'],
  [/DashboardMe|Modules\/Dashboard\//i, 'Tổng quan của tôi'],
  [/WorkspaceConfig|AppSidebar|navigation/i, 'Cấu hình menu'],
];

function screensOf(files) {
  const found = [];
  for (const [pattern, label] of SCREEN_RULES) {
    if (files.some((file) => pattern.test(file)) && !found.includes(label)) found.push(label);
  }
  return found;
}

function migrationPlain(name) {
  const rules = [
    [/chat_mood|group_accent/, 'Chat thêm tâm trạng và màu nhóm'],
    [/evaluation_template/, 'Đổi bảng mẫu đánh giá'],
    [/tasks_/, 'Đổi bảng công việc'],
  ];
  for (const [pattern, label] of rules) {
    if (pattern.test(name)) return label;
  }
  return 'Đổi cấu trúc dữ liệu';
}

function featureFiles(feature) {
  return feature.commits.flatMap((commit) => commit.files);
}

function cell(value) {
  return String(value ?? '—').replace(/\|/g, '\\|').replace(/\r?\n/g, ' ');
}

function changeKind(subject) {
  const text = subject.trim();
  if (isJunk(text)) return 'Sửa tiếp';
  if (/^fix\b/i.test(text)) return 'Sửa';
  if (/^feat\b/i.test(text)) return 'Thêm';
  if (/^docs\b/i.test(text)) return 'Tài liệu';
  if (/^refactor\b/i.test(text)) return 'Cấu trúc lại';
  if (/^test\b/i.test(text)) return 'Kiểm thử';
  return 'Cập nhật';
}

function placeOf(files) {
  const screens = screensOf(files);
  if (screens.length) return screens.join(', ');
  return 'Không gắn màn hình';
}

function touchedOf(files) {
  const names = files.slice(0, 4).map(shortFile);
  if (!names.length) return '—';
  const more = files.length > 4 ? ` (+${files.length - 4})` : '';
  return `${names.join(', ')}${more}`;
}

const commits = [];
let current = null;
for (const line of raw.split(/\r?\n/)) {
  if (line.startsWith('---')) {
    const body = line.slice(3);
    const [stamp, sha, ...rest] = body.split('|');
    const [date, time] = stamp.trim().split(' ');
    current = { date, time: time || '', sha, subject: rest.join('|').trim(), files: [] };
    commits.push(current);
  } else if (current && line.trim()) {
    current.files.push(line.trim());
  }
}
commits.reverse();

const pad = (n) => String(n).padStart(2, '0');
const fmtVi = (iso) => {
  const [y, m, d] = iso.split('-');
  return `${d}/${m}/${y}`;
};
function addDays(iso, days) {
  const date = new Date(`${iso}T00:00:00`);
  date.setDate(date.getDate() + days);
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}
function weekStart(iso) {
  const date = new Date(`${iso}T00:00:00`);
  const day = date.getDay();
  date.setDate(date.getDate() + (day === 0 ? -6 : 1 - day));
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}
function isoWeek(iso) {
  const date = new Date(`${iso}T00:00:00`);
  date.setDate(date.getDate() + 3 - ((date.getDay() + 6) % 7));
  const week1 = new Date(date.getFullYear(), 0, 4);
  return 1 + Math.round(((date - week1) / 86400000 - 3 + ((week1.getDay() + 6) % 7)) / 7);
}
function rangeLabel(start, end) {
  const [, ms, ds] = start.split('-');
  const [ye, me, de] = end.split('-');
  const [ys] = start.split('-');
  if (start.slice(0, 4) === ye && ms === me) return `${Number(ds)}–${Number(de)}/${ms}/${ys}`;
  if (start.slice(0, 4) === ye) return `${Number(ds)}/${ms}–${Number(de)}/${me}/${ys}`;
  return `${fmtVi(start)}–${fmtVi(end)}`;
}

const byDate = new Map();
for (const commit of commits) {
  if (!byDate.has(commit.date)) byDate.set(commit.date, []);
  byDate.get(commit.date).push(commit);
}

function featuresOf(dayCommits) {
  const features = [];
  let feature = null;
  for (const commit of dayCommits) {
    const area = primaryArea(commit.files);
    const junk = isJunk(commit.subject);
    const same = feature && (feature.area === area || area === 'docs' || area === 'tests' || area === 'other');
    if (junk && same) {
      feature.commits.push(commit);
      continue;
    }
    feature = {
      title: junk ? `Chỉnh ${areaLabel(area).toLowerCase()}` : commit.subject,
      area,
      commits: [commit],
    };
    features.push(feature);
  }
  features.forEach((item, index) => {
    item.code = String(index + 1);
  });
  return features;
}

function migrations(dayCommits) {
  const found = [];
  for (const commit of dayCommits) {
    for (const file of commit.files) {
      if (/migrations\/.+\.php$/.test(file)) found.push(path.basename(file));
    }
  }
  return [...new Set(found)];
}

function write(rel, body) {
  const file = path.join(releaseDir, rel);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, body.replace(/\n/g, '\r\n'), 'utf8');
}

if (fs.existsSync(releaseDir)) {
  for (const name of fs.readdirSync(releaseDir)) {
    const full = path.join(releaseDir, name);
    if (/^20\d{2}-\d{2}-\d{2}$/.test(name) && fs.statSync(full).isDirectory()) {
      fs.rmSync(full, { recursive: true, force: true });
    }
    if (/^\d{4}-W\d+-summary\.md$/.test(name)) fs.rmSync(full, { force: true });
    if (name === 'task' && fs.statSync(full).isDirectory()) {
      fs.rmSync(full, { recursive: true, force: true });
    }
    if (/^20\d{2}-\d{2}-\d{2}-release\.md$/.test(name)) fs.rmSync(full, { force: true });
    if (/^20\d{2}-\d{2}-\d{2}-\d{2}-summary\.md$/.test(name)) fs.rmSync(full, { force: true });
  }
  const summaryDir = path.join(releaseDir, 'summary');
  if (fs.existsSync(summaryDir)) fs.rmSync(summaryDir, { recursive: true, force: true });
} else {
  fs.mkdirSync(releaseDir, { recursive: true });
}

function auditSections(days) {
  const counts = new Map();
  for (const day of days) {
    for (const commit of day.commits) {
      const area = primaryArea(commit.files);
      counts.set(area, (counts.get(area) || 0) + 1);
    }
  }
  const order = [...Object.keys(AREA_LABEL), 'platform', 'shell', 'docs', 'tests', 'tooling', 'other'];
  const areas = [...counts.keys()].sort((a, b) => {
    const ia = order.indexOf(a);
    const ib = order.indexOf(b);
    return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
  });
  const lines = ['## Module', '', '| Module | Commit |', '| --- | ---: |'];
  for (const area of areas) lines.push(`| ${areaLabel(area)} | ${counts.get(area)} |`);
  lines.push('', '## Tính năng lớn', '');
  const named = [];
  for (const day of days) {
    for (const feature of day.features) {
      if (feature.title.startsWith('Chỉnh ')) continue;
      if (feature.area === 'docs' || feature.area === 'tests' || feature.area === 'tooling') continue;
      named.push({ day, feature });
    }
  }
  if (!named.length) {
    lines.push('Không có message tính năng trong tuần. Commit một chữ nằm ở mục «Việc trong tuần».', '');
    return lines;
  }
  lines.push('| Ngày | Module | Tính năng | Commit |', '| --- | --- | --- | ---: |');
  for (const { day, feature } of named) {
    lines.push(`| ${fmtVi(day.date)} | ${areaLabel(feature.area)} | ${plainSubject(feature.title).replace(/\|/g, '\\|')} | ${feature.commits.length} |`);
  }
  lines.push('');
  return lines;
}

const weeks = new Map();
for (const date of byDate.keys()) {
  const start = weekStart(date);
  if (!weeks.has(start)) weeks.set(start, []);
  weeks.get(start).push(date);
}

const weekMeta = [];
for (const [start, dates] of [...weeks.entries()].sort(([a], [b]) => a.localeCompare(b))) {
  dates.sort();
  const end = addDays(start, 6);
  const weekNo = isoWeek(start);
  const id = `${start.slice(0, 4)}-W${pad(weekNo)}`;
  const days = [];
  for (let i = 0; i < 7; i += 1) {
    const date = addDays(start, i);
    const dayCommits = byDate.get(date) || [];
    days.push({ date, commits: dayCommits, features: dayCommits.length ? featuresOf(dayCommits) : [] });
  }
  const commitCount = days.reduce((sum, day) => sum + day.commits.length, 0);
  weekMeta.push({ id, weekNo, start, end, days, commitCount });

  for (const day of days) {
    if (!day.commits.length) continue;
    const lines = [
      `# ${fmtVi(day.date)} — ${PRODUCT}`,
      '',
      `Hôm nay có **${day.commits.length} lần lưu**, gom thành **${day.features.length} việc**.`,
      '',
      `Phiếu tuần: [${id}](../summary/${id}-summary.md) · Đối soát: [audit.md](./audit.md) · [Mục lục](../README.md)`,
      '',
      '## Nhìn nhanh',
      '',
    ];
    for (const feature of day.features) {
      const screens = screensOf(featureFiles(feature));
      const where = screens.length ? ` Màn: ${screens.join(', ')}.` : '';
      lines.push(`${feature.code}. **${areaLabel(feature.area)}.**${where} ${displayTitle(feature)} (${feature.commits.length} lần lưu)`);
    }
    lines.push('', '## Từng việc', '');
    for (const feature of day.features) {
      const files = featureFiles(feature);
      const screens = screensOf(files);
      const migs = migrations(feature.commits);
      lines.push(`### ${feature.code}. ${displayTitle(feature)}`, '');
      lines.push(`- **Phần:** ${areaLabel(feature.area)}`);
      lines.push(`- **Màn hình:** ${screens.length ? screens.join(', ') : 'Không gắn một màn hình cụ thể'}`);
      lines.push(`- **Lần lưu:** ${feature.commits.length}`);
      lines.push(`- **Đổi dữ liệu:** ${migs.length ? migs.map(migrationPlain).join('; ') : 'Không'}`);
      lines.push('', 'Các lần lưu, từ cũ đến mới:', '');
      for (const commit of feature.commits) {
        lines.push(`- \`${commit.sha}\` — ${explain(commit, feature.title)}`);
      }
      lines.push('');
    }
    lines.push('## Khi đưa lên máy chủ', '');
    const mig = migrations(day.commits);
    if (mig.length) {
      lines.push('Ngày này có đổi dữ liệu. Cập nhật dữ liệu trước khi dùng:', '');
      for (const name of mig) lines.push(`- ${migrationPlain(name)}`);
    } else {
      lines.push('Không đổi cấu trúc dữ liệu. Chỉ cần bản phần mềm mới.');
    }
    lines.push('');
    write(`${day.date}/README.md`, `${lines.join('\n')}\n`);
    write(`${day.date}/audit.md`, `${dayAudit(day, id).join('\n')}\n`);
  }

  const weekdays = ['Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7', 'Chủ nhật'];
  const summary = [
    `# Tuần ${weekNo} — ${rangeLabel(start, end)}`,
    '',
    '| | |',
    '| --- | --- |',
    `| **Cửa sổ** | Thứ 2 ${fmtVi(start)} → Chủ nhật ${fmtVi(end)} |`,
    `| **Commit** | **${commitCount}** |`,
    `| **Ngày có commit** | ${dates.length} |`,
    `| **Nhánh đối soát** | \`${branch}\` |`,
    `| **Bảng audit** | [${id}-audit](./${id}-audit.md) |`,
    '',
    '## Theo ngày',
    '',
    '| Ngày | Thứ | Commit | Nhóm việc | Phiếu |',
    '| --- | --- | ---: | ---: | --- |',
  ];
  days.forEach((day, index) => {
    const link = day.commits.length ? `[\`${day.date}\`](../${day.date}/README.md)` : '—';
    summary.push(`| **${fmtVi(day.date)}** | ${weekdays[index]} | ${day.commits.length} | ${day.features.length} | ${link} |`);
  });
  summary.push('', ...auditSections(days), '## Việc trong tuần', '');
  for (const day of days) {
    if (!day.commits.length) continue;
    summary.push(`### ${fmtVi(day.date)}`, '');
    for (const feature of day.features) {
      summary.push(`- **${feature.code}.** ${displayTitle(feature)} (${feature.commits.length} lần lưu)`);
    }
    summary.push('');
  }
  summary.push('[← Mục lục](../README.md)', '');
  write(`summary/${id}-summary.md`, `${summary.join('\n')}\n`);
  write(`summary/${id}-audit.md`, `${weekAudit(id, weekNo, start, end, days).join('\n')}\n`);
}

function dayAudit(day, id) {
  const lines = [
    `# Đối soát ${fmtVi(day.date)}`,
    '',
    `Đọc nhanh: [README.md](./README.md) · Tuần: [${id}](../summary/${id}-summary.md) · Bảng tuần: [${id}-audit](../summary/${id}-audit.md)`,
    '',
    '## Việc trong ngày',
    '',
    '| # | Loại | Phần | Màn hình | Việc | Lần lưu | Đổi dữ liệu |',
    '| --- | --- | --- | --- | --- | ---: | --- |',
  ];
  for (const feature of day.features) {
    const files = featureFiles(feature);
    const migs = migrations(feature.commits);
    const kind = changeKind(feature.commits[0].subject);
    lines.push(`| ${feature.code} | ${kind} | ${cell(areaLabel(feature.area))} | ${cell(placeOf(files))} | ${cell(displayTitle(feature))} | ${feature.commits.length} | ${cell(migs.length ? migs.map(migrationPlain).join('; ') : 'Không')} |`);
  }
  lines.push('', '## Từng lần lưu', '', '| Giờ | Mã | Loại | Phần | Màn hình | Đã ghi | Chỗ sửa |', '| --- | --- | --- | --- | --- | --- | --- |');
  for (const commit of day.commits) {
    const feature = day.features.find((item) => item.commits.some((row) => row.sha === commit.sha));
    lines.push(`| ${commit.time || '—'} | \`${commit.sha}\` | ${changeKind(commit.subject)} | ${cell(areaLabel(feature.area))} | ${cell(placeOf(commit.files))} | ${cell(explain(commit, feature.title))} | ${cell(touchedOf(commit.files))} |`);
  }
  lines.push('');
  return lines;
}

function weekAudit(id, weekNo, start, end, days) {
  const lines = [
    `# Đối soát tuần ${weekNo} — ${rangeLabel(start, end)}`,
    '',
    `Phiếu đọc: [${id}-summary](./${id}-summary.md) · [Mục lục](../README.md)`,
    '',
    '## Việc cần đối soát',
    '',
    '| Ngày | Loại | Phần | Màn hình | Việc | Lần lưu | Đổi dữ liệu | Phiếu ngày |',
    '| --- | --- | --- | --- | --- | ---: | --- | --- |',
  ];
  let any = false;
  for (const day of days) {
    for (const feature of day.features) {
      any = true;
      const files = featureFiles(feature);
      const migs = migrations(feature.commits);
      const kind = changeKind(feature.commits[0].subject);
      lines.push(`| ${fmtVi(day.date)} | ${kind} | ${cell(areaLabel(feature.area))} | ${cell(placeOf(files))} | ${cell(displayTitle(feature))} | ${feature.commits.length} | ${cell(migs.length ? migs.map(migrationPlain).join('; ') : 'Không')} | [audit](../${day.date}/audit.md) |`);
    }
  }
  if (!any) lines.push('| — | — | — | — | Không có lần lưu | 0 | Không | — |');
  lines.push('');
  return lines;
}

const head = commits[commits.length - 1];
const index = [
  `# Release — ${PRODUCT}`,
  '',
  `Tổng hợp **toàn bộ commit** trên nhánh \`${branch}\`, chia theo ngày và tổng kết theo tuần (thứ 2 → Chủ nhật). Phiếu đọc nằm trong [\`summary/\`](./summary/). Mỗi ngày và mỗi tuần có thêm file \`audit.md\` — bảng đối soát việc đã sửa.`,
  '',
  '| | |',
  '| --- | --- |',
  `| **Cửa sổ** | ${fmtVi(commits[0].date)} → ${fmtVi(head.date)} |`,
  `| **Commit** | **${commits.length}** |`,
  `| **HEAD** | \`${head.sha}\` — ${head.subject} |`,
  '| **Đối tượng** | BA, QA — đối soát commit theo ngày |',
  '',
  'Commit message một chữ không được diễn giải thành nghiệp vụ mới. Cột diễn giải trong phiếu ngày trích file commit đó đụng.',
  '',
  '## Tuần',
  '',
  '| Tuần | Cửa sổ | Commit | Phiếu | Đối soát |',
  '| --- | --- | ---: | --- | --- |',
];
let total = 0;
for (const week of weekMeta) {
  total += week.commitCount;
  index.push(`| **W${week.weekNo}** | ${rangeLabel(week.start, week.end)} | ${week.commitCount} | [${week.id}](./summary/${week.id}-summary.md) | [audit](./summary/${week.id}-audit.md) |`);
}
index.push(`| **Tổng** | | **${total}** | | |`, '', '## Ngày', '');
for (const week of weekMeta) {
  index.push(`### W${week.weekNo}`, '');
  for (const day of week.days) {
    if (!day.commits.length) continue;
    index.push(`- [${fmtVi(day.date)}](./${day.date}/README.md) — ${day.commits.length} lần lưu, ${day.features.length} việc · [đối soát](./${day.date}/audit.md)`);
  }
  index.push('');
}
write('README.md', `${index.join('\n')}\n`);
console.log(`commits=${commits.length} weeks=${weekMeta.length} days=${byDate.size} branch=${branch}`);
