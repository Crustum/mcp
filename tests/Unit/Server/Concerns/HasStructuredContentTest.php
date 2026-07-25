<?php
declare(strict_types=1);

use Crustum\Mcp\Server\Trait\HasStructuredContentTrait;

it('can set structured content with array', function (): void {
    $object = new class
    {
        use HasStructuredContentTrait;

        public function getStructuredContent(): ?array
        {
            return $this->structuredContent;
        }
    };

    $object->setStructuredContent(['key1' => 'value1', 'key2' => 'value2']);

    expect($object->getStructuredContent())->toEqual([
        'key1' => 'value1',
        'key2' => 'value2',
    ]);
});

it('merges structured content into base array', function (): void {
    $object = new class
    {
        use HasStructuredContentTrait;
    };

    $object->setStructuredContent(['temperature' => 22.5, 'humidity' => 65]);

    $result = $object->mergeStructuredContent([
        'content' => [['type' => 'text', 'text' => 'Weather data']],
        'isError' => false,
    ]);

    expect($result)->toEqual([
        'content' => [['type' => 'text', 'text' => 'Weather data']],
        'isError' => false,
        'structuredContent' => [
            'temperature' => 22.5,
            'humidity' => 65,
        ],
    ]);
});

it('returns base array when structured content is null', function (): void {
    $object = new class
    {
        use HasStructuredContentTrait;
    };

    $result = $object->mergeStructuredContent([
        'content' => [['type' => 'text', 'text' => 'Weather data']],
        'isError' => false,
    ]);

    expect($result)->toEqual([
        'content' => [['type' => 'text', 'text' => 'Weather data']],
        'isError' => false,
    ])->not->toHaveKey('structuredContent');
});

it('merges multiple setStructuredContent calls with arrays', function (): void {
    $object = new class
    {
        use HasStructuredContentTrait;

        public function getStructuredContent(): ?array
        {
            return $this->structuredContent;
        }
    };

    $object->setStructuredContent(['key1' => 'value1']);
    $object->setStructuredContent(['key2' => 'value2', 'key3' => 'value3']);

    expect($object->getStructuredContent())->toEqual([
        'key1' => 'value1',
        'key2' => 'value2',
        'key3' => 'value3',
    ]);
});

it('overwrites existing keys when setting structured content', function (): void {
    $object = new class
    {
        use HasStructuredContentTrait;

        public function getStructuredContent(): ?array
        {
            return $this->structuredContent;
        }
    };

    $object->setStructuredContent(['key1' => 'value1']);
    $object->setStructuredContent(['key1' => 'value2']);

    expect($object->getStructuredContent())->toEqual([
        'key1' => 'value2',
    ]);
});
