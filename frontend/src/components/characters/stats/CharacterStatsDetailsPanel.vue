<template>
  <section class="character-stats-panel character-stats-details cRight">
    <div class="character-stats-identity usedName">
      <h3>{{ $t("vtt.table.characterStats.infoAboutCharacter") }}</h3>
      <strong class="character-stats-identity__name">{{
        model.trueName
      }}</strong>
      <p v-if="model.trueName !== model.usedName">
        <em>{{ $t("vtt.table.characterStats.falseIdentity") }}:</em>
        <span>{{ model.usedName }}</span>
      </p>
    </div>

    <dl class="character-stats-details-grid descHero">
      <template v-for="detail in details" :key="detail.id">
        <div
          :class="`character-stats-detail character-stats-detail--${detail.width || 'half'}`"
        >
          <dt>{{ $t(detail.labelKey) }}</dt>
          <dd>
            {{ model.details[detail.id]
            }}<span v-if="detail.suffixKey"> {{ $t(detail.suffixKey) }}</span>
          </dd>
        </div>
      </template>
    </dl>

    <div class="character-stats-history">
      <template v-if="model.history.length">
        <p v-if="model.history[0]">
          <strong>{{ $t("vtt.table.characterStats.history") }}:</strong>
          {{ model.history[0] }}
        </p>
        <p v-if="model.history[1]">
          <strong>{{ $t("vtt.table.characterStats.historyAddition") }}:</strong>
          {{ model.history[1] }}
        </p>
        <p v-if="model.history[2]">
          <strong>{{ $t("vtt.table.characterStats.secret") }}:</strong>
          {{ model.history[2] }}
        </p>
        <p v-if="model.history[3]" class="character-stats-history__note">
          {{ model.history[3] }}
        </p>
      </template>
      <p v-else>{{ $t("vtt.table.characterStats.noHistory") }}</p>
    </div>
  </section>
</template>

<script>
export default {
  name: "CharacterStatsDetailsPanel",
  props: {
    model: { type: Object, required: true },
  },
  data: () => ({
    details: [
      {
        id: "currentProfession",
        labelKey: "vtt.table.characterStats.details.currentProfession",
      },
      {
        id: "previousProfession",
        labelKey: "vtt.table.characterStats.details.previousProfession",
      },
      {
        id: "race",
        labelKey: "vtt.table.characterStats.details.race",
        width: "quarter",
      },
      {
        id: "sex",
        labelKey: "vtt.table.characterStats.details.sex",
        width: "quarter",
      },
      { id: "deity", labelKey: "vtt.table.characterStats.details.deity" },
      {
        id: "eyes",
        labelKey: "vtt.table.characterStats.details.eyes",
        width: "quarter",
      },
      {
        id: "hair",
        labelKey: "vtt.table.characterStats.details.hair",
        width: "quarter",
      },
      {
        id: "birthplace",
        labelKey: "vtt.table.characterStats.details.birthplace",
      },
      {
        id: "age",
        labelKey: "vtt.table.characterStats.details.age",
        width: "sixth",
        suffixKey: "vtt.table.characterStats.units.age",
      },
      {
        id: "height",
        labelKey: "vtt.table.characterStats.details.height",
        width: "sixth",
        suffixKey: "vtt.table.characterStats.units.height",
      },
      {
        id: "weight",
        labelKey: "vtt.table.characterStats.details.weight",
        width: "sixth",
        suffixKey: "vtt.table.characterStats.units.weight",
      },
      { id: "siblings", labelKey: "vtt.table.characterStats.details.siblings" },
      { id: "starSign", labelKey: "vtt.table.characterStats.details.starSign" },
      {
        id: "disorders",
        labelKey: "vtt.table.characterStats.details.disorders",
      },
      {
        id: "specialSigns",
        labelKey: "vtt.table.characterStats.details.specialSigns",
      },
      { id: "scars", labelKey: "vtt.table.characterStats.details.scars" },
    ],
  }),
};
</script>
