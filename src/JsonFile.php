<?php declare(strict_types=1);

namespace Posternak\JsonFile;

use RuntimeException;

class JsonFile {
    /** @var array<array-key, mixed> */
    private array $fileDecoded;
    private int $loadedMtime;
    private bool $mutated = false;

    public function __construct(private readonly string $filePath) {
        $this->loadFromDisk();
    }

    public function reload(): void {
        $this->loadFromDisk();
    }

    public function getFilePath(): string {
        return $this->filePath;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function all(): array {
        $this->refreshIfStaleAndUnmutated();
        return $this->fileDecoded;
    }

    /**
     * @param string|list<string> $path
     */
    public function has(string|array $path): bool {
        try {
            $this->navigateToPath($path);
            return true;
        } catch (RuntimeException) {
            return false;
        }
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
    public function set(string|array $path, mixed $value): void {
        $result = $this->navigateToPath($path, createNonExistingPath: true);
        $result[0][$result[1]] = $value;
        $this->mutated = true;
    }

    /**
     * @param string|list<string> $path
     */
    public function remove(string|array $path): void {
        if (!$this->has($path)) {
            return;
        }
        $result = $this->navigateToPath($path);
        unset($result[0][$result[1]]);
        $this->mutated = true;
    }

    /**
     * @param string|list<string> $path
     * @return array{0: array<array-key, mixed>, 1: string}
     */
    private function navigateToPath(string|array $path, bool $createNonExistingPath = false): array {
        $this->refreshIfStaleAndUnmutated();

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
        if (!$this->mutated) {
            return;
        }
        if ($this->currentMtime() !== $this->loadedMtime) {
            throw new RuntimeException(
                "Cannot save '{$this->filePath}': it was modified externally since load and there are unsaved in-memory changes. Call reload() to discard local changes and pick up the external state."
            );
        }
        Json::prettyPrintIntoFile($this->fileDecoded, $this->filePath);
        $this->loadedMtime = $this->currentMtime();
        $this->mutated = false;
    }

    private function refreshIfStaleAndUnmutated(): void {
        if ($this->mutated) {
            return;
        }
        if ($this->currentMtime() !== $this->loadedMtime) {
            $this->loadFromDisk();
        }
    }

    private function loadFromDisk(): void {
        $decoded = Json::decodeFile($this->filePath);
        if (!is_array($decoded)) {
            throw new RuntimeException("File '{$this->filePath}' does not contain a JSON object or array at its root");
        }
        $this->fileDecoded = $decoded;
        $this->loadedMtime = $this->currentMtime();
        $this->mutated = false;
    }

    private function currentMtime(): int {
        clearstatcache(true, $this->filePath);
        return (int) filemtime($this->filePath);
    }
}
