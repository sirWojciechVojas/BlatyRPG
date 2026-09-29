import { ProtocolError } from "./protocol-error.js";

const exact = (value, allowed) => {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    throw new ProtocolError("token_sync_data_invalid");
  }
  for (const key of Object.keys(value)) {
    if (!allowed.includes(key)) throw new ProtocolError("unexpected_field", key);
  }
};

const positive = (value, code) => {
  if (!Number.isSafeInteger(value) || value < 1) throw new ProtocolError(code);
  return value;
};

const requestId = (value) => {
  const normalized = String(value || "");
  if (!/^[A-Za-z0-9._:-]{1,128}$/.test(normalized)) {
    throw new ProtocolError("request_id_invalid");
  }
  return normalized;
};

const targetList = (value) => {
  if (!Array.isArray(value) || !value.length || value.length > 250) {
    throw new ProtocolError("token_sync_targets_invalid");
  }
  const seen = new Set();
  return value.map((target) => {
    exact(target, ["tokenId", "revision"]);
    const tokenId = positive(target.tokenId, "token_id_invalid");
    if (seen.has(tokenId)) throw new ProtocolError("token_sync_targets_invalid");
    seen.add(tokenId);
    return {
      tokenId,
      revision: positive(target.revision, "token_revision_invalid"),
    };
  });
};

export const parseTokenSyncMessage = (message) => {
  if (message.type !== "token.sync.command") return null;
  exact(message, ["v", "type", "requestId", "action", "data"]);
  const action = String(message.action || "");
  const data = message.data;
  if (["transfer", "createLinks"].includes(action)) {
    exact(data, ["sourceTokenId", "sourceRevision", "targets"]);
    return {
      type: message.type,
      requestId: requestId(message.requestId),
      action,
      data: {
        sourceTokenId: positive(data.sourceTokenId, "token_id_invalid"),
        sourceRevision: positive(
          data.sourceRevision,
          "token_revision_invalid",
        ),
        targets: targetList(data.targets),
      },
    };
  }
  if (action === "updateLink") {
    exact(data, ["linkId", "enabled"]);
    if (typeof data.enabled !== "boolean") {
      throw new ProtocolError("token_sync_enabled_invalid");
    }
    return {
      type: message.type,
      requestId: requestId(message.requestId),
      action,
      data: {
        linkId: positive(data.linkId, "token_sync_link_id_invalid"),
        enabled: data.enabled,
      },
    };
  }
  if (["applyLink", "deleteLink"].includes(action)) {
    exact(data, ["linkId"]);
    return {
      type: message.type,
      requestId: requestId(message.requestId),
      action,
      data: { linkId: positive(data.linkId, "token_sync_link_id_invalid") },
    };
  }
  throw new ProtocolError("token_sync_action_invalid");
};
