import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/vtt/table/TableMovementRequestsPanel.vue",
);
const source = readFileSync(componentPath, "utf8");
const { descriptor } = parse(source, { filename: componentPath });

const componentOptions = () =>
  new Function(
    descriptor.script.content.replace("export default {", "return {"),
  )();

describe("TableMovementRequestsPanel", () => {
  it("counts only pending campaign invitations", () => {
    const pendingInvitations = componentOptions().computed.pendingInvitations;
    expect(
      pendingInvitations.call({
        invitations: [
          { id: 1, status: "pending" },
          { id: 2, status: "accepted" },
          { id: 3, status: "pending" },
        ],
      }),
    ).toHaveLength(2);
  });

  it("shows every member, connection recovery, and existing movement actions", () => {
    const template = descriptor.template.content;

    expect(template).toContain('v-for="member in members"');
    expect(template).toContain('v-if="manualRetryAvailable"');
    expect(template).toContain("$emit('retry')");
    expect(template).toContain("resolve(request.id, 'approve')");
    expect(template).toContain("resolve(request.id, 'reject')");
  });
});
