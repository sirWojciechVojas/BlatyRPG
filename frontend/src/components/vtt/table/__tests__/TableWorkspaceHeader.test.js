import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TableWorkspaceHeader.vue",
);
const stylesPath = resolve(
  process.cwd(),
  "src/components/vtt/scene/styles/table-header.css",
);
const workspaceOptionsPath = resolve(
  process.cwd(),
  "src/views/options/SceneWorkspaceView.options.js",
);

describe("TableWorkspaceHeader calendar clock", () => {
  it("shows the authoritative world date in the upper-right header and opens the calendar", () => {
    const descriptor = parse(readFileSync(componentPath, "utf8"), {
      filename: componentPath,
    }).descriptor;
    const styles = readFileSync(stylesPath, "utf8");
    const workspaceOptions = readFileSync(workspaceOptionsPath, "utf8");

    expect(descriptor.template.content).toContain('v-if="calendarDate"');
    expect(descriptor.template.content).toContain("{{ calendarDate }}");
    expect(descriptor.template.content).toContain('v-if="calendarTime"');
    expect(descriptor.template.content).toContain("$emit('open-calendar')");
    expect(descriptor.script.content).toContain(
      'calendarDate: { type: String, default: "" }',
    );
    expect(descriptor.script.content).toContain(
      'calendarTime: { type: String, default: "" }',
    );
    expect(styles).toContain(".table-workspace-header__calendar");
    expect(styles).toContain("text-align: right");
    expect(workspaceOptions).toContain("calendarCurrentDate()");
    expect(workspaceOptions).toContain("calendarCurrentTime()");
    expect(workspaceOptions).toMatch(
      /calendarCurrentTime\(\)\s*{\s*return "";/,
    );
  });
});
