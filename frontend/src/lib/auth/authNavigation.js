const canonicalRole = (session) =>
  String(session?.user?.role || "player").toLowerCase();

export const defaultAuthenticatedRoute = (session) =>
  canonicalRole(session) === "admin" ? { name: "admin" } : { name: "tables" };

export const safeRedirectTarget = (router, value) => {
  if (typeof value !== "string" || !value.startsWith("/")) return null;
  if (value.startsWith("//") || value.includes("\\")) return null;

  try {
    const resolved = router.resolve(value);
    if (!resolved.matched.length || resolved.name === "not-found") return null;
    if (["landing", "home", "login"].includes(resolved.name)) return null;
    return resolved.fullPath;
  } catch (_error) {
    return null;
  }
};

export const postAuthenticationTarget = (router, session, redirect) =>
  safeRedirectTarget(router, redirect) || defaultAuthenticatedRoute(session);
