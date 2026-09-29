import {
  JsonApiError,
  jsonApiClient,
  resolveAccessToken,
} from "@/lib/api/jsonApiClient";
import { normalizeTokenTemplate } from "@/lib/vtt/tokenTemplateApiClient";

const normalizeUser = (user = {}) => ({
  id: Number(user.id) || null,
  username: String(user.username || user.login || ""),
  email: String(user.email || ""),
  role: ["player", "gm"].includes(String(user.role || "user").toLowerCase())
    ? "user"
    : String(user.role || "user").toLowerCase(),
  avatarUrl: user.avatarUrl || user.avatar_url || null,
  campaignCount: Number(user.campaignCount ?? user.campaign_count ?? 0),
  createdAt: user.createdAt || user.created_at || null,
});

const normalizeCampaign = (campaign = {}) => ({
  id: Number(campaign.id) || null,
  name: String(campaign.name || ""),
  systemType: String(campaign.systemType || campaign.system_type || ""),
  isActive: Boolean(campaign.isActive ?? campaign.is_active),
  gameMasterId:
    Number(campaign.gameMasterId ?? campaign.game_master_id) || null,
  gameMasterName: String(
    campaign.gameMasterName || campaign.game_master_name || "",
  ),
  memberCount: Number(campaign.memberCount ?? campaign.member_count ?? 0),
  status: String(
    campaign.status ||
      ((campaign.isActive ?? campaign.is_active) ? "active" : "paused"),
  ),
  lastActivityAt: campaign.lastActivityAt || campaign.last_activity_at || null,
});

const normalizeCharacter = (character = {}) => ({
  id: Number(character.id) || null,
  name: String(character.name || ""),
  updatedAt: character.updatedAt || character.updated_at || null,
});

const normalizeCharacterCampaign = (item = {}) => ({
  characterId: Number(item.characterId ?? item.character_id) || null,
  campaignId: Number(item.campaignId ?? item.campaign_id) || null,
  campaignName: String(item.campaignName || item.campaign_name || ""),
});

const normalizeCharacterOwner = (item = {}) => ({
  ...normalizeCharacterCampaign(item),
  userId: Number(item.userId ?? item.user_id) || null,
  username: String(item.username || ""),
});

const normalizeCharacterGameMaster = (item = {}) => ({
  campaignId: Number(item.campaignId ?? item.campaign_id) || null,
  userId: Number(item.userId ?? item.user_id) || null,
  username: String(item.username || ""),
  isCampaignOwner: Boolean(item.isCampaignOwner ?? item.is_campaign_owner),
});

const normalizeDistribution = (items) =>
  Array.isArray(items)
    ? items.map((item) => ({
        key: String(item?.key || ""),
        value: Number(item?.value || 0),
      }))
    : [];

const normalizeAnalytics = (analytics = {}) => ({
  accountRoles: normalizeDistribution(analytics.accountRoles),
  campaignStatuses: normalizeDistribution(analytics.campaignStatuses),
  membershipRoles: normalizeDistribution(analytics.membershipRoles),
  systems: normalizeDistribution(analytics.systems),
  growth: Array.isArray(analytics.growth)
    ? analytics.growth.map((point) => ({
        label: String(point?.label || ""),
        users: Number(point?.users || 0),
        campaigns: Number(point?.campaigns || 0),
      }))
    : [],
});

const normalizeCompendiumImport = (item = {}) => ({
  id: Number(item.id) || null,
  universeId: Number(item.universeId) || null,
  worldName: String(item.worldName || ""),
  packName: String(item.packName || ""),
  mode: String(item.mode || ""),
  status: String(item.status || ""),
  expected: Number(item.expected || 0),
  processed: Number(item.processed || 0),
  added: Number(item.added || 0),
  updated: Number(item.updated || 0),
  skipped: Number(item.skipped || 0),
  errors: Number(item.errors || 0),
  checkpoint: Number(item.checkpoint || 0),
  startedAt: item.startedAt || null,
  finishedAt: item.finishedAt || null,
  rolledBackAt: item.rolledBackAt || null,
});

