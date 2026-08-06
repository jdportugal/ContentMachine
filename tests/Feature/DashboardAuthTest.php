<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The app holds the API keys and every generated asset. It must never serve a
 * request that did not present the dashboard password.
 */
class DashboardAuthTest extends TestCase
{
    private function comSenha(string $senha): void
    {
        config(['contentmachine.dashboard.password' => $senha]);
    }

    public function test_recusa_sem_credenciais(): void
    {
        $this->comSenha('correct-horse');

        $this->get('/definicoes')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Basic realm="Content Machine", charset="UTF-8"');
    }

    public function test_recusa_senha_errada(): void
    {
        $this->comSenha('correct-horse');

        $this->withBasicAuth('admin', 'battery-staple')->get('/definicoes')->assertStatus(401);
    }

    /**
     * A correct password gets past the gate. Asserted on a non-Blade route so the
     * check stays about authentication — a 404 from the route itself still proves
     * the request was let through, and it does not depend on a built Vite manifest.
     */
    public function test_aceita_senha_certa(): void
    {
        $this->comSenha('correct-horse');

        $this->withBasicAuth('admin', 'correct-horse')
            ->get('/clips-animados/showreel')
            ->assertStatus(404);
    }

    /** The gate is global, not web-group only: the media routes must be covered too. */
    public function test_protege_rotas_de_ficheiros(): void
    {
        $this->comSenha('correct-horse');

        $this->get('/clips-animados/showreel')->assertStatus(401);
        $this->get('/clips-animados/upload/abc.png')->assertStatus(401);
    }

    /**
     * Production serves via `php artisan serve`, which does not populate
     * PHP_AUTH_PW — the gate must also read the raw Authorization header, or the
     * real deploy would 401 on every request no matter the password.
     */
    public function test_aceita_cabecalho_authorization_cru(): void
    {
        $this->comSenha('correct-horse');

        $this->withHeader('Authorization', 'Basic '.base64_encode('admin:correct-horse'))
            ->get('/clips-animados/showreel')
            ->assertStatus(404);

        $this->withHeader('Authorization', 'Basic '.base64_encode('admin:wrong'))
            ->get('/clips-animados/showreel')
            ->assertStatus(401);
    }

    /** Health probes stay reachable — they expose no data. */
    public function test_health_fica_aberto(): void
    {
        $this->comSenha('correct-horse');

        $this->get('/up')->assertOk();
    }

    /**
     * `php artisan serve` (what the container runs) does not forward APP_PASSWORD
     * to the process that serves requests, so the persisted file is the channel
     * that actually works in production. If this breaks, the deploy 503s.
     */
    public function test_le_a_senha_do_ficheiro_quando_env_nao_chega(): void
    {
        $this->comSenha('');
        $ficheiro = storage_path('app/app_password');
        file_put_contents($ficheiro, "file-secret\n");

        try {
            $this->get('/clips-animados/showreel')->assertStatus(401);
            $this->withBasicAuth('admin', 'file-secret')
                ->get('/clips-animados/showreel')
                ->assertStatus(404);
        } finally {
            @unlink($ficheiro);
        }
    }

    /** Fails CLOSED: a deploy with no password configured must refuse, not expose. */
    public function test_sem_senha_em_producao_recusa(): void
    {
        $this->comSenha('');
        app()->detectEnvironment(fn () => 'production');

        $this->get('/definicoes')->assertStatus(503);
    }
}
