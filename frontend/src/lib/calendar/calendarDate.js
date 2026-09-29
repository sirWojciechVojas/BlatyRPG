const mod = (value, divisor) => ((value % divisor) + divisor) % divisor;

export const monthByKey = (definition, key) =>
  definition?.months?.find((month) => month.key === key) || null;

export const dayOfYearForMonth = (definition, monthKey, day) => {
  const month = monthByKey(definition, monthKey);
  const numeric = Number(day);
  if (
    !month ||
    !Number.isInteger(numeric) ||
    numeric < 1 ||
    numeric > month.length
  ) {
    return null;
  }
  return Number(month.startDayOfYear) + numeric - 1;
};

export const weekdayIndexForDate = (definition, year, month, day) => {
  const anchor = definition.week.anchor;
  const anchorMonth = monthByKey(definition, anchor.monthKey);
  if (!anchorMonth) return null;
  const current =
    (Number(year) - Number(anchor.year)) *
      Number(definition.numberedDaysPerYear) +
    Number(month.startNumberedIndex) +
    Number(day) -
    1;
  const anchored =
    Number(anchorMonth.startNumberedIndex) + Number(anchor.day) - 1;
  return mod(
    Number(anchor.weekdayIndex) + current - anchored,
    definition.week.names.length,
  );
};

export const calendarDate = (definition, year, dayOfYear, minuteOfDay = 0) => {
  if (!definition) return null;
  const numericDay = Number(dayOfYear);
  const special = definition.intercalaryDays.find(
    (item) => Number(item.dayOfYear) === numericDay,
  );
  let month = null;
  let day = null;
  let weekdayIndex = null;
  if (!special) {
    month = definition.months.find(
      (item) =>
        numericDay >= Number(item.startDayOfYear) &&
        numericDay < Number(item.startDayOfYear) + Number(item.length),
    );
    if (month) {
      day = numericDay - Number(month.startDayOfYear) + 1;
      weekdayIndex = weekdayIndexForDate(definition, year, month, day);
    }
  }
  const seasons = [...(definition.seasons || [])].sort(
    (a, b) => Number(a.startDayOfYear) - Number(b.startDayOfYear),
  );
  let season = seasons.length ? seasons[seasons.length - 1] : null;
  for (const candidate of seasons) {
    if (Number(candidate.startDayOfYear) <= numericDay) season = candidate;
  }
  const holidays = (definition.holidays || []).filter(
    (item) => Number(item.dayOfYear) === numericDay,
  );
  const cycle = definition.moonCycles?.mannslieb;
  let mannslieb = null;
  if (cycle?.type === "cycle") {
    const serial =
      (Number(year) - Number(cycle.anchor.year)) *
        Number(definition.daysPerYear) +
      numericDay -
      Number(cycle.anchor.dayOfYear) +
      Number(cycle.anchor.offset || 0);
    const offset = mod(serial, Number(cycle.periodDays));
    const key = cycle.phaseByOffset[offset];
    mannslieb = {
      ...(cycle.phases.find((phase) => phase.key === key) || {}),
      cycleOffset: offset,
    };
  }
  const minute = Math.max(0, Math.min(1439, Number(minuteOfDay) || 0));
  const time = `${String(Math.floor(minute / 60)).padStart(2, "0")}:${String(
    minute % 60,
  ).padStart(2, "0")}`;
  const replacements = {
    "{day}": String(day ?? ""),
    "{month}": month?.name || "",
    "{special}": special?.name || "",
    "{year}": String(year),
    "{era}": definition.era?.suffix || "",
  };
  const template = special
    ? definition.dateFormat.intercalary
    : definition.dateFormat.regular;
  const formatted = Object.entries(replacements).reduce(
    (value, [token, replacement]) => value.split(token).join(replacement),
    template,
  );
  return {
    year: Number(year),
    dayOfYear: numericDay,
    minuteOfDay: minute,
    time,
    monthKey: month?.key || null,
    monthName: month?.name || null,
    day,
    specialDayKey: special?.key || null,
    specialDayName: special?.name || null,
    weekdayIndex,
    weekdayName:
      weekdayIndex === null ? null : definition.week.names[weekdayIndex],
    season,
    holidays,
    mannslieb,
    formatted,
    formattedWithTime: definition.dateFormat.time
      .replace("{date}", formatted)
      .replace("{time}", time),
  };
};

export const datesForYear = (definition, year) =>
  Array.from({ length: Number(definition?.daysPerYear) || 0 }, (_, index) =>
    calendarDate(definition, year, index + 1),
  );

export const monthDates = (definition, year, monthKey) => {
  const month = monthByKey(definition, monthKey);
  if (!month) return [];
  return Array.from({ length: Number(month.length) }, (_, index) =>
    calendarDate(definition, year, Number(month.startDayOfYear) + index),
  );
};

export const compareCalendarPosition = (left, right) =>
  Number(left?.year) - Number(right?.year) ||
  Number(left?.dayOfYear) - Number(right?.dayOfYear) ||
  Number(left?.minuteOfDay ?? left?.minute ?? 0) -
    Number(right?.minuteOfDay ?? right?.minute ?? 0);

export const eventsForDate = (events, date) =>
  (events || []).filter((event) => {
    const start = event.start;
    const end = event.end || start;
    const point = {
      year: date.year,
      dayOfYear: date.dayOfYear,
      minuteOfDay: 0,
    };
    const endOfDay = { ...point, minuteOfDay: 1439 };
    return (
      compareCalendarPosition(start, endOfDay) <= 0 &&
      compareCalendarPosition(end, point) >= 0
    );
  });

export const adjacentSpecialDays = (definition, monthKey) => {
  const index = definition.months.findIndex((month) => month.key === monthKey);
  if (index < 0) return { before: [], after: [] };
  const previousKey = index > 0 ? definition.months[index - 1].key : null;
  return {
    before: definition.intercalaryDays.filter(
      (day) => day.afterMonthKey === previousKey,
    ),
    after: definition.intercalaryDays.filter(
      (day) => day.afterMonthKey === monthKey,
    ),
  };
};

export const morrsliebForDate = (overrides, date) =>
  (overrides || []).find(
    (item) =>
      Number(item.year) === Number(date?.year) &&
      Number(item.dayOfYear) === Number(date?.dayOfYear),
  )?.phase || null;
