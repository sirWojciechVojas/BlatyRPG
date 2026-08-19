export const CLIENT_INSTANCE_KEY = "blatyrpg.realtime.client-instance-id";
export const CLIENT_INSTANCE_PATTERN = /^[A-Za-z0-9_-]{16,128}$/u;

const defaultStorage = () =>
  typeof window === "undefined" ? null : window.sessionStorage;

const randomHex = (cryptoImpl) => {
  if (typeof cryptoImpl?.randomUUID === "function") {
    return cryptoImpl.randomUUID().replace(/[^A-Za-z0-9_-]/gu, "");
  }
  if (typeof cryptoImpl?.getRandomValues === "function") {
    const bytes = new Uint8Array(18);
    cryptoImpl.getRandomValues(bytes);
    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, "0")).join(
      "",
    );
  }
  return `${Date.now().toString(36)}${Math.random().toString(36).slice(2)}`
    .padEnd(24, "0")
    .slice(0, 48);
};

export const getClientInstanceId = (options = {}) => {
  const storage = options.storage ?? defaultStorage();
  try {
    const current = String(storage?.getItem(CLIENT_INSTANCE_KEY) || "");
    if (CLIENT_INSTANCE_PATTERN.test(current)) return current;
  } catch (_error) {
    // A blocked sessionStorage still gets an in-memory identifier.
  }

  const cryptoImpl =
    options.crypto ?? (typeof window === "undefined" ? null : window.crypto);
  const created = `rt_${randomHex(cryptoImpl)}`.slice(0, 128);
  if (!CLIENT_INSTANCE_PATTERN.test(created)) {
    throw new TypeError("client_instance_id_generation_failed");
  }
  try {
    storage?.setItem(CLIENT_INSTANCE_KEY, created);
  } catch (_error) {
    // The returned id remains stable for the lifetime of the caller.
  }
  return created;
};
