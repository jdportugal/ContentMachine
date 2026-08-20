<?php

namespace Tests\Feature;

use App\Livewire\Painel;
use App\Services\Monitoring\MonitoringHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Dashboard cards show today's channel totals with the previous recorded
 * day's underneath. MonitoringStore only ever holds the CURRENT numbers and
 * overwrites them on each collection, so that earlier day has to be written
 * down when it happens — there is nothing to reconstruct it from afterwards.
 */
class MonitoringHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function history(): MonitoringHistory
    {
        return app(MonitoringHistory::class);
    }

    public function test_a_day_is_recorded_and_read_back_as_the_previous_day(): void
    {
        $history = $this->history();

        $history->registar(
            ['subscritores' => 1180, 'publicacoes' => 42, 'visualizacoes' => 90000, 'interacoes' => 1200],
            Carbon::parse('2026-08-19'),
        );

        $anterior = $history->anterior(Carbon::parse('2026-08-20'));

        $this->assertSame('2026-08-19', $anterior['data']);
        $this->assertSame(1180, $anterior['metricas']['subscritores']);
        $this->assertSame(42, $anterior['metricas']['publicacoes']);
        $this->assertSame(90000, $anterior['metricas']['visualizacoes']);
        $this->assertSame(1200, $anterior['metricas']['interacoes']);
    }

    /** Nothing recorded yet is the normal first-run state, not an error. */
    public function test_no_history_yet_reads_as_null(): void
    {
        $this->assertNull($this->history()->anterior(Carbon::parse('2026-08-20')));
    }

    /** Today is not its own previous day, however many times it was recorded. */
    public function test_todays_own_record_is_not_offered_as_the_previous_day(): void
    {
        $history = $this->history();
        $history->registar(['subscritores' => 9], Carbon::parse('2026-08-20'));

        $this->assertNull($history->anterior(Carbon::parse('2026-08-20')));
    }

    /** A day's later collections replace it, so the day ends on its final numbers. */
    public function test_recording_the_same_day_twice_keeps_the_later_figures(): void
    {
        $history = $this->history();

        $history->registar(['subscritores' => 100], Carbon::parse('2026-08-19'));
        $history->registar(['subscritores' => 175], Carbon::parse('2026-08-19'));

        $anterior = $history->anterior(Carbon::parse('2026-08-20'));
        $this->assertSame(175, $anterior['metricas']['subscritores']);
    }

    /**
     * A day the app was down leaves no note. The last figures we actually have
     * beat showing nothing — the date comes back so the view can say which day.
     */
    public function test_a_gap_falls_back_to_the_most_recent_recorded_day(): void
    {
        $history = $this->history();
        $history->registar(['subscritores' => 500], Carbon::parse('2026-08-14'));

        $anterior = $history->anterior(Carbon::parse('2026-08-20'));

        $this->assertSame('2026-08-14', $anterior['data']);
        $this->assertSame(500, $anterior['metricas']['subscritores']);
    }

    public function test_the_dashboard_shows_the_previous_day_under_the_totals(): void
    {
        Carbon::setTestNow('2026-08-20 09:00:00');

        $this->history()->registar(
            ['subscritores' => 1180, 'publicacoes' => 42, 'visualizacoes' => 90000, 'interacoes' => 1200],
            Carbon::parse('2026-08-19'),
        );

        $this->comSessaoIniciada();
        Livewire::test(Painel::class)
            ->assertSee('1.2k')       // 1,180 subscribers a day earlier, formatted like the headline
            ->assertSee('42')         // posts
            ->assertSee('90k')        // views, same formatting
            ->assertSee('yesterday');

        Carbon::setTestNow();
    }

    /** An older figure must never read as last night's — it gets its date instead. */
    public function test_an_older_figure_is_labelled_with_its_date_not_yesterday(): void
    {
        Carbon::setTestNow('2026-08-20 09:00:00');

        $this->history()->registar(['subscritores' => 500], Carbon::parse('2026-08-14'));

        $this->comSessaoIniciada();
        Livewire::test(Painel::class)
            ->assertSee('14 Aug')
            ->assertDontSee('yesterday');

        Carbon::setTestNow();
    }
}
