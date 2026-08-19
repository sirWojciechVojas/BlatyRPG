export const createCampaignChatRequestScope = () => {
  let active = false;
  let version = 0;

  return {
    mount() {
      active = true;
    },
    reset() {
      version += 1;
    },
    unmount() {
      active = false;
      version += 1;
    },
    isActive: () => active,
    capture: (campaignId) => ({ version, campaignId: String(campaignId) }),
    isCurrent(context, campaignId) {
      return (
        active &&
        context.version === version &&
        context.campaignId === String(campaignId)
      );
    },
  };
};

export const createPendingChatMessage = (nonceFactory) => {
  let pending = null;
  return {
    nonceFor(body) {
      if (!pending || pending.body !== body) {
        pending = { body, nonce: nonceFactory() };
      }
      return pending.nonce;
    },
    clear() {
      pending = null;
    },
  };
};
