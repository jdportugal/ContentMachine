<!DOCTYPE html>
<html lang="pt-PT" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Imocena' }}</title>
    <meta name="description" content="Cole o link do anúncio e receba um reel do seu imóvel com narração em português de Portugal. O primeiro vídeo é grátis.">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">

    <meta name="facebook-domain-verification" content="tkv24bfueeoyv54kf287j67bq3dal4">

    @php
        $pixel = (string) config('contentmachine.meta_pixel');
        $ga4 = (string) config('contentmachine.ga4');
        $medicao = $pixel !== '' || $ga4 !== '';
    @endphp
    @if ($medicao)
    {{-- Measurement (Meta Pixel, Google Analytics) loads only after "Aceitar" on the cookie banner.
         Lead: a listing link accepted (Landing::pedir). Purchase: the thank-you page, once. --}}
    <script>
    window.imocenaMedicao = (function () {
        const PIXEL = @js($pixel), GA4 = @js($ga4);
        let ativo = false;
        const fila = [];
        function carregar() {
            if (ativo) return; ativo = true;
            if (PIXEL) {
                !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
                fbq('init', PIXEL); fbq('track', 'PageView');
            }
            if (GA4) {
                const t = document.createElement('script'); t.async = true; t.src = 'https://www.googletagmanager.com/gtag/js?id=' + GA4; document.head.appendChild(t);
                window.dataLayer = window.dataLayer || []; window.gtag = function(){ dataLayer.push(arguments); };
                gtag('js', new Date());
                gtag('consent', 'default', { ad_storage: 'granted', analytics_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
                gtag('config', GA4);
            }
            fila.splice(0).forEach(fn => fn());
        }
        // Each event runs now if measurement is on, or when the visitor accepts.
        function quando(fn) { ativo ? fn() : fila.push(fn); }
        return {
            carregar, quando,
            lead(id) { quando(() => { window.fbq && fbq('track', 'Lead', {}, { eventID: 'lead-' + id }); window.gtag && gtag('event', 'generate_lead', { lead_id: 'lead-' + id }); }); },
            compra(c) { quando(() => {
                window.fbq && fbq('track', 'Purchase', { value: c.valor, currency: c.moeda, num_items: c.videos, content_type: 'product' }, { eventID: c.id });
                window.gtag && gtag('event', 'purchase', { transaction_id: c.id, value: c.valor, currency: c.moeda, items: [{ item_id: 'videos-' + c.videos, item_name: c.videos > 1 ? 'Pack ' + c.videos + ' vídeos' : 'Vídeo', quantity: 1, price: c.valor }] });
            }); },
        };
    })();
    if (document.cookie.split('; ').includes('imocena_cookies=sim')) imocenaMedicao.carregar();
    document.addEventListener('livewire:init', () => Livewire.on('lead-criado', ({ id }) => imocenaMedicao.lead(id)));
    </script>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-navy text-ivory font-sans antialiased">
    {{ $slot }}
    @if ($medicao)
        <x-cookies />
    @endif
    @livewireScripts
</body>
</html>
