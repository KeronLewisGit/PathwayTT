{{-- In-app notifications. Livewire components dispatch('notify', message:, tone:); Alpine renders them here. --}}
<div
    x-data="{
        toasts: [],
        push(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message: detail.message ?? '', tone: detail.tone ?? 'info' });
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id) }, detail.tone === 'celebrate' ? 7000 : 4000);
        }
    }"
    @notify.window="push($event.detail)"
    class="pointer-events-none fixed bottom-4 right-4 z-50 flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="translate-y-2 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            class="pointer-events-auto card border-l-4 p-3 text-sm text-gray-800 shadow-lg"
            :class="toast.tone === 'celebrate' ? 'border-accent-400 bg-accent-50' : (toast.tone === 'success' ? 'border-green-500' : 'border-brand-500')"
        >
            <div class="flex items-start gap-2">
                <span x-show="toast.tone === 'celebrate'" aria-hidden="true">🎉</span>
                <span x-text="toast.message" class="flex-1"></span>
                <button type="button" @click="toasts = toasts.filter(t => t.id !== toast.id)" class="text-gray-400 hover:text-gray-600" aria-label="Dismiss">×</button>
            </div>
        </div>
    </template>
</div>
