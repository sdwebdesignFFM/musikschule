<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Musikschule Frankfurt' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#3D8BC9',
                            dark: '#2C6FA0',
                            light: '#E8F1FA',
                        },
                        navy: {
                            DEFAULT: '#2C4A6B',
                            dark: '#1E3A5F',
                            light: '#1A3D5C',
                        },
                        muted: '#7B8FA0',
                        subtle: '#5A7A99',
                        'muted-light': '#9EADB9',
                        border: '#E8ECF1',
                        'border-input': '#DCE3EB',
                        card: '#F7F9FC',
                        'field-disabled': '#F3F6F9',
                    }
                }
            }
        }
    </script>
    <style>
        /*
         * TODO: Uncomment when Futura + Filson Pro font files (.woff2) are placed in /public/fonts/
         * Custom font-face declarations:
         *
         * @font-face {
         *     font-family: 'Futura';
         *     src: url('/fonts/futura.woff2') format('woff2');
         *     font-weight: 400;
         *     font-style: normal;
         *     font-display: swap;
         * }
         * @font-face {
         *     font-family: 'Futura';
         *     src: url('/fonts/futura-bold.woff2') format('woff2');
         *     font-weight: 700;
         *     font-style: normal;
         *     font-display: swap;
         * }
         * @font-face {
         *     font-family: 'Filson Pro';
         *     src: url('/fonts/filson-pro-bold.woff2') format('woff2');
         *     font-weight: 700;
         *     font-style: normal;
         *     font-display: swap;
         * }
         */

        body { font-family: 'Futura', 'Century Gothic', 'CenturyGothic', sans-serif; }
        h1, h2, h3, h4, h5, h6 { font-family: 'Filson Pro', 'Century Gothic', 'CenturyGothic', sans-serif; }
        .modal-backdrop { transition: opacity 0.2s; }
        .modal-content { transition: transform 0.2s, opacity 0.2s; }
        .prose-modal h2 { font-size: 1.25rem; font-weight: 700; color: #2C4A6B; margin-top: 1.25rem; margin-bottom: 0.5rem; }
        .prose-modal h3 { font-size: 1.1rem; font-weight: 700; color: #2C4A6B; margin-top: 1rem; margin-bottom: 0.4rem; }
        .prose-modal h4 { font-size: 0.95rem; font-weight: 600; color: #2C4A6B; margin-top: 0.75rem; margin-bottom: 0.3rem; }
        .prose-modal p { margin-bottom: 0.6rem; }
        .prose-modal a { color: #3D8BC9; text-decoration: underline; }
        .prose-modal a:hover { color: #2C6FA0; }
        .prose-modal ul, .prose-modal ol { margin-left: 1.25rem; margin-bottom: 0.6rem; }
        .prose-modal li { margin-bottom: 0.25rem; }
    </style>
</head>
<body class="bg-white min-h-screen flex flex-col">
    {{-- Header --}}
    <header class="bg-white border-b border-border flex items-center justify-center h-20 shrink-0">
        <img src="{{ asset('images/logo.jpg') }}" alt="Musikschule Frankfurt" class="h-14">
    </header>

    {{-- Main Content --}}
    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-navy-light text-white py-6 px-8 shrink-0">
        <div class="max-w-4xl mx-auto">
            <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm">
                <span class="font-semibold text-[13px]">Musikschule Frankfurt e.&thinsp;V.</span>
                <span class="text-[#6B9CC8]">|</span>
                <span class="text-[#B0CBE4] text-xs">Berliner Straße 51, 60311 Frankfurt</span>
                <span class="text-[#6B9CC8]">|</span>
                <span class="text-[#B0CBE4] text-xs">www.musikschule-frankfurt.de</span>
            </div>
            <div class="flex items-center justify-center gap-4 mt-3 text-[11px] text-[#6B9CC8]">
                <button onclick="openModal('impressumModal')" class="hover:underline cursor-pointer">Impressum</button>
                <button onclick="openModal('datenschutzModal')" class="hover:underline cursor-pointer">Datenschutz</button>
                <button onclick="openModal('kontaktModal')" class="hover:underline cursor-pointer">Kontakt</button>
            </div>
        </div>
    </footer>

    @php
        $footerPages = \App\Models\Page::whereIn('slug', ['impressum', 'datenschutz', 'kontakt'])->get()->keyBy('slug');
    @endphp

    @foreach($footerPages as $slug => $page)
        @include('landing.partials.modal', [
            'modalId' => $slug . 'Modal',
            'title' => $page->title,
            'content' => $page->content,
        ])
    @endforeach

    {{-- Document Modal (used by landing pages) --}}
    <div id="documentModal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0 bg-black/50" onclick="closeModal('documentModal')"></div>
        <div class="modal-content relative z-10 flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h2 id="documentModalTitle" class="text-navy font-bold text-lg"></h2>
                    <button onclick="closeModal('documentModal')" class="text-gray-400 hover:text-gray-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="flex-1 overflow-hidden">
                    <iframe id="documentModalFrame" class="w-full h-full min-h-[70vh]" src=""></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
            document.body.style.overflow = '';
            const frame = document.getElementById('documentModalFrame');
            if (frame) frame.src = '';
        }
        function openDocumentModal(url, title) {
            document.getElementById('documentModalTitle').textContent = title;
            document.getElementById('documentModalFrame').src = url;
            openModal('documentModal');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.fixed:not(.hidden)').forEach(function(m) {
                    closeModal(m.id);
                });
            }
        });
    </script>
</body>
</html>
