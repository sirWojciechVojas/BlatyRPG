export const campaignContextGetters = {
  canManage: (state) => state.capabilities.canManage === true,
  isLoading: (state) => state.phase === "loading" || state.pendingRequests > 0,
  onlineUserIds: (_state, _getters, rootState) =>
    new Set(
      Object.values(rootState.realtime?.presenceByUser || {})
        .filter((entry) => entry.online === true)
        .map((entry) => Number(entry.userId)),
    ),
  membersWithPresence: (state, getters) =>
    state.members.map((member) => ({
      ...member,
      isOnline:
        member.isOnline === true ||
        getters.onlineUserIds.has(Number(member.userId)),
    })),
  characterById: (state) => (characterId) =>
    state.characters.find(
      (character) => Number(character.id) === Number(characterId),
    ) || null,
  permissionsForCharacter: (state) => (characterId) =>
    state.characterPermissions[Number(characterId)] || [],
};
