export const TABLE_WINDOW_Z_INDEX_START = 700;
export const TABLE_WINDOW_Z_INDEX_END = 880;

const registrations = new Map();
let order = [];
let registrationSequence = 0;

const synchronizeLayers = () => {
  order.forEach((registrationId, index) => {
    const registration = registrations.get(registrationId);
    if (!registration) return;
    registration.update(
      Math.min(TABLE_WINDOW_Z_INDEX_START + index, TABLE_WINDOW_Z_INDEX_END),
    );
  });
};

const focusRegistration = (registrationId) => {
  if (!registrations.has(registrationId)) return false;
  order = order.filter((id) => id !== registrationId);
  order.push(registrationId);
  synchronizeLayers();
  return true;
};

export const registerTableWindow = (windowId, update) => {
  const registrationId = ++registrationSequence;
  registrations.set(registrationId, {
    windowId: String(windowId),
    update,
  });
  order.push(registrationId);
  synchronizeLayers();

  return {
    focus: () => focusRegistration(registrationId),
    unregister: () => {
      registrations.delete(registrationId);
      order = order.filter((id) => id !== registrationId);
      synchronizeLayers();
    },
  };
};

export const focusTableWindow = (windowId) => {
  const normalizedId = String(windowId);
  const registrationId = [...order]
    .reverse()
    .find((id) => registrations.get(id)?.windowId === normalizedId);
  return registrationId ? focusRegistration(registrationId) : false;
};
