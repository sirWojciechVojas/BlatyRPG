export const createRuntimePart1 = () => {
  const mockHeroes = [
    {
      id: 1,
      code: "BG1",
      firstName: "Heinz",
      lastName: "Breuer",
    },
    {
      id: 2,
      code: "BG2",
      firstName: "Mira",
      lastName: "Thorn",
    },
    {
      id: 3,
      code: "BG3",
      firstName: "Cedric",
      lastName: "Hale",
    },
    {
      id: 4,
      code: "BG4",
      firstName: "Elara",
      lastName: "Quinn",
    },
    {
      id: 5,
      code: "BG5",
      firstName: "Dorian",
      lastName: "Blackwood",
    },
    {
      id: 6,
      code: "BG6",
      firstName: "Irena",
      lastName: "Falk",
    },
    {
      id: 7,
      code: "BG7",
      firstName: "Tomas",
      lastName: "Wyrzyk",
    },
    {
      id: 8,
      code: "BG8",
      firstName: "Nadia",
      lastName: "Rook",
    },
    {
      id: 9,
      code: "BG9",
      firstName: "Oskar",
      lastName: "Draven",
    },
    {
      id: 10,
      code: "BG10",
      firstName: "Livia",
      lastName: "Storm",
    },
    {
      id: 11,
      code: "BG11",
      firstName: "Marek",
      lastName: "Dunst",
    },
    {
      id: 12,
      code: "BG12",
      firstName: "Selene",
      lastName: "Ward",
    },
    {
      id: 13,
      code: "BG13",
      firstName: "Viktor",
      lastName: "Ashen",
    },
    {
      id: 14,
      code: "BG14",
      firstName: "Klara",
      lastName: "Morn",
    },
    {
      id: 15,
      code: "BG15",
      firstName: "Bruno",
      lastName: "Kestrel",
    },
    {
      id: 16,
      code: "BG16",
      firstName: "Yara",
      lastName: "Vayne",
    },
    {
      id: 17,
      code: "BG17",
      firstName: "Piotr",
      lastName: "Halberg",
    },
    {
      id: 18,
      code: "BG18",
      firstName: "Sabina",
      lastName: "Crowe",
    },
    {
      id: 19,
      code: "BG19",
      firstName: "Leon",
      lastName: "Stroud",
    },
    {
      id: 20,
      code: "BG20",
      firstName: "Helena",
      lastName: "Frost",
    },
  ].map((hero) => ({
    ...hero,
    fullName: `${hero.firstName} ${hero.lastName}`,
  }));
  return { mockHeroes };
};
