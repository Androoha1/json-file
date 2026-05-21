<?php declare(strict_types=1);

namespace Tests\Unit\Json;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Posternak\JsonFile\Json;

class JsonTest extends TestCase {
    private static string $fileWithSomeContent = __DIR__ . '/Mocks/file-with-some-content.json';
    private static string $emptyFile = __DIR__ . '/Mocks/empty-file.json';

    protected function tearDown(): void
    {
        parent::tearDown();
        file_put_contents(self::$emptyFile, '{}');
    }

    #[Test]
    public function decodesFile(): void {
        $this->assertSame(
            Json::decodeFile(self::$fileWithSomeContent),
            ['key1' => 'value1', 'key2' => 'value2', 'key3' => ['value3', 'value4']],
        );
    }

    #[Test]
    #[DataProvider('provideForPrettyPrintsIntoAFile')]
    public function prettyPrintsIntoAFile(string $readFilePath): void {
        $writeFile = self::$emptyFile;

        $decodedContent = Json::decodeFile($readFilePath);
        Json::prettyPrintIntoFile($decodedContent, $writeFile);

        $this->assertFileEquals($readFilePath, $writeFile);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function provideForPrettyPrintsIntoAFile(): array {
        return [
            [self::$fileWithSomeContent],
            [__DIR__ . '/Mocks/real-life-composer-json-file.json'],
        ];
    }

    #[Test]
    public function reprintsFileContentWithFlags(): void {
        file_put_contents(self::$emptyFile, file_get_contents(__DIR__ . '/Mocks/real-life-composer-json-file-not-pretty.json'));
        Json::reprintFileContentWithFlags(self::$emptyFile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $this->assertFileEquals(
            __DIR__ . '/Mocks/real-life-composer-json-file.json',
            __DIR__ . '/Mocks/empty-file.json'
        );
    }
}
