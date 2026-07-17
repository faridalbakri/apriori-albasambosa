<x-layouts.app>
    @section('title', 'Kebijakan Cookie — AlbaSambosa')

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        {{-- Header --}}
        <h1 class="text-3xl md:text-4xl font-bold font-[family-name:var(--font-heading)] text-[var(--color-foreground)] mb-4">
            Kebijakan Cookie
        </h1>
        <p class="text-[var(--color-foreground)]/60 mb-8">Terakhir diperbarui: 10 Juli 2026</p>

        {{-- Content --}}
        <div class="prose prose-sm max-w-none space-y-6 text-[var(--color-foreground)]/80">
            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">1. Apa Itu Cookie?</h2>
                <p>
                    Cookie adalah file teks kecil yang disimpan di perangkat Anda (komputer, tablet, atau ponsel)
                    saat Anda mengunjungi situs web. Cookie membantu situs web mengingat preferensi dan aktivitas
                    Anda untuk meningkatkan pengalaman browsing.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">2. Jenis Cookie yang Kami Gunakan</h2>
                <p class="mb-2">AlbaSambosa menggunakan dua kategori cookie:</p>

                <h3 class="text-lg font-medium text-[var(--color-foreground)] mt-4 mb-2">2.1 Cookie Esensial (Wajib)</h3>
                <p>
                    Cookie ini diperlukan agar situs web berfungsi dengan benar. Cookie ini tidak dapat dinonaktifkan.
                    Cookie esensial meliputi:
                </p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li><strong>Session Cookie</strong> — mengelola sesi login dan keranjang belanja Anda selama berkunjung</li>
                    <li><strong>CSRF Token</strong> — melindungi Anda dari serangan keamanan Cross-Site Request Forgery</li>
                </ul>

                <h3 class="text-lg font-medium text-[var(--color-foreground)] mt-4 mb-2">2.2 Cookie Fungsional (Opsional)</h3>
                <p>
                    Cookie ini membantu kami memahami bagaimana pengunjung berinteraksi dengan situs web
                    melalui data agregat dan anonim. Cookie ini membantu kami meningkatkan layanan:
                </p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li><strong>Preferensi Tampilan</strong> — mengingat preferensi bahasa dan tampilan Anda</li>
                    <li><strong>Keranjang Persisten</strong> — menyimpan isi keranjang Anda antar kunjungan</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">3. Cookie Pihak Ketiga</h2>
                <p>
                    Kami <strong>tidak</strong> menggunakan cookie pelacakan pihak ketiga (seperti Google Analytics,
                    Facebook Pixel, atau sejenisnya) di situs AlbaSambosa. Satu-satunya cookie yang berasal dari
                    domain pihak ketiga adalah cookie yang diperlukan untuk pemrosesan pembayaran melalui Midtrans
                    saat Anda memulai checkout.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">4. Masa Berlaku Cookie</h2>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li><strong>Cookie sesi</strong> — dihapus otomatis saat Anda menutup browser</li>
                    <li><strong>Cookie fungsional</strong> — bertahan maksimal 30 hari sejak kunjungan terakhir</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">5. Pilihan Anda</h2>
                <p>
                    Saat pertama kali mengunjungi AlbaSambosa, Anda akan melihat banner persetujuan cookie.
                    Anda dapat memilih:
                </p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li><strong>Setuju</strong> — mengizinkan semua cookie (esensial + fungsional)</li>
                    <li><strong>Tolak</strong> — hanya cookie esensial yang akan digunakan</li>
                </ul>
                <p class="mt-2">
                    Pilihan Anda disimpan di browser dan dapat diubah kapan saja dengan menghapus cookie browser
                    Anda dan mengunjungi kembali situs ini. Anda juga dapat mengatur browser Anda untuk menolak
                    semua cookie, namun beberapa fitur situs mungkin tidak berfungsi dengan baik.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">6. Dasar Hukum</h2>
                <p>
                    Penggunaan cookie di situs ini mematuhi:
                </p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>Undang-Undang No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP)</li>
                    <li>Peraturan Pemerintah terkait penyelenggaraan sistem elektronik</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">7. Hubungi Kami</h2>
                <p>
                    Jika Anda memiliki pertanyaan tentang kebijakan cookie ini, silakan hubungi kami di:
                    <a href="mailto:admin@albasambosa.com" class="text-[var(--color-primary)] hover:underline">admin@albasambosa.com</a>
                    atau melalui WhatsApp Admin.
                </p>
            </section>
        </div>
    </div>
</x-layouts.app>
