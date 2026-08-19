import { onScopeDispose, readonly, ref, watch } from "vue";

export const useDeferredValue = (source, delay = 120) => {
  const deferred = ref(source.value);
  let timeoutId;
  const stop = watch(source, (value) => {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      deferred.value = value;
    }, delay);
  });

  onScopeDispose(() => {
    clearTimeout(timeoutId);
    stop();
  });
  return readonly(deferred);
};
