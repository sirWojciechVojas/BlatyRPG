import assert from "node:assert/strict";
import test from "node:test";
import { createConfig } from "../src/config.js";
import { TEST_SECRET } from "./helpers.js";

test("configuration is fail-closed for secrets and origins", () => {
  assert.throws(() => createConfig({ WS_ALLOWED_ORIGINS: "https://allowed.test" }), /SECRET/);
  assert.throws(
    () => createConfig({ WS_TICKET_SECRET: TEST_SECRET, WS_ALLOWED_ORIGINS: "*" }),
    /Wildcard/,
  );
  assert.throws(() => createConfig({ WS_TICKET_SECRET: TEST_SECRET }), /ORIGINS/);
});

test("configuration normalizes an exact origin allowlist", () => {
  const config = createConfig({
    WS_TICKET_SECRET: TEST_SECRET,
    WS_ALLOWED_ORIGINS: "https://example.test/path, http://localhost:8080",
    WS_PORT: "0",
  });
  assert.deepEqual(config.allowedOrigins, ["https://example.test", "http://localhost:8080"]);
  assert.equal(config.port, 0);
  assert.equal(config.path, "/realtime");
});
