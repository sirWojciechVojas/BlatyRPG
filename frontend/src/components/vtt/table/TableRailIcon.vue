<template>
  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path
      :d="path"
      fill="none"
      stroke="currentColor"
      stroke-linecap="round"
      stroke-linejoin="round"
      stroke-width="1.8"
    />
  </svg>
</template>

<script>
const PATHS = Object.freeze({
  chat: "M4 5h16v11H9l-5 4V5Zm4 5h8M8 8h5",
  sword: "m5 19 4-4m-2 6-4-4m7-3L20 4v5L10 19v-5Z",
  image: "M4 5h16v14H4V5Zm3 11 4-5 3 3 2-2 4 4M8 9h.01",
  users:
    "M8 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8-1a2.5 2.5 0 1 0 0-5M3 19c0-3 2-5 5-5s5 2 5 5m1-5c3 0 5 2 5 5",
  file: "M6 3h8l4 4v14H6V3Zm8 0v5h4M9 12h6m-6 4h6",
  package: "m4 7 8-4 8 4-8 4-8-4Zm0 0v10l8 4 8-4V7m-8 4v10",
  book: "M4 5c4-1 6 0 8 2v13c-2-2-4-3-8-2V5Zm16 0c-4-1-6 0-8 2v13c2-2 4-3 8-2V5Z",
  scene: "M3 5h18v14H3V5Zm3 11 4-5 3 3 2-2 3 3",
  dice: "M5 3h14l3 9-7 9H5l-3-9 3-9Zm3 5h.01m8 0h.01M12 12h.01m-4 4h.01m8 0h.01",
  shop: "M4 9h16l-1-5H5L4 9Zm1 0v11h14V9M9 20v-6h6v6",
  music: "M9 18a3 2 0 1 1-3-2m3 2V6l10-2v10m0 0a3 2 0 1 1-3-2",
  "audio-waveform": "M3 13v-2m3 5V8m3 11V5m3 17V2m3 17V5m3 11V8m3 5v-2",
  microphone:
    "M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3Zm-6 9a6 6 0 0 0 12 0m-6 6v3m-4 0h8",
  microphoneOff:
    "M9 9v3a3 3 0 0 0 5 2.2M15 10V6a3 3 0 0 0-5.7-1.3M6 12a6 6 0 0 0 9.4 5M18 12a6 6 0 0 1-.7 2.8M12 18v3m-4 0h8M3 3l18 18",
  camera: "M4 7h11v10H4V7Zm11 3 5-3v10l-5-3v-4Z",
  cameraOff: "M9 7H4v10h11v-3m0-4 5-3v8M3 3l18 18",
  headphones:
    "M4 14v-2a8 8 0 0 1 16 0v2M4 14h3v6H5a1 1 0 0 1-1-1v-5Zm16 0h-3v6h2a1 1 0 0 0 1-1v-5Z",
  phoneOff:
    "M5 15c4.2-3 9.8-3 14 0l-2 4-3.2-1.4v-2.2a9.6 9.6 0 0 0-3.6 0v2.2L7 19l-2-4Z",
  more: "M6 12h.01M12 12h.01M18 12h.01",
  signal: "M5 19v-3m5 3v-7m5 7V8m5 11V4",
  database:
    "M4 6c0-2 4-3 8-3s8 1 8 3-4 3-8 3-8-1-8-3Zm0 0v6c0 2 4 3 8 3s8-1 8-3V6m-16 6v6c0 2 4 3 8 3s8-1 8-3v-6",
  bell: "M6 17h12l-2-3V9a4 4 0 0 0-8 0v5l-2 3Zm4 3h4",
  calendar:
    "M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Zm2-2v4m10-4v4M3 9h18M7 13h2m3 0h2m3 0h2m-12 4h2m3 0h2m3 0h2",
  settings:
    "M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0-6 1 2.5 2.5.8 2.3-1.1 1.1 1.1-1.1 2.3.8 2.5L21 12l-2.5 1-.8 2.5 1.1 2.3-1.1 1.1-2.3-1.1-2.5.8L12 21l-1-2.5-2.5-.8-2.3 1.1-1.1-1.1 1.1-2.3-.8-2.5L3 12l2.5-1 .8-2.5-1.1-2.3 1.1-1.1 2.3 1.1 2.5-.8L12 3Z",
  cursor: "m5 3 12 10-6 1-3 6L5 3Z",
  selectRectangle: "M4 5h16v14H4V5Zm3 3h10v8H7V8Z",
  selectCircle:
    "M12 4a8 8 0 1 0 0 16 8 8 0 0 0 0-16Zm0 4a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z",
  selectPolygon: "m12 3 8 6-3 10H7L3 9l9-6Zm0 4-5 3 2 5h6l2-5-5-3Z",
  token: "M12 3 4 7v10l8 4 8-4V7l-8-4Zm0 0v8m-8-4 8 4 8-4",
  ruler: "m4 17 13-13 3 3L7 20l-3-3Zm8-8 3 3m-6 0 2 2m4-8 3 3",
  template: "M4 4h7v7H4V4Zm9 9h7v7h-7v-7ZM15 4h5v5m-16 6v5h5",
  wall: "M3 5h18v14H3V5Zm6 0v5m6-5v5M3 10h18M6 10v5m6-5v5m6-5v5M3 15h18m6 0v4m6-4v4",
  door: "M5 21V3h14v18M9 21V7h7v14m-2-7h.01",
  window: "M4 5h16v14H4V5Zm8 0v14M4 12h16",
  light:
    "M9 18h6m-5 3h4m-2-18a7 7 0 0 0-4 12c1 1 1 2 1 3h6c0-1 0-2 1-3a7 7 0 0 0-4-12Z",
  layers: "m4 8 8-4 8 4-8 4-8-4Zm0 4 8 4 8-4m-16 4 8 4 8-4",
  pencil: "m4 20 1-5L16 4l4 4L9 19l-5 1Zm10-14 4 4",
  pin: "M12 21s6-6 6-12a6 6 0 1 0-12 0c0 6 6 12 6 12Zm0-9a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z",
  region: "M4 5c3-2 5 1 8 0s5-2 8 0v14c-3 2-5-1-8 0s-5 2-8 0V5Z",
  fog: "M3 9h11a3 3 0 1 0-3-3M3 13h16a2 2 0 1 1-2 2m-14 2h9",
  grid: "M4 4h16v16H4V4Zm0 5h16M4 15h16M9 4v16m6-16v16",
  check: "m5 12 4 4L19 6",
  chain:
    "M10 13a4 4 0 0 0 6 0l3-3a4 4 0 0 0-6-6l-2 2m3 5a4 4 0 0 1-6 0L5 8a4 4 0 0 1 6-6l2 2",
  palette:
    "M12 3a9 9 0 0 0 0 18h1.5a2 2 0 0 0 0-4H12a2 2 0 0 1 0-4h4a5 5 0 0 0 5-5c0-6-4-11-9-11ZM7 10h.01m2-3h.01m4-1h.01m4 3h.01",
  power: "M12 3v9m-5-6a8 8 0 1 0 10 0",
  eye: "M3 12s3-6 9-6 9 6-3 6-9 6-9-6-9-6Zm9 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z",
  eyeOff:
    "M3 3l18 18M10.6 6.1A10 10 0 0 1 12 6c6 0 9 6 9 6a15 15 0 0 1-2.1 3M6.2 6.2C4.1 7.7 3 12 3 12s3 6 9 6a9 9 0 0 0 3-.5M9.9 9.9a3 3 0 0 0 4.2 4.2",
  list: "M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01",
  trash: "M4 7h16M9 7V4h6v3m-9 0 1 14h10l1-14M10 11v6m4-6v6",
  trashAll: "M3 7h13M7 7V4h6v3M5 7l1 14h8l1-14m4 3v9m-2-7h4m-4 4h4",
  plus: "M12 5v14M5 12h14",
  copy: "M8 8h11v11H8V8ZM5 16H4V5h11v1",
  sun: "M12 4V2m0 20v-2m8-8h2M2 12h2m13.7-5.7 1.4-1.4M4.9 19.1l1.4-1.4m11.4 0 1.4 1.4M4.9 4.9l1.4 1.4M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8Z",
  cone: "M4 12 20 4v16L4 12Zm5 0h.01",
  area: "M4 6h16v12H4V6Zm4 4h8v4H8v-4Z",
  darkness: "M20 15.5A8 8 0 0 1 8.5 4 8 8 0 1 0 20 15.5Z",
  zoomIn: "M10 4a6 6 0 1 0 0 12 6 6 0 0 0 0-12Zm4 10 6 6M10 7v6M7 10h6",
  zoomOut: "M10 4a6 6 0 1 0 0 12 6 6 0 0 0 0-12Zm4 10 6 6M7 10h6",
  fit: "M4 9V4h5m6 0h5v5m0 6v5h-5m-6 0H4v-5",
  refresh: "M20 7v5h-5M4 17v-5h5m9-3a7 7 0 0 0-12-2m0 10a7 7 0 0 0 12-2",
});

export default {
  name: "TableRailIcon",
  props: {
    name: {
      type: String,
      required: true,
      validator: (value) => Object.prototype.hasOwnProperty.call(PATHS, value),
    },
  },
  computed: {
    path() {
      return PATHS[this.name] || PATHS.settings;
    },
  },
};
</script>