const normalizeCompendiumSource = (source = {}) => ({
  id: Number(source.id) || null,
  key: String(source.key || ""),
  kind: String(source.kind || ""),
  name: String(source.name || ""),
  language: String(source.language || ""),
  edition: source.edition || null,
  baseUrl: source.baseUrl || null,
  licenseStatus: String(source.licenseStatus || "unknown"),
  attributionStatus: String(source.attributionStatus || "unknown"),
  documents: Number(source.documents || 0),
  revisions: Number(source.revisions || 0),
  updatedAt: source.updatedAt || null,
});

const normalizeCompendiumWorld = (world = {}) => ({
  universeId: Number(world.universeId) || null,
  worldId: Number(world.worldId) || null,
  code: String(world.code || ""),
  name: String(world.name || ""),
  description: String(world.description || ""),
  systemId: Number(world.systemId) || null,
  systemCode: String(world.systemCode || ""),
  systemName: String(world.systemName || ""),
  gameIsActive: Boolean(world.gameIsActive),
  owner: world.owner
    ? {
        userId: Number(world.owner.userId) || null,
        username: String(world.owner.username || ""),
        email: String(world.owner.email || ""),
      }
    : null,
  storageLimitBytes: Number(world.storageLimitBytes || 0),
  entries: Number(world.entries || 0),
  published: Number(world.published || 0),
  drafts: Number(world.drafts || 0),
  archived: Number(world.archived || 0),
  sourceEntries: Number(world.sourceEntries || 0),
  verified: Number(world.verified || 0),
  needsReview: Number(world.needsReview || 0),
  assetsRegistered: Number(world.assetsRegistered || 0),
  assetsAvailable: Number(world.assetsAvailable || 0),
  usedBytes: Number(world.usedBytes || 0),
  unresolvedLinks: Number(world.unresolvedLinks || 0),
  mechanicalProfiles: Number(world.mechanicalProfiles || 0),
  usableProfiles: Number(world.usableProfiles || 0),
  editors: Number(world.editors || 0),
  campaigns: Number(world.campaigns || 0),
  lastImport: world.lastImport
    ? normalizeCompendiumImport(world.lastImport)
    : null,
  updatedAt: world.updatedAt || null,
});

const normalizeRpgSystem = (system = {}) => ({
  id: Number(system.id) || null,
  code: String(system.code || ""),
  name: String(system.name || ""),
});

const normalizeProfessionImage = (image) =>
  image
    ? {
        id: Number(image.id) || null,
        slot: String(image.slot || ""),
        url: String(image.url || ""),
        name: String(image.name || ""),
        mimeType: String(image.mimeType || image.mime_type || ""),
        byteSize: Number(image.byteSize ?? image.byte_size ?? 0),
        width: Number(image.width || 0),
        height: Number(image.height || 0),
      }
    : null;

const normalizeRequirementItem = (item = {}) => ({
  raw: String(item.raw || ""),
  display: String(item.display || item.raw || ""),
  decoded: item.decoded !== false,
});

const normalizeRequirementOption = (option = {}) => ({
  raw: String(option.raw || ""),
  display: String(option.display || option.name || option.raw || ""),
  name: String(option.name || option.display || ""),
  systemId: Number(option.systemId ?? option.system_id) || null,
  legacyId: Number(option.legacyId ?? option.legacy_id) || null,
  specializationId:
    option.specializationId === null || option.specializationId === undefined
      ? null
      : Number(option.specializationId),
});

const normalizeAdminProfession = (profession = {}) => ({
  id: Number(profession.id) || null,
  systemId: Number(profession.systemId ?? profession.system_id) || null,
  systemCode: String(profession.systemCode ?? profession.system_code ?? ""),
  systemName: String(profession.systemName ?? profession.system_name ?? ""),
  name: String(profession.name || ""),
  description:
    profession.description === null || profession.description === undefined
      ? null
      : String(profession.description),
  details:
    profession.details === null || profession.details === undefined
      ? null
      : String(profession.details),
  isAdvanced: Boolean(profession.isAdvanced ?? profession.is_advanced),
  isMain: Boolean(profession.isMain ?? profession.is_main),
  skills: String(profession.skills || ""),
  skillsDecoded: String(profession.skillsDecoded || ""),
  skillsFullyDecoded: profession.skillsFullyDecoded !== false,
  skillItems: Array.isArray(profession.skillItems)
    ? profession.skillItems
        .map(normalizeRequirementItem)
        .filter((item) => item.raw)
    : [],
  talents: String(profession.talents || ""),
  talentsDecoded: String(profession.talentsDecoded || ""),
  talentsFullyDecoded: profession.talentsFullyDecoded !== false,
  talentItems: Array.isArray(profession.talentItems)
    ? profession.talentItems
        .map(normalizeRequirementItem)
        .filter((item) => item.raw)
    : [],
  images: {
    male: normalizeProfessionImage(profession.images?.male),
    female: normalizeProfessionImage(profession.images?.female),
  },
  createdAt: profession.createdAt ?? profession.created_at ?? null,
  updatedAt: profession.updatedAt ?? profession.updated_at ?? null,
  usage: {
    characters: Number(profession.usage?.characters || 0),
    pathReferences: Number(profession.usage?.pathReferences || 0),
  },
  canDelete: Boolean(profession.canDelete),
});

