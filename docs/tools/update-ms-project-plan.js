#!/usr/bin/env node

"use strict";

const fs = require("fs");
const path = require("path");

const root = path.resolve(__dirname, "..", "..");
const inputPath = path.join(root, "docs", "BlatyRPG-Planner.v3.fixed.xml");
const outputPath = path.join(
  root,
  "docs",
  "BlatyRPG-Planner.v3.status-2026-08-26.xml",
);

const STATUS_DATE = new Date("2026-08-26T23:59:00Z");
const STATUS_LABEL = "2026-08-26";
const PROJECT_START = new Date("2025-12-01T09:00:00Z");
const HOLIDAYS = [
  ["2025-12-23", "2026-01-03"],
  ["2026-04-03", "2026-04-07"],
];

const complete = new Set();
const addComplete = (...values) => {
  for (const value of values) {
    if (Array.isArray(value)) {
      for (let id = value[0]; id <= value[1]; id += 1) complete.add(id);
    } else {
      complete.add(value);
    }
  }
};

addComplete(
  1,
  4,
  5,
  7,
  9,
  10,
  11,
  12,
  14,
  20,
  [35, 44],
  48,
  51,
  53,
  [56, 64],
  66,
  [71, 73],
  [77, 81],
  [85, 89],
  [94, 117],
  [119, 133],
  135,
  [138, 150],
  [153, 157],
  [159, 161],
  163,
  165,
  [167, 168],
  [172, 176],
  178,
  [180, 184],
  [186, 188],
  [191, 205],
  209,
  [211, 219],
  [223, 228],
  230,
  234,
  [243, 245],
  247,
  [249, 260],
  262,
  [264, 281],
  [283, 288],
  [290, 293],
  [295, 301],
  307,
  [310, 314],
  [318, 321],
  338,
);

const percent = {
  6: 70,
  8: 70,
  13: 75,
  15: 90,
  21: 90,
  46: 25,
  47: 25,
  49: 70,
  50: 90,
  52: 85,
  55: 50,
  68: 85,
  69: 75,
  74: 90,
  76: 90,
  151: 80,
  162: 60,
  169: 25,
  206: 80,
  220: 20,
  231: 40,
  238: 40,
  261: 0,
  282: 10,
  289: 85,
  294: 25,
  334: 60,
  335: 60,
  336: 40,
  337: 50,
  339: 80,
};

for (const id of complete) percent[id] = 100;

const remainingHours = {
  46: 8,
  47: 12,
  49: 8,
  50: 4,
  52: 8,
  55: 12,
  65: 6,
  67: 6,
  68: 8,
  69: 12,
  74: 2,
  75: 6,
  76: 4,
  82: 8,
  83: 8,
  84: 6,
  134: 8,
  136: 12,
  151: 8,
  158: 16,
  162: 8,
  166: 8,
  169: 12,
  170: 8,
  177: 8,
  185: 8,
  189: 8,
  206: 10,
  207: 16,
  208: 16,
  220: 32,
  221: 16,
  229: 20,
  231: 12,
  233: 8,
  235: 12,
  236: 8,
  237: 16,
  238: 8,
  239: 32,
  240: 8,
  241: 12,
  246: 8,
  261: 12,
  282: 32,
  289: 12,
  294: 12,
  304: 40,
  305: 24,
  306: 24,
  315: 12,
  316: 8,
  323: 8,
  324: 16,
  325: 24,
  326: 16,
  327: 16,
  329: 8,
  330: 24,
  331: 16,
  332: 12,
  334: 20,
  335: 20,
  336: 24,
  337: 16,
  338: 4,
  339: 8,
  341: 24,
  342: 16,
  343: 24,
  344: 16,
  345: 40,
  346: 40,
  348: 16,
  349: 24,
  350: 16,
  351: 16,
  352: 24,
  353: 16,
  354: 8,
  355: 16,
  356: 16,
  357: 8,
  359: 16,
  360: 8,
  361: 20,
  362: 40,
};

