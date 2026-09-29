const modal = (id, titleKey, width, height, content, options = {}) =>
  Object.freeze({
    id,
    titleKey,
    width,
    height,
    content,
    contentClass: `player-hud-modal-shell__body--${content}`,
    requiresManage: options.requiresManage === true,
    placeholder: options.placeholder === true,
  });

export const PLAYER_HUD_MODALS = Object.freeze({
  character: modal(
    "character",
    "vtt.table.playerHud.actions.character",
    1600,
    842,
    "character",
  ),
  combat: modal(
    "combat",
    "vtt.table.playerHud.actions.combat",
    520,
    760,
    "combat",
  ),
  journal: modal(
    "journal",
    "vtt.table.playerHud.actions.journal",
    1180,
    720,
    "journal",
  ),
  bestiary: modal(
    "bestiary",
    "vtt.table.playerHud.actions.bestiary",
    1180,
    760,
    "bestiary",
  ),
  spells: modal(
    "spells",
    "vtt.table.playerHud.actions.spells",
    1120,
    700,
    "spells",
  ),
  shop: modal("shop", "vtt.table.playerHud.actions.shop", 1600, 820, "shop"),
  settings: modal(
    "settings",
    "vtt.table.playerHud.actions.settings",
    920,
    600,
    "settings",
    { requiresManage: true },
  ),
  advance: modal(
    "advance",
    "vtt.table.playerHud.actions.advance",
    720,
    420,
    "placeholder",
    { placeholder: true },
  ),
  history: modal(
    "history",
    "vtt.table.playerHud.actions.history",
    720,
    420,
    "placeholder",
    { placeholder: true },
  ),
  notes: modal(
    "notes",
    "vtt.table.playerHud.actions.notes",
    720,
    420,
    "placeholder",
    { placeholder: true },
  ),
  traits: modal(
    "traits",
    "vtt.table.playerHud.actions.traits",
    720,
    420,
    "placeholder",
    { placeholder: true },
  ),
  purse: modal(
    "purse",
    "vtt.table.playerHud.actions.purse",
    720,
    420,
    "placeholder",
    { placeholder: true },
  ),
  abilities: modal(
    "abilities",
    "vtt.table.playerHud.actions.abilities",
    720,
    420,
    "placeholder",
    { placeholder: true },
  ),
});

export const playerHudModalById = (id) =>
  PLAYER_HUD_MODALS[String(id || "")] || null;

export const isPlayerHudModalId = (id) => Boolean(playerHudModalById(id));
