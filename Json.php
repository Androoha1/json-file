<?php

declare(strict_types=1);

namespace RectorIntegrationTool\Libraries\Files\Json;

class Json {
    public static function decodeFile(string $filePath): array {
        return json_decode(file_get_contents($filePath), true);
    }

    public static function printIntoFile(array $content, string $filePath, int $flags = 0): void {
        file_put_contents($filePath, json_encode($content, $flags));
    }

    public static function prettyPrintIntoFile(array $content, string $filePath): void {
        self::printIntoFile($content, $filePath, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public static function reprintFileContentWithFlags(string $filePath, int $flags = 0): void {
        $newContent = json_encode(self::decodeFile($filePath), $flags);
        file_put_contents($filePath, $newContent);
    }

    public static function reprintFileContentInPrettyWay(string $filePath): void {
        self::reprintFileContentWithFlags($filePath, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
