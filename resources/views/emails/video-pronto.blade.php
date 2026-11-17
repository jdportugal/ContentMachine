<!doctype html>
<html lang="pt"><body style="margin:0;background:#F4F1EA;font-family:Helvetica,Arial,sans-serif;color:#0E1626">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border-radius:12px;padding:32px">
<tr><td style="font-family:Georgia,serif;font-size:28px">Imocena<span style="color:#22B8CF">.</span></td></tr>
<tr><td style="padding-top:20px;font-family:Georgia,serif;font-size:24px;line-height:1.3">O vídeo do seu imóvel está pronto.</td></tr>
<tr><td style="padding-top:14px;font-size:16px;line-height:1.6">Olá{{ $nome ? ' '.$nome : '' }}, o reel que pediu na Imocena já está feito: vertical, com narração em português de Portugal e o seu contacto no fim. Pronto para Reels, TikTok e WhatsApp.</td></tr>
<tr><td style="padding-top:24px"><a href="{{ $descarregar }}" style="display:inline-block;background:#F2665A;color:#0E1626;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:999px">Descarregar o vídeo (MP4) →</a></td></tr>
<tr><td style="padding-top:12px;font-size:14px;line-height:1.6"><a href="{{ $video }}" style="color:#0E7C93">Ver o vídeo no browser</a></td></tr>
@if (! $limpo)
<tr><td style="padding-top:28px;border-top:1px solid #E6E1D6"></td></tr>
<tr><td style="font-size:13px;letter-spacing:.2em;color:#0E7C93;font-weight:bold">OFERTA DE LANÇAMENTO</td></tr>
<tr><td style="padding-top:8px;font-size:16px;line-height:1.6">Este vídeo leva a marca d’água Imocena. A versão limpa, sem marca d’água, fica por <strong>10 €</strong> (IVA incluído). Chega-lhe na hora, no mesmo link.</td></tr>
@if ($oferta)
<tr><td style="padding-top:16px"><a href="{{ $oferta }}" style="display:inline-block;background:#0E1626;color:#F4F1EA;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:999px">Quero a versão limpa · 10 €</a></td></tr>
@endif
@if ($pack)
<tr><td style="padding-top:10px;font-size:14px;line-height:1.6">Tem mais imóveis? <a href="{{ $pack }}" style="color:#0E7C93">Pack de 10 vídeos: 10 € por vídeo (100 €)</a>, sem marca d’água, válido 12 meses.</td></tr>
@endif
@if ($convite)
<tr><td style="padding-top:22px;font-size:14px;line-height:1.6;color:#3A4456">Ou grátis: envie <a href="{{ $convite }}" style="color:#0E7C93">este link</a> a um colega consultor. Quando ele fizer o vídeo grátis dele, enviamos-lhe a versão limpa do seu.</td></tr>
@endif
@endif
<tr><td style="padding-top:28px;font-size:13px;line-height:1.6;color:#5A6474">Recebeu este email porque pediu um vídeo em imocena.com. É o único aviso sobre este vídeo.</td></tr>
</table></td></tr></table></body></html>
