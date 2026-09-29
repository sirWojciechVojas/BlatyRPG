import { ProtocolError } from "./protocol-error.js";

const object = (value) =>
  value !== null && typeof value === "object" && !Array.isArray(value);

const exact = (value, allowed) => {
  for (const key of Object.keys(value)) {
    if (!allowed.includes(key)) throw new ProtocolError("unexpected_field", key);
  }
};

const positive = (value, code) => {
  if (!Number.isSafeInteger(value) || value < 1) throw new ProtocolError(code);
  return value;
};

const coordinate = (value, code) => {
  if (typeof value !== "number" || !Number.isFinite(value) || Math.abs(value) > 1000000) {
    throw new ProtocolError(code);
  }
  return value;
};

const requestId = (value) => {
  const id = String(value || "");
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(id)) {
    throw new ProtocolError("request_id_invalid");
  }
  return id;
};

const waypoints = (value) => {
  if (value === undefined) return [];
  if (!Array.isArray(value) || value.length > 20) {
    throw new ProtocolError("token_waypoints_invalid");
  }
  return value.map((point) => {
    if (!object(point)) throw new ProtocolError("token_waypoints_invalid");
    exact(point, ["x", "y"]);
    return {
      x: coordinate(point.x, "token_waypoint_x_invalid"),
      y: coordinate(point.y, "token_waypoint_y_invalid"),
    };
  });
};

const groupMoves = (value) => {
  if (!Array.isArray(value) || value.length < 2 || value.length > 50) {
    throw new ProtocolError("token_group_moves_invalid");
  }
  const ids = new Set();
  return value.map((move) => {
    if (!object(move)) throw new ProtocolError("token_group_moves_invalid");
    exact(move, ["tokenId", "revision", "x", "y", "waypoints"]);
    const tokenId = positive(move.tokenId, "token_id_invalid");
    if (ids.has(tokenId)) throw new ProtocolError("token_group_duplicate");
    ids.add(tokenId);
    return {
      tokenId,
      revision: positive(move.revision, "token_revision_invalid"),
      x: coordinate(move.x, "token_x_invalid"),
      y: coordinate(move.y, "token_y_invalid"),
      waypoints: waypoints(move.waypoints),
    };
  });
};

export const parseTokenMovementMessage = (message) => {
  if (message.type === "token.move.group") {
    exact(message, ["v", "type", "requestId", "sceneId", "moves"]);
    return {
      type: message.type,
      requestId: requestId(message.requestId),
      sceneId: positive(message.sceneId, "scene_id_invalid"),
      moves: groupMoves(message.moves),
    };
  }
  if (message.type === "token.movement.request") {
    exact(message, [
      "v", "type", "requestId", "sceneId", "tokenId", "revision", "x", "y",
      "waypoints",
    ]);
    return {
      type: message.type,
      requestId: requestId(message.requestId),
      sceneId: positive(message.sceneId, "scene_id_invalid"),
      tokenId: positive(message.tokenId, "token_id_invalid"),
      revision: positive(message.revision, "token_revision_invalid"),
      x: coordinate(message.x, "token_x_invalid"),
      y: coordinate(message.y, "token_y_invalid"),
      waypoints: waypoints(message.waypoints),
    };
  }
  if (message.type === "token.movement.resolve") {
    exact(message, ["v", "type", "requestId", "movementRequestId", "decision"]);
    if (!["approve", "reject"].includes(message.decision)) {
      throw new ProtocolError("movement_decision_invalid");
    }
    return {
      type: message.type,
      requestId: requestId(message.requestId),
      movementRequestId: positive(
        message.movementRequestId, "movement_request_id_invalid"
      ),
      decision: message.decision,
    };
  }
  return null;
};
