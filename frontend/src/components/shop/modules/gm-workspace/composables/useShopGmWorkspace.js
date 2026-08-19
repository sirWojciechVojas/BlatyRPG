import { installWorkspaceGroup1 } from "./groups/group1";
import { installWorkspaceGroup2 } from "./groups/group2";
import { installWorkspaceGroup3 } from "./groups/group3";
import { installWorkspaceGroup4 } from "./groups/group4";
import { installWorkspaceGroup5 } from "./groups/group5";
import { installWorkspaceGroup6 } from "./groups/group6";
import { installWorkspaceGroup7 } from "./groups/group7";
import { installWorkspaceGroup8 } from "./groups/group8";
import { installWorkspaceGroup9 } from "./groups/group9";
import { installShopProfileWorkspace } from "./useShopProfileWorkspace";
import { installShopProfileOperations } from "./useShopProfileOperations";

export const useShopGmWorkspace = () => {
  const deps = {};
  installWorkspaceGroup1(deps);
  installWorkspaceGroup2(deps);
  installWorkspaceGroup3(deps);
  installWorkspaceGroup4(deps);
  installWorkspaceGroup5(deps);
  installWorkspaceGroup6(deps);
  installWorkspaceGroup7(deps);
  installWorkspaceGroup8(deps);
  installWorkspaceGroup9(deps);
  installShopProfileWorkspace(deps);
  installShopProfileOperations(deps);
  return deps;
};
