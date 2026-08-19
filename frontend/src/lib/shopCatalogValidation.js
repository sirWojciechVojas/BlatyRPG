const VALID_LEVELS = new Set(["domain", "group", "type"]);

export const validateShopCatalog = (nodes = []) => {
  const errors = [];
  const ids = new Set();
  const byId = new Map();

  (nodes || []).forEach((node, idx) => {
    const id = String(node?.id || "").trim();
    if (!id) {
      errors.push(`Node at index ${idx} has empty id.`);
      return;
    }
    if (ids.has(id)) {
      errors.push(`Duplicate node id: ${id}`);
      return;
    }
    ids.add(id);
    byId.set(id, node);

    if (!VALID_LEVELS.has(node?.level)) {
      errors.push(`Node ${id} has invalid level: ${String(node?.level || "")}`);
    }
    if (!String(node?.namePl || "").trim()) {
      errors.push(`Node ${id} has empty namePl.`);
    }
  });

  (nodes || []).forEach((node) => {
    const id = String(node?.id || "").trim();
    if (!id) {
      return;
    }
    const parentId = node?.parentId;
    if (parentId === null || parentId === undefined || parentId === "") {
      return;
    }
    if (!byId.has(String(parentId))) {
      errors.push(
        `Node ${id} references missing parentId: ${String(parentId)}`,
      );
    }
  });

  return {
    valid: errors.length === 0,
    errors,
  };
};

export default validateShopCatalog;
