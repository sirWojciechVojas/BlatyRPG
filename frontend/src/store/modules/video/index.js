import { videoService } from "@/services/videoService";

const initialSnapshot = () => ({
  campaignId: null,
  status: "disconnected",
  cameraEnabled: false,
  cameraDeviceId: "",
  quality: "auto",
  busy: false,
  error: null,
  devices: { videoInputs: [] },
  participants: [],
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
      if (!unsubscribe) {
        unsubscribe = videoService.subscribe((snapshot) =>
          commit("SET_SNAPSHOT", snapshot),
        );
      }
      videoService.initialize();
    },
    toggleCamera() {
      return videoService.toggleCamera();
    },
    setCameraEnabled(_context, enabled) {
      return videoService.setCameraEnabled(enabled);
    },
    changeCamera(_context, deviceId) {
      return videoService.changeCamera(deviceId);
    },
    changeQuality(_context, quality) {
      return videoService.changeQuality(quality);
    },
    refreshDevices() {
      return videoService.refreshDevices();
    },
    attach(_context, { identity, element }) {
      videoService.attach(identity, element);
    },
    detach(_context, { identity, element }) {
      videoService.detach(identity, element);
    },
  },
};
