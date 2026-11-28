<?php

namespace Tests\Unit;

use App\Models\FerramentaIa;
use App\Services\Ferramentas\Realizador;
use Tests\TestCase;

/** Works clips go in steps (Realizador::animar): the steps fill the clip exactly and end on the approved frame. */
class EtapasObraTest extends TestCase
{
    public function test_steps_fill_each_works_clip_and_end_on_the_approved_frame(): void
    {
        foreach ([['remodelacao', 'interior'], ['remodelacao', 'exterior'], ['remodelacao', 'ruina'], ['construcao', null]] as [$tool, $obra]) {
            $f = new FerramentaIa(['ferramenta' => $tool, 'opcoes' => $obra ? ['obra' => $obra] : []]);
            $etapas = Realizador::prompts($f)['etapas'];
            $this->assertSame($f->segundos() * Realizador::ACELERAR_OBRA, array_sum(array_column($etapas, 'segundos')), "$tool/$obra");   // played 2x
            $this->assertNull(end($etapas)['quadro'], "$tool/$obra");
            $this->assertNotContains(null, array_column(array_slice($etapas, 0, -1), 'quadro'), "$tool/$obra");
            $demolir = array_map(fn ($e) => $e['demolir'] ?? false, $etapas);   // demolition only first (drawn forward)
            $this->assertSame($demolir, array_merge(array_fill(0, (int) array_sum($demolir), true), array_fill(0, count($etapas) - (int) array_sum($demolir), false)), "$tool/$obra");
            foreach ($etapas as $e) {
                $this->assertGreaterThanOrEqual(3, $e['segundos']);   // Kling 3.0's shortest clip
            }
        }
    }

    public function test_steps_are_joined_into_one_faster_clip_with_sound(): void
    {
        $d = sys_get_temp_dir().'/etapas-'.uniqid();
        mkdir($d);
        foreach ([0, 1] as $i) {
            exec("ffmpeg -v error -y -f lavfi -i testsrc=s=320x240:d=1:r=24 -f lavfi -i sine=d=1 -shortest $d/$i.mp4");
        }
        Realizador::juntar(["$d/0.mp4", "$d/1.mp4"], "$d/o.mp4", true, 2);
        $dur = (float) exec("ffprobe -v error -show_entries format=duration -of csv=p=0 $d/o.mp4");
        array_map('unlink', glob("$d/*"));
        rmdir($d);
        $this->assertEqualsWithDelta(1.0, $dur, 0.15);   // 2 s of steps, played twice as fast
    }

    public function test_a_drifted_frame_is_put_back_on_the_photos_camera(): void
    {
        $python = (string) config('services.ferramentas.python', 'python3');
        exec(escapeshellarg($python).' -c "import cv2" 2>/dev/null', $o, $falta);
        if ($falta) {
            $this->markTestSkipped('no OpenCV for '.$python);
        }
        $d = sys_get_temp_dir().'/alinhar-'.uniqid();
        mkdir($d);
        mt_srand(3);   // a "photo": random blocks, plenty to match
        $im = imagecreatetruecolor(1280, 720);
        for ($k = 0; $k < 400; $k++) {
            $x = mt_rand(0, 1240);
            $y = mt_rand(0, 690);
            imagefilledrectangle($im, $x, $y, $x + mt_rand(8, 60), $y + mt_rand(8, 40), mt_rand(0, 0xFFFFFF));
        }
        imagepng($im, "$d/foto.png");
        // the AI's drift: 4 % closer and 50 px lower
        exec("ffmpeg -v error -y -i $d/foto.png -vf 'scale=1332:750,crop=1280:720:26:-20+30' $d/torto.png");
        $diff = function (string $a, string $b) {   // mean grey difference over the middle
            $x = imagecreatefrompng($a);
            $y = imagecreatefrompng($b);
            $t = 0;
            for ($i = 200; $i < 1080; $i += 7) {
                for ($j = 150; $j < 570; $j += 7) {
                    $t += abs((imagecolorat($x, $i, $j) & 0xFF) - (imagecolorat($y, $i, $j) & 0xFF));
                }
            }

            return $t / (126 * 60);
        };
        exec(escapeshellarg($python).' '.base_path('scripts/alinhar.py')." $d/ok.png $d/torto.png $d/foto.png", $saida);
        $this->assertTrue(json_decode($saida[0], true)['ok']);
        $this->assertLessThan($diff("$d/torto.png", "$d/foto.png") / 5, $diff("$d/ok.png", "$d/foto.png"));
        array_map('unlink', glob("$d/*"));
        rmdir($d);
    }
}
