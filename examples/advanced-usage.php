<?php

declare(strict_types=1);

/**
 * Advanced usage example for Rarus Echo PHP SDK
 * Demonstrates error handling, batch operations, and custom configuration
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Carbon\Carbon;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Rarus\Echo\Application\EchoApplication;
use Rarus\Echo\Core\Credentials\CredentialsBuilder;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Exception\EchoException;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;

// ============================================================================
// Advanced Configuration
// ============================================================================

// 1. Create credentials using builder
$credentials = (new CredentialsBuilder())
    ->withApiKey('your-api-key')
    ->withUserId('00000000-0000-0000-0000-000000000000')
    ->withBaseUrl('https://production-ai-ui-api.ai.rarus-cloud.ru')
    ->build();

// 2. Setup PSR-3 logger (optional, using Monolog)
$logger = new Logger('rarus-echo');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// 3. Create application with custom settings
$app = new EchoApplication(
    credentials: $credentials,
    logger: $logger,
    maxRetries: 5,        // Increase retry attempts
    timeout: 180          // 3 minutes timeout
);

// ============================================================================
// Batch File Processing with Error Handling
// ============================================================================

$files = [
    '/path/to/audio1.mp3',
    '/path/to/audio2.wav',
    '/path/to/audio3.ogg',
    '/path/to/audio4.flac',
];

$options = TranscriptionOptions::create()
    ->withTaskType(TaskType::TIMESTAMPS)
    ->withLanguage(Language::AUTO)
    ->withLowPriority()  // Use low priority for batch processing
    ->build();

$transcriptionService = $app->getTranscriptionService();
$submittedFiles = [];

echo "Processing batch of " . count($files) . " files...\n\n";

// Submit files with individual error handling
foreach ($files as $file) {
    try {
        echo "Submitting: {$file}... ";
        $result = $transcriptionService->submitTranscription([$file], $options);
        $fileId = $result->getFirstFileId();
        $submittedFiles[$file] = $fileId;
        echo "✓ Success (ID: {$fileId})\n";
    } catch (EchoException $e) {
        echo "✗ Failed: {$e->getMessage()}\n";
        continue;
    }
}

echo "\nSuccessfully submitted: " . count($submittedFiles) . "/" . count($files) . " files\n\n";

// ============================================================================
// Monitoring Transcription Progress
// ============================================================================

if (empty($submittedFiles)) {
    echo "No files to monitor\n";
    exit(1);
}

echo "Monitoring transcription progress...\n\n";

$statusService = $app->getStatusService();
$maxWaitTime = 300; // 5 minutes
$checkInterval = 10; // 10 seconds
$startTime = time();

while ((time() - $startTime) < $maxWaitTime) {
    $fileIds = array_values($submittedFiles);
    $statuses = $statusService->getStatusList($fileIds, Pagination::firstPage(perPage: 100));

    $completed = 0;
    $processing = 0;
    $waiting = 0;
    $failed = 0;

    echo "\n" . str_repeat('=', 80) . "\n";
    echo date('H:i:s') . " - Status Update:\n";
    echo str_repeat('-', 80) . "\n";

    foreach ($statuses->getResults() as $status) {
        $fileName = array_search($status->getFileId(), $submittedFiles);

        echo sprintf(
            "%-40s | %-12s | %6.2f MB | %5.1f min\n",
            basename($fileName ?: 'unknown'),
            $status->getStatus()->value,
            $status->getFileSize(),
            $status->getFileDuration()
        );

        if ($status->isSuccessful()) {
            $completed++;
        } elseif ($status->getStatus()->value === 'processing') {
            $processing++;
        } elseif ($status->getStatus()->value === 'waiting') {
            $waiting++;
        } else {
            $failed++;
        }
    }

    echo str_repeat('-', 80) . "\n";
    echo sprintf(
        "Completed: %d | Processing: %d | Waiting: %d | Failed: %d\n",
        $completed,
        $processing,
        $waiting,
        $failed
    );

    // Check if all completed or failed
    if ($completed + $failed === count($submittedFiles)) {
        echo "\n✓ All files processed!\n";
        break;
    }

    echo "\nWaiting {$checkInterval} seconds...\n";
    sleep($checkInterval);
}

// ============================================================================
// Retrieve Completed Transcriptions
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "Retrieving completed transcriptions...\n";
echo str_repeat('=', 80) . "\n\n";

foreach ($submittedFiles as $file => $fileId) {
    try {
        $transcript = $transcriptionService->getTranscript($fileId);

        if ($transcript->isSuccessful()) {
            echo "File: " . basename($file) . "\n";
            echo "Status: ✓ Success\n";
            echo "Type: {$transcript->getTaskType()->getDescription()}\n";
            echo "Result length: " . strlen($transcript->getResult()) . " characters\n";
            echo "Preview: " . substr($transcript->getResult(), 0, 100) . "...\n";
            echo str_repeat('-', 80) . "\n\n";
        } else {
            echo "File: " . basename($file) . "\n";
            echo "Status: ✗ {$transcript->getStatus()->value}\n";
            echo str_repeat('-', 80) . "\n\n";
        }
    } catch (EchoException $e) {
        echo "Error retrieving {$file}: {$e->getMessage()}\n\n";
    }
}

// ============================================================================
// Get Statistics for Period
// ============================================================================

echo str_repeat('=', 80) . "\n";
echo "Monthly Statistics\n";
echo str_repeat('=', 80) . "\n\n";

use Rarus\Echo\Core\Pagination;

$startDate = Carbon::parse('first day of this month')->startOfDay();
$endDate = Carbon::parse('last day of this month')->endOfDay();

// Create pagination for larger page size
$pagination = Pagination::firstPage(perPage: 100);

try {
    $transcripts = $transcriptionService->getTranscriptsByPeriod($startDate, $endDate, $pagination);

    echo "Total transcriptions this month: {$transcripts->getCount()}\n";
    echo "Pages: {$transcripts->getTotalPages()}\n\n";

    // Count by status
    $statusCounts = [];
    foreach ($transcripts->getResults() as $item) {
        $status = $item->getStatus()->value;
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
    }

    echo "Breakdown by status:\n";
    foreach ($statusCounts as $status => $count) {
        echo "  {$status}: {$count}\n";
    }

    // Count by task type
    $typeCounts = [];
    foreach ($transcripts->getResults() as $item) {
        $type = $item->getTaskType()->value;
        $typeCounts[$type] = ($typeCounts[$type] ?? 0) + 1;
    }

    echo "\nBreakdown by type:\n";
    foreach ($typeCounts as $type => $count) {
        echo "  {$type}: {$count}\n";
    }
} catch (EchoException $e) {
    echo "Error getting statistics: {$e->getMessage()}\n";
}

// ============================================================================
// Queue Monitoring
// ============================================================================

echo "\n" . str_repeat('=', 80) . "\n";
echo "Current Queue Status\n";
echo str_repeat('=', 80) . "\n\n";

$queueService = $app->getQueueService();

try {
    $queueInfo = $queueService->getQueueInfo();

    if ($queueInfo->isEmpty()) {
        echo "✓ Queue is empty - all files processed!\n";
    } else {
        echo "Files in queue: " . (int) $queueInfo->getFilesCount() . "\n";
        echo "Total size: {$queueInfo->getFilesSize()} MB\n";
        echo "Total duration: {$queueInfo->getFilesDuration()} minutes\n";
        echo "\nEstimated wait time: ~" . round($queueInfo->getFilesDuration() / 10) . " minutes\n";
        echo "(assuming ~10x real-time processing speed)\n";
    }
} catch (EchoException $e) {
    echo "Error getting queue info: {$e->getMessage()}\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Advanced example completed!\n";
echo str_repeat('=', 80) . "\n";
