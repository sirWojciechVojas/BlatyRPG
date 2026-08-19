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
  image: "M4 5h16v14H4V5Zm3 11 4-5 3 3 2-2 4 4M8 9h.01",
  users:
    "M8 12a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8-1a2.5 2.5 0 1 0 0-5M3 19c0-3 2-5 5-5s5 2 5 5m1-5c3 0 5 2 5 5",
  file: "M6 3h8l4 4v14H6V3Zm8 0v5h4M9 12h6m-6 4h6",
  book: "M4 5c4-1 6 0 8 2v13c-2-2-4-3-8-2V5Zm16 0c-4-1-6 0-8 2v13c2-2 4-3 8-2V5Z",
  shop: "M4 9h16l-1-5H5L4 9Zm1 0v11h14V9M9 20v-6h6v6",
  music: "M9 18a3 2 0 1 1-3-2m3 2V6l10-2v10m0 0a3 2 0 1 1-3-2",
  bell: "M6 17h12l-2-3V9a4 4 0 0 0-8 0v5l-2 3Zm4 3h4",
  settings:
    "M12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6Zm0-6 1 2.5 2.5.8 2.3-1.1 1.1 1.1-1.1 2.3.8 2.5L21 12l-2.5 1-.8 2.5 1.1 2.3-1.1 1.1-2.3-1.1-2.5.8L12 21l-1-2.5-2.5-.8-2.3 1.1-1.1-1.1 1.1-2.3-.8-2.5L3 12l2.5-1 .8-2.5-1.1-2.3 1.1-1.1 2.3 1.1 2.5-.8L12 3Z",
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
