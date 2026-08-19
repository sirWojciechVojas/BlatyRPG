<template>
  <section class="campaign-panel">
    <h2>{{ $t("campaignLobby.settings.title") }}</h2>
    <form class="campaign-panel-form" @submit.prevent="submit">
      <label>
        <span>{{ $t("campaignLobby.fields.name") }}</span>
        <input
          v-model.trim="draft.name"
          required
          maxlength="255"
          :disabled="busy"
        />
      </label>
      <label>
        <span>{{ $t("campaignLobby.fields.description") }}</span>
        <textarea
          v-model.trim="draft.description"
          maxlength="10000"
          :disabled="busy"
        />
      </label>
      <label>
        <span>{{ $t("campaignLobby.fields.banner") }}</span>
        <input
          v-model.trim="draft.bannerUrl"
          type="url"
          maxlength="2048"
          :disabled="busy"
        />
      </label>
      <label>
        <span>{{ $t("campaignLobby.fields.status") }}</span>
        <select v-model="draft.status" :disabled="busy">
          <option value="active">
            {{ $t("campaignLobby.status.active") }}
          </option>
          <option value="paused">
            {{ $t("campaignLobby.status.paused") }}
          </option>
          <option value="archived">
            {{ $t("campaignLobby.status.archived") }}
          </option>
        </select>
      </label>
      <button type="submit" :disabled="busy">
        {{ $t("campaignLobby.actions.save") }}
      </button>
    </form>
  </section>
</template>

<script>
const campaignDraft = (campaign = {}) => ({
  name: campaign.name || "",
  description: campaign.description || "",
  bannerUrl: campaign.bannerUrl || "",
  status: campaign.status || "active",
});

export default {
  name: "CampaignSettingsPanel",
  props: {
    campaign: { type: Object, required: true },
    busy: { type: Boolean, default: false },
  },
  emits: ["save"],
  data() {
    return { draft: campaignDraft(this.campaign) };
  },
  watch: {
    campaign: {
      deep: true,
      handler(value) {
        this.draft = campaignDraft(value);
      },
    },
  },
  methods: {
    submit() {
      this.$emit("save", { ...this.draft });
    },
  },
};
</script>
