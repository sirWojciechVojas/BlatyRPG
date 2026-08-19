const ROUTE_FALLBACKS = Object.freeze({
  public: new Set([
    "landing",
    "login",
    "about",
    "register",
    "password-reset-request",
    "password-reset-confirm",
    "forbidden",
    "not-found",
  ]),
  workspace: new Set(["scene-workspace", "character-workspace"]),
  overlayNavigation: new Set(["dice", "shop-gm"]),
  hiddenNavigation: new Set(["landing"]),
  disabledUi: new Set(["dice"]),
});

const UI_LAYOUTS = new Set(["app", "public", "workspace", "overlay"]);
const NAVIGATION_MODES = new Set(["standard", "overlay", "hidden"]);

export const UI_ROOT_CLASS_NAMES = Object.freeze([
  "ui-system-active",
  "ui-shell",
  "ui-shell--app",
  "ui-shell--public",
  "ui-shell--workspace",
  "ui-shell--overlay",
  "ui-navigation--overlay",
]);

function routeName(route) {
  return typeof route?.name === "string" ? route.name : "";
}

function mergedMeta(route) {
  const matchedMeta = Array.isArray(route?.matched)
    ? route.matched.reduce(
        (meta, record) => ({ ...meta, ...(record?.meta || {}) }),
        {},
      )
    : {};

  return { ...matchedMeta, ...(route?.meta || {}) };
}

function readMeta(meta, keys) {
  for (const key of keys) {
    if (Object.prototype.hasOwnProperty.call(meta, key)) {
      return meta[key];
    }
  }

  const nestedUi = meta?.ui;
  if (!nestedUi || typeof nestedUi !== "object") {
    return undefined;
  }

  for (const key of keys) {
    if (Object.prototype.hasOwnProperty.call(nestedUi, key)) {
      return nestedUi[key];
    }
  }

  return undefined;
}

function readBoolean(meta, keys) {
  const value = readMeta(meta, keys);
  return typeof value === "boolean" ? value : undefined;
}

function readEnum(meta, keys, allowed) {
  const value = readMeta(meta, keys);
  if (typeof value !== "string") {
    return undefined;
  }

  const normalized = value.toLowerCase();
  return allowed.has(normalized) ? normalized : undefined;
}

export function resolveRouteUi(route = {}) {
  const name = routeName(route);
  const path = typeof route?.path === "string" ? route.path : "";
  const meta = mergedMeta(route);
  const configuredLayout = readEnum(meta, ["uiLayout", "layout"], UI_LAYOUTS);
  const workspace = readBoolean(meta, ["workspace", "isWorkspace"]);
  const publicRoute = readBoolean(meta, ["public", "isPublic"]);

  let layout = configuredLayout;
  if (!layout && workspace === true) {
    layout = "workspace";
  } else if (!layout && publicRoute === true) {
    layout = "public";
  } else if (
    !layout &&
    workspace !== false &&
    ROUTE_FALLBACKS.workspace.has(name)
  ) {
    layout = "workspace";
  } else if (!layout && ROUTE_FALLBACKS.overlayNavigation.has(name)) {
    layout = "overlay";
  } else if (
    !layout &&
    publicRoute !== false &&
    ROUTE_FALLBACKS.public.has(name)
  ) {
    layout = "public";
  }
  layout ||= "app";

  const enabledSetting = readBoolean(meta, ["uiSystem", "designSystem"]);
  const isProtectedLegacyRoute =
    ROUTE_FALLBACKS.disabledUi.has(name) || path === "/dice";
  const enabled = isProtectedLegacyRoute ? false : (enabledSetting ?? true);

  let navigation = readEnum(
    meta,
    ["navigation", "navigationMode", "nav"],
    NAVIGATION_MODES,
  );
  const showNavigationSetting = readBoolean(meta, ["showNavigation"]);
  const hideNavigationSetting = readBoolean(meta, ["hideNavigation"]);

  if (!navigation && hideNavigationSetting === true) {
    navigation = "hidden";
  } else if (
    !navigation &&
    (workspace === true || configuredLayout === "workspace")
  ) {
    navigation = "standard";
  } else if (!navigation && ROUTE_FALLBACKS.overlayNavigation.has(name)) {
    navigation = "overlay";
  } else if (!navigation) {
    navigation = "standard";
  }

  const showNavigation =
    showNavigationSetting ??
    (hideNavigationSetting !== true &&
      navigation !== "hidden" &&
      !ROUTE_FALLBACKS.hiddenNavigation.has(name));

  if (!showNavigation) {
    navigation = "hidden";
  }

  return {
    enabled,
    layout,
    navigation,
    showNavigation,
    isPublic: publicRoute ?? layout === "public",
    isWorkspace: workspace ?? layout === "workspace",
  };
}

export function routeUiRootClasses(routeUi) {
  if (!routeUi?.enabled) {
    return [];
  }

  const classes = [
    "ui-system-active",
    "ui-shell",
    "ui-shell--" + routeUi.layout,
  ];

  if (routeUi.navigation === "overlay") {
    classes.push("ui-navigation--overlay");
  }

  return classes;
}
