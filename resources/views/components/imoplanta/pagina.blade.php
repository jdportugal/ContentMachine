@props(['direita' => null])
{{-- The public pages' frame (plans page, sign-in): the navy top bar, a light page, the help footer. --}}
<div class="min-h-screen flex flex-col bg-[#F6F4EF] text-navy font-sans">
    <header class="bg-navy">
        <div class="max-w-[1120px] mx-auto px-5 lg:px-8 py-4 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="font-display text-[30px] font-semibold text-ivory">Imocena</a>
            <div class="text-sm font-semibold text-ivory">{{ $direita }}</div>
        </div>
    </header>
    <main class="flex-1 w-full max-w-[1120px] mx-auto px-5 lg:px-8 pt-7 pb-10 lg:pt-16 lg:pb-20">
        {{ $slot }}
    </main>
    <footer class="w-full max-w-[1120px] mx-auto px-5 lg:px-8 py-5 border-t border-[#E4E1DA] text-center text-xs text-[#4A5263]">
        Precisa de ajuda? Envie-nos um email para <a href="mailto:info@imocena.com" class="font-semibold text-navy underline-offset-2 hover:underline">info@imocena.com</a>
    </footer>
</div>
