<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

final class SubscriptionPlans extends BaseConfig
{
    /**
     * Public offer catalog. Prices use minor currency units.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $plans = [
        [
            'code' => 'starter',
            'price' => ['amountMinor' => 0, 'currency' => 'PLN', 'interval' => 'month'],
            'limits' => ['tables' => 1, 'playersPerTable' => 5, 'storageMb' => 512],
            'features' => ['campaigns', 'vtt', 'characters', 'realtimeChat', 'dice3d'],
            'highlighted' => false,
        ],
        [
            'code' => 'adventurer',
            'price' => ['amountMinor' => 1900, 'currency' => 'PLN', 'interval' => 'month'],
            'limits' => ['tables' => 3, 'playersPerTable' => 10, 'storageMb' => 5120],
            'features' => [
                'campaigns', 'vtt', 'characters', 'realtimeChat', 'dice3d',
                'graphicsLibrary', 'handouts',
            ],
            'highlighted' => false,
        ],
        [
            'code' => 'gameMaster',
            'price' => ['amountMinor' => 3900, 'currency' => 'PLN', 'interval' => 'month'],
            'limits' => ['tables' => 10, 'playersPerTable' => 25, 'storageMb' => 20480],
            'features' => [
                'campaigns', 'vtt', 'characters', 'realtimeChat', 'dice3d',
                'graphicsLibrary', 'handouts', 'scenarios', 'shop', 'music', 'gmTools',
            ],
            'highlighted' => true,
        ],
        [
            'code' => 'guild',
            'price' => ['amountMinor' => 7900, 'currency' => 'PLN', 'interval' => 'month'],
            'limits' => ['tables' => null, 'playersPerTable' => 50, 'storageMb' => 102400],
            'features' => [
                'campaigns', 'vtt', 'characters', 'realtimeChat', 'dice3d',
                'graphicsLibrary', 'handouts', 'scenarios', 'shop', 'music', 'gmTools',
                'prioritySupport',
            ],
            'highlighted' => false,
        ],
    ];
}
