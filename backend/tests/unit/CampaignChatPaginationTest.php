<?php

use App\Services\Chat\CampaignChatException;
use App\Services\Chat\CampaignChatPagination;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignChatPaginationTest extends CIUnitTestCase
{
    public function testNormalizesCursorAndCapsLimit(): void
    {
        $page = (new CampaignChatPagination())->parse(['after_id' => '41', 'limit' => '500']);

        $this->assertSame(41, $page['afterId']);
        $this->assertNull($page['beforeId']);
        $this->assertSame(CampaignChatPagination::MAX_LIMIT, $page['limit']);
    }

    public function testRejectsAmbiguousPagination(): void
    {
        $this->expectException(CampaignChatException::class);
        $this->expectExceptionCode(0);

        (new CampaignChatPagination())->parse(['afterId' => 4, 'beforeId' => 9]);
    }

    public function testRejectsInvalidCursor(): void
    {
        try {
            (new CampaignChatPagination())->parse(['beforeId' => '0']);
            $this->fail('Invalid cursor should be rejected.');
        } catch (CampaignChatException $exception) {
            $this->assertSame('invalid_query', $exception->errorCode());
            $this->assertSame(422, $exception->status());
            $this->assertArrayHasKey('beforeId', $exception->details());
        }
    }
}
