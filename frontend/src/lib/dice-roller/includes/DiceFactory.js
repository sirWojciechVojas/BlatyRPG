"use strict";
import * as THREE from "three";
import * as CANNON from "cannon-es";
import { resolveAssetPath } from "../assetPaths.js";
import { DicePreset } from "./DicePreset.js";
export class DiceFactory {
  constructor(options = {}) {
    this.dice = {};
    this.geometries = {};
    this.assetBaseUrl = options.assetBaseUrl || "/dice_roller";

    this.baseScale = 50;

    this.materials = [];
    this.materials_cache = {};
    this.cache_hits = 0;
    this.cache_misses = 0;

    this.label_color = "";
    this.dice_color = "";
    this.edge_color = "";
    this.label_outline = "";
    this.dice_texture = "";
    this.dice_material = "";
    this.bumpMapping = true;

    this.material_options = {
      specular: 0xffffff,
      color: 0xb5b5b5,
      shininess: 5,
      flatShading: true,
    };

    this.cubeMap;

    this.material_types = {
      "": {
        name: "~ Preset ~",
      },
      none: {
        name: "Plastic",
      },
      perfectmetal: {
        name: "Perfect Metal",
        color: 0xdddddd,
        roughness: 0,
        metalness: 1,
        envMapIntensity: 1,
      },
      metal: {
        name: "Metal",
        color: 0xdddddd,
        roughness: 0.5,
        metalness: 0.6,
        envMapIntensity: 1,
      },
      wood: {
        name: "Wood",
        color: 0xdddddd,
        roughness: 0.9,
        metalness: 0.0,
        envMapIntensity: 1,
      },
      glass: {
        name: "Glass",
        color: 0xdddddd,
        roughness: 0.1,
        metalness: 0.0,
        envMapIntensity: 1,
      },
    };

    this.canvas;

    // fixes texture rotations on specific dice models
    this.rotate = {
      d8: { even: -7.5, odd: -127.5 },
      d10: { all: -6 },
      d12: { all: 5 },
      d20: { all: -7.5 },
    };

    this.systems = {
      d20: { id: "d20", name: "D20", dice: [] },
      dweird: { id: "dweird", name: "D-Weird", dice: [] },
      swrpg: { id: "swrpg", name: "Star Wars™ RPG", dice: [] },
      swarmada: { id: "swarmada", name: "Star Wars™ Armada", dice: [] },
      xwing: { id: "xwing", name: "Star Wars™ X-Wing", dice: [] },
      legion: { id: "legion", name: "Star Wars™ Legion", dice: [] },
      all: { id: "alldice", name: "ALL THE DICE", dice: [] },
    };

    let diceobj;
    diceobj = new DicePreset("d2");
    diceobj.name = "d2";
    diceobj.setLabels(["1", "2"]);
    diceobj.setValues(1, 2);
    diceobj.inertia = 5;
    diceobj.mass = 2000;
    diceobj.scale = 0.9;
    diceobj.system = "dweird";
    this.register(diceobj);

    diceobj = new DicePreset("dc", "d2");
    diceobj.name = "Coin";
    diceobj.setLabels([
      resolveAssetPath(this.assetBaseUrl, "textures/silvercoin/tail.png"),
      resolveAssetPath(this.assetBaseUrl, "textures/silvercoin/heads.png"),
    ]);
    diceobj.setBumpMaps([
      resolveAssetPath(this.assetBaseUrl, "textures/silvercoin/tail_bump.png"),
      resolveAssetPath(this.assetBaseUrl, "textures/silvercoin/heads_bump.png"),
    ]);
    diceobj.setValues(0, 1);
    diceobj.inertia = 5;
    diceobj.mass = 2000;
    diceobj.scale = 1;
    diceobj.colorset = "coin_silver";
    this.register(diceobj);

    // diceobj = new DicePreset('d1', 'd6');
    // diceobj.name = 'One-sided Dice';
    // diceobj.setLabels(['1']);
    // diceobj.setValues(1,1);
    // diceobj.scale = 0.9;
    // diceobj.system = 'dweird';
    // this.register(diceobj);

    // diceobj = new DicePreset('D2', 'd6');
    // diceobj.name = 'Two-Sided Dice';
    // diceobj.setLabels(['1', '2']);
    // diceobj.setValues(1,2);
    // diceobj.scale = 0.9;
    // diceobj.system = 'dweird';
    // this.register(diceobj);

    diceobj = new DicePreset("d3", "d6");
    diceobj.name = "Three-Sided Dice";
    diceobj.setLabels(["1", "2", "3"]);
    diceobj.setValues(1, 3);
    diceobj.scale = 0.9;
    diceobj.system = "dweird";
    this.register(diceobj);

    diceobj = new DicePreset("df", "d6");
    diceobj.name = "Fudge Dice";
    diceobj.setLabels(["-", "0", "+"]);
    diceobj.setValues(-1, 1);
    diceobj.scale = 0.9;
    diceobj.system = "dweird";
    this.register(diceobj);

    diceobj = new DicePreset("d4");
    diceobj.name = "Four-Sided Dice";
    diceobj.setLabels(["1", "2", "3", "4"]);
    diceobj.setValues(1, 4);
    diceobj.inertia = 5;
    diceobj.scale = 1.1;
    this.register(diceobj);

    diceobj = new DicePreset("d6");
    diceobj.name = "Six-Sided Dice (Numbers)";
    diceobj.setLabels(["1", "2", "3", "4", "5", "6"]);
    diceobj.setValues(1, 6);
    diceobj.scale = 0.9;
    this.register(diceobj);

    // diceobj = new DicePreset('dpip', 'd6');
    // diceobj.name = 'Six-Sided Dice (Pips)';
    // diceobj.setLabels([ '   \n ⬤ \n   ', '⬤  \n   \n  ⬤', '⬤  \n ⬤ \n  ⬤',
    // 					'⬤ ⬤\n   \n⬤ ⬤', '⬤ ⬤\n ⬤ \n⬤ ⬤', '⬤ ⬤\n⬤ ⬤\n⬤ ⬤']);
    // diceobj.setValues(1,6);
    // diceobj.scale = 0.9;
    // diceobj.font = 'monospace';
    // this.register(diceobj);

    // diceobj = new DicePreset('dsex', 'd6');
    // diceobj.name = 'Sex-Sided Emoji Dice';
    // diceobj.setLabels(['🍆', '🍑', '👌', '💦', '🙏', '💥']);
    // diceobj.setValues(1,6);
    // diceobj.scale = 0.9;
    // diceobj.display = 'labels';
    // diceobj.system = 'dweird';
    // this.register(diceobj);

    // diceobj = new DicePreset('dpoker', 'd6');
    // diceobj.name = 'Poker Dice (9-Ace)';
    // diceobj.setLabels(['A', '9', '10', 'J', 'Q', 'K']);
    // diceobj.setValues(1,6);
    // diceobj.scale = 0.9;
    // diceobj.display = 'labels';
    // diceobj.system = 'dweird';
    // diceobj.font = 'Times New Roman';
    // this.register(diceobj);

    // diceobj = new DicePreset('dspanpoker', 'd8');
    // diceobj.name = 'Spanish Poker Dice (7-Ace)';
    // diceobj.setLabels(['A', '7', '8', '9', '10', 'J', 'Q', 'K']);
    // diceobj.setValues(1,8);
    // diceobj.display = 'labels';
    // diceobj.system = 'dweird';
    // diceobj.font = 'Times New Roman';
    // this.register(diceobj);

    // diceobj = new DicePreset('disotope','d12');
    // diceobj.name = 'Radioactive Twelve-Sided Dice';
    // diceobj.setLabels(['', '', '', '', '', '', '', '', '', '', '', '☢️']);
    // diceobj.values = [0,0,0,0,0,0,0,0,0,0,0,1];
    // diceobj.mass = 350;
    // diceobj.inertia = 8;
    // diceobj.scale = 0.9;
    // diceobj.system = 'dweird';
    // this.register(diceobj);

    // diceobj = new DicePreset('dsuit', 'd4');
    // diceobj.name = 'Four-Suited Dice';
    // diceobj.setLabels(['♠️', '♥️', '♦️', '♣️']);
    // diceobj.setValues(1,4);
    // diceobj.inertia = 5;
    // diceobj.scale = 1.2;
    // diceobj.display = 'labels';
    // diceobj.system = 'dweird';
    // this.register(diceobj);

    diceobj = new DicePreset("d8");
    diceobj.name = "Eight-Sided Dice";
    diceobj.setLabels(["1", "2", "3", "4", "5", "6", "7", "8"]);
    diceobj.setValues(1, 8);
    this.register(diceobj);

    diceobj = new DicePreset("d10");
    diceobj.name = "Ten-Sided Dice (Single Digit)";
    diceobj.setLabels(["0", "1", "2", "3", "4", "5", "6", "7", "8", "9"]);
    diceobj.setValues(0, 9);
    diceobj.mass = 350;
    diceobj.inertia = 9;
    diceobj.scale = 0.9;
    this.register(diceobj);

    diceobj = new DicePreset("D10", "d10");
    diceobj.name = "Ten-Sided Dice (Single Digit)";
    diceobj.setLabels(["1", "2", "3", "4", "5", "6", "7", "8", "9", "10"]);
    diceobj.setValues(1, 10);
    diceobj.mass = 350;
    diceobj.inertia = 9;
    diceobj.scale = 0.9;
    this.register(diceobj);

    diceobj = new DicePreset("d100", "d10");
    diceobj.name = "Ten-Sided Dice (Tens Digit)";
    diceobj.setLabels([
      "00",
      "10",
      "20",
      "30",
      "40",
      "50",
      "60",
      "70",
      "80",
      "90",
    ]);
    diceobj.setValues(0, 90, 10);
    diceobj.mass = 350;
    diceobj.inertia = 9;
    diceobj.scale = 0.9;
    this.register(diceobj);

    diceobj = new DicePreset("d12");
    diceobj.name = "Twelve-Sided Dice";
    diceobj.setLabels([
      "1",
      "2",
      "3",
      "4",
      "5",
      "6",
      "7",
      "8",
      "9",
      "10",
      "11",
      "12",
    ]);
    diceobj.setValues(1, 12);
    diceobj.mass = 350;
    diceobj.inertia = 8;
    diceobj.scale = 0.9;
    this.register(diceobj);

    diceobj = new DicePreset("d20");
    diceobj.name = "Twenty-Sided Dice";
    diceobj.setLabels([
      "1",
      "2",
      "3",
      "4",
      "5",
      "6",
      "7",
      "8",
      "9",
      "10",
      "11",
      "12",
      "13",
      "14",
      "15",
      "16",
      "17",
      "18",
      "19",
      "20",
    ]);
    diceobj.setValues(1, 20);
    diceobj.mass = 400;
    diceobj.inertia = 6;
    this.register(diceobj);

    // diceobj = new DicePreset('D100', 'd100');
    // diceobj.name = 'Hundred-Sided Dice';
    // diceobj.setLabels([
    // 	'1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20',
    // 	'21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40',
    // 	'41', '42', '43', '44', '45', '46', '47', '48', '49', '50', '51', '52', '53', '54', '55', '56', '57', '58', '59', '60',
    // 	'61', '62', '63', '64', '65', '66', '67', '68', '69', '70', '71', '72', '73', '74', '75', '76', '77', '78', '79', '80',
    // 	'81', '82', '83', '84', '85', '86', '87', '88', '89', '90', '91', '92', '93', '94', '95', '96', '97', '98', '99', '100'
    // ]);
    // diceobj.setValues(1, 100);
    // diceobj.mass = 500;  // Możesz dostosować masę w zależności od preferencji
    // diceobj.inertia = 10;  // Inercja może być większa ze względu na bardziej skomplikowaną geometrię
    // diceobj.scale = 0.95;  // Skalowanie może być mniejsze lub większe w zależności od wizualnych preferencji
    // this.register(diceobj);

    //star wars rpg dice
    // Ability
    diceobj = new DicePreset("dabi", "d8");
    diceobj.name = "Star Wars RPG: Ability Dice";
    diceobj.setLabels(["s", "a", "s\na", "s\ns", "a", "s", "a\na", ""]);
    diceobj.setValues(1, 8);
    diceobj.setValueMap([]);
    diceobj.font = "SWRPG-Symbol-Regular";
    diceobj.color = "#00FF00";
    diceobj.colorset = "swrpg_abi";
    diceobj.display = "labels";
    diceobj.system = "swrpg";
    // this.register(diceobj);

    // Difficulty
    diceobj = new DicePreset("ddif", "d8");
    diceobj.name = "Star Wars RPG: Difficulty Dice";
    diceobj.setLabels(["t", "f", "f\nt", "t", "", "t\nt", "f\nf", "t"]);
    diceobj.setValues(1, 8);
    diceobj.font = "SWRPG-Symbol-Regular";
    diceobj.color = "#8000FC";
    diceobj.colorset = "swrpg_dif";
    diceobj.display = "labels";
    diceobj.system = "swrpg";
    // this.register(diceobj);

    // Proficiency
    diceobj = new DicePreset("dpro", "d12");
    diceobj.name = "Star Wars RPG: Proficiency Dice";
    diceobj.setLabels([
      "a\na",
      "a",
      "a\na",
      "x",
      "s",
      "s\na",
      "s",
      "s\na",
      "s\ns",
      "s\na",
      "s\ns",
      "",
    ]);
    diceobj.setValues(1, 12);
    diceobj.mass = 350;
    diceobj.inertia = 8;
    diceobj.scale = 0.9;
    diceobj.font = "SWRPG-Symbol-Regular";
    diceobj.color = "#FFFF00";
    diceobj.colorset = "swrpg_pro";
    diceobj.display = "labels";
    diceobj.system = "swrpg";
    // this.register(diceobj);

    // Challenge
    diceobj = new DicePreset("dcha", "d12");
    diceobj.name = "Star Wars RPG: Challenge Dice";
    diceobj.setLabels([
      "t\nt",
      "t",
      "t\nt",
      "t",
      "t\nf",
      "f",
      "t\nf",
      "f",
      "f\nf",
      "y",
      "f\nf",
      "",
    ]);
    diceobj.setValues(1, 12);
    diceobj.mass = 350;
    diceobj.inertia = 8;
    diceobj.scale = 0.9;
    diceobj.font = "SWRPG-Symbol-Regular";
    diceobj.color = "#FF0000";
    diceobj.colorset = "swrpg_cha";
    diceobj.display = "labels";
    diceobj.system = "swrpg";
    // this.register(diceobj);

    // Force
    diceobj = new DicePreset("dfor", "d12");
    diceobj.name = "Star Wars RPG: Force Dice";
    diceobj.setLabels([
      "z",
      "Z\nZ",
      "z",
      "Z\nZ",
      "z",
      "Z\nZ",
      "z",
      "Z",
      "z",
      "Z",
      "z",
      "z\nz",
    ]);
    diceobj.setValues(1, 12);
    diceobj.mass = 350;
    diceobj.inertia = 8;
    diceobj.scale = 0.9;
    diceobj.font = "SWRPG-Symbol-Regular";
    diceobj.color = "#FFFFFF";
    diceobj.colorset = "swrpg_for";
    diceobj.display = "labels";
    diceobj.system = "swrpg";
    // this.register(diceobj);

    // Boost
    diceobj = new DicePreset("dboo", "d6");
    diceobj.name = "Star Wars RPG: Boost Dice";
    diceobj.setLabels(["s  \n  a", "a  \n  a", "s", "a", "", ""]);
    diceobj.setValues(1, 6);
    diceobj.scale = 0.9;
    diceobj.font = "SWRPG-Symbol-Regular";
    diceobj.color = "#00FFFF";
    diceobj.colorset = "swrpg_boo";
    diceobj.display = "labels";
    diceobj.system = "swrpg";
    // this.register(diceobj);

    // Setback
    diceobj = new DicePreset("dset", "d6");
    diceobj.name = "Star Wars RPG: Setback Dice";
    diceobj.setLabels(["", "t", "f"]);
    diceobj.setValues(1, 3);
    diceobj.scale = 0.9;
    diceobj.font = "SWRPG-Symbol-Regular";
    diceobj.color = "#111111";
    diceobj.colorset = "swrpg_set";
    diceobj.display = "labels";
    diceobj.system = "swrpg";
    // this.register(diceobj);

    // star wars armada dice
    // Attack Red
    diceobj = new DicePreset("swar", "d8");
    diceobj.name = "Star Wars Armada: Red Attack Dice";
    diceobj.setLabels(["F", "F", "F\nF", "E", "E", "G", "", ""]);
    diceobj.setValues(1, 8);
    diceobj.font = "Armada-Symbol-Regular";
    diceobj.color = "#FF0000";
    diceobj.colorset = "swa_red";
    diceobj.display = "labels";
    diceobj.system = "swarmada";
    // this.register(diceobj);

    // Attack Blue
    diceobj = new DicePreset("swab", "d8");
    diceobj.name = "Star Wars Armada: Blue Attack Dice";
    diceobj.setLabels(["F", "F", "F", "F", "E", "E", "G", "G"]);
    diceobj.setValues(1, 8);
    diceobj.font = "Armada-Symbol-Regular";
    diceobj.color = "#0000FF";
    diceobj.colorset = "swa_blue";
    diceobj.display = "labels";
    diceobj.system = "swarmada";
    // this.register(diceobj);

    // Attack Black
    diceobj = new DicePreset("swak", "d8");
    diceobj.name = "Star Wars Armada: Black Attack Dice";
    diceobj.setLabels(["F", "F", "F", "F", "F\nE", "F\nE", "", ""]);
    diceobj.setValues(1, 8);
    diceobj.font = "Armada-Symbol-Regular";
    diceobj.color = "#111111";
    diceobj.colorset = "swa_black";
    diceobj.display = "labels";
    diceobj.system = "swarmada";
    // this.register(diceobj);

    // star wars x-wing
    // Attack - Red
    diceobj = new DicePreset("xwatk", "d8");
    diceobj.name = "Star Wars X-Wing: Red Attack Dice";
    diceobj.setLabels(["c", "d", "d", "d", "f", "f", "", ""]);
    diceobj.setValues(1, 8);
    diceobj.font = "XWing-Symbol-Regular";
    diceobj.color = "#FF0000";
    diceobj.colorset = "xwing_red";
    diceobj.display = "labels";
    diceobj.system = "xwing";
    // this.register(diceobj);

    // Defense - Green
    diceobj = new DicePreset("xwdef", "d8");
    diceobj.name = "Star Wars X-Wing: Green Defense Dice";
    diceobj.setLabels(["e", "e", "e", "f", "f", "", "", ""]);
    diceobj.setValues(1, 8);
    diceobj.font = "XWing-Symbol-Regular";
    diceobj.color = "#00FF00";
    diceobj.colorset = "xwing_green";
    diceobj.display = "labels";
    diceobj.system = "xwing";
    // this.register(diceobj);

    // star wars legion
    // Attack Red
    diceobj = new DicePreset("swlar", "d8");
    diceobj.name = "Star Wars Legion: Red Attack Dice";
    diceobj.setLabels(["h", "h", "h", "h", "h", "c", "o", ""]);
    diceobj.setValues(1, 8);
    diceobj.font = "Legion-Symbol-Regular";
    diceobj.color = "#FF0000";
    diceobj.colorset = "swl_atkred";
    diceobj.display = "labels";
    diceobj.system = "legion";
    // this.register(diceobj);

    // Attack Black
    diceobj = new DicePreset("swlab", "d8");
    diceobj.name = "Star Wars Legion: Black Attack Dice";
    diceobj.setLabels(["h", "h", "h", "", "", "c", "o", ""]);
    diceobj.setValues(1, 8);
    diceobj.font = "Legion-Symbol-Regular";
    diceobj.color = "#111111";
    diceobj.colorset = "swl_atkblack";
    diceobj.display = "labels";
    diceobj.system = "legion";
    // this.register(diceobj);

    // Attack White
    diceobj = new DicePreset("swlaw", "d8");
    diceobj.name = "Star Wars Legion: White Attack Dice";
    diceobj.setLabels(["h", "", "", "", "", "c", "o", ""]);
    diceobj.setValues(1, 8);
    diceobj.font = "Legion-Symbol-Regular";
    diceobj.color = "#FFFFFF";
    diceobj.colorset = "swl_atkwhite";
    diceobj.display = "labels";
    diceobj.system = "legion";
    // this.register(diceobj);

    // Defense Red
    diceobj = new DicePreset("swldr", "d6");
    diceobj.name = "Star Wars Legion: Red Defense Dice";
    diceobj.setLabels(["s", "s", "s", "d", "", ""]);
    diceobj.setValues(1, 6);
    diceobj.scale = 0.9;
    diceobj.font = "Legion-Symbol-Regular";
    diceobj.color = "#FF0000";
    diceobj.colorset = "swl_defred";
    diceobj.display = "labels";
    diceobj.system = "legion";
    // this.register(diceobj);

    // Defense White
    diceobj = new DicePreset("swldw", "d6");
    diceobj.name = "Star Wars Legion: White Defense Dice";
    diceobj.setLabels(["s", "", "", "d", "", ""]);
    diceobj.setValues(1, 6);
    diceobj.scale = 0.9;
    diceobj.font = "Legion-Symbol-Regular";
    diceobj.color = "#FFFFFF";
    diceobj.colorset = "swl_defwhite";
    diceobj.display = "labels";
    diceobj.system = "legion";
    // this.register(diceobj);
  }

