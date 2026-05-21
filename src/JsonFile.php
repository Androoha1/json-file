<?php declare(strict_types=1);

namespace Posternak\JsonFile;

use RuntimeException;

/**
 * @phpstan-type Path string|list<string>
 */
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
        return $this->decoded();
    }

    /**
     * @param Path $path
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
     * @param Path $path
     */
    public function get(string|array $path): mixed {
        [$parent, $key] = $this->navigateToPath($path);
        return $parent[$key];
    }

    /**
     * @param Path $path
     */
    public function set(string|array $path, mixed $value): void {
        $result = $this->navigateToPath($path, createNonExistingPath: true);
        $result[0][$result[1]] = $value;
        $this->mutated = true;
    }

    /**
     * @param Path $path
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
     * @param Path $path
     * @return array{0: array<array-key, mixed>, 1: string}
     */
    private function navigateToPath(string|array $path, bool $createNonExistingPath = false): array {
        ['keys' => $keys, 'display' => $display] = self::parsePath($path);
        $lastKey = array_pop($keys);

        if ($lastKey === null) {
            throw new RuntimeException("Path is empty");
        }

        $current = &$this->decoded();

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
     * @param Path $path
     * @return array{keys: list<string>, display: string}
     */
    private static function parsePath(string|array $path): array {
        return is_array($path)
            ? ['keys' => $path, 'display' => implode('.', $path)]
            : ['keys' => explode('.', $path), 'display' => $path];
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

    /**
     * Single access point for the in-memory content.
     * Auto-refreshes from disk when the file has changed externally and there are no pending mutations.
     *
     * @return array<array-key, mixed>
     */
    private function &decoded(): array {
        if (!$this->mutated && $this->currentMtime() !== $this->loadedMtime) {
            $this->loadFromDisk();
        }
        return $this->fileDecoded;
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
