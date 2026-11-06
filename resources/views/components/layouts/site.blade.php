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

    @if (config('contentmachine.meta_pixel'))
    {{-- Meta Pixel: PageView on load; Lead when a listing link is accepted (see Landing::pedir). --}}
    <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '{{ config('contentmachine.meta_pixel') }}');
    fbq('track', 'PageView');
    document.addEventListener('livewire:init', () => {
        Livewire.on('lead-criado', ({ id }) => fbq('track', 'Lead', {}, { eventID: 'lead-' + id }));
    });
    </script>
    <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ config('contentmachine.meta_pixel') }}&ev=PageView&noscript=1"></noscript>
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-navy text-ivory font-sans antialiased">
    {{ $slot }}
    @livewireScripts
</body>
</html>
