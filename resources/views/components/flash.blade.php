<div
    x-data="{ show: false, message: '', type: 'success' }"
    x-on:notify.window="show = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => show = false, 3000)"
    class="fixed bottom-4 right-4 z-50 pointer-events-none">
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        :class="type === 'success' ? 'bg-success' : 'bg-destructive'"
        class="text-white px-5 py-3 rounded-xl shadow-lg text-sm font-semibold pointer-events-auto"
        style="display: none;">
        <span x-text="message"></span>
    </div>
</div>