const normalizeAdminProfessions = (payload = {}) => ({
  items: Array.isArray(payload.items)
    ? payload.items
        .map(normalizeAdminProfession)
        .filter((profession) => profession.id)
    : [],
  systems: Array.isArray(payload.systems)
    ? payload.systems.map(normalizeRpgSystem).filter((system) => system.id)
    : [],
  requirementOptions: {
    skills: Array.isArray(payload.requirementOptions?.skills)
      ? payload.requirementOptions.skills
          .map(normalizeRequirementOption)
          .filter((option) => option.raw && option.systemId)
      : [],
    talents: Array.isArray(payload.requirementOptions?.talents)
      ? payload.requirementOptions.talents
          .map(normalizeRequirementOption)
          .filter((option) => option.raw && option.systemId)
      : [],
  },
});

const normalizeCompendiumOverview = (payload = {}) => ({
  metrics: {
    worlds: Number(payload.metrics?.worlds || 0),
    configuredWorlds: Number(payload.metrics?.configuredWorlds || 0),
    entries: Number(payload.metrics?.entries || 0),
    published: Number(payload.metrics?.published || 0),
    verified: Number(payload.metrics?.verified || 0),
    needsReview: Number(payload.metrics?.needsReview || 0),
    assetsAvailable: Number(payload.metrics?.assetsAvailable || 0),
    unresolvedLinks: Number(payload.metrics?.unresolvedLinks || 0),
  },
  worlds: Array.isArray(payload.worlds)
    ? payload.worlds
        .map(normalizeCompendiumWorld)
        .filter((world) => world.universeId)
    : [],
  systems: Array.isArray(payload.systems)
    ? payload.systems
        .map(normalizeRpgSystem)
        .filter((system) => system.id && system.code && system.name)
    : [],
  imports: Array.isArray(payload.imports)
    ? payload.imports.map(normalizeCompendiumImport).filter((item) => item.id)
    : [],
  sources: Array.isArray(payload.sources)
    ? payload.sources
        .map(normalizeCompendiumSource)
        .filter((source) => source.id)
    : [],
  generatedAt: payload.generatedAt || null,
});

const normalizeAdminAudioTrack = (track = {}) => ({
  id: Number(track.id) || null,
  libraryId: Number(track.libraryId) || null,
  title: String(track.title || ""),
  category: String(track.category || "music"),
  sourceType: String(track.sourceType || ""),
  provider: track.provider || null,
  duration: track.duration === null ? null : Number(track.duration),
  loop: Boolean(track.loop),
  tags: Array.isArray(track.tags) ? track.tags.map(String) : [],
  thumbnail: track.thumbnail || null,
  originalName: track.originalName || null,
  mimeType: track.mimeType || null,
  fileSize: track.fileSize === null ? null : Number(track.fileSize),
});

const normalizeAdminAudioLibrary = (library = {}) => ({
  id: Number(library.id) || null,
  name: String(library.name || ""),
  systemId: Number(library.systemId) || null,
  systemName: String(library.systemName || ""),
  settingId: Number(library.settingId) || null,
  settingName: String(library.settingName || ""),
  isActive: Boolean(library.isActive),
  trackCount: Number(library.trackCount || 0),
  tracks: Array.isArray(library.tracks)
    ? library.tracks.map(normalizeAdminAudioTrack).filter((track) => track.id)
    : [],
});

