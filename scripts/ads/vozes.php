<?php

// Imocena's Facebook ads: records each section's pt-PT voiceover (the reels' voice) and
// writes each variant's Remotion props (section lengths from the audio).
// Run inside the app container:  php scripts/ads/vozes.php /tmp/adspub
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$saida = rtrim($argv[1] ?? '/tmp/adspub', '/');
@mkdir($saida, 0775, true);

$cta = ['Experimente: o primeiro vídeo é grátis, em imocena ponto com.', 'Experimente: o primeiro vídeo é grátis, em imocena.com'];
$guioes = [
    'preco' => [
        ['Consultor imobiliário? Um vídeo profissional do seu imóvel, por dez euros.', 'Consultor imobiliário? Um vídeo profissional do seu imóvel, por 10 €.'],
        ['Um videógrafo cobra centenas de euros, e ainda espera dias pelo vídeo.', null],
        ['Na Imocena, basta colar o link do anúncio. Nós fazemos o vídeo.', null],
        ['Com narração profissional, as fotos em movimento e o seu contacto no fim.', null],
        $cta,
    ],
    'minutos' => [
        ['Do link do anúncio a um vídeo pronto a publicar. Em minutos.', null],
        ['Nada de esperar dias por um videógrafo, nem perder a tarde a editar.', null],
        ['Cole o link do imóvel: a Imocena escreve a narração, escolhe as fotos e monta o vídeo.', null],
        ['Pronto para o Instagram, o Facebook e o TikTok, com o seu contacto no fim.', null],
        $cta,
    ],
    'simples' => [
        ['Um vídeo do seu imóvel. Sem filmar, sem editar, sem complicações.', null],
        ['Não precisa de câmara, nem de programas de edição, nem de gravar a sua voz.', null],
        ['Só precisa do link do anúncio. A Imocena faz o resto.', null],
        ['Narração, música, as melhores fotos e o seu contacto, prontos a publicar.', null],
        $cta,
    ],
];

$voz = config('contentmachine.real_estate.voices.m');
$tts = app(App\Services\Clips\Api\ElevenLabsVoiceoverService::class);
$fotos = ['03-sala.png', '01-fachada.png', '06-cozinha.png', '14-jardim-terraco.png'];

foreach ($guioes as $variante => $linhas) {
    $secoes = [];
    foreach ($linhas as $i => [$falado, $legenda]) {
        $ficheiro = "vo-$variante-".($i + 1).'.mp3';
        if (! is_file("$saida/$ficheiro")) {
            $tts->synthesizeWith($falado, "$saida/$ficheiro", $voz['voice_id'], $voz['model'], $voz['stability'] ?? null);
        }
        $dur = (float) trim((string) shell_exec('ffprobe -v quiet -show_entries format=duration -of csv=p=0 '.escapeshellarg("$saida/$ficheiro")));
        $secoes[] = ['segundos' => round($dur + ($i === 4 ? 1.6 : 0.35), 2), 'audio' => $ficheiro, 'legenda' => $legenda ?? $falado];
        echo "$variante ".($i + 1).": ".round($dur, 2)."s\n";
    }
    file_put_contents("$saida/props-$variante.json", json_encode(['variante' => $variante, 'secoes' => $secoes, 'reel' => 'reel.mp4', 'fotos' => $fotos], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
echo "PRONTO\n";
