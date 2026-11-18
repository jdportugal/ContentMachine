<?php

namespace Tests\Feature;

use App\Livewire\Landing;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Leads\LeadVideo;
use App\Services\Marketing\Canal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use Tests\TestCase;

/** "Convide um colega": a colleague's free video takes the watermark off the agent's. */
class ConvitesTest extends TestCase
{
    use RefreshDatabase;

    private const ANUNCIO_A = 'https://www.remax.pt/pt/imoveis/apartamento-t2/123456';

    private const ANUNCIO_B = 'https://www.remax.pt/pt/imoveis/moradia-t3/654321';

    /** A free lead for $anuncio, rendered (watermarked file + clean copy, as RenderJob leaves them). */
    private function leadPronto(string $anuncio, array $sessao = []): object
    {
        Queue::fake();
        Session::forget('lead_id');
        session($sessao);
        Livewire::test(Landing::class)->set(['anuncio' => $anuncio])->call('pedir')->assertHasNoErrors();
        $lead = DB::table('leads')->where('anuncio', $anuncio)->sole();
        $dir = sys_get_temp_dir().'/cm-convites-'.uniqid();
        @mkdir($dir, 0777, true);
        file_put_contents("$dir/clip.mp4", 'MARCA');
        file_put_contents("$dir/clip-limpo.mp4", 'LIMPO');
        $clip = app(ClipStore::class)->find($lead->clip_id);
        $clip->update(['status' => ClipRecord::STATUS_DONE, 'output_path' => "$dir/clip.mp4", 'meta' => array_merge((array) $clip->meta, ['limpo_path' => "$dir/clip-limpo.mp4"])]);

        return $lead;
    }

    private function mensagens(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->all();
    }

    public function test_an_invite_brings_a_colleague_and_unlocks_the_agents_clean_video(): void
    {
        $a = $this->leadPronto(self::ANUNCIO_A);
        DB::table('leads')->where('id', $a->id)->update(['nome' => 'Ana Silva']);

        // The ready card offers the invite; the agent leaves name and email, and gets a link.
        session(['lead_id' => $a->id]);
        $c = Livewire::test(Landing::class)->assertSee('CONVIDE UM COLEGA')
            ->set(['meuNome' => 'Ana Silva', 'meuEmail' => 'ana@exemplo.pt'])->call('ativarConvite')->assertHasNoErrors()
            ->assertSee('Enviar por WhatsApp')->assertSee('utm_source=convite', false);
        $a = DB::table('leads')->find($a->id);
        $this->assertNotNull($a->convite);
        $this->assertSame('ana@exemplo.pt', $a->convite_email);

        // We email a colleague in her name; replies go to her.
        $c->set('emailColega', 'Bruno@Exemplo.pt')->call('convidar')->assertHasNoErrors()->assertSee('Convite enviado para bruno@exemplo.pt');
        $convite = $this->mensagens()[0]->getOriginalMessage();
        $this->assertSame('Ana Silva ofereceu-lhe um vídeo do seu imóvel', $convite->getSubject());
        $this->assertSame('bruno@exemplo.pt', $convite->getTo()[0]->getAddress());
        $this->assertSame('ana@exemplo.pt', $convite->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('convite='.$a->convite, $convite->getHtmlBody());
        $c->set('emailColega', 'bruno@exemplo.pt')->call('convidar')->assertHasErrors('emailColega');   // not twice

        // The colleague arrives by the link: counted, told who invited them, and their lead remembers it.
        Session::flush();
        $this->get('/video-gratis?convite='.$a->convite.'&agencia=remax&utm_source=convite&utm_medium=email&utm_campaign=convite-colega&utm_content='.$a->convite)
            ->assertOk()->assertSee('Ana Silva ofereceu-lhe um vídeo grátis');
        $this->assertSame(1, DB::table('landing_eventos')->where('tipo', 'convite-visita')->count());
        $this->assertSame('convite', Canal::de(session('origem')));
        $b = $this->leadPronto(self::ANUNCIO_B, ['convite' => $a->convite, 'origem' => session('origem')]);
        $this->assertSame($a->id, (int) $b->convidado_por);
        $this->assertSame('convite', json_decode($b->origem, true)['utm_source']);

        // Her video stays watermarked until his is made; then it is clean and she is emailed.
        DB::table('leads')->where('id', $b->id)->update(['nome' => 'Bruno Costa']);
        $this->assertNull(DB::table('leads')->find($a->id)->oferta_em);
        Artisan::call('leads:atualizar');

        $a = DB::table('leads')->find($a->id);
        $this->assertNotNull($a->oferta_em);
        $this->assertSame('LIMPO', file_get_contents(app(LeadVideo::class)->ficheiro($a)));
        $oferta = collect($this->mensagens())->map->getOriginalMessage()->first(fn ($m) => $m->getTo()[0]->getAddress() === 'ana@exemplo.pt');
        $this->assertSame('O seu vídeo sem marca d’água está pronto', $oferta->getSubject());
        $this->assertStringContainsString('/v/'.$a->id, $oferta->getHtmlBody());
        $this->assertSame(1, DB::table('landing_eventos')->where('tipo', 'convite-recompensa')->count());

        // Her card now says so, and no longer offers the invite.
        Session::flush();
        session(['lead_id' => $a->id]);
        Livewire::test(Landing::class)->assertSee('oferecida pelo convite')->assertDontSee('CONVIDE UM COLEGA');
    }

    public function test_the_agent_cannot_invite_themselves(): void
    {
        $a = $this->leadPronto(self::ANUNCIO_A);
        DB::table('leads')->where('id', $a->id)->update(['nome' => 'Ana Silva', 'convite' => 'abcdef12', 'convite_email' => 'ana@exemplo.pt']);

        // Her own second listing through her own link: same name on both listings → nothing unlocked.
        $b = $this->leadPronto(self::ANUNCIO_B, ['convite' => 'abcdef12']);
        DB::table('leads')->where('id', $b->id)->update(['nome' => 'Ana  Silva ']);
        Artisan::call('leads:atualizar');

        $this->assertNull(DB::table('leads')->find($a->id)->oferta_em);
        $this->assertSame(1, DB::table('landing_eventos')->where('tipo', 'convite-ignorado')->count());

        // And her own email is not a colleague's.
        Session::forget('convite');
        session(['lead_id' => $a->id]);
        Livewire::test(Landing::class)->set('emailColega', 'ana@exemplo.pt')->call('convidar')->assertHasErrors('emailColega');
    }

    public function test_a_paid_video_has_no_invite_and_bad_codes_are_ignored(): void
    {
        $a = $this->leadPronto(self::ANUNCIO_A);
        DB::table('leads')->where('id', $a->id)->update(['pago_em' => now()]);
        app(LeadVideo::class)->limpar(DB::table('leads')->find($a->id));
        session(['lead_id' => $a->id]);
        Livewire::test(Landing::class)->assertDontSee('CONVIDE UM COLEGA')->call('ativarConvite');
        $this->assertNull(DB::table('leads')->find($a->id)->convite);

        Session::flush();
        $this->get('/video-gratis?convite=nao-existe')->assertOk();
        $this->assertNull(session('convite'));
        $this->assertSame(0, DB::table('landing_eventos')->where('tipo', 'convite-visita')->count());
    }
}