  setBumpMapping(bumpMapping) {
    this.bumpMapping = bumpMapping;
    this.materials_cache = {};
  }

  setCubeMap(basepath, sources) {
    if (basepath === false) {
      this.cubeMap = null;
      return;
    }

    let loader = new THREE.CubeTextureLoader();
    loader.setPath(basepath);
    this.cubeMap = loader.load(sources);
  }

  register(diceobj) {
    this.dice[diceobj.type] = diceobj;
    this.systems[diceobj.system].dice.push(diceobj.type);
  }

  // returns a dicemesh (THREE.Mesh) object
  create(type) {
    let diceobj = this.dice[type];
    if (!diceobj) return null;

    let geom = this.geometries[type];
    if (!geom) {
      geom = this.createGeometry(diceobj.shape, diceobj.scale * this.baseScale);
      this.geometries[type] = geom;
    }
    if (!geom) return null;

    const diceFavorites = window.DiceFavorites;
    if (
      diceobj.colorset &&
      diceFavorites &&
      diceFavorites.settings.allowDiceOverride.value == "1"
    ) {
      this.setMaterialInfo(diceobj.colorset);
    } else {
      this.setMaterialInfo();
    }

    const getGeometryFaces = (geometry) => {
      if (!geometry) return [];
      if (geometry.userData && Array.isArray(geometry.userData.faces)) {
        return geometry.userData.faces;
      }
      return geometry.faces || [];
    };

    let dicemesh = new THREE.Mesh(
      geom,
      this.createMaterials(diceobj, this.baseScale / 2, 1.0)
    );
    dicemesh.result = [];
    dicemesh.shape = diceobj.shape;
    dicemesh.rerolls = 0;
    dicemesh.resultReason = "natural";

    dicemesh.getFaceValue = function () {
      let reason = this.resultReason;
      let vector = new THREE.Vector3(0, 0, this.shape == "d4" ? -1 : 1);

      let closest_face,
        closest_angle = Math.PI * 2;
      const faces = getGeometryFaces(this.geometry);
      if (!faces.length) {
        return { value: 0, label: "", reason: reason };
      }
      for (let i = 0, l = faces.length; i < l; ++i) {
        let face = faces[i];
        if (face.materialIndex == 0) continue;
        let angle = face.normal
          .clone()
          .applyQuaternion(this.body.quaternion)
          .angleTo(vector);
        if (angle < closest_angle) {
          closest_angle = angle;
          closest_face = face;
        }
      }
      let matindex = closest_face.materialIndex - 1;

      const diceobj = window.DiceFactory.dice[this.notation.type];

      if (this.shape == "d4") {
        let labelindex2 = matindex - 1 == 0 ? 5 : matindex;

        return {
          value: matindex,
          label: diceobj.labels[matindex - 1][labelindex2][0],
          reason: reason,
        };
      }
      if (["d10", "d2"].includes(this.shape)) matindex += 1;

      let value = diceobj.values[(matindex - 1) % diceobj.values.length];
      let label =
        diceobj.labels[((matindex - 1) % (diceobj.labels.length - 2)) + 2];

      return { value: value, label: label, reason: reason };
    };

    dicemesh.storeRolledValue = function (reason) {
      this.resultReason = reason || this.resultReason;
      this.result.push(this.getFaceValue());
    };

    dicemesh.getLastValue = function () {
      if (!this.result || this.result.length < 1)
        return { value: undefined, label: "", reason: "" };

      return this.result[this.result.length - 1];
    };

    dicemesh.ignoreLastValue = function (ignore) {
      let lastvalue = this.getLastValue();
      if (lastvalue.value === undefined) return;

      lastvalue.ignore = ignore;
      this.setLastValue(lastvalue);
    };

    dicemesh.setLastValue = function (result) {
      if (!this.result || this.result.length < 1) return;
      if (!result || result.length < 1) return;

      return (this.result[this.result.length - 1] = result);
    };

    if (diceobj.color) {
      dicemesh.material[0].color = new THREE.Color(diceobj.color);
      dicemesh.material[0].emissive = new THREE.Color(diceobj.color);
      dicemesh.material[0].emissiveIntensity = 1;
      dicemesh.material[0].needsUpdate = true;
    }

    /*switch (type) {
			case 'd1':
				return this.fixmaterials(dicemesh, 1);
			case 'd2':
				return this.fixmaterials(dicemesh, 2);
			case 'd3': case 'df': case 'dset':
				return this.fixmaterials(dicemesh, 3);
			default:
				return dicemesh;
		}*/

    switch (diceobj.values.length) {
      case 1:
        return this.fixmaterials(dicemesh, 1);
      case 2:
        return this.fixmaterials(dicemesh, 2);
      case 3:
        return this.fixmaterials(dicemesh, 3);
      default:
        return dicemesh;
    }
  }

