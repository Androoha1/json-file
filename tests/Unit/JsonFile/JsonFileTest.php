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
    #[DataProvider('provideForGetsValue')]
    public function getsValue(string $path, mixed $value): void {
        $instance = new JsonFile(self::SOURCE_FILE);
        $this->assertSame($value, $instance->get($path));
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function provideForGetsValue(): array {
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
        $instance->get('does.not.exist');
    }

    #[Test]
    public function throwsWhenTraversingThroughNonArrayKey(): void {
        $instance = new JsonFile(self::SOURCE_FILE);
        $this->expectException(RuntimeException::class);
        $instance->get('key1.something');
    }

    #[Test]
    public function setsValue(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('key1', 'changed');
        $this->assertSame('changed', $instance->get('key1'));
    }

    #[Test]
    public function setsNestedValue(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('key3.key4', 'changed');
        $this->assertSame('changed', $instance->get('key3.key4'));
        $this->assertSame('value5', $instance->get('key3.key5'));
    }

    #[Test]
    public function setsNonStringValues(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('anInt', 42, true);
        $instance->set('aBool', false, true);
        $instance->set('aNull', null, true);
        $instance->set('aFloat', 1.5, true);

        $this->assertSame(42, $instance->get('anInt'));
        $this->assertSame(false, $instance->get('aBool'));
        $this->assertNull($instance->get('aNull'));
        $this->assertSame(1.5, $instance->get('aFloat'));
    }

    #[Test]
    public function throwsWhenSettingMissingPathWithoutCreateFlag(): void {
        $instance = new JsonFile($this->scratchFile);
        $this->expectException(RuntimeException::class);
        $instance->set('new.nested.path', 'x');
    }

    #[Test]
    public function createsNonExistingPathWhenFlagged(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('new.nested.path', 'x', true);
        $this->assertSame('x', $instance->get('new.nested.path'));
    }

    #[Test]
    public function removesValue(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->remove('key3.key4');

        $this->expectException(RuntimeException::class);
        $instance->get('key3.key4');
    }

    #[Test]
    public function removeSilentlyIgnoresMissingPath(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->remove('does.not.exist');
        $this->assertSame('value1', $instance->get('key1'));
    }

    #[Test]
    public function savePersistsChangesToDisk(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('key1', 'persisted');
        $instance->save();

        $reloaded = new JsonFile($this->scratchFile);
        $this->assertSame('persisted', $reloaded->get('key1'));
    }
}
