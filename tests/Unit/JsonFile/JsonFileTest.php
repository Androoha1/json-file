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
    public function returnsEntireDecodedContents(): void {
        $instance = new JsonFile(self::SOURCE_FILE);
        $this->assertSame(
            ['key1' => 'value1', 'key2' => 'value2', 'key3' => ['key4' => 'value4', 'key5' => 'value5']],
            $instance->all(),
        );
    }

    #[Test]
    public function exposesFilePath(): void {
        $instance = new JsonFile(self::SOURCE_FILE);
        $this->assertSame(self::SOURCE_FILE, $instance->getFilePath());
    }

    #[Test]
    public function reportsPathExistence(): void {
        $instance = new JsonFile(self::SOURCE_FILE);

        $this->assertTrue($instance->has('key1'));
        $this->assertTrue($instance->has('key3.key4'));
        $this->assertFalse($instance->has('does.not.exist'));
        $this->assertFalse($instance->has('key1.something'));
    }

    #[Test]
    public function hasReturnsTrueForNullValue(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('aNull', null);

        $this->assertTrue($instance->has('aNull'));
    }

    #[Test]
    public function acceptsArrayPathsToAvoidDotAmbiguity(): void {
        $instance = new JsonFile($this->scratchFile);

        $instance->set(['servers', '127.0.0.1', 'port'], 8080);

        $this->assertTrue($instance->has(['servers', '127.0.0.1', 'port']));
        $this->assertSame(8080, $instance->get(['servers', '127.0.0.1', 'port']));

        $instance->remove(['servers', '127.0.0.1', 'port']);
        $this->assertFalse($instance->has(['servers', '127.0.0.1', 'port']));
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
        $instance->set('anInt', 42);
        $instance->set('aBool', false);
        $instance->set('aNull', null);
        $instance->set('aFloat', 1.5);

        $this->assertSame(42, $instance->get('anInt'));
        $this->assertSame(false, $instance->get('aBool'));
        $this->assertNull($instance->get('aNull'));
        $this->assertSame(1.5, $instance->get('aFloat'));
    }

    #[Test]
    public function setCreatesMissingParentsByDefault(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('new.nested.path', 'x');
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
