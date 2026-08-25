const tokenIdSet = (values = []) =>
  new Set(values.map(Number).filter(Number.isFinite));

const statusCode = (status) =>
  String(
    typeof status === "string"
      ? status
      : status?.code || status?.id || status?.name || "",
  ).toLocaleLowerCase();

const hasStatus = (token, expected) =>
  (token?.statuses || []).some((status) => expected.has(statusCode(status)));

export const tokenUiFlags = (token = {}, context = {}) => {
  const tokenId = Number(token.id);
  const selectedIds = tokenIdSet(context.selectedIds);
  const waitingIds = tokenIdSet(context.waitingTurnIds);
  const targetedIds = tokenIdSet(context.targetedIds);
  const controlled = token.capabilities?.canControl === true;
  const dead = token.dead === true || hasStatus(token, new Set(["dead"]));
  const defeated =
    dead ||
    token.defeated === true ||
    hasStatus(token, new Set(["defeated", "unconscious"]));
  const activeTurn =
    Number(context.activeTurnId) === tokenId ||
    token.combat?.activeTurn === true;
  const selected = selectedIds.has(tokenId);

  return {
    default: true,
    hover: Number(context.hoveredId) === tokenId,
    selected,
    multiSelected: selected && selectedIds.size > 1,
    dragging: Number(context.draggingId) === tokenId,
    locked: token.locked === true,
    disabled: context.disabled === true || token.disabled === true,
    controlled,
    uncontrolled: !controlled,
    activeTurn,
    waitingTurn:
      !activeTurn &&
      (waitingIds.has(tokenId) || token.combat?.waitingTurn === true),
    targeted: targetedIds.has(tokenId) || token.combat?.targeted === true,
    hidden: token.hidden === true,
    GMOnly: token.gmOnly === true || token.disposition === "secret",
    defeated,
    dead,
  };
};

const STATE_CLASS_NAMES = Object.freeze({
  default: "default",
  hover: "hover",
  selected: "selected",
  multiSelected: "multi-selected",
  dragging: "dragging",
  locked: "locked",
  disabled: "disabled",
  controlled: "controlled",
  uncontrolled: "uncontrolled",
  activeTurn: "active-turn",
  waitingTurn: "waiting-turn",
  targeted: "targeted",
  hidden: "hidden",
  GMOnly: "gm-only",
  defeated: "defeated",
  dead: "dead",
});

export const tokenUiClasses = (flags = {}) =>
  Object.fromEntries(
    Object.entries(STATE_CLASS_NAMES).map(([state, className]) => [
      `token-state--${className}`,
      flags[state] === true,
    ]),
  );

export const activeTokenUiStates = (flags = {}) =>
  Object.keys(STATE_CLASS_NAMES).filter((state) => flags[state] === true);
