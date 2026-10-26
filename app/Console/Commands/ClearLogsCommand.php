<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'log:clear {--all : Bersihkan seluruh file log di folder storage/logs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengosongkan atau menghapus file laravel.log (dan log lainnya) untuk menghemat storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $logPath = storage_path('logs');
        $clearAll = $this->option('all');

        $this->info("🧹 Memulai pembersihan log pada: " . now()->format('Y-m-d H:i:s'));

        if (!File::isDirectory($logPath)) {
            $this->warn("⚠️ Folder log tidak ditemukan: {$logPath}");
            return Command::SUCCESS;
        }

        if ($clearAll) {
            // Bersihkan semua file .log di storage/logs
            $files = File::glob("{$logPath}/*.log");
            foreach ($files as $file) {
                try {
                    File::put($file, '');
                    $this->info("✅ Berhasil mengosongkan: " . basename($file));
                } catch (\Throwable $e) {
                    $this->warn("⚠️ Gagal mengosongkan " . basename($file) . ": " . $e->getMessage());
                }
            }
        } else {
            // Hapus/kosongkan hanya laravel.log
            $laravelLog = "{$logPath}/laravel.log";
            if (File::exists($laravelLog)) {
                try {
                    File::put($laravelLog, '');
                    $this->info("✅ File laravel.log berhasil dikosongkan.");
                } catch (\Throwable $e) {
                    $this->error("❌ Gagal mengosongkan laravel.log: " . $e->getMessage());
                    return Command::FAILURE;
                }
            } else {
                $this->info("ℹ️ File laravel.log belum ada atau sudah kosong.");
            }
        }

        $this->info("🎉 Selesai membersihkan log!");
        return Command::SUCCESS;
    }
}
