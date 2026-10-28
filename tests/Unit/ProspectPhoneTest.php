<?php

namespace Tests\Unit;

use App\Support\ProspectPhone;
use PHPUnit\Framework\TestCase;

class ProspectPhoneTest extends TestCase
{
    public function test_indonesian_mobile_formats_share_one_canonical_value(): void
    {
        $this->assertSame('+6281234567890', ProspectPhone::normalize('0812-3456 7890'));
        $this->assertSame('+6281234567890', ProspectPhone::normalize('+62 812 3456 7890'));
    }

    public function test_invalid_or_short_numbers_are_rejected(): void
    {
        $this->assertNull(ProspectPhone::normalize('08123'));
        $this->assertNull(ProspectPhone::normalize('+62812-abc'));
        $this->assertNull(ProspectPhone::normalize('0211234567'));
    }
}
