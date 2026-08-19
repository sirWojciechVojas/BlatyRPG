export const createRuntimePart3 = (runtime) => {
  const createContainerState = () => {
    const actors = [
      {
        id: 1,
        type: "PC",
        code: "BG1",
        name: "Bohater 1",
      },
      {
        id: 2,
        type: "PC",
        code: "BG2",
        name: "Bohater 2",
      },
      {
        id: 3,
        type: "PC",
        code: "BG3",
        name: "Bohater 3",
      },
      {
        id: 10,
        type: "NPC",
        code: "NPC1",
        name: "Kupiec Bazyl",
      },
    ];
    const shops = [
      {
        id: 1,
        code: "SHOP1",
        name: "Pod Kuflem Piwa",
        ownerActorId: 10,
      },
      {
        id: 2,
        code: "SHOP2",
        name: "Kuznia Zbrojmistrza",
        ownerActorId: 10,
      },
      {
        id: 3,
        code: "SHOP3",
        name: "Alchemik u Bazyla",
        ownerActorId: 10,
      },
    ];
    const containers = [
      {
        id: 1,
        type: "SYSTEM",
        systemKey: "DEFAULT",
        name: "Magazyn DEFAULT",
      },
      {
        id: 2,
        type: "SYSTEM",
        systemKey: "TRASH",
        capacity: runtime.GENERAL_TRASH_SLOT_CAPACITY,
        name: "Otchlan Odrzutow",
      },
      {
        id: 3,
        type: "TRASH",
        actorId: 1,
        ownerCode: "BG1",
        capacity: runtime.PLAYER_TRASH_SLOT_CAPACITY,
        name: "BG1 - Strefa wyrzucania",
      },
      {
        id: 4,
        type: "TRASH",
        actorId: 2,
        ownerCode: "BG2",
        capacity: runtime.PLAYER_TRASH_SLOT_CAPACITY,
        name: "BG2 - Strefa wyrzucania",
      },
      {
        id: 5,
        type: "TRASH",
        actorId: 3,
        ownerCode: "BG3",
        capacity: runtime.PLAYER_TRASH_SLOT_CAPACITY,
        name: "BG3 - Strefa wyrzucania",
      },
      {
        id: 10,
        type: "CHARACTER",
        actorId: 1,
        name: "BG1 - Ekwipunek",
      },
      {
        id: 11,
        type: "CHARACTER",
        actorId: 2,
        name: "BG2 - Ekwipunek",
      },
      {
        id: 12,
        type: "CHARACTER",
        actorId: 3,
        name: "BG3 - Ekwipunek",
      },
      {
        id: 20,
        type: "SHOP",
        shopId: 1,
        name: "Sklep 1 - Asortyment",
      },
      {
        id: 21,
        type: "SHOP",
        shopId: 2,
        name: "Sklep 2 - Asortyment",
      },
      {
        id: 22,
        type: "SHOP",
        shopId: 3,
        name: "Sklep 3 - Asortyment",
      },
    ];
    const itemTemplates = [
      {
        id: 1,
        name: "Miecz imperialny",
        category: "WEAPON",
        isStackable: false,
        basePrice: 1920,
        baseData: {
          damage: "1d8",
          weight: 4,
        },
      },
      {
        id: 2,
        name: "Topor krasnoludzki",
        category: "WEAPON",
        isStackable: false,
        basePrice: 2160,
        baseData: {
          damage: "1d10",
          weight: 5,
        },
      },
      {
        id: 3,
        name: "Eliksir leczenia",
        category: "ALCHEMY",
        isStackable: true,
        basePrice: 960,
        baseData: {
          heal: "2d10",
        },
      },
      {
        id: 4,
        name: "Lina konopna",
        category: "TOOL",
        isStackable: true,
        basePrice: 36,
        baseData: {
          length: "20m",
        },
      },
      {
        id: 5,
        name: "Pancerz skorzeny",
        category: "ARMOR",
        isStackable: false,
        basePrice: 600,
        baseData: {
          armor: 1,
        },
      },
    ];
    const itemInstances = [
      {
        id: 100,
        templateId: 1,
        nameOverride: "Miecz rodowy",
        dataOverride: {
          damage: "1d8+1",
        },
        note: "Pamiatka po przodkach",
      },
      {
        id: 101,
        templateId: 5,
        nameOverride: "Skorzany pancerz zwiadowcy",
        dataOverride: {
          armor: 2,
        },
        note: "Wzmocniony",
      },
    ];
    const containerTemplateItems = [
      {
        containerId: 1,
        templateId: 3,
        quantity: 2,
        priceOverride: null,
      },
      {
        containerId: 1,
        templateId: 4,
        quantity: 4,
        priceOverride: null,
      },
      {
        containerId: 20,
        templateId: 1,
        quantity: 1,
        priceOverride: null,
      },
      {
        containerId: 20,
        templateId: 3,
        quantity: null,
        priceOverride: null,
      },
      {
        containerId: 21,
        templateId: 2,
        quantity: 1,
        priceOverride: null,
      },
      {
        containerId: 22,
        templateId: 4,
        quantity: 10,
        priceOverride: null,
      },
    ];
    const containerInstanceItems = [
      {
        containerId: 1,
        instanceId: 100,
        priceOverride: null,
      },
      {
        containerId: 1,
        instanceId: 101,
        priceOverride: null,
      },
    ];
    const itemMovements = [];
    return {
      containers,
      actors,
      shops,
      itemTemplates,
      itemInstances,
      containerTemplateItems,
      containerInstanceItems,
      itemMovements,
    };
  };
  Object.assign(runtime, {
    createContainerState,
  });
  return {
    createContainerState,
  };
};
