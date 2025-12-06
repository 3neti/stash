<?php

use App\Contracts\Processors\ProcessorInterface;
use App\Data\Pipeline\PipelineConfigData;
use App\Data\Pipeline\ProcessorConfigData;
use App\Data\Processors\ProcessorContextData;
use App\Data\Processors\ProcessorResultData;
use App\Exceptions\ProcessorException;
use App\Models\Document;
use App\Processors\AbstractProcessor;
use App\Services\Pipeline\ProcessorRegistry;

uses(Tests\TestCase::class);

describe('Pipeline DTOs', function () {
    test('ProcessorConfigData can be instantiated', function () {
        $data = new ProcessorConfigData(
            id: 'test_processor',
            type: 'App\\Processors\\TestProcessor',
            config: ['key' => 'value'],
            next: ['next_processor'],
            branches: null
        );

        expect($data->id)->toBe('test_processor')
            ->and($data->type)->toBe('App\\Processors\\TestProcessor')
            ->and($data->config)->toBe(['key' => 'value'])
            ->and($data->next)->toBe(['next_processor'])
            ->and($data->branches)->toBeNull();
    });

    test('ProcessorConfigData can be created from array', function () {
        $data = ProcessorConfigData::from([
            'id' => 'test_processor',
            'type' => 'App\\Processors\\TestProcessor',
            'config' => ['key' => 'value'],
        ]);

        expect($data->id)->toBe('test_processor')
            ->and($data->type)->toBe('App\\Processors\\TestProcessor')
            ->and($data->config)->toBe(['key' => 'value']);
    });

    test('PipelineConfigData can be created with processors', function () {
        $data = PipelineConfigData::from([
            'processors' => [
                [
                    'id' => 'processor1',
                    'type' => 'App\\Processors\\Test1',
                    'config' => [],
                ],
                [
                    'id' => 'processor2',
                    'type' => 'App\\Processors\\Test2',
                    'config' => ['foo' => 'bar'],
                ],
            ],
        ]);

        expect($data->processors)->toHaveCount(2)
            ->and($data->processors[0])->toBeInstanceOf(ProcessorConfigData::class)
            ->and($data->processors[0]->id)->toBe('processor1')
            ->and($data->processors[1]->id)->toBe('processor2');
    });

    test('ProcessorContextData holds runtime context', function () {
        $data = new ProcessorContextData(
            documentJobId: '01abc123',
            processorIndex: 2,
            previousOutputs: ['step1' => ['result' => 'data']],
            metadata: ['key' => 'value']
        );

        expect($data->documentJobId)->toBe('01abc123')
            ->and($data->processorIndex)->toBe(2)
            ->and($data->previousOutputs)->toHaveKey('step1')
            ->and($data->metadata)->toHaveKey('key');
    });

    test('ProcessorResultData represents success', function () {
        $data = new ProcessorResultData(
            success: true,
            output: ['text' => 'extracted'],
            error: null,
            metadata: ['duration' => 100],
            tokensUsed: 50,
            costCredits: 10
        );

        expect($data->success)->toBeTrue()
            ->and($data->output)->toBe(['text' => 'extracted'])
            ->and($data->error)->toBeNull()
            ->and($data->tokensUsed)->toBe(50)
            ->and($data->costCredits)->toBe(10);
    });

    test('ProcessorResultData represents failure', function () {
        $data = new ProcessorResultData(
            success: false,
            output: [],
            error: 'Processing failed'
        );

        expect($data->success)->toBeFalse()
            ->and($data->output)->toBe([])
            ->and($data->error)->toBe('Processing failed');
    });
});

describe('Processor Registry', function () {
    test('can register and retrieve processor', function () {
        $registry = app(ProcessorRegistry::class);

        // Create a test processor class
        $testProcessorClass = new class extends AbstractProcessor
        {
            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['success' => true];
            }
        };

        $registry->register('test_processor', get_class($testProcessorClass));

        expect($registry->has('test_processor'))->toBeTrue()
            ->and($registry->get('test_processor'))->toBeInstanceOf(ProcessorInterface::class);
    });

    test('throws exception for unregistered processor', function () {
        $registry = app(ProcessorRegistry::class);

        expect(fn () => $registry->get('nonexistent'))
            ->toThrow(ProcessorException::class);
    });

    test('throws exception for invalid class', function () {
        $registry = app(ProcessorRegistry::class);

        expect(fn () => $registry->register('invalid', 'NonExistentClass'))
            ->toThrow(InvalidArgumentException::class);
    });

    test('throws exception for non-processor class', function () {
        $registry = app(ProcessorRegistry::class);

        expect(fn () => $registry->register('invalid', \stdClass::class))
            ->toThrow(InvalidArgumentException::class);
    });

    test('can list registered processor IDs', function () {
        $registry = app(ProcessorRegistry::class);

        $testProcessorClass = new class extends AbstractProcessor
        {
            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return [];
            }
        };

        $registry->register('processor1', get_class($testProcessorClass));
        $registry->register('processor2', get_class($testProcessorClass));

        $ids = $registry->getRegisteredIds();

        expect($ids)->toContain('processor1')
            ->and($ids)->toContain('processor2');
    });
});

describe('Abstract Processor', function () {
    test('handles successful processing', function () {
        $processor = new class extends AbstractProcessor
        {
            protected string $name = 'TestProcessor';

            protected string $category = 'test';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return ['result' => 'success'];
            }
        };

        $document = Mockery::mock(Document::class);
        $config = new ProcessorConfigData('test', 'Test', []);
        $context = new ProcessorContextData('job123', 0);

        $result = $processor->handle($document, $config, $context);

        expect($result->success)->toBeTrue()
            ->and($result->output)->toBe(['result' => 'success'])
            ->and($result->error)->toBeNull();
    });

    test('handles processing exceptions', function () {
        $processor = new class extends AbstractProcessor
        {
            protected function process(Document $document, ProcessorConfigData $config): array
            {
                throw new Exception('Processing failed');
            }
        };

        $document = Mockery::mock(Document::class);
        $config = new ProcessorConfigData('test', 'Test', []);
        $context = new ProcessorContextData('job123', 0);

        $result = $processor->handle($document, $config, $context);

        expect($result->success)->toBeFalse()
            ->and($result->output)->toBe([])
            ->and($result->error)->toBe('Processing failed');
    });

    test('returns processor name', function () {
        $processor = new class extends AbstractProcessor
        {
            protected string $name = 'MyProcessor';

            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return [];
            }
        };

        expect($processor->getName())->toBe('MyProcessor');
    });

    test('returns default category', function () {
        $processor = new class extends AbstractProcessor
        {
            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return [];
            }
        };

        expect($processor->getCategory())->toBe('custom');
    });

    test('can process returns true by default', function () {
        $processor = new class extends AbstractProcessor
        {
            protected function process(Document $document, ProcessorConfigData $config): array
            {
                return [];
            }
        };

        $document = Mockery::mock(Document::class);

        expect($processor->canProcess($document))->toBeTrue();
    });
});
