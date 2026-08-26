import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

test("validates light changes without accepting client campaign scope", () => {
  const create = {
    v: 1,
    type: "light.change",
    requestId: "light-create-1",
    operation: "create",
    sceneId: 4,
    changes: {
      x: 100,
      y: 150,
      brightRadius: 200,
      dimRadius: 400,
      color: "#FFD27A",
      opacity: 0.8,
      softness: 0.4,
      gradualIllumination: true,
      darknessMin: 0.2,
      darknessMax: 0.9,
      sourceType: "darkness",
      providesVision: false,
      constrainedByWalls: true,
      animation: "vortex",
      animationSpeed: 1.5,
      animationIntensity: 0.6,
      elevation: 5,
    },
  };
  assert.deepEqual(parseAuthenticatedMessage(create), {
    type: "light.change",
    requestId: "light-create-1",
    operation: "create",
    sceneId: 4,
    changes: create.changes,
  });
  assert.throws(
    () => parseAuthenticatedMessage({ ...create, campaignId: 99 }),
    (error) =>
      error instanceof ProtocolError && error.code === "unexpected_field",
  );
  assert.throws(
    () => parseAuthenticatedMessage({ ...create, changes: { x: 100 } }),
    (error) =>
      error instanceof ProtocolError &&
      error.code === "light_geometry_required",
  );
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        ...create,
        changes: { x: 100, y: 150, brightRadius: 500, dimRadius: 100 },
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "light_radius_invalid",
  );
});

test("rejects invalid advanced light ranges", () => {
  const base = {
    v: 1,
    type: "light.change",
    requestId: "light-update-range",
    operation: "update",
    sceneId: 4,
    lightId: 8,
    revision: 1,
  };
  assert.throws(
    () => parseAuthenticatedMessage({
      ...base,
      changes: { darknessMin: 0.8, darknessMax: 0.2 },
    }),
    (error) => error.code === "light_darkness_range_invalid",
  );
  assert.throws(
    () => parseAuthenticatedMessage({ ...base, changes: { animation: "rainbow" } }),
    (error) => error.code === "light_animation_invalid",
  );
});

test("requires optimistic revisions for light updates", () => {
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "light.change",
        requestId: "light-update-1",
        operation: "update",
        sceneId: 4,
        lightId: 8,
        changes: { enabled: false },
      }),
    (error) =>
      error instanceof ProtocolError && error.code === "light_change_invalid",
  );
});

test("accepts a bounded request to synchronize authoritative scene lighting", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "light.change",
      requestId: "scene-lighting-sync-1",
      operation: "syncScene",
      sceneId: 4,
    }),
    {
      type: "light.change",
      requestId: "scene-lighting-sync-1",
      operation: "syncScene",
      sceneId: 4,
    },
  );
});
