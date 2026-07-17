<x-layouts.app>
    @section('title', 'Kebijakan Privasi — AlbaSambosa')

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        {{-- Header --}}
        <h1 class="text-3xl md:text-4xl font-bold font-[family-name:var(--font-heading)] text-[var(--color-foreground)] mb-4">
            Kebijakan Privasi
        </h1>
        <p class="text-[var(--color-foreground)]/60 mb-8">Terakhir diperbarui: 10 Juli 2026</p>

        {{-- Content --}}
        <div class="space-y-6 text-[var(--color-foreground)]/80">
            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">1. Informasi Umum</h2>
                <p>
                    AlbaSambosa ("kami", "situs") berkomitmen untuk melindungi privasi dan data pribadi Anda.
                    Kebijakan ini menjelaskan bagaimana kami mengumpulkan, menggunakan, menyimpan, dan melindungi
                    informasi Anda sesuai dengan Undang-Undang No. 27 Tahun 2022 tentang Pelindungan Data Pribadi (UU PDP).
                </p>
                <p class="mt-2">
                    Dengan menggunakan situs AlbaSambosa, Anda menyetujui pengumpulan dan penggunaan informasi
                    sesuai dengan kebijakan ini.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">2. Data yang Kami Kumpulkan</h2>
                <p class="mb-2">Kami mengumpulkan data berikut untuk memproses pesanan dan memberikan layanan:</p>

                <h3 class="text-lg font-medium text-[var(--color-foreground)] mt-4 mb-2">2.1 Data Akun (Pengguna Terdaftar)</h3>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Nama lengkap</li>
                    <li>Alamat email</li>
                    <li>Nomor telepon (opsional)</li>
                    <li>Alamat pengiriman (buku alamat)</li>
                </ul>

                <h3 class="text-lg font-medium text-[var(--color-foreground)] mt-4 mb-2">2.2 Data Pesanan (Semua Pengguna)</h3>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Nomor telepon (wajib untuk guest checkout)</li>
                    <li>Alamat pengiriman (untuk pesanan delivery)</li>
                    <li>Riwayat pembelian dan detail pesanan</li>
                    <li>Metode pembayaran yang dipilih</li>
                </ul>

                <h3 class="text-lg font-medium text-[var(--color-foreground)] mt-4 mb-2">2.3 Data Teknis (Otomatis)</h3>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Alamat IP</li>
                    <li>Jenis browser dan perangkat</li>
                    <li>Halaman yang dikunjungi dan durasi kunjungan</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">3. Tujuan Penggunaan Data</h2>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Memproses dan mengirimkan pesanan Anda</li>
                    <li>Mengirimkan konfirmasi pesanan dan notifikasi pengiriman</li>
                    <li>Menyediakan layanan pelanggan dan dukungan teknis</li>
                    <li>Memenuhi kewajiban hukum dan peraturan yang berlaku</li>
                    <li>Meningkatkan pengalaman pengguna di situs kami</li>
                    <li>Mencegah penipuan dan penyalahgunaan</li>
                </ul>
                <p class="mt-2">
                    Kami <strong>tidak</strong> menjual, menyewakan, atau membagikan data pribadi Anda kepada
                    pihak ketiga untuk tujuan pemasaran.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">4. Pihak Ketiga</h2>
                <p>Kami membagikan data Anda hanya kepada mitra yang diperlukan untuk memproses pesanan:</p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>
                        <strong>Midtrans</strong> — pemroses pembayaran. Menerima data pesanan dan jumlah pembayaran.
                        <a href="https://midtrans.com/id/kebijakan-privasi" target="_blank" rel="noopener noreferrer" class="text-[var(--color-primary)] hover:underline">Kebijakan Privasi Midtrans</a>
                    </li>
                    <li>
                        <strong>Biteship</strong> — agregator pengiriman. Menerima data alamat dan kontak untuk pengiriman.
                        <a href="https://biteship.com/id/privacy" target="_blank" rel="noopener noreferrer" class="text-[var(--color-primary)] hover:underline">Kebijakan Privasi Biteship</a>
                    </li>
                    <li>
                        <strong>Twilio</strong> — penyedia notifikasi WhatsApp. Menerima nomor telepon untuk pengiriman pesan.
                    </li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">5. Penyimpanan & Penghapusan Data</h2>
                <p>
                    Kami menyimpan data Anda hanya selama diperlukan untuk tujuan yang dijelaskan dalam kebijakan ini:
                </p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li><strong>Pengguna Tamu (Guest):</strong> 24 bulan sejak pesanan terakhir</li>
                    <li><strong>Pengguna Terdaftar:</strong> 36 bulan sejak aktivitas terakhir</li>
                </ul>
                <p class="mt-2">
                    Setelah periode tersebut, data identitas pribadi Anda akan dianonimkan secara otomatis.
                    Data transaksi tetap disimpan dalam bentuk agregat untuk keperluan statistik dan akuntansi.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">6. Hak Anda (UU PDP)</h2>
                <p>Berdasarkan UU PDP, Anda memiliki hak untuk:</p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>Mengakses data pribadi Anda yang kami simpan</li>
                    <li>Memperbaiki data yang tidak akurat</li>
                    <li>Menghapus data pribadi Anda ("Hak Dilupakan")</li>
                    <li>Menarik persetujuan pemrosesan data</li>
                    <li>Mengajukan keberatan atas pemrosesan data</li>
                </ul>
                <p class="mt-2">
                    Untuk menggunakan hak-hak tersebut, kirim permintaan ke
                    <a href="mailto:admin@albasambosa.com" class="text-[var(--color-primary)] hover:underline">admin@albasambosa.com</a>.
                    Kami akan merespons dalam waktu maksimal 7 hari kerja.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">7. Keamanan Data</h2>
                <p>
                    Kami menerapkan langkah-langkah keamanan teknis dan organisasional untuk melindungi data Anda:
                </p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>Enkripsi data dalam transmisi (HTTPS/TLS)</li>
                    <li>Pembatasan akses data hanya untuk personel yang berwenang</li>
                    <li>Pemantauan keamanan berkala</li>
                    <li>Penyimpanan kredensial yang di-hash dan di-salt</li>
                </ul>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">8. Perubahan Kebijakan</h2>
                <p>
                    Kami dapat memperbarui kebijakan privasi ini dari waktu ke waktu. Perubahan signifikan
                    akan diumumkan melalui banner di situs web atau notifikasi email (untuk pengguna terdaftar).
                    Tanggal "Terakhir diperbarui" di bagian atas halaman ini menunjukkan revisi terbaru.
                </p>
            </section>

            <section>
                <h2 class="text-xl font-semibold text-[var(--color-foreground)] mb-3">9. Kontak</h2>
                <p>
                    Untuk pertanyaan, keberatan, atau permintaan terkait privasi data Anda, hubungi kami di:
                </p>
                <ul class="list-disc pl-5 mt-2 space-y-1">
                    <li>Email: <a href="mailto:admin@albasambosa.com" class="text-[var(--color-primary)] hover:underline">admin@albasambosa.com</a></li>
                    <li>WhatsApp Admin (nomor tertera di halaman utama)</li>
                </ul>
            </section>
        </div>
    </div>
</x-layouts.app>
