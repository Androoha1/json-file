<?php declare(strict_types=1);

namespace Tests\Unit\JsonFile;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Posternak\JsonFile\JsonFile;
use RuntimeException;

class JsonFileTest extends TestCase {
    private const SOURCE_FILE = __DIR__ . '/Mocks/file-with-some-content.json';
    private string $scratchFile;

    protected function setUp(): void {
        parent::setUp();
        $this->scratchFile = tempnam(sys_get_temp_dir(), 'jsonfile-test-');
        copy(self::SOURCE_FILE, $this->scratchFile);
    }

    protected function tearDown(): void {
        parent::tearDown();
        @unlink($this->scratchFile);
    }

    #[Test]
    #[DataProvider('provideForGetsValueByPath')]
    public function getsValueByPath(string $path, mixed $value): void {
        $instance = new JsonFile(self::SOURCE_FILE);
        $this->assertSame($value, $instance->getValueByPath($path));
    }

    public static function provideForGetsValueByPath(): array {
        return [
            'top-level string'  => ['key1', 'value1'],
            'top-level object'  => ['key3', ['key4' => 'value4', 'key5' => 'value5']],
            'nested string'     => ['key3.key5', 'value5'],
        ];
    }

    #[Test]
    public function throwsWhenGettingMissingPath(): void {
        $instance = new JsonFile(self::SOURCE_FILE);
        $this->expectException(RuntimeException::class);
        $instance->getValueByPath('does.not.exist');
    }

    #[Test]
    public function throwsWhenTraversingThroughNonArrayKey(): void {
        $instance = new JsonFile(self::SOURCE_FILE);
        $this->expectException(RuntimeException::class);
        $instance->getValueByPath('key1.something');
    }

    #[Test]
    public function setsValueByPath(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->setValueByPath('key1', 'changed');
        $this->assertSame('changed', $instance->getValueByPath('key1'));
    }

    #[Test]
    public function setsNestedValueByPath(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->setValueByPath('key3.key4', 'changed');
        $this->assertSame('changed', $instance->getValueByPath('key3.key4'));
        $this->assertSame('value5', $instance->getValueByPath('key3.key5'));
    }

    #[Test]
    public function setsNonStringValues(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->setValueByPath('anInt', 42, true);
        $instance->setValueByPath('aBool', false, true);
        $instance->setValueByPath('aNull', null, true);
        $instance->setValueByPath('aFloat', 1.5, true);

        $this->assertSame(42, $instance->getValueByPath('anInt'));
        $this->assertSame(false, $instance->getValueByPath('aBool'));
        $this->assertNull($instance->getValueByPath('aNull'));
        $this->assertSame(1.5, $instance->getValueByPath('aFloat'));
    }

    #[Test]
    public function throwsWhenSettingMissingPathWithoutCreateFlag(): void {
        $instance = new JsonFile($this->scratchFile);
        $this->expectException(RuntimeException::class);
        $instance->setValueByPath('new.nested.path', 'x');
    }

    #[Test]
    public function createsNonExistingPathWhenFlagged(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->setValueByPath('new.nested.path', 'x', true);
        $this->assertSame('x', $instance->getValueByPath('new.nested.path'));
    }

    #[Test]
    public function removesValueByPath(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->removeByPath('key3.key4');

        $this->expectException(RuntimeException::class);
        $instance->getValueByPath('key3.key4');
    }

    #[Test]
    public function removeByPathSilentlyIgnoresMissingPath(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->removeByPath('does.not.exist');
        $this->assertSame('value1', $instance->getValueByPath('key1'));
    }

    #[Test]
    public function savePersistsChangesToDisk(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->setValueByPath('key1', 'persisted');
        $instance->save();

        $reloaded = new JsonFile($this->scratchFile);
        $this->assertSame('persisted', $reloaded->getValueByPath('key1'));
    }
}
