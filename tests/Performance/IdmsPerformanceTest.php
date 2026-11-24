<?php

namespace Tests\Performance;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Permission;
use App\Models\Program;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdmsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        if (env('IDMS_RUN_PERFORMANCE') !== '1') {
            $this->markTestSkipped('Set IDMS_RUN_PERFORMANCE=1 to run isolated benchmarks.');
        }

        $permissions = collect(['students.view', 'cards.view', 'reports.view', 'cards.print', 'cards.release'])
            ->map(fn (string $slug) => Permission::create(['name' => $slug, 'slug' => $slug]));
        $role = Role::create(['name' => 'Benchmark operator', 'slug' => 'benchmark-operator']);
        $role->permissions()->attach($permissions);
        $this->operator = User::factory()->create();
        $this->operator->roles()->attach($role);
        $this->actingAs($this->operator);
    }

    public function test_representative_workflows_at_1k_5k_and_10k_records(): void
    {
        $results = [];
        $current = 0;

        foreach (array_map('intval', explode(',', env('IDMS_BENCHMARK_SIZES', '1000,5000,10000'))) as $size) {
            $this->growDataset($current + 1, $size);
            $current = $size;

            $needle = sprintf('B%05d', $size);
            $results[(string) $size] = [
                'dashboard' => $this->measure(fn () => $this->get('/')->assertOk()),
                'student_search' => $this->measure(fn () => $this->get('/students?search='.$needle)->assertOk()),
                'production_queue' => $this->measure(fn () => $this->get('/production?search='.$needle)->assertOk()),
                'verification' => $this->measure(fn () => $this->postJson('/scanner/verify', ['payload' => $needle, 'mode' => 'verification'])->assertOk()),
                'report_page' => $this->measure(fn () => $this->get('/reports?report_type=printed_not_released')->assertOk()),
                'csv_export' => $this->measure(function (): void {
                    $response = $this->get('/reports/export?report_type=released_cards');
                    $response->assertOk();
                    $response->streamedContent();
                }),
                'pdf_export' => $this->measure(fn () => $this->get('/reports/pdf?report_type=released_cards')->assertStatus($size <= 1000 ? 200 : 422)),
            ];
        }

        $output = base_path('docs/audit/phase-3-performance.json');
        file_put_contents($output, json_encode([
            'generated_at' => now()->toIso8601String(),
            'environment' => 'isolated sqlite in-memory phpunit database',
            'php_version' => PHP_VERSION,
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->assertFileExists($output);
        $this->assertSame($current, DB::table('students')->count());
    }

    private function growDataset(int $from, int $to): void
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026-2027']);
        $term = AcademicTerm::firstOrCreate(
            ['academic_year_id' => $year->id, 'code' => 'first'],
            ['name' => 'First Semester', 'status' => 'active'],
        );
        $program = Program::firstOrCreate(['code' => 'BSIT'], ['name' => 'Information Technology']);
        $now = now()->toDateTimeString();

        foreach (array_chunk(range($from, $to), 250) as $numbers) {
            $students = [];
            foreach ($numbers as $number) {
                $students[] = [
                    'student_number' => sprintf('B%05d', $number),
                    'first_name' => 'Benchmark',
                    'last_name' => sprintf('Student %05d', $number),
                    'status' => 'active',
                    'record_source' => 'benchmark',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('students')->insert($students);

            $studentRows = DB::table('students')
                ->whereIn('student_number', array_column($students, 'student_number'))
                ->orderBy('id')
                ->get(['id', 'student_number']);

            $enrollments = [];
            $cards = [];
            foreach ($studentRows as $index => $student) {
                $number = $numbers[$index];
                $status = ['pending', 'printed', 'ready_for_release', 'released'][$number % 4];
                $printed = in_array($status, ['printed', 'ready_for_release', 'released'], true) ? $now : null;
                $ready = in_array($status, ['ready_for_release', 'released'], true) ? $now : null;
                $released = $status === 'released' ? $now : null;
                $enrollments[] = [
                    'student_id' => $student->id,
                    'program_id' => $program->id,
                    'academic_year_id' => $year->id,
                    'academic_term_id' => $term->id,
                    'semester' => 'First Semester',
                    'year_level' => ($number % 4 + 1).'th Year',
                    'section' => 'A',
                    'status' => 'enrolled',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $cards[] = [
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'academic_term_id' => $term->id,
                    'card_token' => (string) Str::uuid(),
                    'external_identifier' => $student->student_number,
                    'semester' => 'First Semester',
                    'issuance_type' => $number % 10 === 0 ? 'replacement' : 'initial',
                    'replacement_sequence' => $number % 10 === 0 ? 1 : 0,
                    'status' => $status,
                    'requested_by' => $this->operator->id,
                    'requested_at' => $now,
                    'printed_at' => $printed,
                    'printed_by' => $printed ? $this->operator->id : null,
                    'ready_at' => $ready,
                    'released_at' => $released,
                    'released_by' => $released ? $this->operator->id : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('student_enrollments')->insert($enrollments);
            DB::table('id_cards')->insert($cards);
            $releasedCards = DB::table('id_cards')
                ->whereIn('student_id', $studentRows->pluck('id'))
                ->where('status', 'released')
                ->get(['id', 'released_by', 'released_at', 'created_at', 'updated_at']);
            if ($releasedCards->isNotEmpty()) {
                DB::table('id_releases')->insert($releasedCards->map(fn ($card) => [
                    'id_card_id' => $card->id,
                    'released_by' => $card->released_by,
                    'released_at' => $card->released_at,
                    'acknowledged_at' => $card->released_at,
                    'created_at' => $card->created_at,
                    'updated_at' => $card->updated_at,
                ])->all());
            }
        }
    }

    private function measure(callable $operation): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startMemory = memory_get_usage(true);
        $start = hrtime(true);
        $operation();
        $milliseconds = round((hrtime(true) - $start) / 1_000_000, 2);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return [
            'milliseconds' => $milliseconds,
            'queries' => $queries,
            'memory_delta_mb' => round((memory_get_usage(true) - $startMemory) / 1048576, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];
    }
}
