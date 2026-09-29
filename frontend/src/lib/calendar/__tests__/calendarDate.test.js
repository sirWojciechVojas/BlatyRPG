import { describe, expect, it } from "vitest";
import {
  adjacentSpecialDays,
  calendarDate,
  compareCalendarPosition,
  dayOfYearForMonth,
  eventsForDate,
  monthDates,
} from "../calendarDate";

const definition = Object.freeze({
  daysPerYear: 8,
  numberedDaysPerYear: 6,
  era: { suffix: "ER" },
  dateFormat: {
    regular: "{day} {month}, {year} {era}",
    intercalary: "{special}, {year} {era}",
    time: "{date}, {time}",
  },
  week: {
    names: ["Pierwszy", "Drugi", "Trzeci"],
    anchor: {
      year: 10,
      monthKey: "alpha",
      day: 1,
      weekdayIndex: 0,
    },
  },
  months: [
    {
      key: "alpha",
      name: "Alfa",
      length: 3,
      startDayOfYear: 2,
      startNumberedIndex: 0,
    },
    {
      key: "beta",
      name: "Beta",
      length: 3,
      startDayOfYear: 6,
      startNumberedIndex: 3,
    },
  ],
  intercalaryDays: [
    { key: "opening", name: "Otwarcie", afterMonthKey: null, dayOfYear: 1 },
    {
      key: "middle",
      name: "Przesilenie",
      afterMonthKey: "alpha",
      dayOfYear: 5,
    },
  ],
  seasons: [
    { key: "cold", name: "Chłód", startDayOfYear: 1 },
    { key: "warm", name: "Ciepło", startDayOfYear: 4 },
  ],
  holidays: [{ key: "middle", name: "Przesilenie", dayOfYear: 5 }],
  moonCycles: {
    mannslieb: {
      type: "cycle",
      periodDays: 4,
      anchor: { year: 10, dayOfYear: 1, offset: 0 },
      phases: [
        { key: "new", name: "Nów" },
        { key: "full", name: "Pełnia" },
      ],
      phaseByOffset: ["new", "new", "full", "full"],
    },
  },
});

describe("setting calendar date tools", () => {
  it("formats regular and intercalary dates without assigning a weekday to special days", () => {
    const regular = calendarDate(definition, 10, 2, 8 * 60 + 5);
    const special = calendarDate(definition, 10, 5);

    expect(regular).toMatchObject({
      formatted: "1 Alfa, 10 ER",
      formattedWithTime: "1 Alfa, 10 ER, 08:05",
      weekdayName: "Pierwszy",
    });
    expect(special).toMatchObject({
      formatted: "Przesilenie, 10 ER",
      monthKey: null,
      day: null,
      weekdayName: null,
    });
  });

  it("derives month offsets, moon phases and seasons only from the definition", () => {
    expect(dayOfYearForMonth(definition, "beta", 1)).toBe(6);
    expect(monthDates(definition, 10, "beta")).toHaveLength(3);
    expect(calendarDate(definition, 10, 4)).toMatchObject({
      season: expect.objectContaining({ key: "warm" }),
      mannslieb: expect.objectContaining({ key: "full" }),
    });
    expect(adjacentSpecialDays(definition, "alpha")).toEqual({
      before: [expect.objectContaining({ key: "opening" })],
      after: [expect.objectContaining({ key: "middle" })],
    });
  });

  it("filters events spanning the selected calendar day and compares neutral dates", () => {
    const date = calendarDate(definition, 10, 6);
    const events = [
      {
        id: 1,
        start: { year: 10, dayOfYear: 4, minuteOfDay: 1200 },
        end: { year: 10, dayOfYear: 6, minuteOfDay: 10 },
      },
      {
        id: 2,
        start: { year: 10, dayOfYear: 7, minuteOfDay: 0 },
        end: null,
      },
    ];

    expect(eventsForDate(events, date).map(({ id }) => id)).toEqual([1]);
    expect(
      compareCalendarPosition(events[0].start, events[0].end),
    ).toBeLessThan(0);
  });
});
