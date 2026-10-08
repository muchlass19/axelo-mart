<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;

// Dipakai entrypoint Docker saat RUN_SEEDER=true: seed data dummy hanya jika database masih kosong.
Artisan::command('app:seed-demo {--force : Tetap seed walau sudah ada user}', function () {
    if (User::query()->exists() && ! $this->option('force')) {
        $this->info('[axelo] Database sudah berisi data, seeding dilewati.');

        return;
    }

    $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
    $this->info('[axelo] Data dummy berhasil di-seed.');
})->purpose('Seed data dummy Axelo Mart jika database masih kosong');
