<?php

use App\Controllers\Api\InternalRealtimeLightController;
use PHPUnit\Framework\TestCase;

final class InternalRealtimeLightControllerTest extends TestCase
{
    public function testSceneSyncOperationKeepsItsCanonicalProtocolName(): void
    {
        $reflection = new ReflectionClass(InternalRealtimeLightController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $operation = $reflection->getMethod('operation');
        $operation->setAccessible(true);

        $this->assertSame('syncScene', $operation->invoke($controller, 'syncScene'));
        $this->assertSame('syncScene', $operation->invoke($controller, ' SYNCSCENE '));
        $this->assertSame('update', $operation->invoke($controller, 'UPDATE'));
    }
}
