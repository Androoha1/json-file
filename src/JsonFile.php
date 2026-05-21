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

    public function has(string $path): bool {
        $current = $this->fileDecoded;

        foreach (explode('.', $path) as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return false;
            }
            $current = $current[$key];
        }

        return true;
    }

    public function get(string $path): mixed {
        [$parent, $key] = $this->navigateToPath($path);
        return $parent[$key];
    }

    public function set(string $path, mixed $value, bool $createNonExistingPath = false): void {
        $result = $this->navigateToPath($path, $createNonExistingPath);
        $result[0][$result[1]] = $value;
    }

    public function remove(string $path): void {
        try {
            $result = $this->navigateToPath($path, false);
            unset($result[0][$result[1]]);
        } catch (RuntimeException) {}
    }

    /**
     * @return array{0: array<array-key, mixed>, 1: string}
     */
    private function navigateToPath(string $path, bool $createNonExistingPath = false): array {
        $keys = explode('.', $path);
        $lastKey = array_pop($keys);

        $current = &$this->fileDecoded;

        foreach ($keys as $key) {
            if (!array_key_exists($key, $current)) {
                if (!$createNonExistingPath) {
                    throw new RuntimeException("Path '$path' does not exist");
                }
                $current[$key] = [];
            } elseif (!is_array($current[$key])) {
                throw new RuntimeException("Cannot traverse path '$path': key '$key' is not an array");
            }

            $current = &$current[$key];
        }

        if (!$createNonExistingPath && !array_key_exists($lastKey, $current)) {
            throw new RuntimeException("Path '$path' does not exist");
        }

        return [&$current, $lastKey];
    }

    public function save(): void {
        Json::prettyPrintIntoFile($this->fileDecoded, $this->filePath);
    }
}
