<?php
/**
 * Script to seed dummy archive files for testing delete feature
 * Run with: php create_test_archives.php
 */

$storagePath = __DIR__ . '/storage/app/private/exports/logs';

// Create directory if it doesn't exist
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0755, true);
    echo "Created directory: $storagePath\n";
}

// Create 3 dummy CSV archive files
$files = [
    'logs_archive_2026-03-01_000000.csv',
    'logs_archive_2026-03-02_000000.csv',
    'logs_archive_2026-03-03_000000.csv',
];

foreach ($files as $filename) {
    $filepath = $storagePath . '/' . $filename;
    $handle = fopen($filepath, 'w');
    fputcsv($handle, ['ID', 'Device ID', 'dB SPL', 'RMS', 'Peak Freq', 'Band Low', 'Band Mid', 'Band High', 'Creation Time']);
    // Write some dummy data rows
    for ($i = 1; $i <= 5; $i++) {
        fputcsv($handle, [
            $i,
            'ESP32-TEST01',
            rand(45, 90) + (rand(0,9)/10),
            round(rand(100, 900)/10000, 4),
            rand(100, 4000),
            rand(10, 50),
            rand(10, 50),
            rand(10, 50),
            '2026-03-01 ' . sprintf('%02d:%02d:%02d', rand(0,23), rand(0,59), rand(0,59))
        ]);
    }
    fclose($handle);
    echo "Created: $filename\n";
}

echo "\nDone! Files created in: $storagePath\n";
echo "Now go to /logs/fft to test the delete feature.\n";