const normalizeAdminAudio = (payload = {}) => ({
  libraries: Array.isArray(payload.libraries)
    ? payload.libraries
        .map(normalizeAdminAudioLibrary)
        .filter((library) => library.id)
    : [],
  systems: Array.isArray(payload.systems)
    ? payload.systems.map(normalizeRpgSystem).filter((system) => system.id)
    : [],
  settings: Array.isArray(payload.settings)
    ? payload.settings.map((setting) => ({
        id: Number(setting.id) || null,
        code: String(setting.code || ""),
        name: String(setting.name || ""),
        defaultSystemId: Number(setting.defaultSystemId) || null,
        systemIds: Array.isArray(setting.systemIds)
          ? setting.systemIds.map(Number).filter(Boolean)
          : [],
      }))
    : [],
  upload: payload.upload || null,
});

const uploadAdminAudio = async (libraryId, file, metadata = {}) => {
  if (typeof window === "undefined") {
    throw new JsonApiError("network_error", { network: true });
  }
  const body = new FormData();
  body.append("file", file, file.name);
  Object.entries(metadata).forEach(([key, value]) => {
    if (value === null || value === undefined) return;
    body.append(
      key,
      Array.isArray(value) ? JSON.stringify(value) : String(value),
    );
  });
  const token = resolveAccessToken();
  const baseUrl = String(process.env.VUE_APP_API_BASE || "/api").replace(
    /\/+$/u,
    "",
  );
  let response;
  try {
    response = await window.fetch(
      `${baseUrl}/admin/audio/libraries/${Number(libraryId)}/tracks/upload`,
      {
        method: "POST",
        headers: {
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        credentials: "same-origin",
        body,
      },
    );
  } catch (cause) {
    throw new JsonApiError("network_error", {
      network: true,
      payload: cause,
    });
  }
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    throw new JsonApiError(
      String(payload?.code || payload?.message || `http_${response.status}`),
      { status: response.status, payload },
    );
  }
  return normalizeAdminAudioTrack(payload?.track);
};

const multipartTokenTemplate = async (path, method, draft, file) => {
  if (typeof window === "undefined") {
    throw new JsonApiError("network_error", { network: true });
  }
  const body = new FormData();
  if (method === "PATCH") body.append("_method", "PATCH");
  body.append("payload", JSON.stringify(draft || {}));
  if (file) body.append("file", file, file.name);
  const token = resolveAccessToken();
  const baseUrl = String(process.env.VUE_APP_API_BASE || "/api").replace(
    /\/+$/u,
    "",
  );
  let response;
  try {
    response = await window.fetch(`${baseUrl}/${path.replace(/^\/+/, "")}`, {
      method: method === "PATCH" ? "POST" : method,
      headers: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      credentials: "same-origin",
      body,
    });
  } catch (cause) {
    throw new JsonApiError("network_error", { network: true, payload: cause });
  }
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    throw new JsonApiError(
      String(payload?.code || payload?.message || `http_${response.status}`),
      { status: response.status, payload },
    );
  }
  return normalizeTokenTemplate(payload?.template);
};

const uploadProfessionImage = async (professionId, slot, file) => {
  if (typeof window === "undefined") {
    throw new JsonApiError("network_error", { network: true });
  }
  const body = new FormData();
  body.append("file", file, file.name);
  const token = resolveAccessToken();
  const baseUrl = String(process.env.VUE_APP_API_BASE || "/api").replace(
    /\/+$/u,
    "",
  );
  let response;
  try {
    response = await window.fetch(
      `${baseUrl}/admin/professions/${Number(professionId)}/images/${slot}`,
      {
        method: "POST",
        headers: {
          Accept: "application/json",
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        credentials: "same-origin",
        body,
      },
    );
  } catch (cause) {
    throw new JsonApiError("network_error", { network: true, payload: cause });
  }
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    throw new JsonApiError(
      String(payload?.code || payload?.message || `http_${response.status}`),
      { status: response.status, payload },
    );
  }
  return normalizeAdminProfession(payload?.profession);
};

const normalizeMediaAudioLibrary = (library = {}) => ({
  id: Number(library.id) || null,
  name: String(library.name || ""),
  owner: library.owner
    ? {
        id: Number(library.owner.id) || null,
        username: String(library.owner.username || ""),
      }
    : null,
  settingId: Number(library.settingId) || null,
  settingName: library.settingName || null,
  systemId: Number(library.systemId) || null,
  systemName: library.systemName || null,
  isActive: Boolean(library.isActive),
  trackCount: Number(library.trackCount || 0),
  assetCount: Number(library.assetCount || 0),
});

