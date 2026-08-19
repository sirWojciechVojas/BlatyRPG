<?php

namespace App\Services\Shop;

trait ShopPricingServicePart2
{
    private static function resolveAssignedPricingPolicy(array $normalized, array $raw): array
    {
        $definitions = self::pricingPolicyDefinitions();
        $knownPolicyIds = ['balanced', 'friendly', 'premium', 'unrestricted', 'custom'];
        $hasAssignment = array_key_exists('policyId', $raw);
        $requestedId = strtolower(trim((string) ($raw['policyId'] ?? '')));
        $policyId = null;

        if ($hasAssignment) {
            $policyId = in_array($requestedId, $knownPolicyIds, true)
                ? $requestedId
                : null;
        } elseif (
            self::pricingPolicySnapshot($normalized) !==
            self::pricingPolicySnapshot($definitions['general'])
        ) {
            $policyId = 'custom';
        }

        if ($policyId === 'custom') {
            $normalized['policyId'] = 'custom';

            return $normalized;
        }

        $definition = $definitions[$policyId ?: 'general'];
        foreach (
            [
                'baseMultipliers',
                'priceBands',
                'minimumPrice',
                'roundingStep',
                'roundingMode',
                'guardrails',
                'enabledModifiers',
                'rules',
            ] as $key
        ) {
            $normalized[$key] = $definition[$key];
        }
        $normalized['policyId'] = $policyId;

        return $normalized;
    }
}
