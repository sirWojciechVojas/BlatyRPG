<?php

use App\Services\Audio\SoundEffectScreenLabel;
use CodeIgniter\Test\CIUnitTestCase;

final class SoundEffectScreenLabelTest extends CIUnitTestCase
{
    /** @dataProvider labels */
    public function testGeneratesSpreadsheetStyleLabels(int $index, string $label): void
    {
        $this->assertSame($label, SoundEffectScreenLabel::fromIndex($index));
        $this->assertSame($index, SoundEffectScreenLabel::toIndex($label));
    }

    public function labels(): array
    {
        return [
            'A' => [0, 'A'],
            'Z' => [25, 'Z'],
            'AA' => [26, 'AA'],
            'AZ' => [51, 'AZ'],
            'BA' => [52, 'BA'],
        ];
    }

    public function testRejectsInvalidLabelsAndIndexes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SoundEffectScreenLabel::fromIndex(-1);
    }
}