const finishDates = {};
const date = (value, ...ids) => ids.forEach((id) => (finishDates[id] = value));
const dateRange = (value, first, last) => {
  for (let id = first; id <= last; id += 1) finishDates[id] = value;
};

dateRange("2025-12-04", 35, 44);
date("2026-08-19", 46, 47, 49, 50, 52, 53, 55);
date("2025-12-04", 48);
date("2025-12-07", 51, 56);
date("2026-08-19", 57, 58, 59, 62, 63, 64, 66, 68, 69);
date("2026-08-24", 60);
date("2025-12-09", 61);
date("2025-12-04", 71, 72, 73, 74, 87);
date("2025-12-05", 76, 77, 78, 79, 80, 81, 85, 88, 89);
date("2026-08-26", 86);
date("2025-12-07", 94, 153, 154, 155, 156, 157);
date("2026-08-19", 95, 128, 129, 131, 133, 135, 159, 160, 161, 162);
date("2025-12-09", 96);
dateRange("2025-12-20", 97, 127);
date("2026-08-24", 130);
date("2026-08-19", 132);
dateRange("2026-01-11", 138, 150);
date("2026-08-26", 151, 163);
date("2026-08-23", 165, 167, 168);
date("2026-08-18", 169);
dateRange("2026-08-19", 172, 188);
dateRange("2026-08-19", 191, 209);
dateRange("2026-08-19", 211, 215);
dateRange("2026-08-24", 216, 220);
dateRange("2026-08-19", 223, 231);
date("2025-12-29", 234, 238);
dateRange("2026-08-23", 243, 247);
dateRange("2025-12-04", 249, 255);
dateRange("2026-08-23", 257, 276);
date("2026-08-23", 278);
dateRange("2026-08-24", 279, 281);
dateRange("2026-08-26", 282, 291);
dateRange("2026-08-23", 293, 296);
dateRange("2026-01-17", 298, 301);
date("2026-08-23", 307);
dateRange("2026-08-26", 310, 321);
dateRange("2026-08-26", 334, 339);

date("2025-12-01", 1, 4);
date("2025-12-04", 5);
date("2025-12-05", 6);
date("2026-08-19", 7, 8, 9, 10, 11, 12, 13);
date("2026-08-23", 14, 15, 20);
date("2026-08-26", 21);

