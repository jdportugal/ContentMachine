<!doctype html>
<html lang="pt"><body style="margin:0;background:#F4F1EA;font-family:Helvetica,Arial,sans-serif;color:#0E1626">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#fff;border-radius:12px;padding:32px">
<tr><td style="font-family:Georgia,serif;font-size:28px">Imocena<span style="color:#22B8CF">.</span></td></tr>
<tr><td style="padding-top:20px;font-family:Georgia,serif;font-size:24px;line-height:1.3">O seu vídeo pode ficar sem marca d’água, sem pagar.</td></tr>
<tr><td style="padding-top:14px;font-size:16px;line-height:1.6">Olá{{ $nome ? ' '.$nome : '' }}, há dois dias fez na Imocena o <a href="{{ $video }}" style="color:#0E7C93">vídeo do seu imóvel</a>. Ainda tem a marca d’água.</td></tr>
<tr><td style="padding-top:12px;font-size:16px;line-height:1.6">Envie o seu link a um colega consultor. Quando ele fizer o vídeo grátis dele, enviamos-lhe a versão do seu <strong>sem marca d’água</strong>, para este email. Ele também ganha um vídeo grátis.</td></tr>
<tr><td style="padding-top:24px"><a href="{{ $whatsapp }}" style="display:inline-block;background:#F2665A;color:#0E1626;text-decoration:none;font-weight:bold;padding:14px 24px;border-radius:999px">Enviar a um colega pelo WhatsApp →</a></td></tr>
<tr><td style="padding-top:14px;font-size:14px;line-height:1.6;color:#3A4456">Ou copie e envie o seu link:<br><a href="{{ $link }}" style="color:#0E7C93;word-break:break-all">{{ $link }}</a></td></tr>
@if ($oferta)
<tr><td style="padding-top:24px;border-top:1px solid #E6E1D6"></td></tr>
<tr><td style="font-size:14px;line-height:1.6;color:#3A4456">Prefere não esperar? <a href="{{ $oferta }}" style="color:#0E7C93">A versão limpa fica por 10 €</a> (IVA incluído) e chega-lhe na hora.</td></tr>
@endif
<tr><td style="padding-top:24px;font-size:13px;line-height:1.6;color:#5A6474">Recebe este email porque aceitou receber novidades da Imocena ao pedir o seu vídeo. <a href="{{ $cancelar }}" style="color:#5A6474">Deixar de receber estes emails</a>.</td></tr>
</table></td></tr></table></body></html>
