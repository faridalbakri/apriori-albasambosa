<div
    x-data="{
        visible: false,
        init() {
            if (!localStorage.getItem('cookie_consent')) {
                this.visible = true
            }
        },
        choose(value) {
            localStorage.setItem('cookie_consent', value)
            this.visible = false
        }
    }"
    x-show="visible"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="translate-y-full opacity-0"
    x-transition:enter-end="translate-y-0 opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="translate-y-0 opacity-100"
    x-transition:leave-end="translate-y-full opacity-0"
    class="fixed bottom-0 inset-x-0 z-50"
    role="dialog"
    aria-label="Pemberitahuan Cookie"
    style="display: none"
>
    <div class="bg-white border-t border-border shadow-[0_-4px_20px_rgba(0,0,0,0.08)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                {{-- Text --}}
                <div class="flex-1 text-sm text-foreground/80">
                    <p>
                        Kami menggunakan cookie untuk meningkatkan pengalaman Anda.
                        Cookie esensial diperlukan agar situs berfungsi. Cookie fungsional membantu kami memahami penggunaan situs.
                        <a href="{{ route('pages.cookie') }}" class="text-primary hover:underline font-medium">Pelajari lebih lanjut</a>
                    </p>
                </div>

                {{-- Buttons --}}
                <div class="flex items-center gap-3 shrink-0">
                    <button
                        @click="choose('rejected')"
                        class="px-4 py-2 text-sm font-medium border-2 border-border text-foreground rounded-lg hover:bg-muted transition-colors duration-200 cursor-pointer"
                    >
                        Tolak
                    </button>
                    <button
                        @click="choose('accepted')"
                        class="px-4 py-2 text-sm font-medium text-white bg-accent rounded-lg hover:opacity-90 transition-opacity duration-200 cursor-pointer"
                    >
                        Setuju
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
