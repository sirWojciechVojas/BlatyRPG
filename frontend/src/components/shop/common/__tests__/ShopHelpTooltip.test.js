import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import { describe, expect, it } from "vitest";

describe("ShopHelpTooltip", () => {
  it("uses a keyboard-accessible Bootstrap tooltip rendered outside panels", () => {
    const source = readFileSync(
      resolve(process.cwd(), "src/components/shop/common/ShopHelpTooltip.vue"),
      "utf8",
    );

    expect(source).toContain('import { Tooltip } from "bootstrap";');
    expect(source).toContain('data-bs-toggle="tooltip"');
    expect(source).toContain('container: "body"');
    expect(source).toContain(':aria-label="label || text"');
    expect(source).toContain("text-transform: none !important;");
    expect(source).not.toContain("shop-help-tooltip__bubble");
  });
});
