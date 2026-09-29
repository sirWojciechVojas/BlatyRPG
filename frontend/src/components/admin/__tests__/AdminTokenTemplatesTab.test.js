import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { parse } from "@vue/compiler-sfc";
import { describe, expect, it } from "vitest";

const path = resolve(
  process.cwd(),
  "src/components/admin/AdminTokenTemplatesTab.vue",
);
const descriptor = parse(readFileSync(path, "utf8"), {
  filename: path,
}).descriptor;

describe("AdminTokenTemplatesTab", () => {
  it("offers URL and immutable upload workflows with a protected preview", () => {
    const template = descriptor.template.content;
    const script = descriptor.script.content;

    expect(template).toContain('value="url"');
    expect(template).toContain('value="upload"');
    expect(template).toContain(
      'accept="image/png,image/jpeg,image/webp,image/gif"',
    );
    expect(template).toContain("<AuthenticatedImage");
    expect(script).toContain("URL.createObjectURL(this.file)");
    expect(script).toContain("URL.revokeObjectURL(this.filePreviewUrl)");
  });

  it("uses revision-aware CRUD and excludes session state from its payload", () => {
    const script = descriptor.script.content;
    const payloadStart = script.indexOf("payload() {");
    const saveStart = script.indexOf("async save()", payloadStart);
    const payload = script.slice(payloadStart, saveStart);

    expect(script).toContain("adminApiClient.tokenTemplates()");
    expect(script).toContain("adminApiClient.createTokenTemplate(");
    expect(script).toContain("adminApiClient.updateTokenTemplate(");
    expect(script).toContain("adminApiClient.deleteTokenTemplate(");
    expect(payload).toContain("payload.revision = this.original.revision");
    expect(payload).not.toContain("movementSpent");
    expect(payload).not.toContain("statuses");
    expect(payload).not.toContain("hidden");
    expect(payload).not.toContain("locked");
    expect(payload).not.toContain("visibleTo");
  });
});