  get(type) {
    return this.dice[type];
  }

  getGeometry(type) {
    return this.geometries[type];
  }

  createMaterials(
    diceobj,
    size,
    margin,
    allowcache = true,
    d4specialindex = 0
  ) {
    let materials = [];
    let labels = diceobj.labels;
    if (diceobj.shape == "d4") {
      labels = diceobj.labels[d4specialindex];
      size = this.baseScale / 2;
      margin = this.baseScale * 2;
    }

    for (var i = 0; i < labels.length; ++i) {
      var mat;
      if (this.dice_material != "none") {
        mat = new THREE.MeshStandardMaterial(
          this.material_types[this.dice_material]
        );
        if (this.cubeMap) {
          mat.envMap = this.cubeMap;
        } else {
          mat.envMapIntensity = 0;
        }
      } else {
        mat = new THREE.MeshPhongMaterial(this.material_options);
      }

      let canvasTextures;
      if (i == 0) {
        //edge
        //if the texture is fully opaque, we do not use it for edge
        let texture = { name: "none" };
        if (this.dice_texture_rand.composite != "source-over")
          texture = this.dice_texture_rand;

        canvasTextures = this.createTextMaterial(
          diceobj,
          labels,
          i,
          size,
          margin,
          texture,
          this.label_color_rand,
          this.label_outline_rand,
          this.edge_color_rand,
          allowcache
        );
        mat.map = canvasTextures.composite;
      } else {
        canvasTextures = this.createTextMaterial(
          diceobj,
          labels,
          i,
          size,
          margin,
          this.dice_texture_rand,
          this.label_color_rand,
          this.label_outline_rand,
          this.dice_color_rand,
          allowcache
        );
        mat.map = canvasTextures.composite;

        if (this.bumpMapping) {
          let scale = 0.75;
          if (size > 35) scale = 1;
          if (size > 40) scale = 2.5;
          if (size > 45) scale = 4;
          mat.bumpScale = scale;

          if (canvasTextures.bump) {
            mat.bumpMap = canvasTextures.bump;
          }
          if (diceobj.shape != "d4" && diceobj.normals[i]) {
            mat.bumpMap = new THREE.Texture(diceobj.normals[i]);
            mat.bumpScale = 4;
            mat.bumpMap.needsUpdate = true;
          }
        }
      }
      mat.opacity = 1;
      mat.transparent = true;
      mat.depthTest = false;
      mat.needsUpdate = true;
      materials.push(mat);
    }
    //Edge mat

    return materials;
  }

