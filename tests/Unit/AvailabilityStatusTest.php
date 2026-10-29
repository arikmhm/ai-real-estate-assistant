<?php

namespace Tests\Unit;

use App\Enums\AvailabilityStatus;
use PHPUnit\Framework\TestCase;

class AvailabilityStatusTest extends TestCase
{
    public function test_labels_are_indonesian(): void
    {
        $this->assertSame(
            ['available' => 'Tersedia', 'reserved' => 'Dipesan', 'sold' => 'Terjual'],
            array_column(array_map(fn (AvailabilityStatus $status) => [$status->value, $status->label()], AvailabilityStatus::cases()), 1, 0),
        );
    }
}
