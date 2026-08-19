export const emptyCampaignCapabilities = () => ({
  canAccess: false,
  canManage: false,
  canViewHidden: false,
  canOpenShop: false,
});

export const createCampaignContextState = () => ({
  campaignId: null,
  currentCampaign: null,
  members: [],
  invitations: [],
  characters: [],
  characterPermissions: {},
  capabilities: emptyCampaignCapabilities(),
  phase: "idle",
  pendingRequests: 0,
  error: null,
  unauthorized: false,
  generation: 0,
});