  createTextMaterial(
    diceobj,
    labels,
    index,
    size,
    margin,
    texture,
    forecolor,
    outlinecolor,
    backcolor,
    allowcache
  ) {
    if (labels[index] === undefined) return null;

    texture = texture || this.dice_texture_rand;
    forecolor = forecolor || this.label_color_rand;
    outlinecolor = outlinecolor || this.label_outline_rand;
    backcolor = backcolor || this.dice_color_rand;
    allowcache = allowcache == undefined ? true : allowcache;

    let text = labels[index];
    let isTexture = false;
    let textCache = text;
    if (text instanceof HTMLImageElement) {
      textCache = text.src;
    } else if (text instanceof Array) {
      text.forEach((el) => {
        textCache += el.src;
      });
    }

    // an attempt at materials caching
    let cachestring =
      diceobj.type +
      textCache +
      index +
      texture.name +
      forecolor +
      outlinecolor +
      backcolor;
    if (diceobj.shape == "d4") {
      cachestring =
        diceobj.type +
        textCache +
        texture.name +
        forecolor +
        outlinecolor +
        backcolor;
    }
    if (allowcache && this.materials_cache[cachestring] != null) {
      this.cache_hits++;
      return this.materials_cache[cachestring];
    }

    let canvas = document.createElement("canvas");
    let context = canvas.getContext("2d", { alpha: true });
    context.globalAlpha = 0;

    context.clearRect(0, 0, canvas.width, canvas.height);

    let canvasBump = document.createElement("canvas");
    let contextBump = canvasBump.getContext("2d", { alpha: true });
    contextBump.globalAlpha = 0;

    contextBump.clearRect(0, 0, canvasBump.width, canvasBump.height);

    let ts;

    if (diceobj.shape == "d4") {
      ts = this.calc_texture_size(size + margin) * 2;
    } else {
      ts = this.calc_texture_size(size + size * 2 * margin) * 2;
    }

    canvas.width = canvas.height = ts;
    canvasBump.width = canvasBump.height = ts;

    // create color
    context.fillStyle = backcolor;
    context.fillRect(0, 0, canvas.width, canvas.height);

    contextBump.fillStyle = "#FFFFFF";
    contextBump.fillRect(0, 0, canvasBump.width, canvasBump.height);

    //create underlying texture
    if (texture.name != "" && texture.name != "none") {
      context.globalCompositeOperation = texture.composite || "source-over";
      context.drawImage(texture.texture, 0, 0, canvas.width, canvas.height);
      context.globalCompositeOperation = "source-over";

      if (texture.bump != "") {
        contextBump.globalCompositeOperation = "source-over";
        contextBump.drawImage(texture.bump, 0, 0, canvas.width, canvas.height);
      }
    } else {
      context.globalCompositeOperation = "source-over";
    }

    // create text
    context.globalCompositeOperation = "source-over";
    context.textAlign = "center";
    context.textBaseline = "middle";

    contextBump.textAlign = "center";
    contextBump.textBaseline = "middle";

    if (diceobj.shape != "d4") {
      // fix for some faces being weirdly rotated
      let rotateface = this.rotate[diceobj.shape];
      if (rotateface) {
        let degrees =
          (Object.prototype.hasOwnProperty.call(rotateface, "all")
            ? rotateface.all
            : false) ||
          (index > 0 && index % 2 != 0)
            ? rotateface.odd
            : rotateface.even;

        if (degrees && degrees != 0) {
          const halfWidth = canvas.width / 2;
          const halfHeight = canvas.height / 2;

          context.translate(halfWidth, halfHeight);
          context.rotate(degrees * (Math.PI / 180));
          context.translate(-halfWidth, -halfHeight);

          contextBump.translate(halfWidth, halfHeight);
          contextBump.rotate(degrees * (Math.PI / 180));
          contextBump.translate(-halfWidth, -halfHeight);
        }
      }

      //custom texture face
      if (text instanceof HTMLImageElement) {
        isTexture = true;
        context.drawImage(
          text,
          0,
          0,
          text.width,
          text.height,
          0,
          0,
          canvas.width,
          canvas.height
        );

        // text-only face
      } else {
        let fontsize = ts / (1 + 2 * margin);
        let textstarty = canvas.height / 2;
        let textstartx = canvas.width / 2;

        if (diceobj.shape == "d10") {
          fontsize = fontsize * 0.75;
          textstarty = textstarty * 1.15;
        } else if (diceobj.shape == "d20") {
          textstartx = textstartx * 0.98;
        }

        context.font = fontsize + "pt " + diceobj.font;
        contextBump.font = fontsize + "pt " + diceobj.font;

        let lineHeight = context.measureText("M").width * 1.4;
        let textlines = text.split("\n");

        if (textlines.length > 1) {
          fontsize = fontsize / textlines.length;
          context.font = fontsize + "pt " + diceobj.font;
          contextBump.font = fontsize + "pt " + diceobj.font;
          lineHeight = context.measureText("M").width * 1.2;
          textstarty -= (lineHeight * textlines.length) / 2;
        }

        for (let i = 0, l = textlines.length; i < l; i++) {
          let textline = textlines[i].trim();

          // attempt to outline the text with a meaningful color
          if (outlinecolor != "none" && outlinecolor != backcolor) {
            context.strokeStyle = outlinecolor;
            context.lineWidth = 5;
            context.strokeText(textlines[i], textstartx, textstarty);

            contextBump.strokeStyle = "#000000";
            contextBump.lineWidth = 5;
            contextBump.strokeText(textlines[i], textstartx, textstarty);

            if (textline == "6" || textline == "9") {
              context.strokeText("  .", textstartx, textstarty);
              contextBump.strokeText("  .", textstartx, textstarty);
            }
          }

          context.fillStyle = forecolor;
          context.fillText(textlines[i], textstartx, textstarty);

          contextBump.fillStyle = "#000000";
          contextBump.fillText(textlines[i], textstartx, textstarty);

          if (textline == "6" || textline == "9") {
            context.fillText("  .", textstartx, textstarty);
            contextBump.fillText("  .", textstartx, textstarty);
          }
          textstarty += lineHeight * 1.5;
        }
      }
    } else {
      const centerX = canvas.width / 2;
      const centerY = canvas.height / 2;

      context.font = (ts / 128) * 24 + "pt " + diceobj.font;
      contextBump.font = (ts / 128) * 24 + "pt " + diceobj.font;

      //draw the numbers
      for (let i = 0; i < text.length; i++) {
        //custom texture face
        if (text[i] instanceof HTMLImageElement) {
          let scaleTexture = text[i].width / canvas.width;
          context.drawImage(
            text[i],
            0,
            0,
            text[i].width,
            text[i].height,
            100 / scaleTexture,
            25 / scaleTexture,
            60 / scaleTexture,
            60 / scaleTexture
          );
        } else {
          // attempt to outline the text with a meaningful color
          if (outlinecolor != "none" && outlinecolor != backcolor) {
            context.strokeStyle = outlinecolor;
            context.lineWidth = 5;
            context.strokeText(text[i], centerX, centerY - ts * 0.3);

            contextBump.strokeStyle = "#000000";
            contextBump.lineWidth = 5;
            contextBump.strokeText(text[i], centerX, centerY - ts * 0.3);
          }

          //draw label in top middle section
          context.fillStyle = forecolor;
          context.fillText(text[i], centerX, centerY - ts * 0.3);

          contextBump.fillStyle = "#000000";
          contextBump.fillText(text[i], centerX, centerY - ts * 0.3);
        }

        //rotate 1/3 for next label
        context.translate(centerX, centerY);
        context.rotate((Math.PI * 2) / 3);
        context.translate(-centerX, -centerY);

        contextBump.translate(centerX, centerY);
        contextBump.rotate((Math.PI * 2) / 3);
        contextBump.translate(-centerX, -centerY);
      }

      //debug side numbering
      //context.fillStyle = forecolor;
      //context.fillText(index, hw, hh);
    }

    var compositetexture = new THREE.CanvasTexture(canvas);
    var bumpMap;
    if (!isTexture) {
      bumpMap = new THREE.CanvasTexture(canvasBump);
    } else {
      bumpMap = null;
    }

    if (allowcache) {
      // cache new texture
      this.cache_misses++;
      this.materials_cache[cachestring] = {
        composite: compositetexture,
        bump: bumpMap,
      };
    }

    return { composite: compositetexture, bump: bumpMap };
  }

