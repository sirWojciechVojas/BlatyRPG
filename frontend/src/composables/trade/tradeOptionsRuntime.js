import { computed, isRef, onBeforeUnmount, onMounted, unref, watch } from "vue";

const hasOwn = (target, key) =>
  Object.prototype.hasOwnProperty.call(target, key);

const createVm = ({ store, state, api, deps }) =>
  new Proxy(
    {},
    {
      get(_, key) {
        if (typeof key === "symbol") return undefined;
        if (key === "$store") return store;
        if (hasOwn(state, key)) return state[key];
        if (hasOwn(api, key)) {
          const value = api[key];
          return typeof value === "function" ? value : unref(value);
        }
        if (hasOwn(deps, key)) {
          const value = deps[key];
          return typeof value === "function" ? value : unref(value);
        }
        return undefined;
      },
      set(_, key, value) {
        if (typeof key === "symbol") return false;
        if (hasOwn(state, key)) {
          state[key] = value;
          return true;
        }
        for (const source of [api, deps]) {
          if (!hasOwn(source, key)) continue;
          const current = source[key];
          if (isRef(current)) {
            current.value = value;
            return true;
          }
          if (typeof current !== "function") {
            source[key] = value;
            return true;
          }
          return false;
        }
        state[key] = value;
        return true;
      },
    },
  );

export const bindTradeOptions = (ctx, deps, options) => {
  const { store, state } = ctx;
  const api = {};
  const vm = createVm({ store, state, api, deps });

  Object.entries(options.methods || {}).forEach(([name, method]) => {
    if (typeof method === "function") {
      api[name] = (...args) => method.apply(vm, args);
    }
  });
  Object.entries(options.computed || {}).forEach(([name, getter]) => {
    if (typeof getter === "function") {
      api[name] = computed(() => getter.call(vm));
    }
  });
  Object.entries(options.watch || {}).forEach(([key, definition]) => {
    const config =
      definition && typeof definition === "object"
        ? { ...definition }
        : { handler: definition };
    const handler = config.handler;
    delete config.handler;
    if (typeof handler === "function") {
      watch(
        () => vm[key],
        (value, oldValue) => handler.call(vm, value, oldValue),
        config,
      );
    }
  });
  onMounted(() => options.mounted?.call(vm));
  onBeforeUnmount(() => options.beforeUnmount?.call(vm));

  return api;
};
