<?php

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The app holds the API keys and every generated asset. Nothing in routes/web.php
 * may be reachable without a signed-in user.
 */
class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    private function utilizador(string $senha = 'a-very-long-password'): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make($senha),
        ]);
    }

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/definicoes')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
        $this->get('/noticias')->assertRedirect('/login');
    }

    /** The media/file routes are not pages, and were open before — cover them too. */
    public function test_rotas_de_ficheiros_tambem_exigem_sessao(): void
    {
        $this->get('/clips-animados/showreel')->assertRedirect('/login');
        $this->get('/clips-animados/upload/abc.png')->assertRedirect('/login');
        $this->get('/clips/musica/x.mp3')->assertRedirect('/login');
    }

    public function test_o_login_esta_acessivel_a_visitantes(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_credenciais_validas_iniciam_sessao(): void
    {
        $this->utilizador();

        Livewire::test(Login::class)
            ->set('email', 'admin@example.test')
            ->set('password', 'a-very-long-password')
            ->call('entrar')
            ->assertHasNoErrors();

        $this->assertTrue(Auth::check());
    }

    public function test_credenciais_invalidas_nao_iniciam_sessao(): void
    {
        $this->utilizador();

        Livewire::test(Login::class)
            ->set('email', 'admin@example.test')
            ->set('password', 'wrong-password')
            ->call('entrar')
            ->assertHasErrors('email');

        $this->assertFalse(Auth::check());
    }

    public function test_o_login_e_limitado_apos_varias_tentativas(): void
    {
        $this->utilizador();
        RateLimiter::clear('login:admin@example.test|127.0.0.1');

        $componente = Livewire::test(Login::class)
            ->set('email', 'admin@example.test')
            ->set('password', 'wrong-password');

        for ($i = 0; $i < 5; $i++) {
            $componente->call('entrar');
        }

        // Now throttled: even the CORRECT password is refused.
        $componente->set('password', 'a-very-long-password')
            ->call('entrar')
            ->assertHasErrors('email');

        $this->assertFalse(Auth::check());
    }

    public function test_utilizador_autenticado_ve_a_app(): void
    {
        $this->actingAs($this->utilizador());

        $this->get('/definicoes')->assertOk();
    }

    public function test_logout_termina_a_sessao(): void
    {
        $this->actingAs($this->utilizador());

        $this->post('/logout')->assertRedirect('/login');
        $this->assertFalse(Auth::check());
    }

    // ── registration ────────────────────────────────────────────────────────

    /** On a fresh install, sign-up creates the first account and signs it in. */
    public function test_primeiro_registo_cria_a_conta(): void
    {
        Livewire::test(\App\Livewire\Auth\Register::class)
            ->set('name', 'Owner')
            ->set('email', 'owner@example.test')
            ->set('password', 'a-very-long-password')
            ->set('password_confirmation', 'a-very-long-password')
            ->call('registar')
            ->assertHasNoErrors();

        $this->assertTrue(Auth::check());
        $this->assertSame(1, User::query()->count());
    }

    /**
     * Sign-up stays available once accounts exist — there is no "closed" state
     * and no flag that could put one back.
     */
    public function test_registo_continua_aberto_com_contas_existentes(): void
    {
        $this->utilizador();

        Livewire::test(\App\Livewire\Auth\Register::class)
            ->set('name', 'Colleague')
            ->set('email', 'colleague@example.test')
            ->set('password', 'a-very-long-password')
            ->set('password_confirmation', 'a-very-long-password')
            ->call('registar')
            ->assertHasNoErrors();

        $this->assertTrue(Auth::check());
        $this->assertSame(2, User::query()->count());
    }

    /** The sign-up page never renders a "closed" state. */
    public function test_a_pagina_de_registo_mostra_sempre_o_formulario(): void
    {
        $this->utilizador();

        $this->get('/register')
            ->assertOk()
            ->assertDontSee('Registration is closed')
            ->assertSee('Create account');
    }

    public function test_registo_exige_password_forte_e_email_unico(): void
    {
        $this->utilizador();

        Livewire::test(\App\Livewire\Auth\Register::class)
            ->set('name', 'Someone')
            ->set('email', 'admin@example.test') // already taken
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('registar')
            ->assertHasErrors(['email', 'password']);
    }
}