  applyColorSet(colordata, texture) {
    if (typeof colordata === "string") {
      colordata = window.DiceColors.getColorSet(colordata);
    }

    texture = texture || colordata.texture;

    this.colordata = colordata;
    this.label_color = colordata.foreground;
    this.dice_color = colordata.background;
    this.label_outline = colordata.outline;
    this.dice_texture = texture;
    this.dice_material =
      colordata?.texture?.material || colordata?.texture[0]?.material || "";
    this.edge_color = Object.prototype.hasOwnProperty.call(colordata, "edge")
      ? colordata.edge
      : colordata.background;
  }

  applyTexture(texture) {
    this.dice_texture = texture;
  }

  applyMaterial(material) {
    this.dice_material = material;
  }

  setMaterialInfo(colorset = "") {
    let prevcolordata = this.colordata;
    let prevtexture = this.dice_texture;
    let prevmaterial = this.dice_material;

    if (colorset) {
      let colordata = window.DiceColors.getColorSet(colorset);

      if (this.colordata.id != colordata.id) {
        this.applyColorSet(colordata);
      }
    }

    //reset random choices
    this.dice_color_rand = "";
    this.label_color_rand = "";
    this.label_outline_rand = "";
    this.dice_texture_rand = "";
    this.dice_material_rand = "";
    this.edge_color_rand = "";

    // set base color first
    if (Array.isArray(this.dice_color)) {
      const colorIndex = Math.floor(Math.random() * this.dice_color.length);

      // if color list and label list are same length, treat them as a parallel list
      if (
        Array.isArray(this.label_color) &&
        this.label_color.length == this.dice_color.length
      ) {
        this.label_color_rand = this.label_color[colorIndex];

        // if label list and outline list are same length, treat them as a parallel list
        if (
          Array.isArray(this.label_outline) &&
          this.label_outline.length == this.label_color.length
        ) {
          this.label_outline_rand = this.label_outline[colorIndex];
        }
      }
      // if texture list is same length do the same
      if (
        Array.isArray(this.dice_texture) &&
        this.dice_texture.length == this.dice_color.length
      ) {
        this.dice_texture_rand = this.dice_texture[colorIndex];
        this.dice_material_rand = this.dice_texture_rand.material;
      }

      //if edge list and color list are same length, treat them as a parallel list
      if (
        Array.isArray(this.edge_color) &&
        this.edge_color.length == this.dice_color.length
      ) {
        this.edge_color_rand = this.edge_color[colorIndex];
      }

      this.dice_color_rand = this.dice_color[colorIndex];
    } else {
      this.dice_color_rand = this.dice_color;
    }

    // set edge color if not set
    if (this.edge_color_rand == "") {
      if (Array.isArray(this.edge_color)) {
        const edgeColorIndex = Math.floor(
          Math.random() * this.edge_color.length
        );

        this.edge_color_rand = this.edge_color[edgeColorIndex];
      } else {
        this.edge_color_rand = this.edge_color;
      }
    }

    // if selected label color is still not set, pick one
    if (this.label_color_rand == "" && Array.isArray(this.label_color)) {
      const labelColorIndex =
        this.label_color[Math.floor(Math.random() * this.label_color.length)];

      // if label list and outline list are same length, treat them as a parallel list
      if (
        Array.isArray(this.label_outline) &&
        this.label_outline.length == this.label_color.length
      ) {
        this.label_outline_rand = this.label_outline[labelColorIndex];
      }

      this.label_color_rand = this.label_color[labelColorIndex];
    } else if (this.label_color_rand == "") {
      this.label_color_rand = this.label_color;
    }

    // if selected label outline is still not set, pick one
    if (this.label_outline_rand == "" && Array.isArray(this.label_outline)) {
      const outlineColorIndex =
        this.label_outline[
          Math.floor(Math.random() * this.label_outline.length)
        ];

      this.label_outline_rand = this.label_outline[outlineColorIndex];
    } else if (this.label_outline_rand == "") {
      this.label_outline_rand = this.label_outline;
    }

    // same for textures list
    if (this.dice_texture_rand == "" && Array.isArray(this.dice_texture)) {
      this.dice_texture_rand =
        this.dice_texture[Math.floor(Math.random() * this.dice_texture.length)];
      this.dice_material_rand =
        this.dice_texture_rand.material || this.dice_material;
    } else if (this.dice_texture_rand == "") {
      this.dice_texture_rand = this.dice_texture;
      this.dice_material_rand =
        this.dice_texture_rand.material || this.dice_material;
    }

    //apply material
    if (this.dice_material_rand == "" && Array.isArray(this.dice_material)) {
      this.dice_material_rand =
        this.dice_material[
          Math.floor(Math.random() * this.dice_material.length)
        ];
    } else if (this.dice_material_rand == "") {
      this.dice_material_rand = this.dice_material;
    }

    if (this.colordata.id != prevcolordata.id) {
      this.applyColorSet(prevcolordata);
      this.applyTexture(prevtexture);
      this.applyMaterial(prevmaterial);
    }
  }

