<?php

namespace App\Services\Contas;

use App\Models\User;
use App\Services\Leads\LeadVideo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The agent on the end card of a video made without a link: name, phone, email, photo,
 * AMI and agency. What the customer typed last time (users.agente), else what their latest
 * video showed (its listing's agent and portrait), else nothing.
 */
class PerfilDoConsultor
{
    public const CAMPOS = ['nome', 'telefone', 'email', 'ami', 'agencia'];

    public function __construct(private LeadVideo $videos) {}

    /** @return array{nome:string,telefone:string,email:string,ami:string,agencia:string,foto:?string,fotoCaminho:?string} */
    public function de(User $user): array
    {
        $vazio = array_fill_keys(self::CAMPOS, '') + ['foto' => null, 'fotoCaminho' => null];
        $guardado = $user->agente ?? null;
        if (is_array($guardado) && trim((string) ($guardado['nome'] ?? '')) !== '') {
            $caminho = (string) ($guardado['foto'] ?? '');
            $existe = $caminho !== '' && $this->disco()->exists($caminho);

            return array_merge($vazio, array_map('strval', array_intersect_key($guardado, array_flip(self::CAMPOS))), [
                'foto' => $existe ? route('conta.foto-consultor', ['v' => substr(md5($caminho), 0, 8)]) : null,
                'fotoCaminho' => $existe ? $caminho : null,
            ]);
        }

        foreach (LinkDeAcesso::leads($user) as $lead) {
            $clip = rescue(fn () => $this->videos->clip($lead), null, report: false);
            $l = $clip?->meta['listing'] ?? null;
            if (! is_array($l) || trim((string) ($l['agent'] ?? '')) === '') {
                continue;
            }
            $retrato = collect($clip->images ?? [])->first(fn ($i) => str_starts_with((string) ($i['description'] ?? ''), 'Retrato')
                && ! empty($i['path']) && $this->disco()->exists($i['path']));

            return array_merge($vazio, [
                'nome' => (string) $l['agent'],
                'telefone' => (string) ($l['phone'] ?? ''),
                'email' => (string) ($l['email'] ?? ''),
                'ami' => (string) ($l['licence'] ?? ''),
                'agencia' => (string) ($l['agency'] ?? ''),
                'foto' => $retrato ? route('conta.imagem', ['lead' => $lead->id, 'imagem' => $retrato['id']]) : null,
                'fotoCaminho' => $retrato['path'] ?? null,
            ]);
        }

        return $vazio;
    }

    /**
     * Keep what was typed for next time. The photo (a path on the clips disk) is copied
     * under clips/agentes, apart from the videos' own files, which edits may delete.
     *
     * @param  array<string,string>  $dados
     */
    public function guardar(User $user, array $dados, ?string $foto): void
    {
        $perfil = array_map(fn ($v) => trim((string) $v), array_intersect_key($dados, array_flip(self::CAMPOS)));
        $anterior = (string) (($user->agente ?? [])['foto'] ?? '');
        if ($foto !== null && $foto !== $anterior && $this->disco()->exists($foto)) {
            $perfil['foto'] = $this->copiar($foto, 'clips/agentes/u'.$user->id.'-');
        } elseif ($anterior !== '') {
            $perfil['foto'] = $anterior;
        }
        $user->forceFill(['agente' => $perfil])->save();
    }

    /** A copy of a file on the clips disk under $prefixo, returning its path. */
    public function copiar(string $caminho, string $prefixo = 'clips/uploads/'): string
    {
        $destino = $prefixo.Str::random(24).'.'.(pathinfo($caminho, PATHINFO_EXTENSION) ?: 'jpg');
        $this->disco()->put($destino, (string) $this->disco()->get($caminho));

        return $destino;
    }

    private function disco()
    {
        return Storage::disk(config('contentmachine.clips.disk'));
    }
}
