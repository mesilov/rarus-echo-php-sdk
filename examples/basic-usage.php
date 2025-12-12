<?php

declare(strict_types=1);

/**
 * Basic usage example for Rarus Echo PHP SDK
 * Demonstrates main functionality of the SDK
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Rarus\Echo\Application\ServiceFactory;
use Rarus\Echo\Core\Credentials\Credentials;
use Rarus\Echo\Enum\Language;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Enum\TranscriptionStatus;
use Rarus\Echo\Exception\ApiException;
use Rarus\Echo\Exception\AuthenticationException;
use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;

// ============================================================================
// 1. Initialize SDK
// ============================================================================

// Option 1: Create credentials manually
$credentials = Credentials::create(
    apiKey: 'your-api-key-here',
    userId: '00000000-0000-0000-0000-000000000000'
);
$factory = new ServiceFactory($credentials);

// Option 2: Load from environment variables
// Set RARUS_ECHO_API_KEY and RARUS_ECHO_USER_ID environment variables
// $factory = ServiceFactory::fromEnvironment();

// ============================================================================
// 2. Submit files for transcription
// ============================================================================

try {
    // Configure transcription options
    $options = TranscriptionOptions::create()
        ->withTaskType(TaskType::DIARIZATION)       // Transcription with speaker separation
        ->withLanguage(Language::RU)                 // Russian language
        ->withCensor(true)                           // Enable text censorship
        ->withSpeakersCorrection(true)               // Enable speaker correction
        ->build();

    // Alternative: use constructor
    // $options = new TranscriptionOptions(
    //     taskType: TaskType::DIARIZATION,
    //     language: Language::RU,
    //     censor: true,
    //     speakersCorrection: true
    // );

    // Or use default options
    // $options = TranscriptionOptions::default();

    // Get transcription service
    $transcriptionService = $factory->getTranscriptionService();

    // Submit files
    $files = [
        '/path/to/audio1.mp3',
        '/path/to/audio2.wav',
    ];

    echo "Submitting files for transcription...\n";
    $result = $transcriptionService->submitTranscription($files, $options);

    echo "Files submitted successfully!\n";
    echo "File IDs:\n";
    foreach ($result->getFileIds() as $fileId) {
        echo "  - {$fileId}\n";
    }

    $fileId = $result->getFirstFileId();
} catch (FileException $e) {
    echo "File error: {$e->getMessage()}\n";
    exit(1);
} catch (ValidationException $e) {
    echo "Validation error: {$e->getMessage()}\n";
    echo "Details:\n{$e->getValidationErrorsAsString()}\n";
    exit(1);
} catch (AuthenticationException $e) {
    echo "Authentication error: {$e->getMessage()}\n";
    echo "Please check your API key.\n";
    exit(1);
} catch (ApiException $e) {
    echo "API error: {$e->getMessage()}\n";
    exit(1);
}

// ============================================================================
// 3. Check transcription status
// ============================================================================

$statusService = $factory->getStatusService();

echo "\nChecking transcription status...\n";
$status = $statusService->getFileStatus($fileId);

echo "File ID: {$status->getFileId()}\n";
echo "Status: {$status->getStatus()->value} - {$status->getStatus()->getDescription()}\n";
echo "File size: {$status->getFileSize()} MB\n";
echo "Duration: {$status->getFileDuration()} minutes\n";
echo "Uploaded: {$status->getTimestampArrival()->format('Y-m-d H:i:s')}\n";

// ============================================================================
// 4. Wait for transcription to complete
// ============================================================================

echo "\nWaiting for transcription to complete...\n";

$maxAttempts = 60; // 5 minutes with 5-second intervals
$attempt = 0;

while ($attempt < $maxAttempts) {
    $transcript = $transcriptionService->getTranscript($fileId);

    if ($transcript->isSuccessful()) {
        echo "✓ Transcription completed successfully!\n\n";
        echo "Result:\n";
        echo str_repeat('=', 80) . "\n";
        echo $transcript->getResult() . "\n";
        echo str_repeat('=', 80) . "\n";
        break;
    }

    if ($transcript->isFailed()) {
        echo "✗ Transcription failed!\n";
        break;
    }

    echo "Status: {$transcript->getStatus()->value}... waiting...\n";
    sleep(5);
    $attempt++;
}

if ($attempt >= $maxAttempts) {
    echo "Timeout: transcription took too long\n";
}

// ============================================================================
// 5. Check queue information
// ============================================================================

$queueService = $factory->getQueueService();

echo "\nQueue information:\n";
$queueInfo = $queueService->getQueueInfo();

if ($queueInfo->isEmpty()) {
    echo "Queue is empty\n";
} else {
    echo $queueInfo->toString() . "\n";
}

// ============================================================================
// 6. Get transcriptions by period
// ============================================================================

use Carbon\Carbon;
use Rarus\Echo\Core\Pagination;

echo "\nGetting today's transcriptions...\n";
$startOfDay = Carbon::today()->startOfDay();
$endOfDay = Carbon::today()->endOfDay();

// Use default pagination (page 1, 10 items per page)
$pagination = Pagination::default();
$transcripts = $transcriptionService->getTranscriptsByPeriod($startOfDay, $endOfDay, $pagination);

// Or with custom pagination
// $pagination = Pagination::create(page: 1, perPage: 50);
// $transcripts = $transcriptionService->getTranscriptsByPeriod($startOfDay, $endOfDay, $pagination);

echo "Found {$transcripts->getCount()} transcriptions\n";
echo "Page {$transcripts->getPage()} of {$transcripts->getTotalPages()}\n";

foreach ($transcripts->getResults() as $item) {
    echo sprintf(
        "  - %s: %s (%s)\n",
        $item->getFileId(),
        $item->getStatus()->value,
        $item->getTaskType()->value
    );
}

// ============================================================================
// 7. Get multiple transcriptions by IDs
// ============================================================================

$fileIds = $result->getFileIds();
if (count($fileIds) > 1) {
    echo "\nGetting multiple transcriptions...\n";
    $batch = $transcriptionService->getTranscriptsList($fileIds, Pagination::default());

    foreach ($batch->getResults() as $item) {
        echo "File {$item->getFileId()}: {$item->getStatus()->value}\n";
    }
}

echo "\nDone!\n";