const normalizeMediaAudioTrack = (track = {}) => ({
  id: Number(track.recordId || track.id) || null,
  name: String(track.name || track.title || ""),
  libraryId: Number(track.libraryId) || null,
  libraryName: track.libraryName || null,
  libraryScope: track.libraryScope || null,
  settingId: Number(track.settingId) || null,
  systemId: Number(track.systemId) || null,
  ownerUserId: Number(track.ownerUserId) || null,
  ownerUsername: track.ownerUsername || null,
  sourceType: track.sourceType || null,
});

export const normalizeMediaAsset = (asset = {}) => ({
  ...asset,
  id: Number(asset.id) || null,
  name: String(asset.name || asset.filename || ""),
  filename: asset.filename || null,
  category: String(asset.category || "other"),
  resourceType: String(asset.resourceType || "raw"),
  mimeType: String(asset.mimeType || "application/octet-stream"),
  format: asset.format || null,
  fileSize: asset.fileSize === null ? null : Number(asset.fileSize || 0),
  width: asset.width === null ? null : Number(asset.width || 0),
  height: asset.height === null ? null : Number(asset.height || 0),
  duration: asset.duration === null ? null : Number(asset.duration || 0),
  revision: Number(asset.revision || 1),
  campaignId: Number(asset.campaignId) || null,
  sourceUrl: asset.sourceUrl || null,
  availabilityStatus: asset.availabilityStatus || null,
  availabilityCheckedAt: asset.availabilityCheckedAt || null,
  tags: Array.isArray(asset.tags) ? asset.tags.map(String) : [],
  customMetadata:
    asset.customMetadata && typeof asset.customMetadata === "object"
      ? asset.customMetadata
      : {},
  providerMetadata:
    asset.providerMetadata && typeof asset.providerMetadata === "object"
      ? asset.providerMetadata
      : {},
  relations: Array.isArray(asset.relations) ? asset.relations : [],
  collections: Array.isArray(asset.collections) ? asset.collections : [],
  audioTracks: Array.isArray(asset.audioTracks)
    ? asset.audioTracks
        .map(normalizeMediaAudioTrack)
        .filter((track) => track.id)
    : [],
  globalAudioLibraries: Array.isArray(asset.globalAudioLibraries)
    ? asset.globalAudioLibraries
        .map(normalizeMediaAudioLibrary)
        .filter((library) => library.id)
    : [],
});

const normalizeMediaCollection = (collection = {}) => ({
  id: Number(collection.id) || null,
  name: String(collection.name || ""),
  description: collection.description || null,
  owner: collection.owner || null,
  assetCount: Number(collection.assetCount || 0),
});

const uploadAdminMedia = async (file, metadata = {}) => {
  const body = new FormData();
  body.append("file", file, file.name);
  Object.entries(metadata).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;
    body.append(
      key,
      typeof value === "object" ? JSON.stringify(value) : String(value),
    );
  });
  const token = resolveAccessToken();
  const baseUrl = String(process.env.VUE_APP_API_BASE || "/api").replace(
    /\/+$/u,
    "",
  );
  let response;
  try {
    response = await window.fetch(`${baseUrl}/admin/media-assets/upload`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      credentials: "same-origin",
      body,
    });
  } catch (cause) {
    throw new JsonApiError("network_error", { network: true, payload: cause });
  }
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    throw new JsonApiError(String(payload?.code || `http_${response.status}`), {
      status: response.status,
      payload,
    });
  }
  return normalizeMediaAsset(payload?.asset);
};

