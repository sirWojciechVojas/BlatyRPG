<?php

namespace App\Services\Consumption;

/** Runtime access to the generated consumption catalogue. */
final class ConsumptionCatalog
{
    private $data;

    public function __construct(?array $data = null)
    {
        $this->data = $data ?: require APPPATH . 'Data/consumption_catalog.php';
    }

    public function profile(?string $id): ?array
    {
        $id = trim((string) $id);
        return $id !== '' && isset($this->data['profiles'][$id])
            ? $this->data['profiles'][$id]
            : null;
    }

    public function effect(?string $id): ?array
    {
        $id = trim((string) $id);
        return $id !== '' && isset($this->data['effects'][$id])
            ? $this->data['effects'][$id]
            : null;
    }

    public function profiles(string $query = ''): array
    {
        $query = $this->lower($query);
        $profiles = array_values((array) ($this->data['profiles'] ?? []));
        if ($query === '') {
            return $profiles;
        }

        return array_values(array_filter($profiles, function (array $profile) use ($query): bool {
            $haystack = implode(' ', [
                $profile['id'] ?? '', $profile['name'] ?? '', $profile['kind'] ?? '',
                $profile['category'] ?? '', $profile['region'] ?? '',
            ]);
            return mb_strpos($this->lower($haystack), $query) !== false;
        }));
    }

    public function effects(): array
    {
        return array_values((array) ($this->data['effects'] ?? []));
    }

    public function resolve(array $template, ?array $instance = null): array
    {
        $mode = strtolower(trim((string) ($instance['consumption_mode'] ?? 'inherit')));
        if ($mode === 'disabled') {
            return ['profile' => null, 'source' => 'disabled'];
        }

        if ($mode === 'override') {
            return [
                'profile' => $this->profile($instance['consumption_profile_id'] ?? null),
                'source' => 'instance',
            ];
        }

        return [
            'profile' => $this->profile($template['consumption_profile_id'] ?? null),
            'source' => 'template',
        ];
    }

    public function adminSummary(array $profile): array
    {
        return [
            'id' => (string) $profile['id'],
            'name' => (string) $profile['name'],
            'unidentifiedName' => (string) $profile['unidentifiedName'],
            'kind' => (string) $profile['kind'],
            'category' => (string) $profile['category'],
            'region' => (string) $profile['region'],
            'availability' => (string) $profile['availability'],
            'basePricePennies' => (int) $profile['basePricePennies'],
            'shelfLife' => (string) $profile['shelfLife'],
            'satietyHours' => (float) $profile['satietyHours'],
            'hydrationHours' => (float) $profile['hydrationHours'],
            'effectId' => (string) $profile['effectId'],
            'effect' => (string) $profile['mechanicalEffect'],
            'effectWindow' => (string) $profile['effectWindow'],
            'risk' => (string) $profile['risk'],
            'negativeTest' => (string) $profile['negativeTest'],
            'failureConsequence' => (string) $profile['failureConsequence'],
            'consumeTime' => (string) $profile['consumeTime'],
            'consumeMinutes' => (int) $profile['consumeMinutes'],
            'usableInCombat' => !empty($profile['usableInCombat']),
            'requiresPreparation' => !empty($profile['requiresPreparation']),
            'actionLabel' => $this->actionLabel($profile),
            'description' => (string) $profile['description'],
        ];
    }

    public function playerView(
        ?array $profile,
        string $identification = 'unknown',
        int $portions = 0,
        array $availability = []
    ): ?array {
        if (!$profile) {
            return null;
        }
        $identified = in_array($identification, ['identified', 'examined'], true);
        $hidden = !empty($profile['hiddenRisk']) && !$identified;
        $disabledReason = $this->disabledReason($profile, $portions, $availability);
        $name = $identified ? $profile['name'] : $profile['unidentifiedName'];

        return [
            'name' => (string) $name,
            'identification' => $identification,
            'portions' => max(0, $portions),
            'kind' => $hidden ? 'Nieznany produkt' : (string) $profile['kind'],
            'effect' => $identified || !$hidden ? (string) $profile['mechanicalEffect'] : '',
            'effectWindow' => $identified || !$hidden ? (string) $profile['effectWindow'] : '',
            'satietyHours' => (float) $profile['satietyHours'],
            'hydrationHours' => (float) $profile['hydrationHours'],
            'consumeTime' => (string) $profile['consumeTime'],
            'usableInCombat' => !empty($profile['usableInCombat']),
            'risk' => $identified ? (string) $profile['risk'] : '',
            'basePricePennies' => (int) $profile['basePricePennies'],
            'actionLabel' => $this->actionLabel($profile),
            'requiresPreparation' => !empty($profile['requiresPreparation']),
            'disabled' => $disabledReason !== '',
            'disabledReason' => $disabledReason,
            'requiresConfirmation' => $identified && (
                !empty($profile['hiddenRisk']) || (int) $profile['basePricePennies'] >= 240
            ),
        ];
    }

    private function actionLabel(array $profile): string
    {
        if (!empty($profile['requiresPreparation'])) {
            return 'Wymaga przygotowania';
        }
        $kind = $this->lower((string) ($profile['kind'] ?? ''));
        if (mb_strpos($kind, 'napój') !== false || mb_strpos($kind, 'alkohol') !== false) {
            return 'Wypij';
        }
        if (mb_strpos($kind, 'produkt spożywczy') !== false || mb_strpos($kind, 'potrawa') !== false
            || mb_strpos($kind, 'owoc') !== false || mb_strpos($kind, 'warzywo') !== false) {
            return 'Zjedz';
        }
        return 'Spożyj';
    }

    private function disabledReason(array $profile, int $portions, array $availability): string
    {
        if ($portions < 1) return 'Brak porcji.';
        if (!empty($profile['requiresPreparation'])) return 'Produkt wymaga przygotowania.';
        if (empty($availability['accessible'])) return 'Przedmiot nie jest w dostępnym ekwipunku.';
        if (!empty($availability['busy'])) return 'Trwa akcja wykluczająca spożycie.';
        if (!empty($availability['incapacitated'])) return 'Postać nie może teraz działać.';
        if (!empty($availability['inCombat']) && empty($profile['usableInCombat'])) return 'Nie można spożyć tego produktu podczas walki.';
        return '';
    }

    private function lower(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
