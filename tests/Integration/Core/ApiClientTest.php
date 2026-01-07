<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Enum\TaskType;
use Rarus\Echo\Services\ServiceFactory;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptionsBuilder;
use Rarus\Echo\Tests\LoggerFactory;

final class ApiClientTest extends TestCase
{
    public function testFooo(): void
    {


        $serviceFactory = ServiceFactory::fromEnvironment(LoggerFactory::defaultStdout());

        var_dump($serviceFactory->getQueueService()->getQueueInfo());

        //        var_dump($serviceFactory->getStatusService()->getFileStatus('ac2f86c6-1910-4673-baef-667bd7f0c724'));
        //
        //        var_dump($serviceFactory->getTranscriptionService()->getTranscript('ac2f86c6-1910-4673-baef-667bd7f0c724'));
        //
        //
        //        var_dump(
        //            file_put_contents(
        //                'tests/Temp/tr_1.txt',
        //                $serviceFactory->getTranscriptionService()->getTranscript('ac2f86c6-1910-4673-baef-667bd7f0c724')->getResult()
        //            )
        //        );
        //
        //    var_dump($factory->getTranscriptionService()->getTranscriptsList());


        //        $factory->getTranscriptionService()->submitTranscription(
        //            ['tests/Temp/module_1.ogg'],
        //            new TranscriptionOptionsBuilder()
        //                ->withTaskType(TaskType::TIMESTAMPS)
        //                ->build()
        //        );


        $this->assertTrue(true);
    }
}
