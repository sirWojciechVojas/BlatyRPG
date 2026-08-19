export const resolveRealtimeUrl = (locationValue) => {
  const configured = String(process.env.VUE_APP_REALTIME_URL || "").trim();
  if (configured) return configured;
  const candidatePath = String(
    process.env.VUE_APP_REALTIME_PATH || "/realtime",
  ).trim();
  const path =
    candidatePath.startsWith("/") && !candidatePath.startsWith("//")
      ? candidatePath
      : "/realtime";
  const location =
    locationValue ?? (typeof window === "undefined" ? null : window.location);
  if (!location) return path;
  const protocol = location.protocol === "https:" ? "wss:" : "ws:";
  return `${protocol}//${location.host}${path}`;
};
