const messageId = (message) => Number(message?.id) || 0;

export const mergeChatMessages = (current = [], incoming = []) => {
  const byId = new Map();
  for (const message of [...current, ...incoming]) {
    const id = messageId(message);
    if (id > 0) byId.set(id, message);
  }
  return [...byId.values()].sort(
    (left, right) => messageId(left) - messageId(right),
  );
};

export const chatCursor = (messages = {}, edge = "after") => {
  if (!Array.isArray(messages) || messages.length === 0) return null;
  const index = edge === "before" ? 0 : messages.length - 1;
  return messageId(messages[index]) || null;
};

export const isNearChatBottom = (element, threshold = 80) => {
  if (!element) return true;
  return (
    element.scrollHeight - element.scrollTop - element.clientHeight <= threshold
  );
};
