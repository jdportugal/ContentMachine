<!doctype html>
<html lang="pt"><body style="margin:0;background:#F4F1EA;font-family:Helvetica,Arial,sans-serif;color:#0E1626">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border-radius:12px;padding:32px">
<tr><td style="font-family:Georgia,serif;font-size:28px">Imocena<span style="color:#22B8CF">.</span></td></tr>
<tr><td style="padding-top:20px;font-size:16px;line-height:1.6">Olá{{ $nome !== '' ? ' '.$nome : '' }},</td></tr>
<tr><td style="padding-top:8px;font-size:16px;line-height:1.6">{{ count($itens) === 1 ? 'Vimos que publicou um imóvel novo. O vídeo dele fica pronto em minutos:' : 'Vimos que publicou imóveis novos. O vídeo de cada um fica pronto em minutos:' }}</td></tr>
@foreach ($itens as $item)
<tr><td style="padding-top:20px;font-size:17px;line-height:1.4;font-weight:bold">{{ $item['titulo'] }}</td></tr>
<tr><td style="padding-top:10px"><a href="{{ $item['link'] }}" style="display:inline-block;background:#F2665A;color:#0E1626;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:999px">Fazer o vídeo deste imóvel →</a></td></tr>
@endforeach
@if ($mais > 0)
<tr><td style="padding-top:20px;font-size:15px;line-height:1.6">E mais {{ $mais }} {{ $mais === 1 ? 'imóvel novo' : 'imóveis novos' }}: cole o link de qualquer anúncio em imocena.com.</td></tr>
@endif
<tr><td style="padding-top:20px;font-size:15px;line-height:1.6">Se o botão não abrir o anúncio certo, cole o link do anúncio em imocena.com.</td></tr>
<tr><td style="padding-top:24px;font-size:13px;line-height:1.6;color:#5A6474">Recebe este email porque pediu para ser avisado quando publica um imóvel novo. <a href="{{ $cancelar }}" style="color:#5A6474">Deixar de receber estes avisos</a>.</td></tr>
</table></td></tr></table></body></html>
