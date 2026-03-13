<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RecordingSchedule;
use App\Models\Setting;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;
use Illuminate\Support\Carbon;

class TriggerScheduledRecordings extends Command
{
    protected $signature = 'schedules:trigger-recordings';
    protected $description = 'Trigger scheduled audio recordings for devices';

    // Lock file path – pakai storage/app agar pasti bisa ditulis
    private string $lockFile;

    public function __construct()
    {
        parent::__construct();
        $this->lockFile = storage_path('app/schedule-trigger.lock');
    }

    public function handle()
    {
        // ── Overlap prevention via lock file ─────────────────────────────
        if (file_exists($this->lockFile)) {
            $pid = (int) file_get_contents($this->lockFile);
            // Cek apakah proses dengan PID tersebut masih berjalan
            if ($pid > 0 && file_exists("/proc/{$pid}")) {
                $this->info("Skipping: previous run (PID {$pid}) still active.");
                return;
            }
            // PID sudah tidak aktif, hapus lock lama
            unlink($this->lockFile);
        }

        // Tulis PID proses ini ke lock file
        file_put_contents($this->lockFile, getmypid());

        try {
            $this->runSchedules();
        } finally {
            // Selalu hapus lock file setelah selesai
            if (file_exists($this->lockFile)) {
                unlink($this->lockFile);
            }
        }
    }

    private function runSchedules()
    {
        $now = Carbon::now();

        // 1. Mark expired schedules as completed
        RecordingSchedule::where('status', '!=', 'completed')
            ->where('status', '!=', 'cancelled')
            ->where('end_time', '<', $now)
            ->update(['status' => 'completed']);

        // 2. Fetch schedules that should run now
        $schedules = RecordingSchedule::where(function ($q) {
                $q->where('status', 'pending')
                  ->orWhere('status', 'active');
            })
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->with('device')
            ->get();

        $this->info("Found {$schedules->count()} active schedules.");

        foreach ($schedules as $schedule) {
            // Check interval
            if ($schedule->last_run_at) {
                $nextRun = $schedule->last_run_at->copy()->addMinutes($schedule->interval_minutes);
                if ($now->lessThan($nextRun)) {
                    continue;
                }
            }

            $this->triggerRecording($schedule);
        }
    }

    private function triggerRecording(RecordingSchedule $schedule)
    {
        if (!$schedule->device || $schedule->device->status !== 'online') {
            $this->warn("Device {$schedule->device_id} is offline or missing. Skipping.");
            return;
        }

        $deviceId = $schedule->device->device_id;
        $topic    = "audio/{$deviceId}/command";

        try {
            // ── 1. Send START RECORDING ───────────────────────────────────
            $mqtt = $this->connectMqtt();

            $mqtt->publish($topic, json_encode([
                'action'    => 'start_recording',
                'timestamp' => now()->timestamp,
                'device_id' => $deviceId,
            ]), 0);

            $mqtt->disconnect();

            $this->info("▶ START sent → {$deviceId} (schedule #{$schedule->id})");

            // Mark schedule as active
            $schedule->update([
                'last_run_at' => now(),
                'status'      => 'active',
            ]);

            // ── 2. Wait for recording duration ────────────────────────────
            $duration = (int) $schedule->duration_seconds;
            $this->info("  Waiting {$duration}s...");
            sleep($duration);

            // ── 3. Send STOP RECORDING ────────────────────────────────────
            $mqtt = $this->connectMqtt();

            $mqtt->publish($topic, json_encode([
                'action'    => 'stop_recording',
                'timestamp' => now()->timestamp,
                'device_id' => $deviceId,
            ]), 0);

            $mqtt->disconnect();

            $this->info("⏹ STOP sent  → {$deviceId} (schedule #{$schedule->id})");

        } catch (\Exception $e) {
            $this->error("Failed to trigger schedule {$schedule->id}: " . $e->getMessage());
        }
    }

    private function connectMqtt(): MqttClient
    {
        $host     = Setting::get('mqtt_host', env('MQTT_HOST'));
        $port     = (int) Setting::get('mqtt_port', env('MQTT_PORT', 8883));
        $username = Setting::get('mqtt_username', env('MQTT_USERNAME'));
        $password = Setting::get('mqtt_password', env('MQTT_PASSWORD'));

        $client = new MqttClient($host, $port, 'laravel_scheduler_' . uniqid());

        $client->connect(
            (new ConnectionSettings)
                ->setUsername($username)
                ->setPassword($password)
                ->setUseTls(true)
                ->setTlsSelfSignedAllowed(true)
                ->setTlsVerifyPeer(false)
                ->setTlsVerifyPeerName(false),
            true
        );

        return $client;
    }
}
