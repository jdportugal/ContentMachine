<!DOCTYPE html>
<html lang="pt-PT">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>{{ $assunto }}</title></head>
<body style="margin:0;padding:24px 12px;background:#F2F1EE;font-family:Helvetica,Arial,sans-serif;color:#0E1626">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#FFFFFF;border:1px solid #E4E1DA;border-radius:16px;overflow:hidden">
    <tr><td style="background:#0E1626;border-bottom:3px solid #F06052;padding:20px 28px;font-family:Georgia,serif;font-size:28px;color:#F4F1EA">Imocena</td></tr>
    <tr><td style="padding:28px">
        <h1 style="margin:0 0 14px;font-family:Georgia,serif;font-size:26px;font-weight:normal;line-height:1.2">{{ $titulo }}</h1>
        <p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#3A4252">{{ $texto }}</p>
        <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="border-radius:999px;background:#F06052">
            <a href="{{ $url }}" style="display:inline-block;padding:15px 30px;font-size:15px;font-weight:bold;color:#0E1626;text-decoration:none">{{ $botao }}</a>
        </td></tr></table>
        @if ($nota)<p style="margin:22px 0 0;font-size:12px;line-height:1.6;color:#4A5263">{{ $nota }}</p>@endif
    </td></tr>
    <tr><td style="padding:16px 28px;border-top:1px solid #E4E1DA;font-size:12px;color:#4A5263">Precisa de ajuda? Envie-nos um email para <a href="mailto:info@imocena.com" style="color:#0E1626">info@imocena.com</a> · Imocena, feito em Portugal</td></tr>
</table>
</td></tr></table>
</body>
</html>
