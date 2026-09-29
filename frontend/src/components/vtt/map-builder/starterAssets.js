const ATLAS_URL = "/map-builder/assets/starter-medieval-atlas-v1.png";
const ATLAS_SIZE = 1254;
const CELL_SIZE = ATLAS_SIZE / 4;

const sprite = (
  id,
  name,
  category,
  column,
  row,
  width,
  height,
  tags = [],
  extra = {},
) =>
  Object.freeze({
    id: `starter.${id}`,
    version: 1,
    name,
    category,
    tags: [name.toLocaleLowerCase("pl"), ...tags],
    kind: "sprite",
    source: {
      type: "atlas",
      url: ATLAS_URL,
      frame: {
        x: column * CELL_SIZE,
        y: row * CELL_SIZE,
        width: CELL_SIZE,
        height: CELL_SIZE,
      },
    },
    thumbnails: { small: ATLAS_URL, medium: ATLAS_URL },
    qualityVariants: { preview: ATLAS_URL, standard: ATLAS_URL },
    physicalSize: { width, height, unit: "m" },
    anchor: { x: 0.5, y: 0.5 },
    obstacle: extra.obstacle || null,
    light: extra.light || null,
    materialMaps: {},
    provenance: {
      type: "ai-generated",
      generator: "OpenAI image generation",
      license: "project-generated",
      sourceHash:
        "sha256:04443d93c3fc7edab5cfa9ab835eed2012dcf07249030bf6c541df887325cdf1",
    },
  });

const material = (id, name, color, tags) =>
  Object.freeze({
    id: `starter.material.${id}`,
    version: 1,
    name,
    category: "materiały",
    tags,
    kind: "material",
    material: { color, roughness: 0.82, blendMode: "normal" },
    thumbnails: {},
    qualityVariants: {},
    physicalSize: { width: 1, height: 1, unit: "m" },
    anchor: { x: 0.5, y: 0.5 },
    obstacle: null,
    light: null,
    materialMaps: {},
    provenance: { type: "code-native", license: "project-owned" },
  });

const composition = (id, name, category, tags, objects) =>
  Object.freeze({
    id: `starter.composition.${id}`,
    version: 1,
    name,
    category,
    tags,
    kind: "composition",
    objects,
    thumbnails: {},
    qualityVariants: {},
    physicalSize: { width: 6, height: 6, unit: "m" },
    anchor: { x: 0.5, y: 0.5 },
    obstacle: null,
    light: null,
    materialMaps: {},
    provenance: { type: "authored", license: "project-owned" },
  });

