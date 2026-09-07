const ROOT_GROUPS = Object.freeze([
  { id: "players", root: true },
  { id: "npcs", root: true },
]);

const text = (value) => String(value || "").trim();

const byName = (left, right) =>
  text(left?.name).localeCompare(text(right?.name), undefined, {
    sensitivity: "base",
  });

const defaultGroupId = (character) =>
  Number(character?.ownerUserId) > 0 ? "players" : "npcs";

const usableGroup = (group) =>
  group &&
  typeof group === "object" &&
  /^custom-[a-z0-9-]+$/u.test(String(group.id || "")) &&
  text(group.name) &&
  text(group.parentId);

export const createCharacterGroupLayout = (raw, characters = null) => {
  const saved = raw && typeof raw === "object" ? raw : {};
  const groups = Array.isArray(saved.groups)
    ? saved.groups.filter(usableGroup).map((group) => ({
        id: String(group.id),
        name: text(group.name).slice(0, 80),
        parentId: String(group.parentId || ""),
      }))
    : [];
  const groupIds = new Set([
    ...ROOT_GROUPS.map((group) => group.id),
    ...groups.map((group) => group.id),
  ]);
  const orderedIds = Array.isArray(saved.order)
    ? saved.order
        .map(String)
        .filter((id, index, list) => list.indexOf(id) === index)
    : [];
  const hasCharacters = Array.isArray(characters);
  const assignment =
    saved.assignment && typeof saved.assignment === "object"
      ? Object.fromEntries(
          Object.entries(saved.assignment)
            .filter(([, groupId]) => groupIds.has(String(groupId)))
            .map(([characterId, groupId]) => [
              String(characterId),
              String(groupId),
            ]),
        )
      : {};
  const collapsed =
    saved.collapsed && typeof saved.collapsed === "object"
      ? Object.fromEntries(
          Object.entries(saved.collapsed).filter(
            ([groupId, value]) =>
              groupIds.has(String(groupId)) && value === true,
          ),
        )
      : {};
  const activeGroupId = groupIds.has(String(saved.activeGroupId || ""))
    ? String(saved.activeGroupId)
    : "players";

  (hasCharacters ? characters : []).forEach((character) => {
    const id = String(character.id);
    if (!assignment[id]) assignment[id] = defaultGroupId(character);
  });

  const knownOrder = orderedIds;
  const missingOrder = (hasCharacters ? characters : [])
    .filter((character) => !knownOrder.includes(String(character.id)))
    .sort(byName)
    .map((character) => String(character.id));

  return {
    version: 1,
    groups,
    assignment,
    order: [...knownOrder, ...missingOrder],
    collapsed,
    activeGroupId,
  };
};

export const characterGroupTree = (layout, characters = []) => {
  const normalized = createCharacterGroupLayout(layout, characters);
  const groups = [
    ...ROOT_GROUPS.map((group) => ({
      ...group,
      name: group.id,
      parentId: null,
    })),
    ...normalized.groups.map((group) => ({ ...group, root: false })),
  ];
  const byId = new Map(
    groups.map((group) => [
      group.id,
      { ...group, children: [], characters: [] },
    ]),
  );
  const order = new Map(normalized.order.map((id, index) => [id, index]));
  characters.forEach((character) => {
    const groupId =
      normalized.assignment[String(character.id)] || defaultGroupId(character);
    (byId.get(groupId) || byId.get(defaultGroupId(character))).characters.push(
      character,
    );
  });
  byId.forEach((group) => {
    group.characters.sort(
      (left, right) =>
        (order.get(String(left.id)) ?? Number.MAX_SAFE_INTEGER) -
          (order.get(String(right.id)) ?? Number.MAX_SAFE_INTEGER) ||
        byName(left, right),
    );
    if (group.parentId && byId.has(group.parentId))
      byId.get(group.parentId).children.push(group);
  });
  const sortGroups = (groupsToSort) => {
    groupsToSort.sort((left, right) => left.name.localeCompare(right.name));
    groupsToSort.forEach((group) => sortGroups(group.children));
  };
  const roots = ROOT_GROUPS.map((group) => byId.get(group.id));
  roots.forEach((group) => sortGroups(group.children));
  return roots;
};

export const appendGroup = (layout, parentId, name) => {
  const normalized = createCharacterGroupLayout(layout);
  const parent = String(parentId || "");
  if (
    !text(name) ||
    ![
      "players",
      "npcs",
      ...normalized.groups.map((group) => group.id),
    ].includes(parent)
  ) {
    return normalized;
  }
  return {
    ...normalized,
    groups: [
      ...normalized.groups,
      {
        id: `custom-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 7)}`,
        name: text(name).slice(0, 80),
        parentId: parent,
      },
    ],
  };
};

export const renameGroup = (layout, groupId, name) => {
  const normalized = createCharacterGroupLayout(layout);
  if (!text(name) || ROOT_GROUPS.some((group) => group.id === groupId))
    return normalized;
  return {
    ...normalized,
    groups: normalized.groups.map((group) =>
      group.id === groupId
        ? { ...group, name: text(name).slice(0, 80) }
        : group,
    ),
  };
};

export const moveCharacterToGroup = (layout, characterId, groupId) => {
  const normalized = createCharacterGroupLayout(layout);
  const target = String(groupId || "");
  const groups = new Set([
    "players",
    "npcs",
    ...normalized.groups.map((group) => group.id),
  ]);
  if (!groups.has(target)) return normalized;
  const id = String(characterId);
  return {
    ...normalized,
    assignment: { ...normalized.assignment, [id]: target },
    order: [...normalized.order.filter((item) => item !== id), id],
  };
};

export const reorderCharacter = (
  layout,
  characterId,
  siblingIds,
  direction,
) => {
  const normalized = createCharacterGroupLayout(layout);
  const id = String(characterId);
  const siblings = siblingIds.map(String);
  const index = siblings.indexOf(id);
  const targetIndex = index + Number(direction);
  if (index < 0 || targetIndex < 0 || targetIndex >= siblings.length)
    return normalized;
  const targetId = siblings[targetIndex];
  const order = normalized.order.slice();
  const currentOrder = order.indexOf(id);
  const targetOrder = order.indexOf(targetId);
  if (currentOrder < 0 || targetOrder < 0) return normalized;
  order.splice(currentOrder, 1);
  order.splice(targetOrder, 0, id);
  return { ...normalized, order };
};

export const placeCharacterBefore = (
  layout,
  characterId,
  beforeCharacterId,
) => {
  const normalized = createCharacterGroupLayout(layout);
  const id = String(characterId);
  const beforeId = String(beforeCharacterId);
  if (id === beforeId) return normalized;
  const order = normalized.order.filter((item) => item !== id);
  const targetIndex = order.indexOf(beforeId);
  if (targetIndex < 0) return normalized;
  order.splice(targetIndex, 0, id);
  return { ...normalized, order };
};
