export const elementActionOptions = (name, plural, normalizeError) => {
  const upper = name.toUpperCase();
  return {
    name,
    plural,
    normalizeError,
    receiveMutation: `RECEIVE_${plural.toUpperCase()}`,
    upsertMutation: `UPSERT_${upper}`,
    removeMutation: `REMOVE_${upper}`,
    selectMutation: `SELECT_${upper}`,
    phaseMutation: `SET_${upper}_PHASE`,
    failureMutation: `${upper}_FAILED`,
  };
};