export const STARTER_ASSETS = Object.freeze([
  sprite("oak", "Dąb", "natura", 0, 0, 5, 5, ["drzewo", "liściaste"], {
    obstacle: { type: "circle", radius: 0.55 },
  }),
  sprite("pine", "Sosna", "natura", 1, 0, 4, 4, ["drzewo", "iglaste"], {
    obstacle: { type: "circle", radius: 0.5 },
  }),
  sprite("boulder", "Głaz", "jaskinie", 2, 0, 2.2, 1.8, ["skała", "kamień"], {
    obstacle: { type: "ellipse", width: 1.7, height: 1.35 },
  }),
  sprite("shrub", "Krzew", "natura", 3, 0, 2, 2, ["roślina", "żywopłot"]),
  sprite(
    "tavern-table",
    "Stół karczemny",
    "wnętrza",
    0,
    1,
    2.4,
    1.6,
    ["stół", "karczma", "meble"],
    { obstacle: { type: "rect", width: 2.1, height: 1.3 } },
  ),
  sprite("chair", "Krzesło", "wyposażenie", 1, 1, 0.75, 0.75, ["meble"]),
  sprite(
    "barrel",
    "Beczka piwa",
    "wyposażenie",
    2,
    1,
    0.8,
    0.8,
    ["karczma", "magazyn"],
    { obstacle: { type: "circle", radius: 0.34 } },
  ),
  sprite("bed", "Łóżko", "wnętrza", 3, 1, 2.1, 1.2, ["sypialnia", "meble"], {
    obstacle: { type: "rect", width: 2, height: 1.1 },
  }),
  sprite(
    "hearth",
    "Kamienne palenisko",
    "ruiny",
    0,
    2,
    2.1,
    1.5,
    ["ogień", "kominek"],
    {
      obstacle: { type: "rect", width: 1.8, height: 1.1 },
      light: {
        color: "#ffb35c",
        brightRadius: 2,
        dimRadius: 5,
        animation: "torch",
      },
    },
  ),
  sprite("door", "Drewniane drzwi", "zabudowa", 1, 2, 1.2, 0.25, [
    "portal",
    "ściana",
  ]),
  sprite("window", "Kamienne okno", "lochy", 2, 2, 1.2, 0.25, [
    "otwór",
    "ściana",
  ]),
  sprite(
    "torch",
    "Pochodnia ścienna",
    "lochy",
    3,
    2,
    0.45,
    0.45,
    ["ogień", "światło"],
    {
      light: {
        color: "#ffb35c",
        brightRadius: 2.5,
        dimRadius: 6,
        animation: "torch",
      },
    },
  ),
  sprite(
    "cart",
    "Drewniany wóz",
    "zabudowa",
    0,
    3,
    2.6,
    1.6,
    ["podwórze", "transport"],
    { obstacle: { type: "rect", width: 2.35, height: 1.35 } },
  ),
  sprite(
    "well",
    "Kamienna studnia",
    "zabudowa",
    1,
    3,
    1.8,
    1.8,
    ["podwórze", "woda"],
    { obstacle: { type: "circle", radius: 0.75 } },
  ),
  sprite(
    "crate",
    "Skrzynia targowa",
    "dekoracje",
    2,
    3,
    1,
    1,
    ["magazyn", "ładunek"],
    { obstacle: { type: "rect", width: 0.9, height: 0.9 } },
  ),
  sprite(
    "campfire",
    "Ognisko",
    "dekoracje",
    3,
    3,
    1.3,
    1.3,
    ["ogień", "obóz"],
    {
      light: {
        color: "#ff9f45",
        brightRadius: 2,
        dimRadius: 5,
        animation: "flicker",
      },
    },
  ),
  material("grass", "Trawa", "#52693c", ["trawa", "ziemia", "podwórze"]),
  material("dirt", "Ubita ziemia", "#74583f", ["ziemia", "droga", "błoto"]),
  material("stone", "Kamienna posadzka", "#686762", [
    "kamień",
    "lochy",
    "ruiny",
  ]),
  material("wood", "Drewniana podłoga", "#725035", [
    "deski",
    "karczma",
    "wnętrze",
  ]),
  material("water", "Woda", "#315b69", ["woda", "rzeka", "staw"]),
  composition(
    "tavern-room",
    "Izba karczemna",
    "gotowe kompozycje",
    ["karczma", "pokój"],
    [
      { assetId: "starter.tavern-table", dx: 0, dy: 0, rotation: 0 },
      { assetId: "starter.chair", dx: -1.45, dy: 0, rotation: 90 },
      { assetId: "starter.chair", dx: 1.45, dy: 0, rotation: -90 },
      { assetId: "starter.hearth", dx: 0, dy: -2.2, rotation: 0 },
    ],
  ),
  composition(
    "courtyard",
    "Podwórze karczmy",
    "gotowe kompozycje",
    ["podwórze", "karczma"],
    [
      { assetId: "starter.well", dx: 0, dy: 0, rotation: 0 },
      { assetId: "starter.cart", dx: -2.5, dy: 1.5, rotation: 18 },
      { assetId: "starter.crate", dx: 2, dy: 1, rotation: -8 },
      { assetId: "starter.barrel", dx: 2.7, dy: 0.7, rotation: 0 },
    ],
  ),
  composition(
    "camp",
    "Leśny obóz",
    "gotowe kompozycje",
    ["las", "obóz", "natura"],
    [
      { assetId: "starter.campfire", dx: 0, dy: 0, rotation: 0 },
      { assetId: "starter.boulder", dx: -2, dy: 1.5, rotation: 25 },
      { assetId: "starter.pine", dx: 3, dy: -2, rotation: -12 },
      { assetId: "starter.crate", dx: 1.8, dy: 1.4, rotation: 12 },
    ],
  ),
]);

export const STARTER_ASSET_COUNT = STARTER_ASSETS.length;

export const starterAssetById = (id) =>
  STARTER_ASSETS.find((asset) => asset.id === id) || null;

export const searchStarterAssets = (query = "", category = "") => {
  const needle = String(query).trim().toLocaleLowerCase("pl");
  return STARTER_ASSETS.filter(
    (asset) =>
      (!category || asset.category === category) &&
      (!needle ||
        [asset.name, asset.category, ...asset.tags]
          .join(" ")
          .toLocaleLowerCase("pl")
          .includes(needle)),
  );
};
