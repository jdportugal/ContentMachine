<?php

// Imocena's Facebook ads: records each section's pt-PT voiceover (the reels' voice) and
// writes each variant's Remotion props (section lengths from the audio).
// Run inside the app container:
//   php scripts/ads/vozes.php /tmp/adspub [f|m]               the three generic variants
//   php scripts/ads/vozes.php /tmp/adspub f remax iad century21  the price ad, one per network
// For a network the demo reel is reel-<network>.mp4 (imocena:demo-reels) when it is in the folder.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RealEstate\ListingScriptWriter;

$saida = rtrim($argv[1] ?? '/tmp/adspub', '/');
$genero = ($argv[2] ?? 'f') === 'm' ? 'm' : 'f';   // the voice: f (default) or m
$redes = array_slice($argv, 3);
$musicas = ['preco' => 'real-estate-2.mp3', 'minutos' => 'real-estate-4.mp3', 'simples' => 'real-estate-3.mp3', 'bf-pack' => 'bf-musica-4.mp3', 'bf-tempo' => 'bf-musica-1.mp3'];   // the user's promo tracks (RealEstateMusic/bombinsound-fashion-promo…)
@mkdir($saida, 0775, true);

$cta = ['Experimente: o primeiro vídeo é grátis, em imocena ponto com.', 'Experimente: o primeiro vídeo é grátis, em imocena.com'];
$guioes = [
    'preco' => [
        ['É consultor imobiliário? Um vídeo profissional do seu imóvel, por dez euros.', 'É consultor imobiliário? Um vídeo profissional do seu imóvel, por 10 €.'],
        ['Um videógrafo cobra centenas de euros, e ainda espera dias pelo vídeo.', null],
        ['Na Imocena, basta colar o link do anúncio. Nós fazemos o vídeo.', null],
        ['Com narração profissional, as fotos em movimento e o seu contacto no fim.', null],
        $cta,
    ],
    'minutos' => [
        ['É consultor imobiliário? O vídeo do seu imóvel, pronto a publicar em minutos.', null],
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
// Black Friday 2026 (until Sunday 30/11): `php scripts/ads/vozes.php /tmp/adsbf f black-friday` makes only these two
$ctaBf = ['Só até domingo. Experimente: o primeiro vídeo é grátis, em imocena ponto com.', 'Só até domingo. O 1.º vídeo é grátis, em imocena.com'];
$blackFriday = [
    'bf-pack' => [
        ['Agentes imobiliários: é Black Friday na Imocena. Mais vinte e cinco por cento de vídeos, ao mesmo preço.', 'Agentes imobiliários: é Black Friday na Imocena. Mais 25% de vídeos, ao mesmo preço.'],
        ['Apenas tem de colar o link e obtém um vídeo.', null],
        ['Sem videógrafo a cobrar centenas de euros, nem dias à espera do vídeo.', null],
        ['O pack de dez passa a treze vídeos. O de vinte passa a vinte e cinco.', 'O pack de 10 passa a 13 vídeos. O de 20 passa a 25.'],
        $ctaBf,
    ],
    'bf-tempo' => [
        ['É consultor imobiliário? Este fim de semana, cada pack de vídeos dá mais vinte e cinco por cento.', 'É consultor imobiliário? Este fim de semana, cada pack de vídeos dá mais 25%.'],
        ['Apenas tem de colar o link e obtém um vídeo.', null],
        ['Nada de esperar dias por um videógrafo, nem perder a tarde a editar.', null],
        ['Na Black Friday, o pack de dez dá treze vídeos, e o de vinte dá vinte e cinco.', 'Na Black Friday, o pack de 10 dá 13 vídeos, e o de 20 dá 25.'],
        $ctaBf,
    ],
];

/**
 * The networks the price ad (the one that brought the requests) is made for: the hook
 * names the network, the demo types a link from its site, the stack says "com a imagem da …".
 * `falado` is how the voice must say it (RE/MAX read with the slash is "re barra max").
 */
$redesConhecidas = [
    'remax' => ['nome' => 'RE/MAX', 'falado' => 'RE/MAX', 'cor' => '#DE212B', 'link' => 'remax.pt/pt/imoveis/moradia-t3-lajeosa'],
    'iad' => ['nome' => 'iad', 'falado' => 'iad', 'cor' => '#0A8FC0', 'link' => 'iadportugal.pt/anuncio/moradia-t3-lajeosa'],
    'century21' => ['nome' => 'Century 21', 'falado' => 'Century 21', 'cor' => '#A08E5E', 'link' => 'century21.pt/imovel/moradia-t3-lajeosa'],
    'exp' => ['nome' => 'eXp', 'falado' => 'eXp', 'cor' => '#2A2A2C', 'link' => 'expportugal.com/imoveis/moradia-t3-lajeosa'],
    'kw' => ['nome' => 'KW', 'falado' => 'KW', 'cor' => '#B40101', 'link' => 'kwportugal.pt/pt/Imovel/moradia-t3-lajeosa'],
    'era' => ['nome' => 'ERA', 'falado' => 'ERA', 'cor' => '#C8102E', 'link' => 'era.pt/imovel/moradia-t3-lajeosa'],
    'zome' => ['nome' => 'Zome', 'falado' => 'Zome', 'cor' => '#F4084F', 'link' => 'zome.pt/imovel/moradia-t3-lajeosa'],
];
$falar = fn (string $t) => ListingScriptWriter::kwFalado(ListingScriptWriter::expFalado(ListingScriptWriter::iadFalado(ListingScriptWriter::semBarra($t))));

$voz = config('contentmachine.real_estate.voices.'.$genero);
$tts = app(App\Services\Clips\Api\ElevenLabsVoiceoverService::class);
$fotos = ['03-sala.png', '01-fachada.png', '06-cozinha.png', '14-jardim-terraco.png'];

/** One variant: record what is missing (a line said the same way is recorded once), then its props. */
$fazer = function (string $nome, string $variante, array $linhas, ?array $agencia, string $reel) use ($saida, $genero, $voz, $tts, $fotos, $musicas, $falar) {
    $secoes = [];
    foreach ($linhas as $i => [$texto, $legenda]) {
        $falado = $falar($texto);
        $ficheiro = "vo-$genero-".substr(md5($falado), 0, 10).'.mp3';
        if (! is_file("$saida/$ficheiro")) {
            $tts->synthesizeWith($falado, "$saida/$ficheiro", $voz['voice_id'], $voz['model'], $voz['stability'] ?? null);
        }
        $dur = (float) trim((string) shell_exec('ffprobe -v quiet -show_entries format=duration -of csv=p=0 '.escapeshellarg("$saida/$ficheiro")));
        $secoes[] = ['segundos' => round($dur + ($i === 4 ? 1.4 : 0.15), 2), 'audio' => $ficheiro, 'legenda' => $legenda ?? $texto];
        echo "$nome ".($i + 1).': '.round($dur, 2)."s  {$falado}\n";
    }
    file_put_contents("$saida/props-$nome.json", json_encode(array_filter([
        'variante' => $variante, 'secoes' => $secoes, 'reel' => $reel, 'fotos' => $fotos,
        'musica' => is_file("$saida/{$musicas[$variante]}") ? $musicas[$variante] : null,
        'agencia' => $agencia,
    ], fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
};

if ($redes === ['black-friday']) {
    foreach ($blackFriday as $variante => $linhas) {
        $fazer($variante, $variante, $linhas, null, 'reel.mp4');
    }
} elseif ($redes === []) {
    foreach ($guioes as $variante => $linhas) {
        $fazer($variante, $variante, $linhas, null, 'reel.mp4');
    }
} else {
    foreach ($redes as $chave) {
        $r = $redesConhecidas[$chave] ?? exit("Rede desconhecida: $chave (".implode(', ', array_keys($redesConhecidas)).")\n");
        $pergunta = 'É consultor da '.$r['nome'].'?';
        $linhas = $guioes['preco'];
        $linhas[0] = ["É consultor da {$r['falado']}? Um vídeo profissional do seu imóvel, por dez euros.", "$pergunta Um vídeo profissional do seu imóvel, por 10 €."];
        $linhas[3] = ["Com a imagem da {$r['falado']}, narração profissional e o seu contacto no fim.", "Com a imagem da {$r['nome']}, narração profissional e o seu contacto no fim."];
        $reel = is_file("$saida/reel-$chave.mp4") ? "reel-$chave.mp4" : 'reel.mp4';
        $fazer("preco-$chave", 'preco', $linhas, ['nome' => $r['nome'], 'pergunta' => $pergunta, 'link' => $r['link'], 'cor' => $r['cor']], $reel);
    }
}
echo "PRONTO\n";
