<template>
  <section class="character-stats-panel character-stats-traits cCenter">
    <h3>{{ $t("vtt.table.characterStats.heroTraits") }}</h3>

    <div class="character-stats-traits__columns">
      <div
        class="character-stats-trait-table character-stats-trait-table--main cechyGlowne"
        role="table"
        :aria-label="$t('vtt.table.characterStats.mainTraits')"
      >
        <div
          v-for="trait in model.mainTraits"
          :key="trait.key"
          class="character-stats-trait-row"
          role="row"
        >
          <input
            type="text"
            readonly
            :value="trait.base"
            :aria-label="traitLabel(trait, 'base')"
          />
          <input
            type="text"
            readonly
            :value="trait.advance"
            :aria-label="traitLabel(trait, 'advance')"
          />
          <span
            :class="`character-stats-rank character-stats-rank--${trait.rank}`"
            aria-hidden="true"
          />
          <input
            type="text"
            readonly
            :value="trait.total"
            :aria-label="traitLabel(trait, 'total')"
          />
          <button
            type="button"
            disabled
            :title="$t('vtt.table.characterStats.unavailable')"
            :aria-label="
              $t('vtt.table.characterStats.advanceTrait', {
                trait: $t(trait.labelKey),
                value: trait.step,
              })
            "
          >
            +{{ trait.step }}
          </button>
        </div>
      </div>

      <div class="character-stats-traits__secondary-column">
        <div
          class="character-stats-trait-table character-stats-trait-table--secondary cechyDrugorzedne-1"
          role="table"
          :aria-label="$t('vtt.table.characterStats.secondaryTraits')"
        >
          <div
            v-for="trait in model.secondaryTraits"
            :key="trait.key"
            class="character-stats-trait-row"
            role="row"
          >
            <input
              type="text"
              readonly
              :value="trait.base"
              :aria-label="traitLabel(trait, 'base')"
            />
            <input
              type="text"
              readonly
              :value="trait.advance"
              :aria-label="traitLabel(trait, 'advance')"
            />
            <span
              :class="`character-stats-rank character-stats-rank--${trait.rank}`"
              aria-hidden="true"
            />
            <input
              type="text"
              readonly
              :value="trait.total"
              :aria-label="traitLabel(trait, 'total')"
            />
            <button
              type="button"
              disabled
              :title="$t('vtt.table.characterStats.unavailable')"
              :aria-label="
                $t('vtt.table.characterStats.advanceTrait', {
                  trait: $t(trait.labelKey),
                  value: trait.step,
                })
              "
            >
              +{{ trait.step }}
            </button>
          </div>
        </div>

        <div
          class="character-stats-points cechyDrugorzedne-2"
          role="group"
          :aria-label="$t('vtt.table.characterStats.points.title')"
        >
          <div>
            <output
              :aria-label="$t('vtt.table.characterStats.points.strengthBonus')"
              >{{ model.points.strengthBonus }}</output
            >
            <output
              :aria-label="$t('vtt.table.characterStats.points.toughnessBonus')"
              >{{ model.points.toughnessBonus }}</output
            >
          </div>
          <div>
            <output
              :aria-label="$t('vtt.table.characterStats.points.fatePoints')"
              >{{ model.points.fatePoints }}</output
            >
            <output
              :aria-label="$t('vtt.table.characterStats.points.insanityPoints')"
              >{{ model.points.insanityPoints }}</output
            >
            <button
              type="button"
              disabled
              :title="$t('vtt.table.characterStats.unavailable')"
              :aria-label="$t('vtt.table.characterStats.unavailable')"
            >
              &amp;
            </button>
          </div>
          <div>
            <output
              :aria-label="$t('vtt.table.characterStats.points.fortunePoints')"
              >{{ model.points.fortunePoints }}</output
            >
            <output
              :aria-label="
                $t('vtt.table.characterStats.points.motivationPoints')
              "
              >{{ model.points.motivationPoints }}</output
            >
            <button
              type="button"
              disabled
              :title="$t('vtt.table.characterStats.unavailable')"
              :aria-label="$t('vtt.table.characterStats.unavailable')"
            >
              &amp;
            </button>
          </div>
        </div>
      </div>
    </div>

    <h3 class="character-stats-traits__list-title">
      {{ $t("vtt.table.characterStats.skillsAndTalents") }}
    </h3>
    <div class="character-stats-skills-talents">
      <div class="skills-talents">
        <p v-if="!model.skills.length" class="character-stats-empty">—</p>
        <div
          v-for="(skill, index) in model.skills"
          :key="`${skill.name}-${index}`"
          class="character-stats-skill"
          :title="skill.description || skill.name"
        >
          <span
            :class="`character-stats-skill__mark character-stats-skill__mark--${skill.status}`"
            aria-hidden="true"
          />
          <span>{{ skill.name }}</span>
        </div>
      </div>
      <div class="skills-talents">
        <p v-if="!model.talents.length" class="character-stats-empty">—</p>
        <div
          v-for="(talent, index) in model.talents"
          :key="`${talent.name}-${index}`"
          class="character-stats-talent"
          :title="talent.description || talent.name"
        >
          <span
            :class="`character-stats-talent__mark character-stats-talent__mark--${talent.status}`"
            aria-hidden="true"
          />
          <span>{{ talent.name }}</span>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
export default {
  name: "CharacterStatsTraitsPanel",
  props: {
    model: { type: Object, required: true },
  },
  methods: {
    traitLabel(trait, field) {
      return this.$t("vtt.table.characterStats.traitValue", {
        trait: this.$t(trait.labelKey),
        field: this.$t(`vtt.table.characterStats.traitFields.${field}`),
      });
    },
  },
};
</script>