  calc_texture_size(approx) {
    return Math.pow(2, Math.floor(Math.log(approx) / Math.log(2)));
  }

  createGeometry(type, radius) {
    switch (type) {
      case "d2":
        return this.create_d2_geometry(radius);
      case "d4":
        return this.create_d4_geometry(radius);
      case "d6":
        return this.create_d6_geometry(radius);
      case "d8":
        return this.create_d8_geometry(radius);
      case "d10":
        return this.create_d10_geometry(radius);
      case "d12":
        return this.create_d12_geometry(radius);
      case "d20":
        return this.create_d20_geometry(radius);
      // case 'd100':
      // 	return this.create_d100_geometry(radius);
      default:
        return null;
    }
  }

  create_d2_geometry(radius) {
    var geom = new THREE.CylinderGeometry(
      1 * radius,
      1 * radius,
      0.1 * radius,
      32
    );
    geom.rotateX(Math.PI / 2);
    const cannonShape = new CANNON.Cylinder(
      1 * radius,
      1 * radius,
      0.1 * radius,
      8
    );
    const bufferGeom = this.buildBufferGeometryFromGeometry(geom);
    bufferGeom.cannon_shape = cannonShape;
    return bufferGeom;
  }

  create_d4_geometry(radius) {
    var vertices = [
      [1, 1, 1],
      [-1, -1, 1],
      [-1, 1, -1],
      [1, -1, -1],
    ];
    var faces = [
      [1, 0, 2, 1],
      [0, 1, 3, 2],
      [0, 3, 2, 3],
      [1, 2, 3, 4],
    ];
    return this.create_geom(
      vertices,
      faces,
      radius,
      -0.1,
      (Math.PI * 7) / 6,
      0.96
    );
  }

  create_d6_geometry(radius) {
    var vertices = [
      [-1, -1, -1],
      [1, -1, -1],
      [1, 1, -1],
      [-1, 1, -1],
      [-1, -1, 1],
      [1, -1, 1],
      [1, 1, 1],
      [-1, 1, 1],
    ];
    var faces = [
      [0, 3, 2, 1, 1],
      [1, 2, 6, 5, 2],
      [0, 1, 5, 4, 3],
      [3, 7, 6, 2, 4],
      [0, 4, 7, 3, 5],
      [4, 5, 6, 7, 6],
    ];
    return this.create_geom(vertices, faces, radius, 0.1, Math.PI / 4, 0.96);
  }

  create_d8_geometry(radius) {
    var vertices = [
      [1, 0, 0],
      [-1, 0, 0],
      [0, 1, 0],
      [0, -1, 0],
      [0, 0, 1],
      [0, 0, -1],
    ];
    var faces = [
      [0, 2, 4, 1],
      [0, 4, 3, 2],
      [0, 3, 5, 3],
      [0, 5, 2, 4],
      [1, 3, 4, 5],
      [1, 4, 2, 6],
      [1, 2, 5, 7],
      [1, 5, 3, 8],
    ];
    return this.create_geom(
      vertices,
      faces,
      radius,
      0,
      -Math.PI / 4 / 2,
      0.965
    );
  }

  create_d10_geometry(radius) {
    var a = (Math.PI * 2) / 10,
      h = 0.105;
    var vertices = [];
    for (var i = 0, b = 0; i < 10; ++i, b += a) {
      vertices.push([Math.cos(b), Math.sin(b), h * (i % 2 ? 1 : -1)]);
    }
    vertices.push([0, 0, -1]);
    vertices.push([0, 0, 1]);

    var faces = [
      [5, 6, 7, 11, 0],
      [4, 3, 2, 10, 1],
      [1, 2, 3, 11, 2],
      [0, 9, 8, 10, 3],
      [7, 8, 9, 11, 4],
      [8, 7, 6, 10, 5],
      [9, 0, 1, 11, 6],
      [2, 1, 0, 10, 7],
      [3, 4, 5, 11, 8],
      [6, 5, 4, 10, 9],
    ];
    return this.create_geom(vertices, faces, radius, 0.3, Math.PI, 0.945);
  }

  create_d12_geometry(radius) {
    var p = (1 + Math.sqrt(5)) / 2,
      q = 1 / p;
    var vertices = [
      [0, q, p],
      [0, q, -p],
      [0, -q, p],
      [0, -q, -p],
      [p, 0, q],
      [p, 0, -q],
      [-p, 0, q],
      [-p, 0, -q],
      [q, p, 0],
      [q, -p, 0],
      [-q, p, 0],
      [-q, -p, 0],
      [1, 1, 1],
      [1, 1, -1],
      [1, -1, 1],
      [1, -1, -1],
      [-1, 1, 1],
      [-1, 1, -1],
      [-1, -1, 1],
      [-1, -1, -1],
    ];
    var faces = [
      [2, 14, 4, 12, 0, 1],
      [15, 9, 11, 19, 3, 2],
      [16, 10, 17, 7, 6, 3],
      [6, 7, 19, 11, 18, 4],
      [6, 18, 2, 0, 16, 5],
      [18, 11, 9, 14, 2, 6],
      [1, 17, 10, 8, 13, 7],
      [1, 13, 5, 15, 3, 8],
      [13, 8, 12, 4, 5, 9],
      [5, 4, 14, 9, 15, 10],
      [0, 12, 8, 10, 16, 11],
      [3, 19, 7, 17, 1, 12],
    ];
    return this.create_geom(
      vertices,
      faces,
      radius,
      0.2,
      -Math.PI / 4 / 2,
      0.968
    );
  }

