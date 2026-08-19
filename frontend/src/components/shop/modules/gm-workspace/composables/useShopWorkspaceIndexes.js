import { computed } from "vue";

export const useCollectionIndex = (source, key) =>
  computed(() => {
    const index = new Map();
    for (const item of source.value || []) {
      const value = item?.[key];
      if (value !== null && value !== undefined && value !== "") {
        index.set(Number(value), item);
      }
    }
    return index;
  });

const groupBy = (items, key) => {
  const groups = new Map();
  for (const item of items || []) {
    const value = String(item?.[key] ?? "");
    const group = groups.get(value);
    if (group) {
      group.push(item);
    } else {
      groups.set(value, [item]);
    }
  }
  return groups;
};

export const useInstanceIndexes = (instances) => {
  const byLocation = computed(() => groupBy(instances.value, "LOCATION_KIND"));
  const byContainer = computed(() => groupBy(instances.value, "CONTAINER_ID"));
  return {
    byLocation,
    byContainer,
    forLocation: (location) => byLocation.value.get(String(location)) || [],
    forContainer: (containerId) =>
      byContainer.value.get(String(containerId)) || [],
  };
};
