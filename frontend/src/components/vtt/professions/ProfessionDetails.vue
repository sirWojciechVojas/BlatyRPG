<template>
  <article v-if="profession" class="profession-details" @dblclick="openWindow">
    <header class="profession-details__header">
      <button
        v-if="compact"
        type="button"
        class="profession-details__back"
        :aria-label="$t('vtt.table.professions.backToList')"
        :title="$t('vtt.table.professions.backToList')"
        @click="back"
      >
        <b aria-hidden="true">←</b>
        <span>{{ $t("vtt.table.professions.backToList") }}</span>
      </button>
      <div>
        <h3>{{ displayName(profession.name) }}</h3>
        <p>
          <span>{{
            $t("vtt.table.professions.entryNumber", { id: profession.id })
          }}</span>
          <span :class="{ 'is-advanced': profession.is_advanced === true }">
            {{
              $t(
                profession.is_advanced
                  ? "vtt.table.professions.advancedProfession"
                  : "vtt.table.professions.basicProfession",
              )
            }}
          </span>
        </p>
      </div>
      <div v-if="canSetCurrentProfession" class="profession-details__gm-action">
        <button
          type="button"
          :disabled="settingCurrent || isCurrentProfession"
          @click="setAsCurrentProfession"
        >
          {{
            $t(
              isCurrentProfession
                ? "vtt.table.professions.catalogProfessionCurrent"
                : settingCurrent
                  ? "vtt.table.professions.savingProfession"
                  : "vtt.table.professions.catalogSetCurrent",
            )
          }}
        </button>
        <small>
          {{
            $t("vtt.table.professions.catalogSetCurrentFor", {
              name: characterState.record?.name || "—",
            })
          }}
        </small>
        <span
          v-if="currentFeedback"
          :class="`is-${currentFeedback.type}`"
          role="status"
        >
          {{ $t(currentFeedback.key) }}
        </span>
      </div>
    </header>

    <section
      v-if="availableImages.length"
      class="profession-details__figures"
      :class="{ 'is-compact': compact }"
      :aria-label="$t('vtt.table.professions.figures')"
    >
      <template v-if="compact">
        <figure v-if="activeFigure">
          <AuthenticatedImage
            :src="activeFigure.url"
            :alt="figureLabel(activeFigure.slot)"
          />
          <figcaption>{{ figureLabel(activeFigure.slot) }}</figcaption>
        </figure>
        <nav
          v-if="availableImages.length > 1"
          :aria-label="$t('vtt.table.professions.figureSwitch')"
        >
          <button
            v-for="image in availableImages"
            :key="image.slot"
            type="button"
            :class="{ 'is-active': figureSlot === image.slot }"
            :aria-pressed="figureSlot === image.slot ? 'true' : 'false'"
            @click="figureSlot = image.slot"
          >
            {{ shortFigureLabel(image.slot) }}
          </button>
        </nav>
      </template>
      <figure v-for="image in compact ? [] : availableImages" :key="image.slot">
        <AuthenticatedImage :src="image.url" :alt="figureLabel(image.slot)" />
        <figcaption>{{ figureLabel(image.slot) }}</figcaption>
      </figure>
    </section>

    <div
      v-if="qualityIssues.length"
      class="profession-details__notice"
      role="note"
    >
      <span aria-hidden="true">!</span>
      <p>{{ qualityIssues.join(" ") }}</p>
    </div>

    <nav
      class="profession-details__tabs"
      :aria-label="$t('vtt.table.professions.cardSections')"
    >
      <button
        v-for="tab in tabs"
        :key="tab.id"
        type="button"
        :class="{ 'is-active': activeSection === tab.id }"
        :aria-pressed="activeSection === tab.id ? 'true' : 'false'"
        @click="setSection(tab.id)"
      >
        {{ $t(tab.labelKey)
        }}<span v-if="tab.id === 'details' && profession.details"> •</span>
      </button>
    </nav>

    <section
      v-if="activeSection === 'info'"
      class="profession-details__document"
    >
      <p v-if="descriptionMissing" class="profession-details__empty">
        {{ $t("vtt.table.professions.descriptionMissing") }}
      </p>
      <p v-else>{{ presentText(profession.description) }}</p>
    </section>

    <section
      v-if="activeSection === 'details'"
      class="profession-details__document"
    >
      <p v-if="profession.details">{{ presentText(profession.details) }}</p>
      <p v-else class="profession-details__empty">
        {{ $t("vtt.table.professions.notesMissing") }}
      </p>
      <small v-if="profession.details">{{
        $t("vtt.table.professions.notesDisclaimer")
      }}</small>
    </section>

    <section v-if="activeSection === 'info'" class="profession-development">
      <div class="profession-development__status">
        <strong>{{ $t(developmentStatusKey) }}</strong>
        <span>{{ $t("vtt.table.professions.developmentDisclaimer") }}</span>
      </div>

      <template v-if="hasDevelopmentData">
        <section class="profession-development__scheme-section">
          <h4>{{ $t("vtt.table.professions.attributesTitle") }}</h4>
          <div v-if="hasAttributes" class="profession-development__scheme">
            <div class="profession-development__scheme-frame">
              <table>
                <caption>
                  —
                  {{
                    displayName(profession.name)
                  }}
                  —
                </caption>
                <tbody v-for="group in attributeGroups" :key="group.id">
                  <tr class="profession-development__scheme-group">
                    <th colspan="8">{{ $t(group.labelKey) }}</th>
                  </tr>
                  <tr class="profession-development__scheme-labels">
                    <th v-for="attribute in group.items" :key="attribute.key">
                      {{ attributeLabel(attribute.key) }}
                    </th>
                  </tr>
                  <tr class="profession-development__scheme-values">
                    <td v-for="attribute in group.items" :key="attribute.key">
                      {{ advancementValue(attribute.value, group.percent) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          <p v-else>{{ $t("vtt.table.professions.notVerified") }}</p>
        </section>

        <section>
          <h4>{{ $t("vtt.table.professions.skillsTitle") }}</h4>
          <p v-if="rawSkills">{{ rawSkills }}</p>
          <p v-else>{{ $t("vtt.table.professions.notCompleted") }}</p>
        </section>

        <section>
          <h4>{{ $t("vtt.table.professions.talentsTitle") }}</h4>
          <p v-if="rawTalents">{{ rawTalents }}</p>
          <p v-else>{{ $t("vtt.table.professions.notCompleted") }}</p>
        </section>

        <section>
          <h4>{{ $t("vtt.table.professions.equipmentTitle") }}</h4>
          <p v-if="equipmentText">{{ equipmentText }}</p>
          <p v-else>{{ $t("vtt.table.professions.notCompleted") }}</p>
        </section>

        <section class="profession-development__paths">
          <div class="profession-development__path-column">
            <h4>{{ $t("vtt.table.professions.entryProfessions") }}</h4>
            <ul v-if="development.paths?.entries?.length">
              <li
                v-for="(item, index) in development.paths.entries"
                :key="`entry-${item.professionId || index}`"
              >
                <button
                  v-if="item.linked !== false && item.professionId"
                  type="button"
                  :title="
                    $t('vtt.table.professions.openRelatedProfession', {
                      name: displayName(item.name),
                    })
                  "
                  @click="selectRelated(item.professionId)"
                >
                  <span>{{ displayName(item.name) }}</span>
                  <b aria-hidden="true">↗</b>
                </button>
                <span
                  v-else
                  class="profession-development__path-unlinked"
                  :title="$t('vtt.table.professions.unlinkedPath')"
                >
                  {{ displayName(item.name) }}
                </span>
              </li>
            </ul>
            <p v-else>{{ $t("vtt.table.professions.notVerified") }}</p>
          </div>
          <div class="profession-development__path-column">
            <h4>{{ $t("vtt.table.professions.exitProfessions") }}</h4>
            <ul v-if="development.paths?.exits?.length">
              <li
                v-for="(item, index) in development.paths.exits"
                :key="`exit-${item.professionId || index}`"
              >
                <button
                  v-if="item.linked !== false && item.professionId"
                  type="button"
                  :title="
                    $t('vtt.table.professions.openRelatedProfession', {
                      name: displayName(item.name),
                    })
                  "
                  @click="selectRelated(item.professionId)"
                >
                  <span>{{ displayName(item.name) }}</span>
                  <b aria-hidden="true">↗</b>
                </button>
                <span
                  v-else
                  class="profession-development__path-unlinked"
                  :title="$t('vtt.table.professions.unlinkedPath')"
                >
                  {{ displayName(item.name) }}
                </span>
              </li>
            </ul>
            <p v-else>{{ $t("vtt.table.professions.notVerified") }}</p>
          </div>
        </section>
      </template>
      <p v-else class="profession-details__empty">
        {{ $t("vtt.table.professions.developmentMissing") }}
      </p>
    </section>
  </article>
</template>

<script>
import {
  displayProfessionName,
  EDITORIAL_NOTE_IDS,
  normalizeProfessionText,
  presentProfessionText,
  SOURCE_REFERENCE_IDS,
} from "./professionPresentation";
import AuthenticatedImage from "@/components/ui/AuthenticatedImage.vue";

export default {
  name: "ProfessionDetails",
  components: { AuthenticatedImage },
  props: {
    compact: { type: Boolean, default: false },
  },
  emits: ["open-window"],
  data: () => ({
    figureSlot: null,
    settingCurrent: false,
    currentFeedback: null,
  }),
  computed: {
    state() {
      return this.$store.state.professions;
    },
    profession() {
      return this.$store.getters["professions/selected"];
    },
    characterState() {
      return this.state.character;
    },
    canSetCurrentProfession() {
      return (
        this.characterState.phase === "ready" &&
        Boolean(this.characterState.id) &&
        this.characterState.capabilities?.canManageProfession === true
      );
    },
    isCurrentProfession() {
      return (
        Number(this.characterState.current?.professionId) ===
        Number(this.profession?.id)
      );
    },
    availableImages() {
      return ["male", "female"]
        .map((slot) => {
          const image = this.profession?.images?.[slot];
          return image?.url ? { ...image, slot } : null;
        })
        .filter(Boolean);
    },
    activeFigure() {
      return (
        this.availableImages.find((image) => image.slot === this.figureSlot) ||
        this.availableImages[0] ||
        null
      );
    },
    development() {
      return (
        this.profession?.development || {
          status: "unavailable",
          attributes: [],
          skills: [],
          talents: [],
          equipment: [],
          paths: { entries: [], exits: [] },
        }
      );
    },
    tabs() {
      return [
        { id: "info", labelKey: "vtt.table.professions.info" },
        { id: "details", labelKey: "vtt.table.professions.notes" },
      ];
    },
    activeSection() {
      return this.state.section === "details" ? "details" : "info";
    },
    descriptionMissing() {
      const value = String(this.profession?.description || "").trim();
      return !value || value.toLocaleUpperCase("pl-PL") === "BRAK";
    },
    qualityIssues() {
      const issues = [];
      if (this.descriptionMissing) {
        issues.push(this.$t("vtt.table.professions.qualityMissingDescription"));
      }
      if (SOURCE_REFERENCE_IDS.includes(Number(this.profession?.id))) {
        issues.push(this.$t("vtt.table.professions.qualitySourceReference"));
      }
      const normalizedName = normalizeProfessionText(this.profession?.name);
      const duplicates = this.state.items.filter(
        (item) => normalizeProfessionText(item.name) === normalizedName,
      );
      if (duplicates.length > 1) {
        issues.push(
          this.$t("vtt.table.professions.qualityDuplicate", {
            id: this.profession.id,
          }),
        );
      }
      if (Number(this.profession?.id) === 226) {
        issues.push(this.$t("vtt.table.professions.qualityTypeConflict"));
      }
      if (EDITORIAL_NOTE_IDS.includes(Number(this.profession?.id))) {
        issues.push(this.$t("vtt.table.professions.qualityEditorialNote"));
      }
      return issues;
    },
    attributeGroups() {
      const values = new Map(
        (this.development.attributes || []).map((attribute) => [
          attribute.key,
          Number(attribute.value || 0),
        ]),
      );
      const group = (id, labelKey, percent, keys) => ({
        id,
        labelKey,
        percent,
        items: keys.map((key) => ({ key, value: values.get(key) || 0 })),
      });
      return [
        group("primary", "vtt.table.professions.primaryAttributes", true, [
          "weapon_skill",
          "ballistic_skill",
          "strength",
          "toughness",
          "agility",
          "intelligence",
          "willpower",
          "fellowship",
        ]),
        group("secondary", "vtt.table.professions.secondaryAttributes", false, [
          "attacks",
          "wounds",
          "strength_bonus",
          "toughness_bonus",
          "movement",
          "magic",
          "insanity_points",
          "fate_points",
        ]),
      ];
    },
    hasAttributes() {
      return (this.development.attributes || []).length > 0;
    },
    rawSkills() {
      return this.rawDefinitions(this.development.skills);
    },
    rawTalents() {
      return this.rawDefinitions(this.development.talents);
    },
    equipmentText() {
      return (this.development.equipment || [])
        .map((item) => presentProfessionText(item.notes || item.name || ""))
        .filter(Boolean)
        .join("\n");
    },
    hasDevelopmentData() {
      return (
        this.development.status === "unverified" ||
        this.hasAttributes ||
        Boolean(this.rawSkills || this.rawTalents || this.equipmentText) ||
        Boolean(
          this.development.paths?.entries?.length ||
          this.development.paths?.exits?.length,
        )
      );
    },
    developmentStatusKey() {
      return this.development.status === "unverified"
        ? "vtt.table.professions.developmentUnverified"
        : "vtt.table.professions.developmentUnavailable";
    },
  },
  watch: {
    "profession.id": {
      immediate: true,
      handler() {
        this.chooseRandomFigure();
        this.currentFeedback = null;
      },
    },
  },
  methods: {
    displayName: displayProfessionName,
    presentText: presentProfessionText,
    openWindow(event) {
      if (!this.compact || event?.target?.closest?.("button, input, a")) return;
      this.$emit("open-window");
    },
    chooseRandomFigure() {
      const images = this.availableImages;
      this.figureSlot = images.length
        ? images[Math.floor(Math.random() * images.length)].slot
        : null;
    },
    figureLabel(slot) {
      return this.$t(
        slot === "female"
          ? "vtt.table.professions.femaleFigure"
          : "vtt.table.professions.maleFigure",
      );
    },
    shortFigureLabel(slot) {
      return this.$t(
        slot === "female"
          ? "vtt.table.professions.femaleFigureShort"
          : "vtt.table.professions.maleFigureShort",
      );
    },
    back() {
      this.$store.commit("professions/SELECT", null);
    },
    setSection(section) {
      this.$store.commit("professions/SET_SECTION", section);
    },
    selectRelated(id) {
      this.$store.commit("professions/SELECT", id);
    },
    async setAsCurrentProfession() {
      if (
        !this.canSetCurrentProfession ||
        this.isCurrentProfession ||
        this.settingCurrent
      ) {
        return;
      }
      const confirmed = window.confirm(
        this.$t("vtt.table.professions.catalogSetCurrentConfirm", {
          profession: displayProfessionName(this.profession.name),
          character: this.characterState.record?.name || "—",
        }),
      );
      if (!confirmed) return;
      this.settingCurrent = true;
      this.currentFeedback = null;
      try {
        await this.$store.dispatch("professions/changeCharacterProfession", {
          campaignId: this.state.campaignId,
          characterId: this.characterState.id,
          professionId: this.profession.id,
        });
        this.currentFeedback = {
          type: "success",
          key: "vtt.table.professions.professionSaved",
        };
      } catch (_error) {
        this.currentFeedback = {
          type: "error",
          key: "vtt.table.professions.professionSaveFailed",
        };
      } finally {
        this.settingCurrent = false;
      }
    },
    rawDefinitions(definitions) {
      return (definitions || [])
        .map((item) =>
          presentProfessionText(item.display || item.raw || item.name || ""),
        )
        .filter(Boolean)
        .join("\n");
    },
    attributeLabel(key) {
      const translationKey = `vtt.table.professions.attributes.${key}`;
      return this.$te(translationKey) ? this.$t(translationKey) : key;
    },
    advancementValue(value, percent) {
      const amount = Number(value || 0);
      return amount ? `+${amount}${percent ? "%" : ""}` : "–";
    },
  },
};
</script>
