<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Two days after a free video: "invite a colleague and get yours without the watermark", once, only to agents who agreed. */
class LembreteConviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('contentmachine.stripe.links', ['avulso' => '', 'pack10' => '', 'pack20' => '', 'primeiro' => 'https://buy.stripe.com/primeiro']);
    }

    private function lead(array $mais = []): int
    {
        return DB::table('leads')->insertGetId($mais + [
            'anuncio' => 'https://www.remax.pt/'.uniqid(), 'projeto' => 'default', 'consentimento_em' => now()->subDays(3),
            'nome' => 'Ana Silva', 'agencia' => 'remax', 'pronto_em' => now()->subDays(3),
            'email' => 'ana@exemplo.pt', 'marketing_em' => now()->subDays(3),
            'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
        ]);
    }

    private function mensagens(): array
    {
        return collect(app('mailer')->getSymfonyTransport()->messages()->all())->map->getOriginalMessage()->all();
    }

    public function test_it_goes_once_two_days_after_the_video_with_the_invite_and_the_unsubscribe(): void
    {
        $id = $this->lead();
        $this->lead(['email' => 'cedo@exemplo.pt', 'pronto_em' => now()->subHours(20)]);            // too soon

        Artisan::call('leads:lembrar-convite');                                                     // lists only
        $this->assertCount(0, $this->mensagens());

        Artisan::call('leads:lembrar-convite', ['--enviar' => true]);
        Artisan::call('leads:lembrar-convite', ['--enviar' => true]);                               // once only
        $enviados = $this->mensagens();
        $this->assertCount(1, $enviados);
        $m = $enviados[0];
        $this->assertSame('ana@exemplo.pt', $m->getTo()[0]->getAddress());
        $this->assertStringContainsString('sem marca d’água', $m->getSubject());
        $html = $m->getHtmlBody();
        $this->assertStringContainsString('Olá Ana', $html);
        $this->assertStringContainsString('https://wa.me/?text=', $html);
        $this->assertStringContainsString('utm_campaign=convite-colega', $html);
        $this->assertStringContainsString('buy.stripe.com/primeiro', $html);
        $this->assertStringContainsString('/emails/cancelar/'.$id, $html);
        $this->assertStringContainsString('One-Click', $m->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());

        $l = DB::table('leads')->find($id);
        $this->assertNotNull($l->lembrete_convite_em);
        $this->assertNotNull($l->convite);
        $this->assertSame('ana@exemplo.pt', $l->convite_email);   // where the clean video will go
    }

    public function test_agents_who_paid_tried_again_opted_out_or_never_agreed_are_left_alone(): void
    {
        $this->lead(['email' => 'pagou@exemplo.pt', 'pago_em' => now()->subDays(2)]);
        $this->lead(['email' => 'ganhou@exemplo.pt', 'oferta_em' => now()->subDays(2)]);
        $this->lead(['email' => 'saiu@exemplo.pt', 'marketing_cancelado_em' => now()->subDay()]);
        $this->lead(['email' => null, 'marketing_em' => null, 'email_video' => 'so-video@exemplo.pt']);
        $this->lead(['email' => 'falhou@exemplo.pt', 'pronto_em' => null, 'falhou_em' => now()->subDays(3)]);
        $this->lead(['email' => 'teste@exemplo.pt', 'origem' => '{"utm_source":"teste-interno"}']);
        // Came back for another video (same email, or same agent page).
        $this->lead(['email' => 'voltou@exemplo.pt']);
        $this->lead(['email' => null, 'marketing_em' => null, 'email_video' => 'Voltou@exemplo.pt', 'pronto_em' => now()->subDay(), 'created_at' => now()->subDay()]);
        $this->lead(['email' => 'pagina@exemplo.pt', 'consultor' => 'remax:123']);
        $this->lead(['email' => 'outro@exemplo.pt', 'consultor' => 'remax:123', 'marketing_em' => null]);
        // Bought a pack under the same email, without a lead of its own.
        $this->lead(['email' => 'pack@exemplo.pt']);
        DB::table('compras')->insert(['sessao' => 'cs_p', 'total' => 10000, 'videos' => 10, 'email' => 'PACK@exemplo.pt', 'created_at' => now(), 'updated_at' => now()]);

        Artisan::call('leads:lembrar-convite', ['--enviar' => true]);

        $this->assertCount(0, $this->mensagens());
    }
}
