import { describe, expect, it } from "vitest";
import {
  resolveAdminUserApiFieldErrors,
  validateAdminUserDraft,
} from "@/lib/admin/adminUserValidation";

describe("administrator user validation", () => {
  it("accepts a complete account payload", () => {
    expect(
      validateAdminUserDraft({
        username: "user001",
        email: "user001@blatyrpg.local",
        password: "Temporary123",
        role: "user",
      }),
    ).toEqual({});
  });

  it("identifies every invalid field before sending the request", () => {
    expect(
      validateAdminUserDraft(
        { username: "x", email: "wrong", password: "short", role: "gm" },
        (field) => `field:${field}`,
      ),
    ).toEqual({
      username: "field:username",
      email: "field:email",
      password: "field:password",
      role: "field:role",
    });
  });

  it("distinguishes duplicate API values from malformed values", () => {
    expect(
      resolveAdminUserApiFieldErrors(
        {
          username: "Ta nazwa użytkownika jest już zajęta.",
          password: "Password must meet the policy.",
        },
        (field) => `field:${field}`,
      ),
    ).toEqual({
      username: "field:usernameTaken",
      password: "field:password",
    });
  });
});
