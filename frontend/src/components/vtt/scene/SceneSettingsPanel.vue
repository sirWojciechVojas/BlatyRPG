<template>
  <div
    class="scene-settings"
    @input="clearApiFeedback"
    @change="clearApiFeedback"
  >
    <form class="scene-settings__form" novalidate @submit.prevent="submit">
      <div class="scene-settings__body">
        <nav
          class="scene-settings__tabs"
          role="tablist"
          :aria-label="$t('vtt.scene.settings.sectionsLabel')"
          @keydown="handleTabKeydown"
        >
          <button
            v-for="section in sections"
            :id="id(`tab-${section.id}`)"
            :key="section.id"
            :ref="`tab-${section.id}`"
            type="button"
            role="tab"
            :tabindex="activeSection === section.id ? 0 : -1"
            :aria-selected="activeSection === section.id"
            :aria-controls="id(`panel-${section.id}`)"
            @click="activeSection = section.id"
          >
            <TableRailIcon :name="section.icon" />
            <span>{{ $t(section.labelKey) }}</span>
            <i v-if="sectionHasError(section.id)" aria-hidden="true">!</i>
          </button>
        </nav>

        <main class="scene-settings__content">
          <p v-if="apiError" class="scene-settings__api-error" role="alert">
            {{ apiError }}
          </p>

          <section
            v-if="activeSection === 'basic'"
            :id="id('panel-basic')"
            role="tabpanel"
            :aria-labelledby="id('tab-basic')"
          >
            <SectionHeader
              :title="$t('vtt.scene.settings.sections.basic')"
              :description="$t('vtt.scene.settings.sectionDescriptions.basic')"
            />
            <div class="scene-settings__fields">
              <div class="scene-settings__field scene-settings__field--wide">
                <label :for="id('name')">{{
                  $t("vtt.scene.fields.name")
                }}</label>
                <input
                  :id="id('name')"
                  v-model.trim="form.name"
                  maxlength="150"
                  required
                  :aria-invalid="Boolean(fieldError('name'))"
                  :aria-describedby="
                    fieldError('name') ? id('name-error') : undefined
                  "
                />
                <p
                  v-if="fieldError('name')"
                  :id="id('name-error')"
                  class="scene-settings__field-error"
                  role="alert"
                >
                  {{ fieldError("name") }}
                </p>
              </div>
              <div class="scene-settings__field scene-settings__field--wide">
                <label :for="id('description')">{{
                  $t("vtt.scene.fields.description")
                }}</label>
                <textarea
                  :id="id('description')"
                  v-model="form.description"
                  rows="4"
                  maxlength="10000"
                  :aria-invalid="Boolean(fieldError('description'))"
                />
                <p
                  v-if="fieldError('description')"
                  class="scene-settings__field-error"
                  role="alert"
                >
                  {{ fieldError("description") }}
                </p>
              </div>
              <div class="scene-settings__field">
                <label :for="id('sort-order')">{{
                  $t("vtt.scene.fields.sortOrder")
                }}</label>
                <input
                  :id="id('sort-order')"
                  v-model.number="form.sortOrder"
                  type="number"
                  min="-100000"
                  max="100000"
                  :aria-invalid="Boolean(fieldError('sortOrder'))"
                />
                <p
                  v-if="fieldError('sortOrder')"
                  class="scene-settings__field-error"
                  role="alert"
                >
                  {{ fieldError("sortOrder") }}
                </p>
              </div>
              <label class="scene-settings__switch-row">
                <input
                  v-model="form.isVisible"
                  type="checkbox"
                  class="scene-settings__switch"
                />
                <span>
                  {{ $t("vtt.scene.fields.isVisible") }}
                  <small>{{ $t("vtt.scene.settings.hints.visibility") }}</small>
                </span>
              </label>
            </div>
          </section>

          <section
            v-else-if="activeSection === 'map'"
            :id="id('panel-map')"
            role="tabpanel"
            :aria-labelledby="id('tab-map')"
          >
            <SectionHeader
              :title="$t('vtt.scene.settings.sections.map')"
              :description="$t('vtt.scene.settings.sectionDescriptions.map')"
            />
            <div class="scene-settings__fields">
              <div class="scene-settings__field scene-settings__field--wide">
                <label :for="id('background-url')">{{
                  $t("vtt.scene.fields.backgroundUrl")
                }}</label>
                <div class="scene-settings__input-actions">
                  <input
                    :id="id('background-url')"
                    v-model.trim="form.backgroundUrl"
                    type="text"
                    inputmode="url"
                    maxlength="2048"
                    :placeholder="$t('vtt.scene.settings.map.urlPlaceholder')"
                    :aria-invalid="Boolean(fieldError('backgroundUrl'))"
                  />
                  <button
                    type="button"
                    class="scene-settings__button"
                    :aria-expanded="libraryOpen"
                    :aria-controls="id('asset-library')"
                    @click="libraryOpen = !libraryOpen"
                  >
                    {{ $t("vtt.scene.settings.map.chooseLibrary") }}
                  </button>
                </div>
                <p
                  v-if="fieldError('backgroundUrl')"
                  class="scene-settings__field-error"
                  role="alert"
                >
                  {{ fieldError("backgroundUrl") }}
                </p>
              </div>

              <div
                v-if="libraryOpen"
                :id="id('asset-library')"
                class="scene-settings__library scene-settings__field--wide"
              >
                <p v-if="assetLoading" role="status">
                  {{ $t("vtt.scene.settings.map.libraryLoading") }}
                </p>
                <p v-else-if="!assetLibrary.length">
                  {{ $t("vtt.scene.settings.map.libraryEmpty") }}
                </p>
                <template v-else>
                  <button
                    v-for="asset in assetLibrary"
                    :key="asset.url"
                    type="button"
                    :aria-pressed="form.backgroundUrl === asset.url"
                    @click="chooseAsset(asset)"
                  >
                    <SceneBackgroundImage :src="asset.url" :alt="asset.name" />
                    <span>{{ asset.name }}</span>
                    <small v-if="asset.width && asset.height"
                      >{{ asset.width }} × {{ asset.height }}</small
                    >
                  </button>
                </template>
              </div>

              <div
                class="scene-settings__dropzone scene-settings__field--wide"
                :class="{ 'scene-settings__dropzone--active': dropActive }"
                @dragenter.prevent="dropActive = true"
                @dragover.prevent="dropActive = true"
                @dragleave.prevent="dropActive = false"
                @drop.prevent="handleFileDrop"
              >
                <TableRailIcon name="image" />
                <span>
                  <strong>{{
                    pendingFile
                      ? pendingFile.name
                      : $t("vtt.scene.settings.map.dropTitle")
                  }}</strong>
                  <small>{{ $t("vtt.scene.settings.map.dropHint") }}</small>
                </span>
                <label
                  class="scene-settings__button"
                  :for="id('background-file')"
                >
                  {{ $t("vtt.scene.settings.map.import") }}
                </label>
                <input
                  :id="id('background-file')"
                  class="visually-hidden"
                  type="file"
                  accept="image/png,image/jpeg,image/webp,image/gif"
                  @change="handleFileInput"
                />
              </div>
              <p
                v-if="fileError"
                class="scene-settings__field-error scene-settings__field--wide"
                role="alert"
              >
                {{ fileError }}
              </p>

              <div class="scene-settings__field">
                <label :for="id('width')">{{
                  $t("vtt.scene.fields.width")
                }}</label>
                <div class="scene-settings__input-unit">
                  <input
                    :id="id('width')"
                    :value="form.width"
                    type="number"
                    min="256"
                    max="50000"
                    required
                    :aria-invalid="Boolean(fieldError('width'))"
                    @input="updateDimension('width', $event.target.value)"
                  />
                  <span>px</span>
                </div>
                <p
                  v-if="fieldError('width')"
                  class="scene-settings__field-error"
                  role="alert"
                >
                  {{ fieldError("width") }}
                </p>
              </div>
              <div class="scene-settings__field">
                <label :for="id('height')">{{
                  $t("vtt.scene.fields.height")
                }}</label>
                <div class="scene-settings__input-unit">
                  <input
                    :id="id('height')"
                    :value="form.height"
                    type="number"
                    min="256"
                    max="50000"
                    required
                    :aria-invalid="Boolean(fieldError('height'))"
                    @input="updateDimension('height', $event.target.value)"
                  />
                  <span>px</span>
                </div>
                <p
                  v-if="fieldError('height')"
                  class="scene-settings__field-error"
                  role="alert"
                >
                  {{ fieldError("height") }}
                </p>
              </div>
              <div class="scene-settings__field">
                <label :for="id('padding')">{{
                  $t("vtt.scene.fields.padding")
                }}</label>
                <div class="scene-settings__input-unit">
                  <input
                    :id="id('padding')"
                    v-model.number="form.padding"
                    type="number"
                    min="0"
                    max="5000"
                    :aria-invalid="Boolean(fieldError('padding'))"
                  />
                  <span>px</span>
                </div>
                <p
                  v-if="fieldError('padding')"
                  class="scene-settings__field-error"
                  role="alert"
                >
                  {{ fieldError("padding") }}
                </p>
              </div>
              <div class="scene-settings__field">
                <label :for="id('background-color')">{{
                  $t("vtt.scene.fields.backgroundColor")
                }}</label>
                <div class="scene-settings__color">
                  <input
                    :id="id('background-color')"
                    v-model="form.backgroundColor"
                    type="color"
                  />
                  <input
                    v-model.trim="form.backgroundColor"
                    maxlength="9"
                    :aria-label="$t('vtt.scene.settings.hexColor')"
                  />
                </div>
                <p
                  v-if="fieldError('backgroundColor')"
                  class="scene-settings__field-error"
                  role="alert"
                >
                  {{ fieldError("backgroundColor") }}
                </p>
              </div>
              <div
                class="scene-settings__inline-actions scene-settings__field--wide"
              >
                <button
                  type="button"
                  class="scene-settings__button"
                  :disabled="!previewUrl || imageDimensionsLoading"
                  @click="detectImageDimensions"
                >
                  {{
                    imageDimensionsLoading
                      ? $t("vtt.scene.settings.map.detecting")
                      : $t("vtt.scene.settings.map.detectDimensions")
                  }}
                </button>
                <button
                  type="button"
                  class="scene-settings__button"
                  :aria-pressed="aspectLocked"
                  @click="toggleAspectRatio"
                >
                  {{ $t("vtt.scene.settings.map.keepRatio") }}
                </button>
              </div>
            </div>
          </section>

          <section
            v-else-if="activeSection === 'grid'"
            :id="id('panel-grid')"
            role="tabpanel"
            :aria-labelledby="id('tab-grid')"
          >
            <SectionHeader
              :title="$t('vtt.scene.settings.sections.grid')"
              :description="$t('vtt.scene.settings.sectionDescriptions.grid')"
            >
              <button
                type="button"
                class="scene-settings__button"
                :disabled="form.gridOffsetX === 0 && form.gridOffsetY === 0"
                @click="resetGridOffset"
              >
                {{ $t("vtt.scene.settings.gridActions.resetOffset") }}
              </button>
            </SectionHeader>
            <div class="scene-settings__fields scene-settings__fields--three">
              <div class="scene-settings__field scene-settings__field--wide">
                <label :for="id('grid-type')">{{
                  $t("vtt.scene.fields.gridType")
                }}</label>
                <select :id="id('grid-type')" v-model="form.gridType">
                  <option v-for="type in gridTypes" :key="type" :value="type">
                    {{ $t(`vtt.scene.grid.${type}`) }}
                  </option>
                </select>
              </div>
              <template v-if="form.gridType !== 'gridless'">
                <div class="scene-settings__field">
                  <label :for="id('grid-size')">{{
                    $t("vtt.scene.fields.gridSize")
                  }}</label>
                  <div class="scene-settings__input-unit">
                    <input
                      :id="id('grid-size')"
                      v-model.number="form.gridSize"
                      type="number"
                      min="1"
                      max="1000"
                      required
                      :aria-invalid="Boolean(fieldError('gridSize'))"
                    />
                    <span>px</span>
                  </div>
                  <p
                    v-if="fieldError('gridSize')"
                    class="scene-settings__field-error"
                    role="alert"
                  >
                    {{ fieldError("gridSize") }}
                  </p>
                </div>
                <div class="scene-settings__field">
                  <label :for="id('grid-distance')">{{
                    $t("vtt.scene.fields.gridDistance")
                  }}</label>
                  <div class="scene-settings__input-unit">
                    <input
                      :id="id('grid-distance')"
                      v-model.number="form.gridDistance"
                      type="number"
                      min="0.01"
                      max="1000000"
                      step="0.01"
                      required
                      :aria-invalid="Boolean(fieldError('gridDistance'))"
                    />
                    <span>{{ form.gridUnit || "—" }}</span>
                  </div>
                  <p
                    v-if="fieldError('gridDistance')"
                    class="scene-settings__field-error"
                    role="alert"
                  >
                    {{ fieldError("gridDistance") }}
                  </p>
                </div>
                <div class="scene-settings__field">
                  <label :for="id('grid-unit')">{{
                    $t("vtt.scene.fields.gridUnit")
                  }}</label>
                  <input
                    :id="id('grid-unit')"
                    v-model.trim="form.gridUnit"
                    maxlength="32"
                    required
                    :aria-invalid="Boolean(fieldError('gridUnit'))"
                  />
                  <p
                    v-if="fieldError('gridUnit')"
                    class="scene-settings__field-error"
                    role="alert"
                  >
                    {{ fieldError("gridUnit") }}
                  </p>
                </div>
                <div class="scene-settings__field">
                  <label :for="id('grid-offset-x')">{{
                    $t("vtt.scene.fields.gridOffsetX")
                  }}</label>
                  <div class="scene-settings__input-unit">
                    <input
                      :id="id('grid-offset-x')"
                      v-model.number="form.gridOffsetX"
                      type="number"
                      min="-50000"
                      max="50000"
                      step="0.1"
                      :aria-invalid="Boolean(fieldError('gridOffsetX'))"
                    />
                    <span>px</span>
                  </div>
                  <p
                    v-if="fieldError('gridOffsetX')"
                    class="scene-settings__field-error"
                    role="alert"
                  >
                    {{ fieldError("gridOffsetX") }}
                  </p>
                </div>
                <div class="scene-settings__field">
                  <label :for="id('grid-offset-y')">{{
                    $t("vtt.scene.fields.gridOffsetY")
                  }}</label>
                  <div class="scene-settings__input-unit">
                    <input
                      :id="id('grid-offset-y')"
                      v-model.number="form.gridOffsetY"
                      type="number"
                      min="-50000"
                      max="50000"
                      step="0.1"
                      :aria-invalid="Boolean(fieldError('gridOffsetY'))"
                    />
                    <span>px</span>
                  </div>
                  <p
                    v-if="fieldError('gridOffsetY')"
                    class="scene-settings__field-error"
                    role="alert"
                  >
                    {{ fieldError("gridOffsetY") }}
                  </p>
                </div>
                <div class="scene-settings__field">
                  <label :for="id('grid-color')">{{
                    $t("vtt.scene.fields.gridColor")
                  }}</label>
                  <div class="scene-settings__color">
                    <input
                      :id="id('grid-color')"
                      v-model="form.gridColor"
                      type="color"
                    />
                    <input
                      v-model.trim="form.gridColor"
                      maxlength="9"
                      :aria-label="$t('vtt.scene.settings.hexColor')"
                    />
                  </div>
                  <p
                    v-if="fieldError('gridColor')"
                    class="scene-settings__field-error"
                    role="alert"
                  >
                    {{ fieldError("gridColor") }}
                  </p>
                </div>
                <RangeField
                  :id="id('grid-opacity')"
                  v-model="form.gridOpacity"
                  class="scene-settings__field--wide"
                  :label="$t('vtt.scene.fields.gridOpacity')"
                  :minimum="0"
                  :maximum="1"
                  :step="0.01"
                  percent
                  :error="fieldError('gridOpacity')"
                />
              </template>
            </div>
          </section>

          <section
            v-else-if="activeSection === 'lighting'"
            :id="id('panel-lighting')"
            role="tabpanel"
            :aria-labelledby="id('tab-lighting')"
          >
            <SectionHeader
              :title="$t('vtt.scene.settings.sections.lighting')"
              :description="
                $t('vtt.scene.settings.sectionDescriptions.lighting')
              "
            />
            <div class="scene-settings__fields">
              <label
                class="scene-settings__switch-row scene-settings__field--wide"
              >
                <input
                  v-model="form.globalIllumination"
                  type="checkbox"
                  class="scene-settings__switch"
                />
                <span>{{ $t("vtt.scene.fields.globalIllumination") }}</span>
              </label>
              <RangeField
                :id="id('darkness-level')"
                v-model="form.darknessLevel"
                class="scene-settings__field--wide"
                :label="$t('vtt.scene.fields.darknessLevel')"
                :minimum="0"
                :maximum="1"
                :step="0.01"
                percent
                :error="fieldError('darknessLevel')"
              />
              <RangeField
                :id="id('illumination-threshold')"
                v-model="form.globalIlluminationThreshold"
                class="scene-settings__field--wide"
                :label="$t('vtt.scene.fields.globalIlluminationThreshold')"
                :minimum="0"
                :maximum="1"
                :step="0.01"
                percent
                :error="fieldError('globalIlluminationThreshold')"
              />
              <RangeField
                :id="id('global-light')"
                v-model="form.globalLightLevel"
                class="scene-settings__field--wide"
                :label="$t('vtt.scene.fields.globalLightLevel')"
                :minimum="0"
                :maximum="1"
                :step="0.01"
                percent
                :error="fieldError('globalLightLevel')"
              />
              <div class="scene-settings__field scene-settings__field--wide">
                <span class="scene-settings__label">{{
                  $t("vtt.scene.settings.lighting.presets")
                }}</span>
                <div class="scene-settings__presets">
                  <button
                    v-for="preset in lightingPresets"
                    :key="preset.value"
                    type="button"
                    class="scene-settings__button"
                    :aria-pressed="lightPercent === preset.value"
                    @click="form.globalLightLevel = preset.value / 100"
                  >
                    {{ $t(preset.labelKey) }} · {{ preset.value }}%
                  </button>
                </div>
              </div>
              <p class="scene-settings__callout scene-settings__field--wide">
                {{ $t("vtt.scene.settings.lighting.hint") }}
              </p>
              <div
                v-if="mode === 'edit'"
                class="scene-settings__inline-actions scene-settings__field--wide"
              >
                <button
                  type="button"
                  class="scene-settings__button"
                  :disabled="transitioningDarkness"
                  @click="transitionDarkness(0)"
                >
                  {{ $t("vtt.scene.settings.lighting.transitionDaylight") }}
                </button>
                <button
                  type="button"
                  class="scene-settings__button"
                  :disabled="transitioningDarkness"
                  @click="transitionDarkness(1)"
                >
                  {{ $t("vtt.scene.settings.lighting.transitionDarkness") }}
                </button>
              </div>
            </div>
          </section>

          <section
            v-else
            :id="id('panel-fog')"
            role="tabpanel"
            :aria-labelledby="id('tab-fog')"
          >
            <SectionHeader
              :title="$t('vtt.scene.settings.sections.fog')"
              :description="$t('vtt.scene.settings.sectionDescriptions.fog')"
            >
              <button
                v-if="mode === 'edit'"
                type="button"
                class="scene-settings__button scene-settings__button--danger"
                :disabled="clearingFog"
                @click="confirmFogClearOpen = true"
              >
                {{ $t("vtt.scene.settings.fog.clearMemory") }}
              </button>
            </SectionHeader>
            <div class="scene-settings__fields">
              <div class="scene-settings__switches scene-settings__field--wide">
                <label
                  class="scene-settings__switch-row scene-settings__switch-row--master"
                >
                  <input
                    v-model="form.fogEnabled"
                    type="checkbox"
                    class="scene-settings__switch"
                  />
                  <span
                    >{{ $t("vtt.fog.enabled")
                    }}<small>{{
                      $t("vtt.scene.settings.hints.fogEnabled")
                    }}</small></span
                  >
                </label>
                <fieldset :disabled="!form.fogEnabled">
                  <label class="scene-settings__switch-row">
                    <input
                      v-model="form.fogExploration"
                      type="checkbox"
                      class="scene-settings__switch"
                    />
                    <span
                      >{{ $t("vtt.scene.fields.fogExploration")
                      }}<small>{{
                        $t("vtt.scene.settings.hints.fogExploration")
                      }}</small></span
                    >
                  </label>
                  <label class="scene-settings__switch-row">
                    <input
                      v-model="form.dynamicVision"
                      type="checkbox"
                      class="scene-settings__switch"
                    />
                    <span
                      >{{ $t("vtt.fog.dynamicVision")
                      }}<small>{{
                        $t("vtt.scene.settings.hints.dynamicVision")
                      }}</small></span
                    >
                  </label>
                  <label class="scene-settings__switch-row">
                    <input
                      v-model="form.explorationMemory"
                      type="checkbox"
                      class="scene-settings__switch"
                    />
                    <span
                      >{{ $t("vtt.fog.explorationMemory")
                      }}<small>{{
                        $t("vtt.scene.settings.hints.explorationMemory")
                      }}</small></span
                    >
                  </label>
                  <label class="scene-settings__switch-row">
                    <input
                      v-model="form.fogUpdateDuringDrag"
                      type="checkbox"
                      class="scene-settings__switch"
                    />
                    <span
                      >{{ $t("vtt.fog.updateDuringDrag")
                      }}<small>{{
                        $t("vtt.scene.settings.hints.updateDuringDrag")
                      }}</small></span
                    >
                  </label>
                </fieldset>
              </div>
              <fieldset
                class="scene-settings__fog-fields scene-settings__field--wide"
                :disabled="!form.fogEnabled"
              >
                <div class="scene-settings__field">
                  <label :for="id('fog-mode')">{{
                    $t("vtt.fog.explorationMode")
                  }}</label>
                  <select
                    :id="id('fog-mode')"
                    v-model="form.fogExplorationMode"
                  >
                    <option value="none">{{ $t("vtt.fog.modes.none") }}</option>
                    <option value="individual">
                      {{ $t("vtt.fog.modes.individual") }}
                    </option>
                    <option value="shared">
                      {{ $t("vtt.fog.modes.shared") }}
                    </option>
                  </select>
                </div>
                <div class="scene-settings__field">
                  <label :for="id('fog-color')">{{
                    $t("vtt.fog.unexploredColor")
                  }}</label>
                  <div class="scene-settings__color">
                    <input
                      :id="id('fog-color')"
                      v-model="form.fogUnexploredColor"
                      type="color"
                    />
                    <input
                      v-model.trim="form.fogUnexploredColor"
                      maxlength="9"
                      :aria-label="$t('vtt.scene.settings.hexColor')"
                    />
                  </div>
                  <p
                    v-if="fieldError('fogUnexploredColor')"
                    class="scene-settings__field-error"
                    role="alert"
                  >
                    {{ fieldError("fogUnexploredColor") }}
                  </p>
                </div>
                <div class="scene-settings__field">
                  <label :for="id('fog-explored-color')">{{
                    $t("vtt.fog.exploredColor")
                  }}</label>
                  <div class="scene-settings__color">
                    <input
                      :id="id('fog-explored-color')"
                      v-model="form.fogExploredColor"
                      type="color"
                    />
                    <input
                      v-model.trim="form.fogExploredColor"
                      maxlength="9"
                      :aria-label="$t('vtt.scene.settings.hexColor')"
                    />
                  </div>
                </div>
                <div class="scene-settings__field scene-settings__field--wide">
                  <label :for="id('fog-image')">{{
                    $t("vtt.fog.explorationImage")
                  }}</label>
                  <input
                    :id="id('fog-image')"
                    v-model.trim="form.fogExplorationImage"
                    type="url"
                    maxlength="2048"
                    :placeholder="$t('vtt.scene.settings.map.urlPlaceholder')"
                  />
                </div>
                <RangeField
                  :id="id('fog-softness')"
                  v-model="form.fogEdgeSoftness"
                  :label="$t('vtt.fog.edgeSoftness')"
                  :minimum="0"
                  :maximum="200"
                  :step="1"
                  suffix="px"
                  :error="fieldError('fogEdgeSoftness')"
                />
                <RangeField
                  :id="id('fog-unexplored-opacity')"
                  v-model="form.fogUnexploredOpacity"
                  :label="$t('vtt.fog.unexploredOpacity')"
                  :minimum="0"
                  :maximum="1"
                  :step="0.01"
                  percent
                  :error="fieldError('fogUnexploredOpacity')"
                />
                <RangeField
                  :id="id('fog-explored-opacity')"
                  v-model="form.fogExploredOpacity"
                  :label="$t('vtt.fog.exploredOpacity')"
                  :minimum="0"
                  :maximum="1"
                  :step="0.01"
                  percent
                  :error="fieldError('fogExploredOpacity')"
                />
              </fieldset>
            </div>
            <p
              v-if="fogActionMessage"
              class="scene-settings__action-status"
              role="status"
            >
              {{ fogActionMessage }}
            </p>
          </section>
        </main>

        <SceneSettingsPreview
          class="scene-settings-preview--persistent"
          :scene="form"
        />
      </div>

      <footer class="scene-settings__footer">
        <button
          v-if="mode === 'edit'"
          type="button"
          class="scene-settings__button scene-settings__button--danger"
          :disabled="busy || saving"
          @click="$emit('delete')"
        >
          {{ $t("vtt.scene.actions.delete") }}
        </button>
        <span class="scene-settings__footer-spacer" />
        <span
          class="scene-settings__save-state"
          role="status"
          aria-live="polite"
        >
          {{ saveStateText }}
        </span>
        <button
          type="button"
          class="scene-settings__button scene-settings__button--ghost"
          :disabled="!dirty || busy || saving"
          @click="restore"
        >
          {{ $t("vtt.scene.settings.restore") }}
        </button>
        <button
          type="button"
          class="scene-settings__button"
          :disabled="busy || saving"
          @click="$emit('cancel')"
        >
          {{ $t("vtt.scene.actions.cancel") }}
        </button>
        <button
          type="submit"
          class="scene-settings__button scene-settings__button--primary"
          :disabled="!dirty || busy || saving"
        >
          {{
            saving
              ? $t("vtt.scene.settings.saving")
              : $t("vtt.scene.actions.save")
          }}
        </button>
      </footer>
    </form>

    <UiConfirmDialog
      v-model="confirmFogClearOpen"
      :title="$t('vtt.scene.settings.fog.clearMemory')"
      :description="$t('vtt.scene.settings.fog.clearConfirm')"
      :confirm-label="$t('vtt.scene.settings.fog.clearMemory')"
      :cancel-label="$t('vtt.scene.actions.cancel')"
      :busy="clearingFog"
      danger
      @confirm="clearFogMemory"
    />
  </div>
