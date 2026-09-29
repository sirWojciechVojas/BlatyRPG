import assert from "node:assert/strict";
import test from "node:test";
import { ProtocolError, parseAuthenticatedMessage } from "../src/protocol.js";

test("parses map publication notifications", () => {
  assert.deepEqual(
    parseAuthenticatedMessage({
      v: 1,
      type: "map.publish.notify",
      requestId: "map-publication-1",
      mapId: 8,
      mapRevision: 3,
      sceneId: 11,
      sceneRevision: 7,
    }),
    {
      type: "map.publish.notify",
      requestId: "map-publication-1",
      mapId: 8,
      mapRevision: 3,
      sceneId: 11,
      sceneRevision: 7,
    },
  );
});

test("rejects incomplete map publication notifications", () => {
  assert.throws(
    () =>
      parseAuthenticatedMessage({
        v: 1,
        type: "map.publish.notify",
        requestId: "map-publication-1",
        mapId: 8,
        sceneId: 11,
        sceneRevision: 7,
      }),
    ProtocolError,
  );
});
