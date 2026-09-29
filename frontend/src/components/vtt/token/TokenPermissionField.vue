<template>
  <fieldset class="token-permission-field">
    <legend>{{ label }}</legend>
    <select :value="modelValue.mode" @change="setMode($event.target.value)">
      <option value="gm">{{ $t("vtt.token.permissions.gm") }}</option>
      <option value="everyone">
        {{ $t("vtt.token.permissions.everyone") }}
      </option>
      <option value="users">{{ $t("vtt.token.permissions.users") }}</option>
      <option v-if="allowInherit" value="inherit">
        {{ $t("vtt.token.permissions.inherit") }}
      </option>
    </select>
    <div
      v-if="modelValue.mode === 'users'"
      class="token-permission-field__users"
    >
      <label v-for="member in activeMembers" :key="member.userId">
        <input
          type="checkbox"
          :checked="selectedIds.has(member.userId)"
          @change="toggle(member.userId)"
        />
        <span>{{ member.username || member.email }}</span>
      </label>
      <small v-if="!activeMembers.length">
        {{ $t("vtt.token.permissions.noMembers") }}
      </small>
    </div>
  </fieldset>
</template>

<script>
export default {
  name: "TokenPermissionField",
  props: {
    modelValue: { type: Object, required: true },
    label: { type: String, required: true },
    members: { type: Array, default: () => [] },
    allowInherit: { type: Boolean, default: false },
  },
  emits: ["update:modelValue"],
  computed: {
    activeMembers() {
      return this.members.filter((member) => member.isActive !== false);
    },
    selectedIds() {
      return new Set(this.modelValue.userIds || []);
    },
  },
  methods: {
    setMode(mode) {
      this.$emit("update:modelValue", {
        mode,
        userIds: mode === "users" ? this.modelValue.userIds || [] : [],
      });
    },
    toggle(userId) {
      const ids = new Set(this.modelValue.userIds || []);
      if (ids.has(userId)) ids.delete(userId);
      else ids.add(userId);
      this.$emit("update:modelValue", {
        mode: "users",
        userIds: [...ids].sort((left, right) => left - right),
      });
    },
  },
};
</script>