const specificNotes = {
  6: "Docker i healthchecki działają; brak konfiguracji CI w repozytorium.",
  8: "Architektura działa w kodzie, ale brakuje kompletu HLD/C4/OpenAPI.",
  13: "Sceny, tokeny, ściany, światło i realtime działają; Fog of War nie jest kompletny.",
  15: "Profil, sesje i role działają; publiczny profil i upload avatara użytkownika są niepełne.",
  21: "Testy lokalne są zielone, ale brak pipeline CI i raportu coverage.",
  46: "Brak kompletnego diagramu C4 Level 1; istnieje audyt architektury VTT.",
  47: "Brak kompletnego diagramu C4 Level 2; kontenery są opisane kodem Docker Compose.",
  49: "REST API działa, lecz repozytorium nie zawiera kompletnej specyfikacji OpenAPI 3.0.",
  50: "Autorytatywny WebSocket z pokojami kampanii działa; pozostaje formalizacja projektu.",
  55: "Schemat jest zaimplementowany migracjami, ale brak aktualnego diagramu ERD.",
  65: "Brak tabeli historii rzutów DiceRolls.",
  67: "Brak tabeli Macros.",
  75: "Brak konfiguracji PHP-CS-Fixer.",
  82: "Brak konfiguracji GitHub Actions dla lint.",
  83: "Brak konfiguracji GitHub Actions dla PHPUnit.",
  84: "Brak konfiguracji GitHub Actions dla buildu frontendu.",
  134: "Brak migracji tabeli DiceRolls.",
  136: "Brak migracji tabeli Macros.",
  151: "Migracje działają, ale wymagają pełnej próby upgrade/rollback; istnieje konflikt prefiksu 2026-08-26-030000.",
  158: "Brak endpointu POST /api/auth/refresh; aplikacja używa sesji logowania.",
  162: "Obsługa poczty i resetu hasła istnieje, ale SMTP nie jest skonfigurowany w środowisku.",
  166: "Brak publicznego endpointu GET /api/profile/:id.",
  169: "Istnieją assety Cloudinary postaci; brak pełnego uploadu avatara konta użytkownika.",
  170: "Brak endpointu GET /api/cloudinary/resources.",
  177: "Brak endpointu usuwania kampanii.",
  185: "Brak endpointu usuwania stołu/kampanii w kontrakcie źródłowym.",
  189: "Brak wysyłki e-maila zaproszenia do stołu.",
  206: "Handel sklepowy działa; wymiana peer-to-peer oparta o ofertę/akceptację jest niepełna.",
  207: "Brak endpointu POST /api/trade/offer w kontrakcie źródłowym.",
  208: "Brak endpointu POST /api/trade/accept w kontrakcie źródłowym.",
  220: "Istnieją ustawienia i warstwa wizualna fog, ale brak trwałej eksploracji per użytkownik.",
  221: "Brak kompletnego endpointu zapisu/odkrywania Fog of War.",
  229: "Brak prywatnych wiadomości WebSocket.",
  231: "Historia czatu jest trwała i stronicowana; brak jawnej polityki bufora 500 wiadomości.",
  233: "Brak backendowego modelu DiceRoll.",
  235: "Brak endpointu POST /api/dice/roll.",
  236: "Brak endpointu historii rzutów stołu.",
  237: "Brak zdarzenia WebSocket dice_roll.",
  239: "Brak kompletnego systemu makr rzutów.",
  240: "Brak endpointu GET /api/macros.",
  241: "Brak endpointu wykonania makra.",
  246: "Brak endpointu usuwania użytkownika w panelu admina.",
  261: "Brak mechanizmu JWT auto-refresh; sesje są obsługiwane innym kontraktem.",
  282: "Warstwa fog ma jedynie ustawienia/wizualizację, bez pełnego odkrywania per użytkownik.",
  289: "Combat tracker działa w bieżącym drzewie; pozostaje domknięcie regresji i integracji.",
  294: "Assety postaci Cloudinary istnieją, ale upload avatara profilu użytkownika jest niepełny.",
  304: "Brak integracji Jitsi.",
  305: "Brak strony zarządzania makrami.",
  306: "Brak edytora makr.",
  315: "Brak dedykowanych testów backendowego DiceRoller Service.",
  316: "PHPUnit zgłasza brak sterownika coverage; raport nie został wytworzony.",
  323: "Brak Cypress w zależnościach i konfiguracji.",
  329: "Brak k6/JMeter i scenariuszy obciążeniowych.",
  330: "Brak wyniku testu 50 równoczesnych połączeń WebSocket.",
  331: "Brak pomiaru przepustowości API.",
  341: "Brak dowodu skonfigurowanego środowiska staging.",
  348: "Brak dowodu konfiguracji domeny produkcyjnej.",
  349: "Brak dowodu gotowego serwera produkcyjnego.",
};

const milestoneDriverIds = {
  6: [82, 83, 84, 85, 86],
  8: [46, 47, 49, 50, 51, 52, 53, 55],
  16: [13, 301],
};

