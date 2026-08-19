import { computed, reactive } from "vue";

const DEFAULT_DURATION = 4200;
const MAX_QUEUE_LENGTH = 12;
const VALID_TYPES = new Set(["success", "error", "warning", "info"]);
const VALID_ZONES = new Set(["buy", "sell", "system", "all"]);

const typeDefaults = {
  success: {
    title: "Transakcja zakończona",
    icon: "bi bi-stars",
    duration: 4200,
  },
  error: {
    title: "Kupiec odmawia",
    icon: "bi bi-exclamation-octagon-fill",
    duration: 5600,
  },
  warning: {
    title: "Uwaga na rachunek",
    icon: "bi bi-exclamation-triangle-fill",
    duration: 5000,
  },
  info: {
    title: "Wieść z kontuaru",
    icon: "bi bi-info-circle-fill",
    duration: 4200,
  },
};

const state = reactive({
  queue: [],
});

const timers = new Map();

const normalizeType = (type) => {
  const normalized = String(type || "info").toLowerCase();
  return VALID_TYPES.has(normalized) ? normalized : "info";
};

const normalizeZone = (zone) => {
  const normalized = String(zone || "system").toLowerCase();
  return VALID_ZONES.has(normalized) ? normalized : "system";
};

const normalizeText = (value) => String(value || "").trim();

const notificationMatchesZone = (notification, zone) =>
  notification.zone === zone || notification.zone === "all";

const sortNewestFirst = (left, right) => right.createdAt - left.createdAt;

export function dismissShopNotification(id) {
  const normalizedId = Number(id);
  const index = state.queue.findIndex((entry) => entry.id === normalizedId);
  if (index >= 0) {
    state.queue.splice(index, 1);
  }
  const timer = timers.get(normalizedId);
  if (timer) {
    clearTimeout(timer);
    timers.delete(normalizedId);
  }
}

export function clearShopNotificationZone(zone = "all") {
  const normalizedZone = normalizeZone(zone);
  [...state.queue].forEach((entry) => {
    if (
      normalizedZone === "all" ||
      notificationMatchesZone(entry, normalizedZone)
    ) {
      dismissShopNotification(entry.id);
    }
  });
}

export function clearAllShopNotifications() {
  [...timers.values()].forEach((timer) => clearTimeout(timer));
  timers.clear();
  state.queue.splice(0, state.queue.length);
}

export function notifyShop(payload = {}) {
  const type = normalizeType(payload.type);
  const defaults = typeDefaults[type];
  const createdAt = Date.now();
  const id = createdAt + Math.floor(Math.random() * 1000);
  const duration = Number.isFinite(Number(payload.duration))
    ? Math.max(0, Number(payload.duration))
    : defaults.duration || DEFAULT_DURATION;
  const notification = {
    id,
    type,
    zone: normalizeZone(payload.zone),
    title: normalizeText(payload.title) || defaults.title,
    message: normalizeText(payload.message),
    details: payload.details || "",
    icon: normalizeText(payload.icon) || defaults.icon,
    duration,
    createdAt,
  };

  if (!notification.message && !notification.details) {
    return null;
  }

  state.queue.push(notification);
  if (state.queue.length > MAX_QUEUE_LENGTH) {
    const removed = state.queue.splice(
      0,
      state.queue.length - MAX_QUEUE_LENGTH,
    );
    removed.forEach((entry) => {
      const timer = timers.get(entry.id);
      if (timer) {
        clearTimeout(timer);
        timers.delete(entry.id);
      }
    });
  }

  if (duration > 0) {
    timers.set(
      id,
      setTimeout(() => {
        dismissShopNotification(id);
      }, duration),
    );
  }

  return notification;
}

export function useShopNotifications(zone = "system") {
  const normalizedZone = computed(() => normalizeZone(zone));
  const notifications = computed(() =>
    state.queue
      .filter((entry) => notificationMatchesZone(entry, normalizedZone.value))
      .sort(sortNewestFirst),
  );
  const current = computed(() => notifications.value[0] || null);

  return {
    notifications,
    current,
    notify: notifyShop,
    dismiss: dismissShopNotification,
    clearZone: () => clearShopNotificationZone(normalizedZone.value),
    clearAll: clearAllShopNotifications,
  };
}

export default useShopNotifications;
