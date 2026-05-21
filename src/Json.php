<?php declare(strict_types=1);

namespace Posternak\JsonFile;

use JsonException;
use RuntimeException;

class Json {
    public static function decodeFile(string $filePath): mixed {
        $contents = @file_get_contents($filePath);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: $filePath");
        }

        try {
            return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to decode JSON from file '$filePath': {$e->getMessage()}", 0, $e);
        }
    }

    public static function printIntoFile(mixed $content, string $filePath, int $flags = 0): void {
        try {
            $encoded = json_encode($content, JSON_THROW_ON_ERROR | $flags);
        } catch (JsonException $e) {
            throw new RuntimeException("Failed to encode JSON for file '$filePath': {$e->getMessage()}", 0, $e);
        }

        if (file_put_contents($filePath, $encoded) === false) {
            throw new RuntimeException("Failed to write file: $filePath");
        }
    }

    public static function prettyPrintIntoFile(mixed $content, string $filePath): void {
        self::printIntoFile($content, $filePath, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function reprintFileContentWithFlags(string $filePath, int $flags = 0): void {
        self::printIntoFile(self::decodeFile($filePath), $filePath, $flags);
    }

    public static function reprintFileContentInPrettyWay(string $filePath): void {
        self::reprintFileContentWithFlags($filePath, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
