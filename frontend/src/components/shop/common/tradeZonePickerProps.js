export const tradeZonePickerProps = {
  hubRef: {
    type: Object,
    default: null,
  },
  searchInputRef: {
    type: Object,
    default: null,
  },
  options: {
    type: Array,
    default: () => [],
  },
  hotOptions: {
    type: Array,
    default: () => [],
  },
  filteredOptions: {
    type: Array,
    default: () => [],
  },
  panelOpen: {
    type: Boolean,
    default: false,
  },
  label: {
    type: String,
    required: true,
  },
  ariaLabel: {
    type: String,
    required: true,
  },
  triggerId: {
    type: String,
    required: true,
  },
  panelId: {
    type: String,
    required: true,
  },
  searchId: {
    type: String,
    required: true,
  },
  searchValue: {
    type: String,
    default: "",
  },
  searchPlaceholder: {
    type: String,
    default: "",
  },
  activeLabel: {
    type: String,
    default: "-",
  },
  triggerCountText: {
    type: String,
    default: "",
  },
  activeOrdinal: {
    type: String,
    default: "",
  },
  emptyText: {
    type: String,
    default: "",
  },
  disableNav: {
    type: Boolean,
    default: false,
  },
  disableTrigger: {
    type: Boolean,
    default: false,
  },
  showMeter: {
    type: Boolean,
    default: true,
  },
  activeMeterStyle: {
    type: [Object, Array, String],
    default: () => ({}),
  },
  meterStyle: {
    type: Function,
    required: true,
  },
  optionCode: {
    type: Function,
    required: true,
  },
  optionTitle: {
    type: Function,
    required: true,
  },
  quickMeta: {
    type: Function,
    required: true,
  },
  rowMeta: {
    type: Function,
    required: true,
  },
  isActive: {
    type: Function,
    required: true,
  },
  optionKey: {
    type: Function,
    default: (option) => option?.value ?? option?.label ?? "option",
  },
  rowExtraClass: {
    type: Function,
    default: () => ({}),
  },
};
