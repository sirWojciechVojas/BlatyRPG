import { describe, expect, it, vi } from "vitest";
import { tokenRotationMethods } from "../tokenRotationMethods";

describe("token rotation interactions", () => {
  it("previews linked artwork rotation together with facing", () => {
    const vm = {
      anglePreview: {},
      displayTokenAngles: tokenRotationMethods.displayTokenAngles,
      $emit: vi.fn(),
    };
    const token = {
      id: 7,
      rotation: 20,
      facing: 350,
      rotationFollowsFacing: true,
    };

    tokenRotationMethods.previewTokenAngle.call(vm, token, {
      field: "facing",
      value: 5,
    });

    expect(vm.anglePreview[7]).toEqual({ facing: 5, rotation: 35 });
    expect(vm.$emit).toHaveBeenCalledWith("vision-angle-preview", {
      tokenId: 7,
      changes: { facing: 5, rotation: 35 },
    });
  });

  it("commits both linked angles after a facing preview", () => {
    const vm = {
      anglePreview: { 7: { facing: 5, rotation: 35 } },
      clearTokenAnglePreview: vi.fn(),
      $emit: vi.fn(),
    };
    const token = {
      id: 7,
      rotation: 20,
      facing: 350,
      rotationFollowsFacing: true,
    };

    tokenRotationMethods.commitTokenAngle.call(vm, token, {
      field: "facing",
      value: 5,
    });

    expect(vm.clearTokenAnglePreview).toHaveBeenCalledWith(7);
    expect(vm.$emit).toHaveBeenCalledWith("update", {
      token,
      changes: { facing: 5, rotation: 35 },
    });
  });
});
