<?php declare(strict_types=1);

namespace Posternak\JsonFile;

use RuntimeException;

class JsonFile {
    /** @var array<array-key, mixed> */
    private array $fileDecoded;

    public function __construct(private readonly string $filePath) {
        $decoded = Json::decodeFile($this->filePath);
        if (!is_array($decoded)) {
            throw new RuntimeException("File '$filePath' does not contain a JSON object at its root");
        }
        $this->fileDecoded = $decoded;
    }

    public function getFilePath(): string {
        return $this->filePath;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array {
        return $this->fileDecoded;
    }

    /**
     * @param string|list<string> $path
     */
    public function has(string|array $path): bool {
        $current = $this->fileDecoded;

        foreach (self::splitPath($path) as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    /**
     * @param string|list<string> $path
     */
    public function get(string|array $path): mixed {
        [$parent, $key] = $this->navigateToPath($path);
        return $parent[$key];
    }

    /**
     * @param string|list<string> $path
     */
    public function set(string|array $path, mixed $value, bool $createNonExistingPath = false): void {
        $result = $this->navigateToPath($path, $createNonExistingPath);
        $result[0][$result[1]] = $value;
    }

    /**
     * @param string|list<string> $path
     */
    public function remove(string|array $path): void {
        try {
            $result = $this->navigateToPath($path, false);
            unset($result[0][$result[1]]);
        } catch (RuntimeException) {}
    }

    /**
     * @param string|list<string> $path
     * @return array{0: array<array-key, mixed>, 1: string}
     */
    private function navigateToPath(string|array $path, bool $createNonExistingPath = false): array {
        $keys = self::splitPath($path);
        $lastKey = array_pop($keys);
        $display = self::pathToString($path);

        if ($lastKey === null) {
            throw new RuntimeException("Path is empty");
        }

        $current = &$this->fileDecoded;

        foreach ($keys as $key) {
            if (!array_key_exists($key, $current)) {
                if (!$createNonExistingPath) {
                    throw new RuntimeException("Path '$display' does not exist");
                }
                $current[$key] = [];
            } elseif (!is_array($current[$key])) {
                throw new RuntimeException("Cannot traverse path '$display': key '$key' is not an array");
            }

            $current = &$current[$key];
        }

        if (!$createNonExistingPath && !array_key_exists($lastKey, $current)) {
            throw new RuntimeException("Path '$display' does not exist");
        }

        return [&$current, $lastKey];
    }

    /**
     * @param string|list<string> $path
     * @return list<string>
     */
    private static function splitPath(string|array $path): array {
        return is_array($path) ? $path : explode('.', $path);
    }

    /**
     * @param string|list<string> $path
     */
    private static function pathToString(string|array $path): string {
        return is_array($path) ? implode('.', $path) : $path;
    }

    public function save(): void {
        Json::prettyPrintIntoFile($this->fileDecoded, $this->filePath);
    }
}
