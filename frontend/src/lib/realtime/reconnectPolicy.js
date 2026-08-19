const NO_RETRY_CLOSE_CODES = new Set([1000, 1008, 4003, 4010]);

export const shouldReconnectClose = (
  event,
  intentional = false,
  recoverableAuth = false,
) =>
  !intentional &&
  (Number(event?.code) !== 4001 ||
    event?.reason === "auth_expired" ||
    recoverableAuth) &&
  !NO_RETRY_CLOSE_CODES.has(Number(event?.code));

export const realtimeCloseStatus = (code) => {
  if (code === 4001) return "auth_failed";
  if (code === 4003 || code === 1008) return "forbidden";
  if (code === 4010) return "replaced";
  return "disconnected";
};

export const shouldReconnectTicketError = (error) =>
  error?.status !== 401 && error?.status !== 403;

export const reconnectDelay = (attempt, options = {}) => {
  const base = Math.max(250, Number(options.baseDelayMs) || 750);
  const cap = Math.max(base, Number(options.maxDelayMs) || 30_000);
  const jitter = Math.min(0.5, Math.max(0, Number(options.jitter ?? 0.2)));
  const random = options.random || Math.random;
  const exponential = Math.min(cap, base * 2 ** Math.max(0, attempt));
  const factor = 1 + jitter * (random() * 2 - 1);
  return Math.max(250, Math.round(exponential * factor));
};
