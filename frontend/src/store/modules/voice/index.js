import { voiceService } from "@/services/voiceService";

const initialSnapshot = () => ({
  campaignId: null,
  status: "disconnected",
  joining: false,
  muted: true,
  deafened: false,
  pushToTalk: false,
  pushToTalkPressed: false,
  microphoneDeviceId: "",
  outputDeviceId: "",
  externalDeviceIds: { "external-1": "", "external-2": "" },
  deviceSettingsStatus: "idle",
  deviceSettingsError: null,
  devices: { inputs: [], outputs: [] },
  participants: [],
  externalInputs: {},
  outputSelectionSupported: false,
  audioBlocked: false,
  audioContextState: "unavailable",
  error: null,
  voiceVolume: 1,
});

let unsubscribe = null;

export default {
  namespaced: true,
  state: initialSnapshot,
  mutations: {
    SET_SNAPSHOT(state, snapshot) {
      Object.assign(state, snapshot);
    },
  },
  actions: {
    initialize({ commit }) {
      if (!unsubscribe)
        unsubscribe = voiceService.subscribe((value) =>
          commit("SET_SNAPSHOT", value),
        );
      voiceService.initialize();
    },
    refreshDevices() {
      return voiceService.refreshDevices();
    },
    join({ dispatch }, campaignId) {
      dispatch("initialize");
      return voiceService.join(campaignId);
    },
    leave() {
      return voiceService.leave();
    },
    toggleMute() {
      return voiceService.toggleMute();
    },
    setMuted(_context, muted) {
      return voiceService.setMuted(muted);
    },
    setPushToTalk(_context, enabled) {
      voiceService.setPushToTalk(enabled);
    },
    pressPushToTalk(_context, pressed) {
      voiceService.pressPushToTalk(pressed);
    },
    changeMicrophone(_context, deviceId) {
      return voiceService.changeMicrophone(deviceId);
    },
    setOutputDevice(_context, deviceId) {
      return voiceService.setOutputDevice(deviceId);
    },
    setExternalInputDevice(_context, { channelId, deviceId }) {
      voiceService.setExternalInputDevice(channelId, deviceId);
    },
    setParticipantVolume(_context, { identity, volume }) {
      voiceService.setParticipantVolume(identity, volume);
    },
    setParticipantPan(_context, { identity, pan }) {
      voiceService.setParticipantPan(identity, pan);
    },
    setParticipantMuted(_context, { identity, muted }) {
      voiceService.setParticipantMuted(identity, muted);
    },
    setDeafened(_context, deafened) {
      voiceService.setDeafened(deafened);
    },
    setMasterVolume(_context, volume) {
      voiceService.setMasterVolume(volume);
    },
    unlockAudio() {
      return voiceService.unlockAudio();
    },
    publishExternalInput(_context, { channelId, deviceId, deviceSlot, label }) {
      return voiceService.publishExternalInput(channelId, deviceId, {
        deviceSlot,
        label,
      });
    },
    unpublishExternalInput(_context, channelId) {
      return voiceService.unpublishExternalInput(channelId);
    },
  },
};
