<?php

namespace Tests\Unit;

use App\Jobs\Clips\NarratePropertyJob;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use App\Services\RealEstate\PropertyVideoEditor;
use PHPUnit\Framework\TestCase;

class PropertyVideoAnalyzerTest extends TestCase
{
    private function anim(float $s, float $e): array
    {
        return ['start' => $s, 'end' => $e, 'motion' => 'forward', 'prompt' => 'A modern living room'];
    }

    public function test_rooms_stay_inside_one_shot_in_order_and_only_the_opener_has_no_transformation(): void
    {
        $rooms = PropertyVideoAnalyzer::sanitize([
            ['room' => 'Sala', 'start' => 5.5, 'end' => 8.0, 'anim' => $this->anim(6, 7)],  // crosses cuts at 6.13 and 7.88
            ['room' => 'Fachada', 'start' => 0.0, 'end' => 3.0, 'anim' => null],           // out of order: the opener
            ['room' => 'Quarto', 'start' => 20, 'end' => 25, 'anim' => $this->anim(21, 23)], // over the max
            ['room' => 'Corredor', 'start' => 30, 'end' => 33, 'anim' => null],             // no transformation: dropped
        ], 40.0, 1, [6.13, 7.88]);

        $this->assertSame(['Fachada', 'Sala'], array_column($rooms, 'room'));
        $this->assertSame([6.18, 7.83], [$rooms[1]['start'], $rooms[1]['end']]);
        $this->assertSame(7.83, $rooms[1]['anim']['end']);
        $this->assertSame('modernise', $rooms[1]['anim']['mode']);
        $this->assertGreaterThanOrEqual($rooms[1]['start'], $rooms[1]['anim']['start']);
    }

    public function test_each_section_is_its_transformation_joined_by_full_transitions_no_footage_between(): void
    {
        $section = fn ($s, $len = 4.0) => ['room' => 'R', 'start' => $s, 'end' => $s + $len, 'anim' => $this->anim($s, $s + $len) + ['clip' => '/x.mp4', 'clip_seconds' => 4.15, 'onset' => 2.5]];
        $rooms = [$section(20), $section(30), $section(200), $section(290)];   // 20 → 30: the next room is right there
        $plan = PropertyVideoEditor::plan($rooms, 60);

        // No real footage between sections: it could show a room just modernised back as it was.
        $this->assertSame(['ai', 'ai', 'ai', 'ai'], array_column($plan['segments'], 'type'));
        $this->assertSame([false, false, false, true], array_column($plan['segments'], 'last'));
        $this->assertLessThanOrEqual(58.5, $plan['seconds']);

        $built = 0.0;
        foreach ($plan['segments'] as $seg) {
            $built += $seg['onset'] / $seg['leadSpeed'] + ($seg['seconds'] - $seg['onset']) / $seg['speed'] + $seg['hold'];
            // No frozen tail: the clip's own end goes straight into the blur.
            $this->assertSame(0.0, $seg['hold']);
            // Played to its end at its own speed: never rushed to fit.
            $this->assertSame(1.0, $seg['speed']);
            // A late start is hurried: the room starts changing within half a second.
            $this->assertLessThanOrEqual(0.51, $seg['onset'] / $seg['leadSpeed']);
        }
        $built -= 0.35 * 3;   // three dissolves, each overlapping two sections
        $this->assertEqualsWithDelta($built, $plan['seconds'], 0.05, "the plan's clock is the reel's");
    }

    public function test_ten_short_sections_and_the_outro_fit_under_a_minute(): void
    {
        // Ten 4 s sections with clips a little longer than their sections, plus the outro.
        $rooms = array_map(fn ($i) => ['room' => "R{$i}", 'start' => $i * 25, 'end' => $i * 25 + 4, 'anim' => $this->anim($i * 25, $i * 25 + 4) + ['clip' => '/x.mp4', 'clip_seconds' => 5.15, 'onset' => 0.0]], range(0, 9));
        $plan = PropertyVideoEditor::plan($rooms, 60, 3.65);

        $this->assertCount(10, $plan['segments']);
        $this->assertLessThanOrEqual(58.0 - 3.65 + 0.05, $plan['seconds'], 'room left for the outro');
        foreach ($plan['segments'] as $seg) {
            $this->assertGreaterThanOrEqual(0.0, $seg['hold']);
            $this->assertLessThanOrEqual(1.3001, $seg['speed'], 'never rushed past 1.3x');
        }
        // And the plan's clock still matches what gets built.
        $built = array_sum(array_map(fn ($s) => $s['seconds'] / $s['speed'] + $s['hold'], $plan['segments'])) - 0.35 * 9;
        $this->assertEqualsWithDelta($built, $plan['seconds'], 0.05);
    }

    public function test_a_section_without_a_transformation_plays_as_its_own_footage(): void
    {
        $plan = PropertyVideoEditor::plan([
            ['room' => 'Sala', 'start' => 30, 'end' => 34, 'anim' => $this->anim(30, 34)],   // no clip
        ], 60);

        $this->assertSame(['footage'], array_column($plan['segments'], 'type'));
        $this->assertSame([[30.0, 34.0, PropertyVideoEditor::PACE]], $plan['segments'][0]['chunks']);
    }

    public function test_final_formats_size_and_the_part_of_the_frame_they_keep(): void
    {
        $this->assertSame([1916, 1080], PropertyVideoEditor::targetSize('source', 1916, 1080));
        $this->assertSame([1080, 1920], PropertyVideoEditor::targetSize('9:16', 1916, 1080));
        $this->assertSame([1080, 1350], PropertyVideoEditor::targetSize('4:5', 1916, 1080));
        $this->assertSame([1080, 1080], PropertyVideoEditor::targetSize('1:1', 1916, 1080));
        $this->assertSame([1920, 1080], PropertyVideoEditor::targetSize('16:9', 1080, 1920));
    }

    public function test_aspect_maps_to_the_nearest_kie_ratio(): void
    {
        $this->assertSame('9:16', PropertyVideoAnalyzer::aspect(1080, 1920));
        $this->assertSame('16:9', PropertyVideoAnalyzer::aspect(3840, 2160));
        $this->assertSame('4:3', PropertyVideoAnalyzer::aspect(1440, 1080));
    }

    public function test_typed_house_details_become_title_price_and_facts(): void
    {
        $f = NarratePropertyJob::facts("Título: Moradia T3 em Braga\nPreço: 350.000,00 €\nÁrea: 180 m²\nQuartos: 3\nperto do centro, com jardim\nVazio:");

        $this->assertSame('Moradia T3 em Braga', $f['title']);
        $this->assertSame(350000, $f['price']);
        $this->assertSame(['Área' => '180 m²', 'Quartos' => '3'], $f['details']);
        $this->assertSame(495000, NarratePropertyJob::facts('Price: 495 000 €')['price']);
    }
}
