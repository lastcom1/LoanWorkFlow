<?php

namespace Tests\Unit;

use App\Services\DuplicateDetectionService;
use PHPUnit\Framework\TestCase;

class DuplicateNormalizationTest extends TestCase
{
    public function test_normalizes_account_amount_and_reference(): void
    {
        $svc = new DuplicateDetectionService;

        $this->assertSame('0123456789', $svc->normalizeAccount('0123-456-789'));
        $this->assertSame('1000.50', $svc->normalizeAmount('1000.5'));
        $this->assertSame('REV-ABC-1', $svc->normalizeReference(' rev-abc-1 '));
    }
}
