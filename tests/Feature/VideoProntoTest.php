<?php

namespace Tests\Feature;

use App\Livewire\Landing;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

/** The video-ready email, and the launch offer (this video clean for 10 €) beside the pack. */
class VideoProntoTest extends TestCase
{
    use RefreshDatabase;

    private const ANUNCIO = 'https://www.remax.pt/pt/imoveis/apartamento-t2/123456';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('contentmachine.stripe.links', ['avulso' => 'https://buy.stripe.com/avulso', 'pack10' => 'https://buy.stripe.com/pack10', 'pack20' => '', 'primeiro' => 'https://buy.stripe.com/primeiro']);
    }

    /** Asks for the video (with $email in the optional box) and renders it. */
    private function pedirEFazer(string $email = '', string $anuncio = self::ANUNCIO): object
    {
        Queue::fake();
        Session::forget('lead_id');
        Livewire::test(Landing::class)->set(['anuncio' => $anuncio, 'emailVideo' => $email])->call('pedir')->assertHasNoErrors();
        $lead = DB::table('leads')->where('anuncio', $anuncio)->sole();
        $dir = sys_get_temp_dir().'/cm-pronto-'.uniqid();
        @mkdir($dir, 0777, true);
        file_put_contents("$dir/clip.mp4", 'MARCA');
        app(ClipStore::class)->find($lead->clip_id)->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => "$dir/clip.mp4"]);

        return $lead;
    }

    private function mensagens(): array
    {
        return collect(app('mailer')->getSymfonyTransport()->messages()->all())->map->getOriginalMessage()->all();
    }

    public function test_the_ready_email_goes_once_with_the_video_the_offer_and_the_invite(): void
    {
        $lead = $this->pedirEFazer('Ana@Exemplo.pt');
        $this->assertSame('ana@exemplo.pt', $lead->email_video);
        $this->assertSame('ana@exemplo.pt', $lead->convite_email);

        Artisan::call('leads:atualizar');
        Artisan::call('leads:atualizar');   // once only

        $enviados = $this->mensagens();
        $this->assertCount(1, $enviados);
        $this->assertSame('O vídeo do seu imóvel está pronto', $enviados[0]->getSubject());
        $this->assertSame('ana@exemplo.pt', $enviados[0]->getTo()[0]->getAddress());
        $html = $enviados[0]->getHtmlBody();
        $this->assertStringContainsString('/v/'.$lead->id, $html);
        $this->assertStringContainsString('https://buy.stripe.com/primeiro?client_reference_id=lead-'.$lead->id, $html);
        $this->assertStringContainsString('https://buy.stripe.com/pack10?client_reference_id=lead-'.$lead->id, $html);
        $this->assertStringContainsString('convite=', $html);
        $this->assertNotNull(DB::table('leads')->find($lead->id)->aviso_pronto_em);
    }

    public function test_no_email_without_an_address_and_the_card_offers_10_euros_beside_the_pack(): void
    {
        $lead = $this->pedirEFazer();
        Artisan::call('leads:atualizar');
        $this->assertCount(0, $this->mensagens());

        session(['lead_id' => $lead->id]);
        Livewire::test(Landing::class)
            ->assertSee('Tirar a marca d’água · 10 €')
            ->assertSee('https://buy.stripe.com/primeiro?client_reference_id=lead-'.$lead->id, false)
            ->assertSee('Pack 10 · 10 €/vídeo')
            ->assertDontSee('Quero a versão limpa (20 €)');
    }

    public function test_earlier_videos_get_the_email_on_request_only(): void
    {
        $lead = $this->pedirEFazer();
        DB::table('leads')->where('id', $lead->id)->update(['pronto_em' => now(), 'email' => 'rui@exemplo.pt']);   // ready before the email existed

        Artisan::call('leads:avisar-prontos');
        $this->assertStringContainsString('rui@exemplo.pt', Artisan::output());
        $this->assertCount(0, $this->mensagens());

        Artisan::call('leads:avisar-prontos', ['--enviar' => true]);
        $this->assertCount(1, $this->mensagens());
        Artisan::call('leads:avisar-prontos', ['--enviar' => true]);
        $this->assertCount(1, $this->mensagens());
    }
}
