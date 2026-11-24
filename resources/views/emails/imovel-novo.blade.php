<!doctype html>
<html lang="pt"><body style="margin:0;background:#F4F1EA;font-family:Helvetica,Arial,sans-serif;color:#0E1626">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border-radius:12px;padding:32px">
<tr><td style="font-family:Georgia,serif;font-size:28px">Imocena<span style="color:#22B8CF">.</span></td></tr>
<tr><td style="padding-top:20px;font-size:16px;line-height:1.6">Olá{{ $nome !== '' ? ' '.$nome : '' }},</td></tr>
@if (! empty($demo))
<tr><td style="padding-top:8px;font-size:16px;line-height:1.6">Vimos que publicou um imóvel novo e fizemos uma demonstração de como pode ficar o vídeo dele, só com as fotografias do anúncio.</td></tr>
<tr><td style="padding-top:20px;font-size:17px;line-height:1.4;font-weight:bold">{{ $demo['titulo'] !== '' ? $demo['titulo'] : 'O seu imóvel novo' }}</td></tr>
@if ($demo['poster'] !== '')
<tr><td style="padding-top:12px"><a href="{{ $demo['pagina'] }}"><img src="{{ $demo['poster'] }}" width="220" alt="Ver a demonstração" style="display:block;width:220px;max-width:100%;border-radius:10px;border:0"></a></td></tr>
@endif
<tr><td style="padding-top:14px"><a href="{{ $demo['pagina'] }}" style="display:inline-block;background:#F2665A;color:#0E1626;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:999px">▶ Ver a demonstração</a></td></tr>
<tr><td style="padding-top:14px;font-size:15px;line-height:1.6">Na versão final, a narração fala deste imóvel e no fim aparecem o seu nome e o seu contacto. Fica pronta em minutos, sem marca d’água, a partir de 8,75 €.</td></tr>
@if (count($itens) > 1)
<tr><td style="padding-top:24px;font-size:16px;line-height:1.6">Também publicou:</td></tr>
@foreach (array_slice($itens, 1) as $item)
<tr><td style="padding-top:16px;font-size:17px;line-height:1.4;font-weight:bold">{{ $item['titulo'] }}</td></tr>
<tr><td style="padding-top:10px"><a href="{{ $item['link'] }}" style="display:inline-block;background:#0E1626;color:#F4F1EA;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:999px">Ver como fazer o vídeo →</a></td></tr>
@endforeach
@endif
@else
<tr><td style="padding-top:8px;font-size:16px;line-height:1.6">{{ count($itens) === 1 ? 'Vimos que publicou um imóvel novo. Quer o vídeo dele? Fica pronto em minutos, sem marca d’água, a partir de 8,75 €.' : 'Vimos que publicou imóveis novos. Quer os vídeos deles? Ficam prontos em minutos, sem marca d’água, a partir de 8,75 € cada.' }}</td></tr>
@foreach ($itens as $item)
<tr><td style="padding-top:20px;font-size:17px;line-height:1.4;font-weight:bold">{{ $item['titulo'] }}</td></tr>
<tr><td style="padding-top:10px"><a href="{{ $item['link'] }}" style="display:inline-block;background:#F2665A;color:#0E1626;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:999px">Ver como fazer o vídeo →</a></td></tr>
@endforeach
@endif
@if ($mais > 0)
<tr><td style="padding-top:20px;font-size:15px;line-height:1.6">E mais {{ $mais }} {{ $mais === 1 ? 'imóvel novo' : 'imóveis novos' }}: cole o link de qualquer anúncio em imocena.com.</td></tr>
@endif
<tr><td style="padding-top:20px;font-size:15px;line-height:1.6">Se o botão não abrir o anúncio certo, cole o link do anúncio em imocena.com.</td></tr>
<tr><td style="padding-top:24px;font-size:13px;line-height:1.6;color:#5A6474">Recebe este email porque pediu para ser avisado quando publica um imóvel novo. <a href="{{ $cancelar }}" style="color:#5A6474">Deixar de receber estes avisos</a>.</td></tr>
</table></td></tr></table></body></html>
