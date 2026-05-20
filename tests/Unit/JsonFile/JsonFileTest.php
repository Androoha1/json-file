<?php declare(strict_types=1);

namespace Tests\Unit\JsonFile;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Posternak\JsonFile\JsonFile;

class JsonFileTest extends TestCase {
    #[Test]
    public function createsNewInstanceWithTheStaticFactoryMethod(): void {
        $instance = JsonFile::getInstance(__DIR__ . '/Mocks/file-with-some-content.json');
        $this->assertInstanceOf(JsonFile::class, $instance);
    }

    #[Test]
    public function createDifferentInstancesForDifferentFiles(): void {
        $instance1 = JsonFile::getInstance(__DIR__ . '/Mocks/empty-file.json');
        $instance2 = JsonFile::getInstance(__DIR__ . '/Mocks/file-with-some-content.json');

        $this->assertNotSame($instance1, $instance2);
    }

    #[Test]
    #[DataProvider('provideForGetsValueByPath')]
    public function getsValueByPath(string $path, string|array $value): void {
        $instance = JsonFile::getInstance(__DIR__ . '/Mocks/file-with-some-content.json');
        $this->assertSame($value, $instance->getValueByPath($path));
    }

    public static function provideForGetsValueByPath(): array {
        return [
            ['key1', 'value1'],
            ['key2', 'value2'],
            ['key3', ['key4' => 'value4', 'key5' => 'value5']],
            ['key3.key5', 'value5'],
        ];
    }
}