</template>

<script>
import UiConfirmDialog from "@/components/ui/UiConfirmDialog.vue";
import TableRailIcon from "@/components/vtt/table/TableRailIcon.vue";
import { GRID_TYPES } from "@/lib/vtt/grid";
import {
  sceneAssetApiClient,
  sceneAssetLocation,
} from "@/lib/vtt/sceneAssetApiClient";
import SceneBackgroundImage from "./SceneBackgroundImage.vue";
import SceneSettingsPreview from "./SceneSettingsPreview.vue";
import RangeField from "./SceneSettingsRangeField.vue";
import SectionHeader from "./SceneSettingsSectionHeader.vue";
import {
  API_SCENE_FIELD_MAP,
  firstInvalidSection,
  sceneDraftChanges,
  sceneDraftFingerprint,
  sceneDraftFrom,
  sceneDraftPayload,
  validateSceneDraft,
} from "./sceneSettingsModel";

export default {
  name: "SceneSettingsPanel",
  components: {
    RangeField,
    SceneBackgroundImage,
    SceneSettingsPreview,
    SectionHeader,
    TableRailIcon,
    UiConfirmDialog,
  },
  props: {
    campaignId: { type: [Number, String], required: true },
    instanceId: { type: String, required: true },
    scene: { type: Object, default: null },
    scenes: { type: Array, default: () => [] },
    mode: { type: String, default: "edit" },
    busy: { type: Boolean, default: false },
    active: { type: Boolean, default: true },
  },
  emits: [
    "save",
    "cancel",
    "delete",
    "clear-fog",
    "transition-darkness",
    "dirty-change",
    "status-change",
    "title-change",
  ],
  data: () => ({
    form: sceneDraftFrom(),
    baselineDraft: sceneDraftFrom(),
    baselineFingerprint: "",
    activeSection: "basic",
    gridTypes: Object.values(GRID_TYPES),
    localErrors: {},
    serverFieldErrors: {},
    apiError: "",
    saving: false,
    saveNoticeVisible: false,
    saveNoticeTimer: null,
    libraryOpen: false,
    assetLoading: false,
    uploadedAssets: [],
    pendingFile: null,
    pendingObjectUrl: "",
    fileError: "",
    dropActive: false,
    aspectLocked: false,
    aspectRatio: 16 / 9,
    imageDimensions: null,
    imageDimensionsLoading: false,
    confirmFogClearOpen: false,
    clearingFog: false,
    fogActionMessage: "",
    transitioningDarkness: false,
    initialized: false,
    lightingPresets: [
      { value: 100, labelKey: "vtt.scene.settings.lighting.day" },
      { value: 35, labelKey: "vtt.scene.settings.lighting.dusk" },
      { value: 5, labelKey: "vtt.scene.settings.lighting.night" },
      { value: 0, labelKey: "vtt.scene.settings.lighting.darkness" },
    ],
  }),
  computed: {
    sections() {
      return [
        {
          id: "basic",
          icon: "settings",
          labelKey: "vtt.scene.settings.sections.basic",
        },
        {
          id: "map",
          icon: "image",
          labelKey: "vtt.scene.settings.sections.map",
        },
        {
          id: "grid",
          icon: "grid",
          labelKey: "vtt.scene.settings.sections.grid",
        },
        {
          id: "lighting",
          icon: "light",
          labelKey: "vtt.scene.settings.sections.lighting",
        },
        {
          id: "fog",
          icon: "fog",
          labelKey: "vtt.scene.settings.sections.fog",
        },
      ];
    },
    dirty() {
      return (
        Boolean(this.pendingFile) ||
        sceneDraftFingerprint(this.form) !== this.baselineFingerprint
      );
    },
    previewUrl() {
      return this.pendingObjectUrl || String(this.form.backgroundUrl || "");
    },
    lightPercent() {
      return Math.round(Number(this.form.globalLightLevel || 0) * 100);
    },
    assetLibrary() {
      const items = [...this.uploadedAssets];
      for (const scene of this.scenes) {
        if (scene?.backgroundUrl) {
          items.push({
            key: `scene-${scene.id}`,
            name: scene.name,
            url: scene.backgroundUrl,
            width: scene.width,
            height: scene.height,
          });
        }
      }
      const seen = new Set();
      return items.filter((item) => {
        if (!item?.url || seen.has(item.url)) return false;
        seen.add(item.url);
        return true;
      });
    },
    saveStateText() {
      if (this.saving) return this.$t("vtt.scene.settings.saving");
      if (this.saveNoticeVisible) return this.$t("vtt.scene.settings.savedNow");
      if (this.dirty) return this.$t("vtt.scene.settings.unsaved");
      return this.$t("vtt.scene.settings.saved");
    },
  },
  watch: {
    scene: {
      deep: true,
      immediate: true,
      handler(value) {
        if (!this.initialized || !this.dirty) this.loadDraft(value);
      },
    },
    mode() {
      this.loadDraft(this.scene);
    },
    dirty(value) {
      this.$emit("dirty-change", value);
      if (!this.saving) this.setStatus(value ? "dirty" : "saved");
    },
    "form.name"(value) {
      this.$emit("title-change", String(value || "").trim());
    },
  },
  mounted() {
    window.addEventListener("keydown", this.handleSaveShortcut);
    this.loadAssetLibrary();
  },
  beforeUnmount() {
    window.removeEventListener("keydown", this.handleSaveShortcut);
    window.clearTimeout(this.saveNoticeTimer);
    this.releasePendingObjectUrl();
  },
  methods: {
    id(suffix) {
      return `${this.instanceId}-${suffix}`.replace(/[^a-zA-Z0-9_-]/gu, "-");
    },
    loadDraft(scene) {
      const draft = sceneDraftFrom(scene);
      this.form = { ...draft };
      this.baselineDraft = { ...draft };
      this.baselineFingerprint = sceneDraftFingerprint(draft);
      this.aspectRatio = Math.max(
        0.0001,
        Number(draft.width) / Number(draft.height),
      );
      this.localErrors = {};
      this.serverFieldErrors = {};
      this.apiError = "";
      this.fileError = "";
      this.initialized = true;
      this.$emit("title-change", String(draft.name || "").trim());
      this.$emit("dirty-change", false);
      this.setStatus("saved");
    },
    restore() {
      this.form = { ...this.baselineDraft };
      this.releasePendingObjectUrl();
      this.pendingFile = null;
      this.localErrors = {};
      this.serverFieldErrors = {};
      this.apiError = "";
      this.fileError = "";
      this.imageDimensions = null;
      this.setStatus("saved");
    },
    setStatus(status) {
      this.$emit("status-change", status);
    },
    clearApiFeedback() {
      if (Object.keys(this.serverFieldErrors).length)
        this.serverFieldErrors = {};
      if (this.apiError) this.apiError = "";
      if (this.saveNoticeVisible) this.saveNoticeVisible = false;
    },
    validationText(issue) {
      if (!issue) return "";
      return this.$t(
        `vtt.scene.settings.validation.${issue.code}`,
        issue.params || {},
      );
    },
    fieldError(field) {
      if (this.serverFieldErrors[field]) return this.serverFieldErrors[field];
      return this.validationText(this.localErrors[field]);
    },
    sectionHasError(section) {
      return (
        firstInvalidSection({
          ...this.localErrors,
          ...this.serverFieldErrors,
        }) === section
      );
    },
    handleTabKeydown(event) {
      const keys = [
        "ArrowLeft",
        "ArrowRight",
        "ArrowUp",
        "ArrowDown",
        "Home",
        "End",
      ];
      if (!keys.includes(event.key)) return;
      event.preventDefault();
      const current = this.sections.findIndex(
        (section) => section.id === this.activeSection,
      );
      const last = this.sections.length - 1;
      let next = current;
      if (["ArrowLeft", "ArrowUp"].includes(event.key))
        next = current <= 0 ? last : current - 1;
      if (["ArrowRight", "ArrowDown"].includes(event.key))
        next = current >= last ? 0 : current + 1;
      if (event.key === "Home") next = 0;
      if (event.key === "End") next = last;
      this.activeSection = this.sections[next].id;
      this.$nextTick(() =>
        this.$refs[`tab-${this.activeSection}`]?.[0]?.focus(),
      );
    },
    handleSaveShortcut(event) {
      if (
        !this.active ||
        event.key.toLowerCase() !== "s" ||
        (!event.ctrlKey && !event.metaKey)
      )
        return;
      event.preventDefault();
      if (this.dirty && !this.busy && !this.saving) this.submit();
    },
    resetGridOffset() {
      this.form.gridOffsetX = 0;
      this.form.gridOffsetY = 0;
    },
    toggleAspectRatio() {
      this.aspectLocked = !this.aspectLocked;
      if (this.aspectLocked) {
        this.aspectRatio = Math.max(
          0.0001,
          Number(this.form.width) / Number(this.form.height),
        );
      }
    },
    updateDimension(field, value) {
      const numeric = Number(value);
      this.form[field] = numeric;
      if (!this.aspectLocked || !Number.isFinite(numeric) || numeric <= 0)
        return;
      if (field === "width")
        this.form.height = Math.round(numeric / this.aspectRatio);
      else this.form.width = Math.round(numeric * this.aspectRatio);
    },
    rememberImageDimensions(dimensions) {
      if (dimensions?.width > 0 && dimensions?.height > 0)
        this.imageDimensions = dimensions;
    },
    async detectImageDimensions() {
      if (!this.previewUrl || this.imageDimensionsLoading) return;
      this.imageDimensionsLoading = true;
      this.fileError = "";
      let temporaryUrl = "";
      try {
        let source = this.previewUrl;
        if (sceneAssetLocation(source)) {
          const blob = await sceneAssetApiClient.fetchBlobFromUrl(source);
          temporaryUrl = URL.createObjectURL(blob);
          source = temporaryUrl;
        }
        const dimensions =
          this.imageDimensions ||
          (await new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () =>
              resolve({
                width: image.naturalWidth,
                height: image.naturalHeight,
              });
            image.onerror = reject;
            image.src = source;
          }));
        this.form.width = dimensions.width;
        this.form.height = dimensions.height;
        this.aspectRatio = dimensions.width / dimensions.height;
      } catch (_error) {
        this.fileError = this.$t("vtt.scene.settings.map.imageError");
      } finally {
        if (temporaryUrl) URL.revokeObjectURL(temporaryUrl);
        this.imageDimensionsLoading = false;
      }
    },
    chooseAsset(asset) {
      this.releasePendingObjectUrl();
      this.pendingFile = null;
      this.form.backgroundUrl = asset.url;
      if (asset.width && asset.height)
        this.imageDimensions = {
          width: asset.width,
          height: asset.height,
        };
      this.libraryOpen = false;
      this.fileError = "";
    },
    handleFileInput(event) {
      this.selectFile(event.target.files?.[0]);
      event.target.value = "";
    },
    handleFileDrop(event) {
      this.dropActive = false;
      this.selectFile(event.dataTransfer?.files?.[0]);
    },
    selectFile(file) {
      this.fileError = "";
      const allowed = ["image/png", "image/jpeg", "image/webp", "image/gif"];
      if (
        !file ||
        !allowed.includes(file.type) ||
        file.size < 1 ||
        file.size > 25 * 1024 * 1024
      ) {
        this.fileError = this.$t("vtt.scene.settings.map.invalidFile");
        return;
      }
      this.releasePendingObjectUrl();
      this.pendingFile = file;
      this.pendingObjectUrl = URL.createObjectURL(file);
      this.imageDimensions = null;
      this.libraryOpen = false;
    },
    releasePendingObjectUrl() {
      if (this.pendingObjectUrl) URL.revokeObjectURL(this.pendingObjectUrl);
      this.pendingObjectUrl = "";
    },
    async loadAssetLibrary() {
      this.assetLoading = true;
      try {
        this.uploadedAssets = await sceneAssetApiClient.list(this.campaignId);
      } catch (_error) {
        this.uploadedAssets = [];
      } finally {
        this.assetLoading = false;
      }
    },
    async uploadPendingFile() {
      if (!this.pendingFile) return;
      const result = await sceneAssetApiClient.upload(
        this.campaignId,
        this.pendingFile,
      );
      const asset = result?.asset;
      if (!asset?.url) throw new Error("scene_asset_upload_failed");
      this.form.backgroundUrl = asset.url;
      this.uploadedAssets = [
        asset,
        ...this.uploadedAssets.filter((item) => item.url !== asset.url),
      ];
      this.pendingFile = null;
    },
    applyApiError(error) {
      const details = error?.payload?.errors || {};
      this.serverFieldErrors = Object.fromEntries(
        Object.keys(details)
          .map((field) => [
            API_SCENE_FIELD_MAP[field],
            this.$t("vtt.scene.settings.validation.server"),
          ])
          .filter(([field]) => field),
      );
      this.apiError = error?.network
        ? this.$t("vtt.scene.errors.network")
        : error?.code === "revision_conflict"
          ? this.$t("vtt.scene.errors.conflict")
          : this.$t("vtt.scene.settings.saveFailed");
      if (Object.keys(this.serverFieldErrors).length) {
        this.activeSection = firstInvalidSection(this.serverFieldErrors);
      }
    },
    async submit() {
      if (this.saving || this.busy || !this.dirty) return;
      this.clearApiFeedback();
      this.localErrors = validateSceneDraft({
        ...this.form,
        backgroundUrl: this.pendingFile ? "" : this.form.backgroundUrl,
      });
      if (Object.keys(this.localErrors).length) {
        this.activeSection = firstInvalidSection(this.localErrors);
        this.apiError = this.$t("vtt.scene.settings.validation.summary");
        this.setStatus("error");
        return;
      }
      this.saving = true;
      this.setStatus("saving");
      try {
        await this.uploadPendingFile();
        const savedScene = await new Promise((resolve, reject) => {
          this.$emit("save", {
            payload:
              this.mode === "create"
                ? sceneDraftPayload(this.form)
                : sceneDraftChanges(this.form, this.baselineDraft),
            resolve,
            reject,
          });
        });
        this.releasePendingObjectUrl();
        this.loadDraft(savedScene || this.form);
        this.saveNoticeVisible = true;
        window.clearTimeout(this.saveNoticeTimer);
        this.saveNoticeTimer = window.setTimeout(() => {
          this.saveNoticeVisible = false;
        }, 2200);
      } catch (error) {
        this.applyApiError(error);
        this.setStatus("error");
      } finally {
        this.saving = false;
      }
    },
    async clearFogMemory() {
      if (this.clearingFog) return;
      this.clearingFog = true;
      this.fogActionMessage = "";
      try {
        await new Promise((resolve, reject) =>
          this.$emit("clear-fog", { resolve, reject }),
        );
        this.confirmFogClearOpen = false;
        this.fogActionMessage = this.$t("vtt.scene.settings.fog.cleared");
      } catch (_error) {
        this.confirmFogClearOpen = false;
        this.fogActionMessage = this.$t("vtt.scene.settings.fog.clearFailed");
      } finally {
        this.clearingFog = false;
      }
    },
    async transitionDarkness(target) {
      if (this.transitioningDarkness || this.mode !== "edit") return;
      this.transitioningDarkness = true;
      try {
        const scene = await new Promise((resolve, reject) =>
          this.$emit("transition-darkness", {
            target,
            duration: 1500,
            resolve,
            reject,
          }),
        );
        if (scene) this.loadDraft(scene);
      } catch (_error) {
        this.apiError = this.$t("vtt.scene.settings.lighting.transitionFailed");
      } finally {
        this.transitioningDarkness = false;
      }
    },
  },
};
</script>