const xmlEscape = (value) =>
  String(value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");

const valueOf = (block, tag) => {
  const match = block.match(new RegExp(`<${tag}>([\\s\\S]*?)</${tag}>`));
  return match ? match[1] : "";
};

const parseDuration = (value) => {
  const match = String(value).match(/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/);
  if (!match) return 0;
  return Number(match[1] || 0) * 60 + Number(match[2] || 0) + Math.round(Number(match[3] || 0) / 60);
};

const duration = (minutes) => `PT${Math.floor(minutes / 60)}H${minutes % 60}M0S`;
const iso = (dateValue) => dateValue.toISOString().slice(0, 19);
const cloneDate = (dateValue) => new Date(dateValue.getTime());

const dateKey = (dateValue) => iso(dateValue).slice(0, 10);
const inHoliday = (dateValue) => {
  const key = dateKey(dateValue);
  return HOLIDAYS.some(([from, to]) => key >= from && key <= to);
};

const isWorkingMinute = (dateValue) => {
  if (inHoliday(dateValue)) return false;
  const day = dateValue.getUTCDay();
  const hour = dateValue.getUTCHours();
  const minute = dateValue.getUTCMinutes();
  const clock = hour * 60 + minute;
  if (day === 3) return clock >= 9 * 60 && clock < 14 * 60;
  return (clock >= 0 && clock < 60) || (clock >= 20 * 60 && clock < 24 * 60);
};

const nextWorkingMinute = (input) => {
  const result = cloneDate(input);
  result.setUTCSeconds(0, 0);
  for (let guard = 0; guard < 800000; guard += 1) {
    if (isWorkingMinute(result)) return result;
    result.setUTCMinutes(result.getUTCMinutes() + 1);
  }
  throw new Error("Could not find next working minute");
};

const previousWorkingMinute = (input) => {
  const result = cloneDate(input);
  result.setUTCSeconds(0, 0);
  for (let guard = 0; guard < 800000; guard += 1) {
    result.setUTCMinutes(result.getUTCMinutes() - 1);
    if (isWorkingMinute(result)) return result;
  }
  throw new Error("Could not find previous working minute");
};

const addWorkingMinutes = (input, minutes) => {
  if (minutes <= 0) return cloneDate(input);
  const result = nextWorkingMinute(input);
  let left = minutes;
  while (left > 0) {
    if (isWorkingMinute(result)) left -= 1;
    if (left > 0) result.setUTCMinutes(result.getUTCMinutes() + 1);
  }
  return result;
};

const subtractWorkingMinutes = (input, minutes) => {
  const result = cloneDate(input);
  let left = Math.max(0, minutes);
  while (left > 0) {
    result.setUTCMinutes(result.getUTCMinutes() - 1);
    if (isWorkingMinute(result)) left -= 1;
  }
  const start = nextWorkingMinute(result);
  return start < PROJECT_START ? cloneDate(PROJECT_START) : start;
};

const workMinutesBetween = (start, finish) => {
  const cursor = cloneDate(start);
  let result = 0;
  while (cursor < finish) {
    if (isWorkingMinute(cursor)) result += 1;
    cursor.setUTCMinutes(cursor.getUTCMinutes() + 1);
  }
  return result;
};

const endOfWorkDate = (dayValue) => {
  const day = new Date(`${dayValue}T23:59:00Z`);
  if (day.getUTCDay() === 3) day.setUTCHours(14, 0, 0, 0);
  return previousWorkingMinute(new Date(day.getTime() + 60000));
};

const setTag = (block, tag, value) => {
  const expression = new RegExp(`(<${tag}>)[\\s\\S]*?(</${tag}>)`);
  if (!expression.test(block)) return block;
  return block.replace(expression, `$1${value}$2`);
};

const removeTag = (block, tag) =>
  block.replace(new RegExp(`\\n\\t\\t\\t<${tag}>[\\s\\S]*?</${tag}>`, "g"), "");

const insertAfter = (block, tag, content) => {
  const expression = new RegExp(`(<${tag}>[\\s\\S]*?</${tag}>)`);
  if (!expression.test(block)) throw new Error(`Missing insertion anchor ${tag}`);
  return block.replace(expression, `$1${content}`);
};

const upsertAfter = (block, tag, value, anchor) => {
  if (new RegExp(`<${tag}>`).test(block)) return setTag(block, tag, value);
  return insertAfter(block, anchor, `\n\t\t\t<${tag}>${value}</${tag}>`);
};

const appendAuditNote = (block, note) => {
  const existing = valueOf(block, "Notes");
  const audit = xmlEscape(`[Status ${STATUS_LABEL}] ${note}`);
  if (existing) return setTag(block, "Notes", `${existing}&#10;${audit}`);
  return insertAfter(block, "IgnoreResourceCalendar", `\n\t\t\t<Notes>${audit}</Notes>`);
};

const source = fs.readFileSync(inputPath, "utf8");
const taskMatches = [...source.matchAll(/<Task>([\s\S]*?)<\/Task>/g)];
const tasks = taskMatches.map((match) => {
  const block = match[1];
  return {
    block,
    id: Number(valueOf(block, "ID")),
    uid: Number(valueOf(block, "UID")),
    name: valueOf(block, "Name"),
    level: Number(valueOf(block, "OutlineLevel")),
    summary: valueOf(block, "Summary") === "1",
    milestone: valueOf(block, "Milestone") === "1",
    baselineStart: valueOf(block, "Start"),
    baselineFinish: valueOf(block, "Finish"),
    baselineDuration: valueOf(block, "Duration"),
    originalConstraintDate: valueOf(block, "ConstraintDate"),
    predecessorUids: [...block.matchAll(/<PredecessorUID>(\d+)<\/PredecessorUID>/g)].map(
      (match) => Number(match[1]),
    ),
  };
});

const byId = new Map(tasks.map((task) => [task.id, task]));
const byUid = new Map(tasks.map((task) => [task.uid, task]));

for (const task of tasks) {
  if (!task.name || task.summary || task.id === 0) continue;
  task.percent = Object.prototype.hasOwnProperty.call(percent, task.id) ? percent[task.id] : 0;
  const originalMinutes = parseDuration(task.baselineDuration);
  if (task.milestone) {
    task.totalMinutes = 0;
    task.actualMinutes = 0;
    task.remainingMinutes = 0;
    continue;
  }
  if (task.percent === 100) {
    task.totalMinutes = Math.max(originalMinutes, 60);
    task.actualMinutes = task.totalMinutes;
    task.remainingMinutes = 0;
  } else {
    task.remainingMinutes = Math.round((remainingHours[task.id] || Math.max(8, originalMinutes / 60)) * 60);
    task.totalMinutes =
      task.percent > 0
        ? Math.round(task.remainingMinutes / (1 - task.percent / 100))
        : task.remainingMinutes;
    task.actualMinutes = task.totalMinutes - task.remainingMinutes;
  }
}

let cursor = nextWorkingMinute(new Date(STATUS_DATE.getTime() + 60000));
for (const task of tasks) {
  if (!task.name || task.summary || task.milestone || task.id === 0) continue;
  const knownFinish = finishDates[task.id];
  if (task.percent === 100) {
    task.finish = endOfWorkDate(knownFinish || STATUS_LABEL);
    task.start = subtractWorkingMinutes(task.finish, task.actualMinutes);
    task.actualStart = cloneDate(task.start);
    task.actualFinish = cloneDate(task.finish);
  } else if (task.percent > 0) {
    const evidenceFinish = endOfWorkDate(knownFinish || STATUS_LABEL);
    task.actualStart = subtractWorkingMinutes(evidenceFinish, task.actualMinutes);
    task.start = cloneDate(task.actualStart);
    task.resume = cloneDate(cursor);
    task.finish = addWorkingMinutes(task.resume, task.remainingMinutes);
    cursor = nextWorkingMinute(new Date(task.finish.getTime() + 60000));
  } else {
    task.start = cloneDate(cursor);
    task.finish = addWorkingMinutes(task.start, task.remainingMinutes);
    cursor = nextWorkingMinute(new Date(task.finish.getTime() + 60000));
  }
}

for (const task of tasks) {
  if (!task.name || !task.milestone) continue;
  task.percent = Object.prototype.hasOwnProperty.call(percent, task.id) ? percent[task.id] : 0;
  const knownFinish = finishDates[task.id];
  if (task.percent === 100) {
    task.start = endOfWorkDate(knownFinish || STATUS_LABEL);
    task.finish = cloneDate(task.start);
    task.actualStart = cloneDate(task.start);
    task.actualFinish = cloneDate(task.finish);
  } else if (task.percent > 0) {
    task.actualStart = endOfWorkDate(knownFinish || STATUS_LABEL);
    const drivers = milestoneDriverIds[task.id]
      ? milestoneDriverIds[task.id].map((id) => byId.get(id))
      : task.predecessorUids.map((uid) => byUid.get(uid));
    const predecessorFinishes = drivers
      .filter(Boolean)
      .map((predecessor) => predecessor.finish)
      .filter(Boolean);
    task.finish = predecessorFinishes.length
      ? new Date(Math.max(...predecessorFinishes.map((value) => value.getTime())))
      : cloneDate(cursor);
    if (task.finish <= STATUS_DATE) task.finish = cloneDate(cursor);
    task.start = cloneDate(task.actualStart);
    task.resume = cloneDate(task.finish);
  } else {
    const drivers = milestoneDriverIds[task.id]
      ? milestoneDriverIds[task.id].map((id) => byId.get(id))
      : task.predecessorUids.map((uid) => byUid.get(uid));
    const predecessorFinishes = drivers
      .filter(Boolean)
      .map((predecessor) => predecessor.finish)
      .filter(Boolean);
    task.finish = predecessorFinishes.length
      ? new Date(Math.max(...predecessorFinishes.map((value) => value.getTime())))
      : cloneDate(cursor);
    if (task.finish <= STATUS_DATE) task.finish = cloneDate(cursor);
    task.start = cloneDate(task.finish);
  }
  task.totalMinutes = 0;
  task.actualMinutes = 0;
  task.remainingMinutes = 0;
}

const descendants = (summaryTask) => {
  const result = [];
  const startIndex = tasks.indexOf(summaryTask);
  for (let index = startIndex + 1; index < tasks.length; index += 1) {
    const candidate = tasks[index];
    if (candidate.level <= summaryTask.level) break;
    if (candidate.name) result.push(candidate);
  }
  return result;
};

const summaries = tasks
  .filter((task) => task.summary && task.name && task.id !== 0)
  .sort((left, right) => right.level - left.level || right.id - left.id);

for (const task of summaries) {
  const children = descendants(task).filter((child) => !child.summary && child.start && child.finish);
  if (!children.length) continue;
  task.start = new Date(Math.min(...children.map((child) => child.start.getTime())));
  task.finish = new Date(Math.max(...children.map((child) => child.finish.getTime())));
  task.totalMinutes = workMinutesBetween(task.start, task.finish);
  const weighted = children.reduce(
    (sum, child) => sum + child.percent * Math.max(child.totalMinutes, child.milestone ? 1 : 0),
    0,
  );
  const weight = children.reduce(
    (sum, child) => sum + Math.max(child.totalMinutes, child.milestone ? 1 : 0),
    0,
  );
  task.percent = weight ? Math.round(weighted / weight) : 0;
  task.actualMinutes = Math.round(task.totalMinutes * task.percent / 100);
  task.remainingMinutes = Math.max(0, task.totalMinutes - task.actualMinutes);
  const actualStarts = children.map((child) => child.actualStart).filter(Boolean);
  if (actualStarts.length) task.actualStart = new Date(Math.min(...actualStarts.map((item) => item.getTime())));
  if (task.percent === 100) {
    const actualFinishes = children.map((child) => child.actualFinish).filter(Boolean);
    if (actualFinishes.length) task.actualFinish = new Date(Math.max(...actualFinishes.map((item) => item.getTime())));
  }
}

const projectTask = byId.get(0);
const namedLeaves = tasks.filter(
  (task) => task.id !== 0 && task.name && !task.summary && task.start && task.finish,
);
projectTask.start = new Date(Math.min(...namedLeaves.map((task) => task.start.getTime())));
projectTask.finish = new Date(Math.max(...namedLeaves.map((task) => task.finish.getTime())));
projectTask.totalMinutes = workMinutesBetween(projectTask.start, projectTask.finish);
const projectWeighted = namedLeaves.reduce(
  (sum, task) => sum + task.percent * Math.max(task.totalMinutes, task.milestone ? 1 : 0),
  0,
);
const projectWeight = namedLeaves.reduce(
  (sum, task) => sum + Math.max(task.totalMinutes, task.milestone ? 1 : 0),
  0,
);
projectTask.percent = Math.round(projectWeighted / projectWeight);
projectTask.actualMinutes = Math.round(projectTask.totalMinutes * projectTask.percent / 100);
projectTask.remainingMinutes = projectTask.totalMinutes - projectTask.actualMinutes;
projectTask.actualStart = cloneDate(projectTask.start);

const taskState = new Map(tasks.map((task) => [task.uid, task]));

const transformTask = (task) => {
  if (!task.name) {
    const baselineParts = [
      "\n\t\t\t<Baseline>",
      "\t\t\t\t<Number>0</Number>",
      `\t\t\t\t<Start>${task.baselineStart}</Start>`,
      `\t\t\t\t<Finish>${task.baselineFinish}</Finish>`,
      `\t\t\t\t<Duration>${task.baselineDuration}</Duration>`,
      `\t\t\t\t<DurationFormat>${valueOf(task.block, "DurationFormat") || 7}</DurationFormat>`,
      "\t\t\t</Baseline>",
    ];
    return `<Task>${insertAfter(task.block, "EarnedValueMethod", baselineParts.join("\n"))}</Task>`;
  }
  if (!task.start && task.id !== 0) return `<Task>${task.block}</Task>`;
  let block = task.block;
  const start = iso(task.start);
  const finish = iso(task.finish);
  const total = duration(task.totalMinutes || 0);
  const actual = duration(task.actualMinutes || 0);
  const remaining = duration(task.remainingMinutes || 0);

  block = setTag(block, "Start", start);
  block = setTag(block, "Finish", finish);
  block = setTag(block, "Duration", total);
  block = setTag(block, "ManualStart", start);
  block = setTag(block, "ManualFinish", finish);
  block = setTag(block, "ManualDuration", total);
  block = setTag(block, "EarlyStart", start);
  block = setTag(block, "EarlyFinish", finish);
  block = setTag(block, "LateStart", start);
  block = setTag(block, "LateFinish", finish);
  block = setTag(block, "PercentComplete", task.percent);
  block = setTag(block, "PercentWorkComplete", task.percent);
  block = setTag(block, "PhysicalPercentComplete", task.percent);
  block = setTag(block, "ActualDuration", actual);
  block = setTag(block, "RemainingDuration", remaining);
  block = setTag(block, "FreeSlack", 0);
  block = setTag(block, "TotalSlack", 0);
  block = setTag(block, "StartSlack", 0);
  block = setTag(block, "FinishSlack", 0);

  block = removeTag(block, "ActualFinish");
  if (task.actualStart) {
    block = upsertAfter(block, "ActualStart", iso(task.actualStart), "OvertimeWork");
    if (task.actualFinish) {
      block = insertAfter(
        block,
        "ActualStart",
        `\n\t\t\t<ActualFinish>${iso(task.actualFinish)}</ActualFinish>`,
      );
    }
  } else {
    block = removeTag(block, "ActualStart");
  }

  if (task.percent > 0 && task.percent < 100 && task.resume) {
    block = upsertAfter(block, "Stop", iso(STATUS_DATE), "Work");
    block = upsertAfter(block, "Resume", iso(task.resume), "Stop");
    block = setTag(block, "ResumeValid", 1);
  } else {
    block = removeTag(block, "Stop");
    block = removeTag(block, "Resume");
    block = setTag(block, "ResumeValid", 0);
  }

  if (task.id >= 4 && task.id <= 30 && task.originalConstraintDate) {
    block = setTag(block, "ConstraintType", 0);
    block = removeTag(block, "ConstraintDate");
    if (/<Deadline>/.test(block)) {
      block = setTag(block, "Deadline", task.originalConstraintDate);
    } else {
      block = insertAfter(
        block,
        "ConstraintType",
        `\n\t\t\t<Deadline>${task.originalConstraintDate}</Deadline>`,
      );
    }
  }

  const baselineParts = [
    "\n\t\t\t<Baseline>",
    "\t\t\t\t<Number>0</Number>",
    `\t\t\t\t<Start>${task.baselineStart}</Start>`,
    `\t\t\t\t<Finish>${task.baselineFinish}</Finish>`,
  ];
  if (task.baselineDuration) {
    baselineParts.push(`\t\t\t\t<Duration>${task.baselineDuration}</Duration>`);
    baselineParts.push(`\t\t\t\t<DurationFormat>${valueOf(task.block, "DurationFormat") || 7}</DurationFormat>`);
  }
  baselineParts.push("\t\t\t</Baseline>");
  block = insertAfter(block, "EarnedValueMethod", baselineParts.join("\n"));

  if (task.id !== 0) {
    let statusText;
    if (task.percent === 100) {
      statusText = `100% — zakończone; data rzeczywista ${iso(task.actualFinish).slice(0, 10)}. ` +
        "Zakres potwierdzony przez kod, migracje, trasy lub zielone testy repozytorium.";
    } else if (task.percent > 0) {
      statusText = `${task.percent}% — w toku; pozostało ${Math.round(task.remainingMinutes / 60)} h; ` +
        `prognoza zakończenia ${finish.slice(0, 10)}. ` +
        (specificNotes[task.id] || "Zakres częściowo obecny; pozostaje kompletacja i odbiór.");
    } else if (task.milestone) {
      statusText = `0% — nieosiągnięty; prognoza ${finish.slice(0, 10)}.`;
    } else {
      statusText = `0% — nierozpoczęte; estymacja ${Math.round(task.remainingMinutes / 60)} h; ` +
        `prognoza zakończenia ${finish.slice(0, 10)}. ` +
        (specificNotes[task.id] || "Brak kompletnego artefaktu lub dowodu odbioru w repozytorium.");
    }
    block = appendAuditNote(block, statusText);
  }
  return `<Task>${block}</Task>`;
};

let taskIndex = 0;
let output = source.replace(/<Task>[\s\S]*?<\/Task>/g, () => transformTask(tasks[taskIndex++]));

output = setTag(output, "Name", path.basename(outputPath));
output = setTag(output, "LastSaved", "2026-08-26T23:59:00");
output = setTag(output, "FinishDate", iso(projectTask.finish));
output = setTag(output, "StatusDate", iso(STATUS_DATE));
output = setTag(output, "CurrentDate", iso(STATUS_DATE));
output = setTag(output, "MoveRemainingStartsForward", 1);
output = setTag(output, "ActualsInSync", 1);

output = output.replace(/<Assignment>([\s\S]*?)<\/Assignment>/g, (whole, block) => {
  const task = taskState.get(Number(valueOf(block, "TaskUID")));
  if (!task || !task.name || !task.start || !task.finish) return whole;
  let changed = block;
  changed = setTag(changed, "Start", iso(task.start));
  changed = setTag(changed, "Finish", iso(task.finish));
  changed = setTag(changed, "PercentWorkComplete", task.percent);
  return `<Assignment>${changed}</Assignment>`;
});

fs.writeFileSync(outputPath, output, "utf8");

const completedCount = namedLeaves.filter((task) => task.percent === 100).length;
const inProgressCount = namedLeaves.filter((task) => task.percent > 0 && task.percent < 100).length;
const notStartedCount = namedLeaves.filter((task) => task.percent === 0).length;
console.log(JSON.stringify({
  output: path.relative(root, outputPath),
  tasks: tasks.length,
  namedLeaves: namedLeaves.length,
  completedCount,
  inProgressCount,
  notStartedCount,
  percentComplete: projectTask.percent,
  forecastFinish: iso(projectTask.finish),
}, null, 2));
