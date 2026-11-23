<?php

namespace Tests\Feature;

use App\Jobs\Clips\ImportListingJob;
use App\Jobs\Clips\RenderJob;
use App\Livewire\Landing;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Leads\LeadVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The free-video page, one question at a time: the agency, which listing, then the answer's screen. */
class LandingPassosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake((string) config('contentmachine.clips.disk'));
        config()->set('contentmachine.stripe.links', ['avulso' => '', 'pack10' => 'https://buy.stripe.com/pack10', 'pack20' => '', 'primeiro' => 'https://buy.stripe.com/primeiro']);
    }

    private function fotos(int $n): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->image("casa-$i.jpg", 1200, 800), range(1, $n));
    }

    public function test_an_agent_without_a_link_makes_the_video_from_photos(): void
    {
        Queue::fake();
        $c = Livewire::withQueryParams(['agencia' => 'kw'])->test(Landing::class)
            ->call('mudarModo', 'fotos')->assertSee('Fotos ou vídeos do imóvel')
            ->set('novasFotos', $this->fotos(2))->assertCount('fotos', 2)
            ->call('criarComFotos')->assertHasErrors('fotos')
            ->set('novasFotos', $this->fotos(3))->assertCount('fotos', 5)
            ->call('tirarFoto', 0)->assertCount('fotos', 4)
            ->call('criarComFotos')->assertHasErrors('sobreFotos')
            ->set('sobreFotos', 'T2 em Alvalade, 320 000 €')->call('criarComFotos')->assertHasNoErrors()
            ->assertSee('Estamos a fazer o seu vídeo');

        $lead = DB::table('leads')->sole();
        $this->assertSame('kw', $lead->agencia);
        $this->assertStringStartsWith('https://imocena.com/carregado/', $lead->anuncio);
        $meta = app(ClipStore::class)->find($lead->clip_id)->meta;
        $this->assertTrue($meta['free_lead']);
        $this->assertCount(4, $meta['listing_manual']['photos']);
        $this->assertSame('T2 em Alvalade, 320 000 €', $meta['listing_manual']['description']);
        $this->assertSame(320000, $meta['listing_manual']['price']);
        Queue::assertPushed(ImportListingJob::class, 1);
    }

    public function test_a_video_of_the_property_is_enough_and_is_cut_per_room(): void
    {
        Queue::fake();
        $filme = UploadedFile::fake()->create('casa.mp4', 2048, 'video/mp4');
        Livewire::withQueryParams(['agencia' => 'remax'])->test(Landing::class)->call('mudarModo', 'fotos')
            ->set('novasFotos', [$filme])->assertCount('videos', 1)->assertCount('fotos', 0)->assertSee('1 vídeo')
            ->set('novasFotos', $this->fotos(2))->assertCount('fotos', 2)
            ->set('sobreFotos', 'T3 em Braga')->call('criarComFotos')->assertHasNoErrors()->assertSee('Estamos a fazer o seu vídeo');

        $meta = app(ClipStore::class)->find(DB::table('leads')->sole()->clip_id)->meta;
        $this->assertNotEmpty($meta['listing_video']);
        $this->assertTrue($meta['mix_photos']);
        $this->assertCount(2, $meta['listing_manual']['photos']);
    }

    public function test_a_photos_video_asks_for_the_contact_with_the_clean_version(): void
    {
        Queue::fake();
        $c = Livewire::withQueryParams(['agencia' => 'kw'])->test(Landing::class)->call('mudarModo', 'fotos')
            ->set('novasFotos', $this->fotos(3))->set('sobreFotos', 'Moradia T3 em Viseu')->call('criarComFotos')->assertHasNoErrors();
        $lead = DB::table('leads')->sole();
        $antigo = app(ClipStore::class)->find($lead->clip_id);
        $antigo->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => $f = tempnam(sys_get_temp_dir(), 'v')]);

        // No contact at the end yet: the one button adds it and takes the watermark off.
        $c = Livewire::test(Landing::class)->assertSee('Adicionar o meu contacto')->assertDontSee('Tirar a marca d’água · 10 €')
            ->call('$set', 'pedirContacto', true)->assertSee('O seu contacto')
            ->call('guardarContacto')->assertHasErrors(['nomeContacto', 'telefoneContacto'])
            ->set(['nomeContacto' => 'Ana  Silva', 'telefoneContacto' => '912 345 678'])->call('guardarContacto')->assertHasNoErrors()
            ->assertRedirect('https://buy.stripe.com/primeiro?client_reference_id=lead-'.$lead->id.'&locale=pt');

        $lead = DB::table('leads')->sole();
        $this->assertNotSame($antigo->id, $lead->clip_id);
        $this->assertSame(['Ana Silva', '912 345 678'], [$lead->nome, $lead->telefone]);
        $novo = app(ClipStore::class)->find($lead->clip_id);
        $this->assertSame(['name' => 'Ana Silva', 'phone' => '912 345 678'], $novo->meta['listing_manual']['agent']);
        $this->assertSame($antigo->meta['listing_manual']['photos'], $novo->meta['listing_manual']['photos']);
        $this->assertTrue($novo->meta['free_lead']);

        // Paid while it is still being made: it simply comes out clean, no second render.
        Queue::fake();
        $this->assertTrue(app(LeadVideo::class)->limpar($lead));
        $novo = app(ClipStore::class)->find($lead->clip_id);
        $this->assertFalse($novo->meta['free_lead']);
        $this->assertTrue($novo->meta['pago']);
        Queue::assertNotPushed(RenderJob::class);
    }

    public function test_a_video_from_a_listing_has_the_plain_clean_button(): void
    {
        Queue::fake();
        Livewire::test(Landing::class)->set('anuncio', 'https://www.remax.pt/pt/imoveis/moradia-t3/616161')->call('pedir')->assertHasNoErrors();
        $lead = DB::table('leads')->sole();
        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => tempnam(sys_get_temp_dir(), 'v')]);

        Livewire::test(Landing::class)->assertSee('Tirar a marca d’água · 10 €')->assertDontSee('Adicionar o meu contacto')
            ->assertSee('https://buy.stripe.com/primeiro?client_reference_id=lead-'.$lead->id, false)
            ->call('guardarContacto')->assertNoRedirect();
    }

    public function test_an_agency_we_do_not_read_can_still_use_photos(): void
    {
        Livewire::test(Landing::class)->call('escolher', 'era')->assertSee('Ou faça já o vídeo com as suas fotos')
            ->call('mudarModo', 'fotos')->assertSee('Fotos ou vídeos do imóvel');
    }
}
