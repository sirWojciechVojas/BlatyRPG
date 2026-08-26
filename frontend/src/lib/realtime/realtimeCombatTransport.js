import { combatCommandMessage } from "./realtimeProtocol";

export const createRealtimeCombatTransport = (authenticated, send) => ({
  commandCombat: (payload) =>
    authenticated() && send(combatCommandMessage(payload)),
});
