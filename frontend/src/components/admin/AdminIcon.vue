<template>
  <svg
    class="admin-icon"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path v-for="path in paths" :key="path" :d="path" />
  </svg>
</template>

<script>
const icons = {
  overview: ["M4 4h6v6H4z", "M14 4h6v4h-6z", "M14 12h6v8h-6z", "M4 14h6v6H4z"],
  users: [
    "M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20",
    "M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7",
    "M17 11a3 3 0 0 0 0-6",
    "M21 20v-1.5a4.5 4.5 0 0 0-3.1-4.28",
  ],
  campaigns: [
    "M3.5 5.5 9 3l6 2.5L20.5 3v15.5L15 21l-6-2.5L3.5 21z",
    "M9 3v15.5",
    "M15 5.5V21",
  ],
  characters: [
    "M6 21v-2a5 5 0 0 1 5-5h2a5 5 0 0 1 5 5v2",
    "M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8",
    "M4 4v16h16",
  ],
  activity: ["M3 12h4l2.2-5 4.1 10 2.2-5H21"],
  system: [
    "M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7",
    "M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.74v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.74v-.51a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z",
  ],
  refresh: ["M20 6v5h-5", "M19 11a7.5 7.5 0 1 0 .2 4.7"],
  addUser: [
    "M14 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-3A4.5 4.5 0 0 0 2 18.5V20",
    "M8 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7",
    "M18 8v6",
    "M15 11h6",
  ],
  search: [
    "m21 21-4.3-4.3",
    "M10.8 18a7.2 7.2 0 1 0 0-14.4 7.2 7.2 0 0 0 0 14.4",
  ],
  home: ["m3 11 9-8 9 8", "M5.5 9.5V21h13V9.5", "M9.5 21v-6h5v6"],
  shield: [
    "M12 22s8-3.8 8-10.5V5l-8-3-8 3v6.5C4 18.2 12 22 12 22z",
    "m9 12 2 2 4-5",
  ],
};

export default {
  name: "AdminIcon",
  props: { name: { type: String, required: true } },
  computed: {
    paths() {
      return icons[this.name] || icons.overview;
    },
  },
};
</script>
