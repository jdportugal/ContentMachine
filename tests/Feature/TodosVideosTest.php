<?php

namespace Tests\Feature;

use App\Livewire\TodosVideos;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin: every customer's video, with the links the customer got, to send them again. */
class TodosVideosTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_sees_everyones_videos_with_their_links_and_customers_do_not(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $ana = User::factory()->cliente()->create(['email' => 'ana@exemplo.pt']);
        $lead = fn (array $l) => DB::table('leads')->insertGetId($l + ['projeto' => 'default', 'ip' => '1.1.1.1', 'consentimento_em' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $pronto = $lead(['anuncio' => 'https://remax.pt/pt/imoveis/a/1-1', 'user_id' => $ana->id, 'pago_em' => now(), 'pronto_em' => now()]);
        $gratis = $lead(['anuncio' => 'https://www.iadportugal.pt/anuncio/b/r2', 'email_video' => 'rui@exemplo.pt', 'nome' => 'Rui Costa', 'pronto_em' => now()]);
        $lead(['anuncio' => 'https://century21.pt/ref/C3', 'email' => 'zeca@exemplo.pt', 'falhou_em' => now()]);

        $this->actingAs($admin)->get(route('videos.todos'))->assertOk()->assertSee('Todos os vídeos');
        $this->get(route('imoveis'))->assertSee(route('videos.todos'), false);   // in the admin sidebar

        Livewire::actingAs($admin)->test(TodosVideos::class)
            ->assertSee('ana@exemplo.pt')->assertSee('rui@exemplo.pt (sem conta)')->assertSee('zeca@exemplo.pt')->assertSee('3 vídeos')
            ->assertSee(URL::signedRoute('lead.video', ['lead' => $pronto]), false)                    // the same permanent link the email had
            ->assertSee(URL::signedRoute('lead.video', ['lead' => $gratis, 'download' => 1]))   // escaped as in the HTML
            ->assertSee('Sem marca d’água')->assertSee('Grátis · com marca')
            ->set('procura', 'rui')->assertSee('rui@exemplo.pt')->assertDontSee('ana@exemplo.pt')
            ->set('procura', '')->set('estado', 'falhados')->assertSee('zeca@exemplo.pt')->assertDontSee('ana@exemplo.pt')->assertSee('1 vídeo');

        $this->actingAs($ana)->get(route('videos.todos'))->assertRedirect();   // customers are sent away, like every admin page
    }
}
