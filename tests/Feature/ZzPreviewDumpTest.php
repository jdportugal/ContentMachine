<?php

namespace Tests\Feature;

use App\Livewire\Landing;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** TEMPORARY: prints the page's screens to the log for a visual preview. */
class ZzPreviewDumpTest extends TestCase
{
    use RefreshDatabase;

    private function out(string $nome, string $html): void
    {
        fwrite(STDERR, "\n@@PREVIEW ".$nome.' '.base64_encode(gzcompress($html))."\n");
    }

    public function test_dump(): void
    {
        Storage::fake((string) config('contentmachine.clips.disk'));
        config()->set('contentmachine.stripe.links', ['avulso' => 'https://buy.stripe.com/a', 'pack10' => 'https://buy.stripe.com/p10', 'pack20' => '', 'primeiro' => 'https://buy.stripe.com/p']);
        Queue::fake();
        $page = $this->get('/video-gratis?agencia=remax')->getContent();
        $this->out('page', $page);
        $t = fn () => Livewire::withQueryParams(['agencia' => 'remax'])->test(Landing::class);
        $this->out('link', $t()->call('mudarModo', 'link')->html());
        $this->out('erro', $t()->call('mudarModo', 'link')->call('pedir')->html());
        $this->out('fotos', $t()->call('mudarModo', 'fotos')->html());
        $this->out('depois', $t()->call('mudarModo', 'depois')->html());
        $this->out('nome', $t()->call('mudarModo', 'nome')->html());
        $this->out('era', Livewire::test(Landing::class)->call('escolher', 'era')->html());
        session()->forget('agencia');
        $t()->set('anuncio', 'https://www.remax.pt/pt/imoveis/moradia-t3/616161')->call('pedir');
        $this->out('afazer', Livewire::test(Landing::class)->html());
        $lead = DB::table('leads')->sole();
        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => tempnam(sys_get_temp_dir(), 'v')]);
        $this->out('pronto', Livewire::test(Landing::class)->html());
        session()->forget('lead_id');
        $c = $t()->call('mudarModo', 'fotos')->set('novasFotos', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg')])
            ->set('sobreFotos', 'T2 em Alvalade')->call('criarComFotos');
        $lead = DB::table('leads')->orderByDesc('id')->first();
        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => tempnam(sys_get_temp_dir(), 'v')]);
        $this->out('prontofotos', Livewire::test(Landing::class)->html());
        $this->out('contacto', Livewire::test(Landing::class)->set('pedirContacto', true)->html());
        $this->assertTrue(true);
    }
}
