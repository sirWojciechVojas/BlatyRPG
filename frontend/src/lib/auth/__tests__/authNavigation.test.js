import { describe, expect, it, vi } from "vitest";
import {
  defaultAuthenticatedRoute,
  postAuthenticationTarget,
  safeRedirectTarget,
} from "@/lib/auth/authNavigation";

const router = {
  resolve: vi.fn((path) => ({
    matched: path === "/missing" ? [] : [{}],
    name: path === "/" ? "landing" : "scene-workspace",
    fullPath: path,
  })),
};

describe("authNavigation", () => {
  it("selects admin or table catalogue from the global role", () => {
    expect(defaultAuthenticatedRoute({ user: { role: "ADMIN" } })).toEqual({
      name: "admin",
    });
    expect(defaultAuthenticatedRoute({ user: { role: "gm" } })).toEqual({
      name: "tables",
    });
    expect(defaultAuthenticatedRoute(null)).toEqual({ name: "tables" });
  });

  it("prefers a safe internal redirect", () => {
    expect(
      postAuthenticationTarget(
        router,
        { user: { role: "admin" } },
        "/campaigns/7/scenes",
      ),
    ).toBe("/campaigns/7/scenes");
  });

  it("rejects external, missing and auth-entry redirects", () => {
    expect(safeRedirectTarget(router, "https://evil.test")).toBeNull();
    expect(safeRedirectTarget(router, "//evil.test")).toBeNull();
    expect(safeRedirectTarget(router, "/missing")).toBeNull();
    expect(safeRedirectTarget(router, "/")).toBeNull();
  });
});