  create_d20_geometry(radius) {
    var t = (1 + Math.sqrt(5)) / 2;
    var vertices = [
      [-1, t, 0],
      [1, t, 0],
      [-1, -t, 0],
      [1, -t, 0],
      [0, -1, t],
      [0, 1, t],
      [0, -1, -t],
      [0, 1, -t],
      [t, 0, -1],
      [t, 0, 1],
      [-t, 0, -1],
      [-t, 0, 1],
    ];
    var faces = [
      [0, 11, 5, 1],
      [0, 5, 1, 2],
      [0, 1, 7, 3],
      [0, 7, 10, 4],
      [0, 10, 11, 5],
      [1, 5, 9, 6],
      [5, 11, 4, 7],
      [11, 10, 2, 8],
      [10, 7, 6, 9],
      [7, 1, 8, 10],
      [3, 9, 4, 11],
      [3, 4, 2, 12],
      [3, 2, 6, 13],
      [3, 6, 8, 14],
      [3, 8, 9, 15],
      [4, 9, 5, 16],
      [2, 4, 11, 17],
      [6, 2, 10, 18],
      [8, 6, 7, 19],
      [9, 8, 1, 20],
    ];
    return this.create_geom(
      vertices,
      faces,
      radius,
      -0.2,
      -Math.PI / 4 / 2,
      0.955
    );
  }

  // create_d100_geometry(radius) {
  // 	var a = Math.PI * 2 / 100; // kąt obrotu wokół osi
  // 	var vertices = [];
  // 	var layers = 10; // liczba poziomych warstw (im więcej, tym bliższe kuli)
  // 	var heightStep = 2 / layers; // krok zmiany wysokości
  // 	var radiusStep = 1 / layers; // krok zmiany promienia w każdej warstwie

  // 	// Tworzenie wierzchołków
  // 	for (var i = 0; i <= layers; i++) {
  // 		var height = -1 + i * heightStep; // wysokość od -1 do 1 (od dołu do góry)
  // 		var layerRadius = Math.sqrt(1 - height * height); // promień warstwy na danej wysokości

  // 		for (var j = 0; j < 100; j++) {
  // 			var angle = j * a;
  // 			var x = layerRadius * Math.cos(angle);
  // 			var y = layerRadius * Math.sin(angle);
  // 			vertices.push([x, y, height]); // dodanie wierzchołków w warstwie
  // 		}
  // 	}

  // 	// Dodanie biegunów
  // 	vertices.push([0, 0, -1]); // dolny biegun
  // 	vertices.push([0, 0, 1]);  // górny biegun

  // 	var faces = [];

  // 	// Tworzenie ścian dla warstw
  // 	for (var i = 0; i < layers; i++) {
  // 		for (var j = 0; j < 100; j++) {
  // 			var nextJ = (j + 1) % 100;

  // 			// dolna ściana
  // 			faces.push([
  // 				i * 100 + j,
  // 				i * 100 + nextJ,
  // 				(i + 1) * 100 + nextJ,
  // 				(i + 1) * 100 + j
  // 			]);
  // 		}
  // 	}

  // 	// Tworzenie ścian dla biegunów
  // 	for (var j = 0; j < 100; j++) {
  // 		var nextJ = (j + 1) % 100;

  // 		// dolne połączenia z biegunem
  // 		faces.push([j, nextJ, vertices.length - 2]); // połączenie z dolnym biegunem

  // 		// górne połączenia z biegunem
  // 		var upperBase = (layers - 1) * 100;
  // 		faces.push([upperBase + j, upperBase + nextJ, vertices.length - 1]); // połączenie z górnym biegunem
  // 	}

  // 	return this.create_geom(vertices, faces, radius, 0.3, Math.PI, 0.945);
  // }

  fixmaterials(mesh, unique_sides) {
    if (!mesh || !mesh.geometry) {
      console.error(
        "Invalid mesh or geometry structure. Please check your input data."
      );
      return mesh;
    }
    const geometry = mesh.geometry;
    const faces =
      geometry.userData && Array.isArray(geometry.userData.faces)
        ? geometry.userData.faces
        : geometry.faces || [];
    if (!faces.length) {
      console.error(
        "Invalid geometry faces data. Please check your input data."
      );
      return mesh;
    }
    // this makes the mesh reuse textures for other sides
    for (let i = 0, l = faces.length; i < l; ++i) {
      var matindex = faces[i].materialIndex - 2;
      if (matindex < unique_sides) continue;

      let modmatindex = matindex % unique_sides;

      faces[i].materialIndex = modmatindex + 2;
    }
    if (geometry.groups && geometry.groups.length === faces.length) {
      const updatedGroups = geometry.groups.map((group, index) => ({
        start: group.start,
        count: group.count,
        materialIndex: faces[index].materialIndex,
      }));
      geometry.clearGroups();
      updatedGroups.forEach((group) => {
        geometry.addGroup(group.start, group.count, group.materialIndex);
      });
    }
    return mesh;
  }

  create_shape(vertices, faces, radius) {
    const cv = new Array(vertices.length);
    const cf = new Array(faces.length);
    for (let i = 0; i < vertices.length; ++i) {
      const v = vertices[i];
      cv[i] = new CANNON.Vec3(v.x * radius, v.y * radius, v.z * radius);
    }
    for (let i = 0; i < faces.length; ++i) {
      cf[i] = faces[i].slice(0, faces[i].length - 1);
    }
    return new CANNON.ConvexPolyhedron(cv, cf);
  }

  buildBufferGeometryFromGeometry(sourceGeometry) {
    const positionAttr = sourceGeometry.getAttribute("position");
    const uvAttr = sourceGeometry.getAttribute("uv");
    const indexArray = sourceGeometry.index
      ? Array.from(sourceGeometry.index.array)
      : null;
    const indices =
      indexArray || Array.from({ length: positionAttr.count }, (_, i) => i);
    const sourceGroups =
      sourceGeometry.groups && sourceGeometry.groups.length
        ? sourceGeometry.groups
        : [{ start: 0, count: indices.length, materialIndex: 0 }];

    const positions = [];
    const normals = [];
    const uvs = [];
    const faceData = [];
    const groups = [];

    let groupIndex = 0;
    let group = sourceGroups[groupIndex];
    let triangleIndex = 0;

    for (let i = 0; i < indices.length; i += 3) {
      while (group && i >= group.start + group.count) {
        groupIndex += 1;
        group = sourceGroups[groupIndex];
      }
      const materialIndex = group ? group.materialIndex : 0;

      const ia = indices[i];
      const ib = indices[i + 1];
      const ic = indices[i + 2];

      const ax = positionAttr.getX(ia);
      const ay = positionAttr.getY(ia);
      const az = positionAttr.getZ(ia);
      const bx = positionAttr.getX(ib);
      const by = positionAttr.getY(ib);
      const bz = positionAttr.getZ(ib);
      const cx = positionAttr.getX(ic);
      const cy = positionAttr.getY(ic);
      const cz = positionAttr.getZ(ic);

      const abx = bx - ax;
      const aby = by - ay;
      const abz = bz - az;
      const acx = cx - ax;
      const acy = cy - ay;
      const acz = cz - az;

      let nx = aby * acz - abz * acy;
      let ny = abz * acx - abx * acz;
      let nz = abx * acy - aby * acx;
      const nlen = Math.sqrt(nx * nx + ny * ny + nz * nz) || 1;
      nx /= nlen;
      ny /= nlen;
      nz /= nlen;

      positions.push(ax, ay, az, bx, by, bz, cx, cy, cz);
      normals.push(nx, ny, nz, nx, ny, nz, nx, ny, nz);

      if (uvAttr) {
        uvs.push(
          uvAttr.getX(ia),
          uvAttr.getY(ia),
          uvAttr.getX(ib),
          uvAttr.getY(ib),
          uvAttr.getX(ic),
          uvAttr.getY(ic)
        );
      } else {
        uvs.push(0, 0, 0, 0, 0, 0);
      }

      groups.push({ start: triangleIndex * 3, count: 3, materialIndex });
      faceData.push({ materialIndex, normal: new THREE.Vector3(nx, ny, nz) });
      triangleIndex += 1;
    }

    return this.buildBufferGeometry({
      positions,
      normals,
      uvs,
      faceData,
      groups,
    });
  }

