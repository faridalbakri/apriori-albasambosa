<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FailedJob;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Admin user
        User::factory()->create([
            'name' => 'Admin AlbaSambosa',
            'email' => 'admin@albasambosa.com',
            'role' => 'admin',
        ]);

        // 85 verified + 15 unverified customers
        User::factory(85)->create(['role' => 'customer']);
        User::factory(15)->unverified()->create(['role' => 'customer']);

        // Kategori asli AlbaSambosa
        $categories = [
            ['name' => 'Frozen Food', 'slug' => 'frozen-food', 'order' => 1],
            ['name' => 'Makanan Matang', 'slug' => 'makanan-matang', 'order' => 2],
            ['name' => 'Minuman', 'slug' => 'minuman', 'order' => 3],
            ['name' => 'Tambahan', 'slug' => 'tambahan', 'order' => 4],
        ];
        foreach ($categories as $cat) {
            Category::create($cat);
        }

        $this->call(ProductSeeder::class);
        $this->call(AdminPickSeeder::class);
        $this->call(OrderSeeder::class);
        $this->call(AprioriTransactionSeeder::class);

        // Notification logs: WhatsApp sent + failed, Biteship failed
        NotificationLog::factory(12)->create();
        NotificationLog::factory(3)->failed()->create();
        NotificationLog::factory(3)->biteshipFailed()->create();

        // Failed jobs for testing
        FailedJob::factory(5)->create();

        // Anonymization logs for testing
        $this->call(AnonymizationLogSeeder::class);
    }
}
