<div id="{{ $modalId }}" class="fixed inset-0 z-50 hidden">
    <div class="modal-backdrop absolute inset-0 bg-black/50" onclick="closeModal('{{ $modalId }}')"></div>
    <div class="modal-content relative z-10 flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 shrink-0">
                <h2 class="text-navy font-bold text-lg">{{ $title }}</h2>
                <button onclick="closeModal('{{ $modalId }}')" class="text-gray-400 hover:text-gray-600 p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="prose-modal px-6 py-5 overflow-y-auto text-sm text-gray-700 leading-relaxed space-y-4">
                {!! $content !!}
            </div>
        </div>
    </div>
</div>
