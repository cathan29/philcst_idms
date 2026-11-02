<?php

namespace Tests\Feature;

use App\Enums\CardStatus;
use App\Models\IdCard;
use App\Models\Student;
use App\Models\User;
use App\Services\CardStatusTransitionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CardStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_persists_card_and_append_only_history(): void
    {
        $user = User::factory()->create();
        $card = $this->card();

        $result = app(CardStatusTransitionService::class)
            ->transition($card, CardStatus::ForPrinting, $user);

        $this->assertSame(CardStatus::ForPrinting, $result->status);
        $this->assertDatabaseHas('id_status_histories', [
            'id_card_id' => $card->id,
            'previous_status' => 'pending',
            'new_status' => 'for_printing',
            'user_id' => $user->id,
        ]);
    }

    public function test_invalid_status_skip_rolls_back_without_history(): void
    {
        $card = $this->card();

        try {
            app(CardStatusTransitionService::class)
                ->transition($card, CardStatus::Released, User::factory()->create());
            $this->fail('Expected invalid transition to throw.');
        } catch (DomainException) {
            $this->assertSame('pending', $card->fresh()->status->value);
            $this->assertDatabaseCount('id_status_histories', 0);
        }
    }

    private function card(): IdCard
    {
        $student = Student::create([
            'student_number' => '00001234',
            'first_name' => 'Synthetic',
            'last_name' => 'Student',
        ]);

        return IdCard::create([
            'student_id' => $student->id,
            'card_token' => (string) Str::uuid(),
            'status' => CardStatus::Pending,
        ]);
    }
}
