<?php

namespace App\Livewire;

use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SharedKeys;
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
     * API keys to ADD — WRITE-ONLY. Never populated from storage: a public
     * Livewire property is serialised into the page, so loading the real values
     * here would ship every key to the browser on each render. Blank means "add
     * nothing"; only what the user types is saved.
     *
     * @var array<string,string>
     */
    public array $chaves = [];

    /** Optional name for each key being added ("Personal", "Client X"). @var array<string,string> */
    public array $rotulos = [];

    /**
     * The keys already stored, by provider — id + label ONLY, never the secret.
     * A provider may hold several; the first is its default.
     *
     * @var array<string,array<int,array{id:string,label:string}>>
     */
    public array $chavesGuardadas = [];

    /** Which providers already have at least one key stored. @var array<string,bool> */
    public array $chavesDefinidas = [];

    /** Per-step key binding: step id => key id | 'local' | '' (auto). @var array<string,string> */
    public array $passos = [];

    /** Sign-in account: email + password change. @var array<string,string> */
    public array $conta = ['email' => '', 'atual' => '', 'nova' => '', 'nova_confirmation' => ''];

    public ?string $contaGuardada = null;

    /** Service/model config. @var array<string,string> */
    public array $modelos = [];

    /** Blotato connected-account ids per platform. @var array<string,string> */
    public array $blotato = [];

    /** Zernio ids for the DM automations (profile + Instagram account). @var array<string,string> */
    public array $zernio = [];

    /** Active project's language (stored in the project registry, not the vault). */
    public string $idioma = 'en';

    /** Active settings tab: geral | fontes | social | motor | chaves | passos | sistema. */
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
        $this->recarregarChaves();
        $this->conta['email'] = (string) (auth()->user()?->email ?? '');
        $this->modelos = $tudo['modelos'];
        $this->passos = array_map(fn ($v) => (string) $v, $tudo['passos'] ?? []);
        $this->blotato = $tudo['blotato'];
        $this->zernio = $tudo['zernio'];
        $this->fontes = collect($tudo['agregador'])
            ->map(fn (array $lista) => implode("\n", $lista))
            ->all();
        // Each platform has a list of URLs; ensure ≥1 line to write into.
        $this->canais = collect($tudo['canais'])
            ->map(fn (array $lista) => $lista === [] ? [''] : array_values($lista))
            ->all();
    }

    public function guardar(SettingsRepository $definicoes, ProjectContext $projeto, ProjectRepository $projetos, SharedKeys $chaves): void
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
            'modelos' => array_map('trim', $this->modelos),
            'blotato' => array_map('trim', $this->blotato),
            'zernio' => array_map('trim', $this->zernio),
            'passos' => array_map('trim', $this->passos),
        ]);

        // Only what was actually typed becomes a NEW key: a blank field means
        // "add nothing", never "erase" (the fields render empty by design — see
        // $chaves). Use removerChave()/limparChave() to delete on purpose.
        foreach ($this->chaves as $fornecedor => $valor) {
            if (trim((string) $valor) !== '') {
                $chaves->add((string) $fornecedor, (string) $valor, (string) ($this->rotulos[$fornecedor] ?? ''));
            }
        }

        $this->guardado = now()->translatedFormat('H:i');
        $this->recarregarChaves();
    }

    /** Refreshes the stored-keys view (ids + labels only — never the secrets). */
    private function recarregarChaves(): void
    {
        $guardadas = app(SharedKeys::class)->entries();

        $this->chavesGuardadas = array_map(
            fn (array $lista) => array_map(fn (array $e) => ['id' => $e['id'], 'label' => $e['label']], $lista),
            $guardadas
        );
        $this->chavesDefinidas = array_map(fn (array $lista) => $lista !== [], $guardadas);
        $this->chaves = array_map(fn () => '', $this->chaves);
        $this->rotulos = array_map(fn () => '', $this->rotulos);
    }

    /** Removes ONE stored key by id (the other keys of that provider stay). */
    public function removerChave(string $id, SharedKeys $chaves): void
    {
        $chaves->remove($id);
        $this->recarregarChaves();
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

    /** Remove EVERY stored key of a provider (e.g. after a leak). Blank fields never erase — this does. */
    public function limparChave(string $chave, SettingsRepository $definicoes): void
    {
        if (! array_key_exists($chave, $this->chavesDefinidas)) {
            return;
        }

        $definicoes->save(['chaves' => [$chave => '']]);
        $this->recarregarChaves();
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

    /**
     * The keys each step may be pinned to: `key id => human label`, prefixed by
     * "auto" and, for transcription, keyless local Whisper.
     *
     * @return array<string,array<string,string>>
     */
    private function opcoesPorPasso(): array
    {
        $opcoes = [];

        foreach (config('contentmachine.passos', []) as $passo => $meta) {
            $lista = ['' => 'auto (default chain)'];

            foreach (config('contentmachine.passos_fornecedores.'.$meta['kind'], []) as $fornecedor) {
                if ($fornecedor === 'local') {
                    $lista['local'] = 'local Whisper (no key needed)';

                    continue;
                }
                foreach ($this->chavesGuardadas[$fornecedor] ?? [] as $i => $chave) {
                    $lista[$chave['id']] = $fornecedor.' · '.($chave['label'] ?: 'key '.($i + 1));
                }
            }

            $opcoes[$passo] = $lista;
        }

        return $opcoes;
    }

    public function render(UpdateService $updates)
    {
        return view('livewire.definicoes', [
            'plataformasMeta' => config('contentmachine.plataformas_meta'),
            'versaoAtual' => $updates->shortVersion(),
            'podeAtualizar' => $updates->updatable(),
            'passosMeta' => config('contentmachine.passos', []),
            'passosOpcoes' => $this->opcoesPorPasso(),
        ]);
    }
}