const directMediaUpload = async (instruction, file) => {
  if (instruction.encoding === "multipart/form-data") {
    const body = new FormData();
    Object.entries(instruction.fields || {}).forEach(([key, value]) =>
      body.append(key, String(value)),
    );
    body.append("file", file);
    const response = await window.fetch(instruction.url, {
      method: instruction.method,
      headers: instruction.headers || {},
      body,
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload)
      throw new JsonApiError(`media_upload_${response.status}`, {
        status: response.status,
        payload,
      });
    return payload;
  }
  const response = await window.fetch(instruction.url, {
    method: instruction.method,
    headers: instruction.headers || {},
    body: file,
  });
  if (!response.ok)
    throw new JsonApiError(`media_upload_${response.status}`, {
      status: response.status,
    });
  return { eTag: response.headers.get("ETag") || "", fileSize: file.size };
};

export const createAdminApiClient = (client = jsonApiClient) => ({
  async overview(options = {}) {
    const payload = await client.request("/admin/overview", options);
    return {
      currentUserId: Number(payload?.currentUserId || 0),
      users: Array.isArray(payload?.users)
        ? payload.users.map(normalizeUser).filter((user) => user.id)
        : [],
      campaigns: Array.isArray(payload?.campaigns)
        ? payload.campaigns.map(normalizeCampaign).filter((item) => item.id)
        : [],
      characters: Array.isArray(payload?.characters)
        ? payload.characters.map(normalizeCharacter).filter((item) => item.id)
        : [],
      characterCampaigns: Array.isArray(payload?.characterCampaigns)
        ? payload.characterCampaigns
            .map(normalizeCharacterCampaign)
            .filter((item) => item.characterId && item.campaignId)
        : [],
      characterOwners: Array.isArray(payload?.characterOwners)
        ? payload.characterOwners
            .map(normalizeCharacterOwner)
            .filter(
              (item) => item.characterId && item.campaignId && item.userId,
            )
        : [],
      characterGameMasters: Array.isArray(payload?.characterGameMasters)
        ? payload.characterGameMasters
            .map(normalizeCharacterGameMaster)
            .filter((item) => item.campaignId && item.userId)
        : [],
      metrics: {
        users: Number(payload?.metrics?.users || 0),
        admins: Number(payload?.metrics?.admins || 0),
        campaigns: Number(payload?.metrics?.campaigns || 0),
        activeCampaigns: Number(payload?.metrics?.activeCampaigns || 0),
        memberships: Number(payload?.metrics?.memberships || 0),
        activeSessions: Number(payload?.metrics?.activeSessions || 0),
      },
      analytics: normalizeAnalytics(payload?.analytics),
      activity: Array.isArray(payload?.activity)
        ? payload.activity.map((item) => ({
            type: String(item?.type || ""),
            label: String(item?.label || ""),
            occurredAt: item?.occurredAt || null,
          }))
        : [],
      system: {
        generatedAt: payload?.system?.generatedAt || null,
        environment: String(payload?.system?.environment || ""),
        phpVersion: String(payload?.system?.phpVersion || ""),
        database: String(payload?.system?.database || ""),
        activeSessions: Number(payload?.system?.activeSessions || 0),
      },
    };
  },

  async createUser(draft) {
    const payload = await client.request("/admin/users", {
      method: "POST",
      body: draft,
    });
    return normalizeUser(payload?.user);
  },

  async changeUserRole(userId, role) {
    const payload = await client.request(
      `/admin/users/${Number(userId)}/role`,
      {
        method: "PATCH",
        body: { role },
      },
    );
    return normalizeUser(payload?.user);
  },

  async compendiumOverview(options = {}) {
    return normalizeCompendiumOverview(
      await client.request("/admin/compendium", options),
    );
  },

  async professions(options = {}) {
    return normalizeAdminProfessions(
      await client.request("/admin/professions", options),
    );
  },

  async createProfession(body) {
    const payload = await client.request("/admin/professions", {
      method: "POST",
      body,
    });
    return normalizeAdminProfession(payload?.profession);
  },

  decodeProfessionRequirements(body) {
    return client.request("/admin/professions/decode", {
      method: "POST",
      body,
    });
  },

  async updateProfession(professionId, body) {
    const payload = await client.request(
      `/admin/professions/${Number(professionId)}`,
      { method: "PATCH", body },
    );
    return normalizeAdminProfession(payload?.profession);
  },

  deleteProfession(professionId, updatedAt) {
    return client.request(`/admin/professions/${Number(professionId)}`, {
      method: "DELETE",
      body: { updatedAt: updatedAt || null },
    });
  },

  uploadProfessionImage,

  async deleteProfessionImage(professionId, slot) {
    const payload = await client.request(
      `/admin/professions/${Number(professionId)}/images/${slot}`,
      { method: "DELETE" },
    );
    return normalizeAdminProfession(payload?.profession);
  },

  async audioOverview(options = {}) {
    return normalizeAdminAudio(await client.request("/admin/audio", options));
  },

  async createAudioLibrary(body) {
    const payload = await client.request("/admin/audio/libraries", {
      method: "POST",
      body,
    });
    return normalizeAdminAudioLibrary(payload?.library);
  },

  async updateAudioLibrary(libraryId, body) {
    const payload = await client.request(
      `/admin/audio/libraries/${Number(libraryId)}`,
      { method: "PATCH", body },
    );
    return normalizeAdminAudioLibrary(payload?.library);
  },

  deleteAudioLibrary(libraryId) {
    return client.request(`/admin/audio/libraries/${Number(libraryId)}`, {
      method: "DELETE",
    });
  },

  uploadAudioTrack: uploadAdminAudio,

  async createExternalAudioTrack(libraryId, body) {
    const payload = await client.request(
      `/admin/audio/libraries/${Number(libraryId)}/tracks/external`,
      { method: "POST", body },
    );
    return normalizeAdminAudioTrack(payload?.track);
  },

  async updateAudioTrack(trackId, body) {
    const payload = await client.request(
      `/admin/audio/tracks/${Number(trackId)}`,
      { method: "PATCH", body },
    );
    return normalizeAdminAudioTrack(payload?.track);
  },

  deleteAudioTrack(trackId) {
    return client.request(`/admin/audio/tracks/${Number(trackId)}`, {
      method: "DELETE",
    });
  },

  async tokenTemplates(options = {}) {
    const payload = await client.request("/admin/token-templates", options);
    return (payload?.items || []).map(normalizeTokenTemplate);
  },

  async createTokenTemplate(draft, file = null) {
    if (file) {
      return multipartTokenTemplate(
        "/admin/token-templates",
        "POST",
        draft,
        file,
      );
    }
    const payload = await client.request("/admin/token-templates", {
      method: "POST",
      body: draft,
    });
    return normalizeTokenTemplate(payload?.template);
  },

  async updateTokenTemplate(templateId, draft, file = null) {
    const path = `/admin/token-templates/${Number(templateId)}`;
    if (file) return multipartTokenTemplate(path, "PATCH", draft, file);
    const payload = await client.request(path, {
      method: "PATCH",
      body: draft,
    });
    return normalizeTokenTemplate(payload?.template);
  },

  deleteTokenTemplate(templateId, revision) {
    return client.request(`/admin/token-templates/${Number(templateId)}`, {
      method: "DELETE",
      body: { revision: Number(revision) },
    });
  },

  async mediaAssets(filters = {}, options = {}) {
    const query = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== "" && value !== null && value !== undefined) {
        query.set(key, String(value));
      }
    });
    const payload = await client.request(
      `/admin/media-assets${query.size ? `?${query}` : ""}`,
      options,
    );
    return {
      items: Array.isArray(payload?.items)
        ? payload.items.map(normalizeMediaAsset).filter((item) => item.id)
        : [],
      pagination: payload?.pagination || {
        page: 1,
        perPage: 30,
        total: 0,
        pages: 1,
      },
      facets: payload?.facets || {},
    };
  },

  async mediaAudioLibraries(filters = {}, options = {}) {
    const query = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== "" && value !== null && value !== undefined) {
        query.set(key, String(value));
      }
    });
    const payload = await client.request(
      `/admin/media-audio-libraries${query.size ? `?${query}` : ""}`,
      options,
    );
    return {
      items: Array.isArray(payload?.items)
        ? payload.items
            .map(normalizeMediaAudioLibrary)
            .filter((library) => library.id)
        : [],
      pagination: payload?.pagination || {
        page: 1,
        perPage: 25,
        total: 0,
        pages: 1,
      },
    };
  },

  async mediaAsset(assetId, options = {}) {
    const payload = await client.request(
      `/admin/media-assets/${Number(assetId)}`,
      options,
    );
    return normalizeMediaAsset(payload?.asset);
  },

  async updateMediaAsset(assetId, body) {
    const payload = await client.request(
      `/admin/media-assets/${Number(assetId)}`,
      { method: "PATCH", body },
    );
    return normalizeMediaAsset(payload?.asset);
  },

  deleteMediaAsset(assetId) {
    return client.request(`/admin/media-assets/${Number(assetId)}`, {
      method: "DELETE",
    });
  },

  retryMediaPurge(assetId) {
    return client.request(
      `/admin/media-assets/${Number(assetId)}/retry-purge`,
      { method: "POST", body: {} },
    );
  },

  bulkUpdateMediaAssets(body) {
    return client.request("/admin/media-assets/bulk", {
      method: "PATCH",
      body,
    });
  },

  uploadMediaAsset: uploadAdminMedia,

  async createExternalMediaAsset(body) {
    const payload = await client.request("/admin/media-assets/external", {
      method: "POST",
      body,
    });
    return normalizeMediaAsset(payload?.asset);
  },

  publishPersonalAudioTrack(trackId, body) {
    return client.request(
      `/admin/media-assets/audio-tracks/${Number(trackId)}/publish`,
      { method: "POST", body },
    );
  },

  async replaceMediaAsset(assetId, file) {
    const initiated = await client.request(
      `/admin/media-assets/${Number(assetId)}/replacement`,
      {
        method: "POST",
        body: {
          filename: file.name,
          mimeType: file.type || "application/octet-stream",
          fileSize: file.size,
        },
      },
    );
    const uploadResult = await directMediaUpload(initiated.upload, file);
    const payload = await client.request(
      `/admin/media-assets/${Number(assetId)}/replacement/${Number(
        initiated.asset.id,
      )}/complete`,
      { method: "POST", body: { uploadResult } },
    );
    return normalizeMediaAsset(payload?.asset);
  },

  async mediaCollections(options = {}) {
    const payload = await client.request("/admin/media-collections", options);
    return Array.isArray(payload?.items)
      ? payload.items.map(normalizeMediaCollection).filter((item) => item.id)
      : [];
  },

  async createMediaCollection(body) {
    const payload = await client.request("/admin/media-collections", {
      method: "POST",
      body,
    });
    return normalizeMediaCollection(payload?.collection);
  },

  updateMediaCollection(collectionId, body) {
    return client.request(`/admin/media-collections/${Number(collectionId)}`, {
      method: "PATCH",
      body,
    });
  },

  deleteMediaCollection(collectionId) {
    return client.request(`/admin/media-collections/${Number(collectionId)}`, {
      method: "DELETE",
    });
  },

  changeMediaCollectionAssets(collectionId, ids, add = true) {
    return client.request(
      `/admin/media-collections/${Number(collectionId)}/assets`,
      { method: add ? "POST" : "DELETE", body: { ids } },
    );
  },

  createCharacterAssetSet(body) {
    return client.request("/admin/media-assets/character-sets", {
      method: "POST",
      body,
    });
  },

  async createCompendiumWorld(body) {
    const payload = await client.request("/admin/compendium/worlds", {
      method: "POST",
      body,
    });
    return normalizeCompendiumWorld(payload?.world);
  },

  async updateCompendiumWorld(universeId, body) {
    const payload = await client.request(
      `/admin/compendium/worlds/${Number(universeId)}`,
      { method: "PATCH", body },
    );
    return normalizeCompendiumWorld(payload?.world);
  },

  updateCompendiumEntryPolicy(universeId, entryId, body) {
    return client.request(
      `/admin/compendium/worlds/${Number(universeId)}/entries/${Number(entryId)}/policy`,
      { method: "PATCH", body },
    );
  },

  updateCompendiumProfile(universeId, profileId, body) {
    return client.request(
      `/admin/compendium/worlds/${Number(universeId)}/profiles/${Number(profileId)}`,
      { method: "PATCH", body },
    );
  },

  updateCompendiumSource(sourceId, body) {
    return client.request(`/admin/compendium/sources/${Number(sourceId)}`, {
      method: "PATCH",
      body,
    });
  },

  rollbackCompendiumImport(universeId, runId) {
    return client.request(
      `/admin/compendium/worlds/${Number(universeId)}/imports/${Number(runId)}/rollback`,
      { method: "POST", body: {} },
    );
  },

  syncCompendiumWfrp2(universeId) {
    return client.request(
      `/admin/compendium/worlds/${Number(universeId)}/sync-wfrp2`,
      { method: "POST", body: {} },
    );
  },

  setCharacterCampaign(characterId, campaignId, assigned) {
    return client.request(
      `/admin/characters/${Number(characterId)}/campaigns/${Number(campaignId)}`,
      { method: assigned ? "PUT" : "DELETE" },
    );
  },

  setCharacterOwner(characterId, campaignId, userId, assigned) {
    return client.request(
      `/admin/characters/${Number(characterId)}/campaigns/${Number(campaignId)}` +
        `/owners/${Number(userId)}`,
      { method: assigned ? "PUT" : "DELETE" },
    );
  },
});

export const adminApiClient = createAdminApiClient();
