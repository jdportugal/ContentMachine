<?php

namespace Tests\Feature\Clips;

use App\Services\RealEstate\PropertyVideoAnalyzer;
use App\Services\RealEstate\PropertyVideoEditor;
use Tests\TestCase;

class PropertyAfterIntroTest extends TestCase
{
    /** The line found in the narration splits it; no line → a pause near the first cut. */
    public function test_the_narration_is_split_on_the_after_intro_line(): void
    {
        $words = collect(['Uma', 'casa', 'em', 'Mafra.', 'Fala', 'com', 'o', 'Nuno.', 'Tem', 'vista.'])
            ->map(fn ($w, $i) => ['word' => $w, 'start' => $i * 0.5, 'end' => $i * 0.5 + 0.4])->all();

        $this->assertSame([2.0, 3.9], PropertyVideoEditor::afterIntroSplit($words, 'Fala com o Nuno', 1.5));
        [$a, $b] = PropertyVideoEditor::afterIntroSplit($words, '', 1.8);
        $this->assertSame($a, $b);
        $this->assertEqualsWithDelta(1.95, $a, 0.01);   // after "Mafra." — a sentence end near the target
    }

    /** The after-intro plays after the first section with its own sound, and the reel grows by it. */
    public function test_the_reel_carries_the_after_intro(): void
    {
        $dir = sys_get_temp_dir().'/cm-ai-'.uniqid();
        mkdir($dir);
        $run = fn (string $args) => exec("ffmpeg -v error -y {$args} 2>&1");
        $run("-f lavfi -i testsrc=s=320x240:r=30:d=12 -pix_fmt yuv420p {$dir}/walk.mp4");
        $run("-f lavfi -i color=c=red:s=160x120:r=25:d=3 -f lavfi -i sine=f=880:d=3 -shortest -pix_fmt yuv420p {$dir}/ai.mp4");
        $run("-f lavfi -i sine=f=440:d=8 {$dir}/voice.mp3");
        $words = collect(range(0, 15))->map(fn ($i) => ['word' => $i === 5 ? 'Nuno.' : 'palavra', 'start' => $i * 0.5, 'end' => $i * 0.5 + 0.4])->all();

        $editor = app(PropertyVideoEditor::class);
        $rooms = [['start' => 0.0, 'end' => 4.0], ['start' => 4.0, 'end' => 8.0], ['start' => 8.0, 'end' => 12.0]];
        $plain = $editor->assemble("{$dir}/walk.mp4", $rooms, null, "{$dir}/plain.mp4", "{$dir}/w1", "{$dir}/voice.mp3");
        $with = $editor->assemble("{$dir}/walk.mp4", $rooms, null, "{$dir}/with.mp4", "{$dir}/w2", "{$dir}/voice.mp3",
            afterIntro: ['path' => "{$dir}/ai.mp4", 'words' => $words, 'line' => '']);

        $this->assertGreaterThan(PropertyVideoAnalyzer::probe($plain)['duration'] + 2.0, PropertyVideoAnalyzer::probe($with)['duration']);
        $this->assertSame(320, PropertyVideoAnalyzer::probe($with)['width']);   // drawn at the reel's size
        exec('rm -rf '.escapeshellarg($dir));
    }
}
