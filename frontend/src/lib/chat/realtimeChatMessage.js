const positiveId = (value) => {
  const number = Number(value);
  return Number.isSafeInteger(number) && number > 0 ? number : null;
};

export const normalizeRealtimeChatMessage = (value = {}) => {
  const id = positiveId(value.id);
  const revision = positiveId(value.revision ?? value.id);
  const campaignId = positiveId(value.campaignId ?? value.campaign_id);
  const rawAuthorId = value.author?.id;
  const authorId = rawAuthorId === null ? null : positiveId(rawAuthorId);
  if (
    !id ||
    revision !== id ||
    !campaignId ||
    (rawAuthorId !== null && !authorId)
  ) {
    return null;
  }
  return {
    id,
    revision,
    campaignId,
    type: String(value.type || "text"),
    body: String(value.body || ""),
    author: {
      id: authorId,
      name: String(value.author?.name || ""),
    },
    clientNonce: value.clientNonce ?? value.client_nonce ?? null,
    metadata: value.metadata ?? null,
    createdAt: value.createdAt ?? value.created_at ?? null,
  };
};

export const withCurrentChatUser = (message, currentUserId) => {
  const viewerId = positiveId(currentUserId);
  return {
    ...message,
    author: {
      ...message.author,
      isCurrentUser: viewerId !== null && message.author?.id === viewerId,
    },
  };
};
