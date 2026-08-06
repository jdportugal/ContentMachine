<?php

namespace App\Livewire;

use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use App\Services\Settings\SettingsRepository;
use App\Services\UpdateService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Definicoes extends Component
{
    /** @var array<string,string> */
    public array $geral = [];

    /** @var array<string,array<string,string>> */
    public array $perfis = [];

    /** Aggregator sources as text (one per line), per source. @var array<string,string> */
    public array $fontes = [];

    /** Channels to aggregate — list of URLs per platform. @var array<string,array<int,string>> */
    public array $canais = [];

    /** @var array<string,string> */
    public array $shorts = [];

    /**
     * API keys — WRITE-ONLY. Never populated from storage: a public Livewire
     * property is serialised into the page, so loading the real values here
     * would ship every key to the browser on each render. Blank means "leave as
     * is"; only what the user types is saved.
     *
     * @var array<string,string>
     */
    public array $chaves = [];

    /** Which keys already have a value stored, so the UI can say so without revealing it. @var array<string,bool> */
    public array $chavesDefinidas = [];

    /** Sign-in account: email + password change. @var array<string,string> */
    public array $conta = ['email' => '', 'atual' => '', 'nova' => '', 'nova_confirmation' => ''];

    public ?string $contaGuardada = null;

    /** Service/model config. @var array<string,string> */
    public array $modelos = [];

    /** Blotato connected-account ids per platform. @var array<string,string> */
    public array $blotato = [];

    /** Active project's language (stored in the project registry, not the vault). */
    public string $idioma = 'en';

    /** Active settings tab: geral | fontes | social | motor | chaves | sistema. */
    public string $secao = 'geral';

    /** Update state: idle | checking | available | uptodate | error | updating. */
    public string $atualizacao = 'idle';

    public ?string $guardado = null;

    public function mount(SettingsRepository $definicoes, ProjectContext $projeto): void
    {
        $tudo = $definicoes->all();

        $this->idioma = $projeto->current()->language ?: 'en';
        $this->geral = $tudo['geral'];
        $this->perfis = $tudo['perfis'];
        $this->shorts = $tudo['shorts'];
        // Only WHETHER each key is set — never the value (see $chaves).
        $this->chavesDefinidas = collect($tudo['chaves'])
            ->map(fn ($v) => is_string($v) && trim($v) !== '')
            ->all();
        $this->chaves = array_map(fn () => '', $this->chavesDefinidas);
        $this->conta['email'] = (string) (auth()->user()?->email ?? '');
        $this->modelos = $tudo['modelos'];
        $this->blotato = $tudo['blotato'];
        $this->fontes = collect($tudo['agregador'])
            ->map(fn (array $lista) => implode("\n", $lista))
            ->all();
        // Each platform has a list of URLs; ensure ≥1 line to write into.
        $this->canais = collect($tudo['canais'])
            ->map(fn (array $lista) => $lista === [] ? [''] : array_values($lista))
            ->all();
    }

    public function guardar(SettingsRepository $definicoes, ProjectContext $projeto, ProjectRepository $projetos): void
    {
        // Language lives in the project registry (not the vault) — update it there.
        $idioma = in_array($this->idioma, ['en', 'pt'], true) ? $this->idioma : 'en';
        $projetos->update($projeto->current()->slug, ['language' => $idioma]);
        app()->setLocale($idioma);

        $definicoes->save([
            'geral' => $this->geral,
            'perfis' => $this->perfis,
            'agregador' => $this->emListas($this->fontes),
            'canais' => collect($this->canais)
                ->map(fn (array $lista) => array_values(array_filter(array_map('trim', $lista))))
                ->all(),
            'shorts' => $this->shorts,
            // Only what was actually typed: a blank field means "keep the stored
            // key", never "erase it" (the fields render empty by design — see
            // $chaves). Use limparChave() to remove one on purpose.
            'chaves' => array_filter(array_map('trim', $this->chaves), fn (string $v) => $v !== ''),
            'modelos' => array_map('trim', $this->modelos),
            'blotato' => array_map('trim', $this->blotato),
        ]);

        $this->guardado = now()->translatedFormat('H:i');
        $this->chaves = array_map(fn () => '', $this->chaves);
        $this->chavesDefinidas = collect($definicoes->all()['chaves'])
            ->map(fn ($v) => is_string($v) && trim($v) !== '')
            ->all();
    }

    /**
     * Change the sign-in email/password. The current password is required even
     * for an email change: a session left open on a shared machine must not be
     * enough to take the account over.
     */
    public function guardarConta(): void
    {
        $utilizador = auth()->user();
        if ($utilizador === null) {
            return;
        }

        $this->validate([
            'conta.email' => ['required', 'string', 'email', 'max:255'],
            'conta.atual' => ['required', 'string'],
            'conta.nova' => ['nullable', 'string', 'min:12', 'confirmed'],
        ], attributes: [
            'conta.email' => 'email',
            'conta.atual' => 'current password',
            'conta.nova' => 'new password',
        ]);

        if (! Hash::check($this->conta['atual'], $utilizador->password)) {
            throw ValidationException::withMessages(['conta.atual' => 'That is not your current password.']);
        }

        $utilizador->email = $this->conta['email'];
        if (($this->conta['nova'] ?? '') !== '') {
            $utilizador->password = Hash::make($this->conta['nova']);
        }
        $utilizador->save();

        $this->conta['atual'] = $this->conta['nova'] = $this->conta['nova_confirmation'] = '';
        $this->contaGuardada = now()->translatedFormat('H:i');
    }

    /** Remove a stored API key (e.g. a leaked one). Blank fields never erase — this does. */
    public function limparChave(string $chave, SettingsRepository $definicoes): void
    {
        if (! array_key_exists($chave, $this->chavesDefinidas)) {
            return;
        }

        $definicoes->save(['chaves' => [$chave => '']]);
        $this->chaves[$chave] = '';
        $this->chavesDefinidas[$chave] = false;
    }

    public function adicionarCanal(string $plataforma): void
    {
        $this->canais[$plataforma][] = '';
    }

    public function removerCanal(string $plataforma, int $i): void
    {
        unset($this->canais[$plataforma][$i]);
        $this->canais[$plataforma] = array_values($this->canais[$plataforma]);

        if ($this->canais[$plataforma] === []) {
            $this->canais[$plataforma] = [''];
        }
    }

    /** Compare the running image against `:latest` on GHCR. */
    public function verificarAtualizacoes(UpdateService $updates): void
    {
        $this->atualizacao = 'checking';
        $disponivel = $updates->updateAvailable();
        $this->atualizacao = $disponivel === null ? 'error' : ($disponivel ? 'available' : 'uptodate');
    }

    /** Trigger the Watchtower sidecar to pull + recreate this container. */
    public function instalarAtualizacao(UpdateService $updates): void
    {
        if (! $updates->updatable()) {
            $this->atualizacao = 'error';

            return;
        }
        // 'triggered' → restarting; anything else is a real failure the user should
        // see (with the manual command as a fallback), not a silent "updating…".
        $this->atualizacao = $updates->triggerUpdate() === 'triggered' ? 'updating' : 'update-failed';
    }

    /**
     * Converts a map of texts (one entry per line) into a map of lists.
     *
     * @param  array<string,string>  $mapa
     * @return array<string,array<int,string>>
     */
    private function emListas(array $mapa): array
    {
        return collect($mapa)
            ->map(fn (string $texto) => collect(preg_split('/\r\n|\r|\n/', $texto))
                ->map(fn ($l) => trim($l))
                ->filter()
                ->values()
                ->all())
            ->all();
    }

    public function render(UpdateService $updates)
    {
        return view('livewire.definicoes', [
            'plataformasMeta' => config('contentmachine.plataformas_meta'),
            'versaoAtual' => $updates->shortVersion(),
            'podeAtualizar' => $updates->updatable(),
        ]);
    }
}