  buildBufferGeometry(data) {
    const geom = new THREE.BufferGeometry();
    geom.setAttribute(
      "position",
      new THREE.Float32BufferAttribute(data.positions, 3)
    );
    geom.setAttribute(
      "normal",
      new THREE.Float32BufferAttribute(data.normals, 3)
    );
    geom.setAttribute("uv", new THREE.Float32BufferAttribute(data.uvs, 2));
    geom.clearGroups();
    data.groups.forEach((group) => {
      geom.addGroup(group.start, group.count, group.materialIndex);
    });
    geom.computeBoundingSphere();
    geom.userData.faces = data.faceData;
    return geom;
  }

  buildGeometryData(vertices, faces, radius, tab, af, options = {}) {
    const positions = [];
    const normals = [];
    const uvs = [];
    const faceData = [];
    const groups = [];

    const isD10 = options.isD10 === true;
    const d10W = 0.65;
    const d10H = 0.85;
    const d10V0 = 1 - 1 * d10H;
    const d10V1 = 1 - (0.895 / 1.105) * d10H;
    const d10V2 = 1;

    let triangleIndex = 0;

    for (let i = 0; i < faces.length; ++i) {
      const ii = faces[i];
      const fl = ii.length - 1;
      const aa = (Math.PI * 2) / fl;

      for (let j = 0; j < fl - 2; ++j) {
        const i0 = ii[0];
        const i1 = ii[j + 1];
        const i2 = ii[j + 2];

        const v0 = vertices[i0];
        const v1 = vertices[i1];
        const v2 = vertices[i2];

        const ax = v0.x * radius;
        const ay = v0.y * radius;
        const az = v0.z * radius;
        const bx = v1.x * radius;
        const by = v1.y * radius;
        const bz = v1.z * radius;
        const cx = v2.x * radius;
        const cy = v2.y * radius;
        const cz = v2.z * radius;

        const abx = bx - ax;
        const aby = by - ay;
        const abz = bz - az;
        const acx = cx - ax;
        const acy = cy - ay;
        const acz = cz - az;

        let nx = aby * acz - abz * acy;
        let ny = abz * acx - abx * acz;
        let nz = abx * acy - aby * acx;
        const nlen = Math.sqrt(nx * nx + ny * ny + nz * nz) || 1;
        nx /= nlen;
        ny /= nlen;
        nz /= nlen;

        let uv0x;
        let uv0y;
        let uv1x;
        let uv1y;
        let uv2x;
        let uv2y;

        if (isD10 && faces[i][faces[i].length - 1] != -1 && j < 2) {
          if (j == 0) {
            uv0x = 0.5 - d10W / 2;
            uv0y = d10V1;
            uv1x = 0.5;
            uv1y = d10V0;
            uv2x = 0.5 + d10W / 2;
            uv2y = d10V1;
          } else {
            uv0x = 0.5 - d10W / 2;
            uv0y = d10V1;
            uv1x = 0.5 + d10W / 2;
            uv1y = d10V1;
            uv2x = 0.5;
            uv2y = d10V2;
          }
        } else {
          uv0x = (Math.cos(af) + 1 + tab) / 2 / (1 + tab);
          uv0y = (Math.sin(af) + 1 + tab) / 2 / (1 + tab);
          uv1x = (Math.cos(aa * (j + 1) + af) + 1 + tab) / 2 / (1 + tab);
          uv1y = (Math.sin(aa * (j + 1) + af) + 1 + tab) / 2 / (1 + tab);
          uv2x = (Math.cos(aa * (j + 2) + af) + 1 + tab) / 2 / (1 + tab);
          uv2y = (Math.sin(aa * (j + 2) + af) + 1 + tab) / 2 / (1 + tab);
        }

        positions.push(ax, ay, az, bx, by, bz, cx, cy, cz);
        normals.push(nx, ny, nz, nx, ny, nz, nx, ny, nz);
        uvs.push(uv0x, uv0y, uv1x, uv1y, uv2x, uv2y);

        const materialIndex = ii[fl] + 1;
        groups.push({ start: triangleIndex * 3, count: 3, materialIndex });
        faceData.push({ materialIndex, normal: new THREE.Vector3(nx, ny, nz) });
        triangleIndex += 1;
      }
    }

    return { positions, normals, uvs, faceData, groups };
  }

  make_geom(vertices, faces, radius, tab, af) {
    const data = this.buildGeometryData(vertices, faces, radius, tab, af, {
      isD10: false,
    });
    return this.buildBufferGeometry(data);
  }

  make_d10_geom(vertices, faces, radius, tab, af) {
    const data = this.buildGeometryData(vertices, faces, radius, tab, af, {
      isD10: true,
    });
    return this.buildBufferGeometry(data);
  }

  chamfer_geom(vectors, faces, chamfer) {
    const chamfer_vectors = [];
    const chamfer_faces = [];
    const corner_faces = new Array(vectors.length);

    for (let i = 0; i < vectors.length; ++i) {
      corner_faces[i] = [];
    }
    for (let i = 0; i < faces.length; ++i) {
      const ii = faces[i];
      const fl = ii.length - 1;
      const center_point = new THREE.Vector3();
      const face = new Array(fl);
      for (let j = 0; j < fl; ++j) {
        const vv = vectors[ii[j]].clone();
        center_point.add(vv);
        corner_faces[ii[j]].push((face[j] = chamfer_vectors.push(vv) - 1));
      }
      center_point.divideScalar(fl);
      for (let j = 0; j < fl; ++j) {
        const vv = chamfer_vectors[face[j]];
        vv.subVectors(vv, center_point)
          .multiplyScalar(chamfer)
          .addVectors(vv, center_point);
      }
      face.push(ii[fl]);
      chamfer_faces.push(face);
    }
    for (let i = 0; i < faces.length - 1; ++i) {
      for (let j = i + 1; j < faces.length; ++j) {
        const pairs = [];
        let lastm = -1;
        for (let m = 0; m < faces[i].length - 1; ++m) {
          const n = faces[j].indexOf(faces[i][m]);
          if (n >= 0 && n < faces[j].length - 1) {
            if (lastm >= 0 && m != lastm + 1) pairs.unshift([i, m], [j, n]);
            else pairs.push([i, m], [j, n]);
            lastm = m;
          }
        }
        if (pairs.length != 4) continue;
        chamfer_faces.push([
          chamfer_faces[pairs[0][0]][pairs[0][1]],
          chamfer_faces[pairs[1][0]][pairs[1][1]],
          chamfer_faces[pairs[3][0]][pairs[3][1]],
          chamfer_faces[pairs[2][0]][pairs[2][1]],
          -1,
        ]);
      }
    }
    for (let i = 0; i < corner_faces.length; ++i) {
      const cf = corner_faces[i];
      const face = [cf[0]];
      let count = cf.length - 1;
      while (count) {
        for (let m = faces.length; m < chamfer_faces.length; ++m) {
          let index = chamfer_faces[m].indexOf(face[face.length - 1]);
          if (index >= 0 && index < 4) {
            if (--index == -1) index = 3;
            const next_vertex = chamfer_faces[m][index];
            if (cf.indexOf(next_vertex) >= 0) {
              face.push(next_vertex);
              break;
            }
          }
        }
        --count;
      }
      face.push(-1);
      chamfer_faces.push(face);
    }
    return { vectors: chamfer_vectors, faces: chamfer_faces };
  }

  create_geom(vertices, faces, radius, tab, af, chamfer) {
    const vectors = new Array(vertices.length);
    for (let i = 0; i < vertices.length; ++i) {
      vectors[i] = new THREE.Vector3().fromArray(vertices[i]).normalize();
    }
    const cg = this.chamfer_geom(vectors, faces, chamfer);
    let geom;
    if (faces.length != 10) {
      geom = this.make_geom(cg.vectors, cg.faces, radius, tab, af);
    } else {
      geom = this.make_d10_geom(cg.vectors, cg.faces, radius, tab, af);
    }
    //var geom = make_geom(vectors, faces, radius, tab, af); // Without chamfer
    geom.cannon_shape = this.create_shape(vectors, faces, radius);
    return geom;
  }
}
