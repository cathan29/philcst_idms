<?php

namespace Tests\Unit;

use App\Enums\CardStatus;
use PHPUnit\Framework\TestCase;

class CardStatusTest extends TestCase
{
    public function test_happy_path_transitions_are_allowed(): void
    {
        $this->assertTrue(CardStatus::Pending->canTransitionTo(CardStatus::ForPrinting));
        $this->assertTrue(CardStatus::ForPrinting->canTransitionTo(CardStatus::Printed));
        $this->assertTrue(CardStatus::Printed->canTransitionTo(CardStatus::ReadyForRelease));
        $this->assertTrue(CardStatus::ReadyForRelease->canTransitionTo(CardStatus::Released));
    }

    public function test_status_skipping_and_duplicate_release_are_rejected(): void
    {
        $this->assertFalse(CardStatus::Pending->canTransitionTo(CardStatus::Released));
        $this->assertFalse(CardStatus::Released->canTransitionTo(CardStatus::Released));
        $this->assertFalse(CardStatus::Revoked->canTransitionTo(CardStatus::ReadyForRelease));
    }
}
