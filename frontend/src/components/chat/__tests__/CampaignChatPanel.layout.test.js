import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const componentPath = resolve(
  process.cwd(),
  "src/components/chat/CampaignChatPanel.vue",
);
const stylesPath = resolve(
  process.cwd(),
  "src/components/chat/styles/CampaignChatPanel.css",
);
const descriptor = parse(readFileSync(componentPath, "utf8"), {
  filename: componentPath,
}).descriptor;
const styles = readFileSync(stylesPath, "utf8");

describe("CampaignChatPanel composer layout", () => {
  it("keeps message entry and its submit action on one line", () => {
    expect(descriptor.template.content).toMatch(
      /<input\s+v-model="draft"\s+type="text"/s,
    );
    expect(descriptor.template.content).not.toContain("<textarea");
    expect(descriptor.template.content).toContain('<button type="submit"');
    expect(styles).toMatch(
      /\.campaign-chat__composer\s*{[^}]*display:\s*flex;/s,
    );
    expect(styles).toMatch(
      /\.campaign-chat__composer input\s*{[^}]*flex:\s*1 1 auto;/s,
    );
  });

  it("renders an avatar and a transient marker for the newest message", () => {
    expect(descriptor.template.content).toContain(
      'class="campaign-chat__avatar"',
    );
    expect(descriptor.template.content).toContain("message.author.initials");
    expect(descriptor.template.content).not.toContain("authorInitials(");
    expect(descriptor.template.content).toContain("message.author.avatarUrl");
    expect(descriptor.template.content).toContain(
      "message.id === newMessageId",
    );
    expect(descriptor.template.content).toContain(
      'class="campaign-chat__new-badge"',
    );
    expect(styles).toContain("@keyframes campaign-chat-message-arrival");
    expect(styles).toContain("@media (prefers-reduced-motion: reduce)");
  });
});
