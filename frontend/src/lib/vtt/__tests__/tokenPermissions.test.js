import { describe, expect, it } from "vitest";
import { normalizeTokenPermissionScope } from "@/lib/vtt/tokenPermissions";

describe("tokenPermissions", () => {
  it("normalizes a selected-player scope", () => {
    expect(
      normalizeTokenPermissionScope({ mode: "users", userIds: [8, "3", 8] }),
    ).toEqual({ mode: "users", userIds: [3, 8] });
  });

  it("fails closed for invalid stored scopes", () => {
    expect(normalizeTokenPermissionScope({ mode: "invalid" })).toEqual({
      mode: "gm",
      userIds: [],
    });
  });
});
