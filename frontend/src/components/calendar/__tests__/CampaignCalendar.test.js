import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const campaignPath = resolve(
  process.cwd(),
  "src/components/calendar/CampaignCalendar.vue",
);
const monthPath = resolve(
  process.cwd(),
  "src/components/calendar/CalendarMonthView.vue",
);
const toolbarPath = resolve(
  process.cwd(),
  "src/components/calendar/CalendarToolbar.vue",
);
const timeManagerPath = resolve(
  process.cwd(),
  "src/components/calendar/CalendarTimeManager.vue",
);
const dayPanelPath = resolve(
  process.cwd(),
  "src/components/calendar/CalendarDayPanel.vue",
);
const stylesPath = resolve(
  process.cwd(),
  "src/components/calendar/calendar.css",
);

describe("CampaignCalendar initialization", () => {
  it("guards the month render until its display key resolves", () => {
    const campaign = parse(readFileSync(campaignPath, "utf8"), {
      filename: campaignPath,
    }).descriptor;
    const month = parse(readFileSync(monthPath, "utf8"), {
      filename: monthPath,
    }).descriptor;

    expect(campaign.template.content).toContain(
      `v-if="view === 'month' && displayMonth"`,
    );
    expect(campaign.template.content).toContain(
      ':month-key="displayMonth.key"',
    );
    expect(campaign.script.content).toContain("displayMonth()");
    expect(month.template.content).toContain('v-if="month"');
    expect(month.template.content).toContain("Przygotowywanie miesiąca…");
  });

  it("keeps GM time management visible in the lower-right sidebar", () => {
    const campaign = parse(readFileSync(campaignPath, "utf8"), {
      filename: campaignPath,
    }).descriptor;
    const toolbar = parse(readFileSync(toolbarPath, "utf8"), {
      filename: toolbarPath,
    }).descriptor;
    const timeManager = parse(readFileSync(timeManagerPath, "utf8"), {
      filename: timeManagerPath,
    }).descriptor;
    const dayPanel = parse(readFileSync(dayPanelPath, "utf8"), {
      filename: dayPanelPath,
    }).descriptor;
    const styles = readFileSync(stylesPath, "utf8");

    expect(campaign.template.content).toContain(
      'class="campaign-calendar__sidebar"',
    );
    expect(campaign.template.content).toContain("<CalendarTimeManager");
    expect(campaign.template.content).toContain(
      'v-if="canManage && selectedDate"',
    );
    expect(campaign.template.content).toContain(
      ':selected-date="selectedDate"',
    );
    expect(campaign.template.content).toContain('@set="setSelectedDate"');
    expect(campaign.template.content).not.toContain("@manage");
    expect(campaign.script.content).not.toContain("timeManagerOpen");
    expect(toolbar.template.content).not.toContain("Zarządzaj czasem");
    expect(timeManager.template.content).toContain("Wybrany dzień");
    expect(timeManager.template.content).toContain(
      "{{ selectedDate.formatted }}",
    );
    expect(timeManager.template.content).toContain("Ustaw jako aktualny");
    expect(timeManager.template.content).not.toContain("<form");
    expect(timeManager.template.content).not.toContain("<input");
    expect(timeManager.template.content).not.toContain("<select");
    expect(campaign.script.content).toContain("async setSelectedDate()");
    expect(campaign.script.content).toContain(
      "dayOfYear: this.selectedDate.dayOfYear",
    );
    expect(campaign.script.content).toContain(
      "minuteOfDay: this.worldState.minuteOfDay",
    );
    expect(campaign.template.content).toContain(
      ':current-date="worldState.date.formatted"',
    );
    expect(campaign.template.content).not.toContain("formattedWithTime");
    expect(campaign.script.content).toContain("showTime: false");
    expect(campaign.script.content).not.toContain("shiftTime(");
    expect(dayPanel.template.content).toContain("set-morrslieb");
    expect(styles).toContain(".campaign-calendar__time-manager--embedded");
  });
});
