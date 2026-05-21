<?php declare(strict_types=1);

namespace Tests\Unit\JsonFile;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Posternak\JsonFile\Json;
use Posternak\JsonFile\JsonFile;
use RuntimeException;

class MutationSafetyTest extends TestCase {
    private string $scratchFile;

    protected function setUp(): void {
        parent::setUp();
        $this->scratchFile = tempnam(sys_get_temp_dir(), 'jsonfile-safety-');
        Json::prettyPrintIntoFile(['key' => 'original'], $this->scratchFile);
    }

    protected function tearDown(): void {
        parent::tearDown();
        @unlink($this->scratchFile);
    }

    #[Test]
    public function reloadPicksUpExternalChanges(): void {
        $instance = new JsonFile($this->scratchFile);

        $this->writeExternally(['key' => 'external']);
        $instance->reload();

        $this->assertSame('external', $instance->get('key'));
    }

    #[Test]
    public function saveIsNoOpWhenNothingMutated(): void {
        $instance = new JsonFile($this->scratchFile);
        $mtimeBeforeSave = filemtime($this->scratchFile);

        // Bump the clock so any save would change mtime.
        sleep(1);
        $instance->save();
        clearstatcache(true, $this->scratchFile);

        $this->assertSame($mtimeBeforeSave, filemtime($this->scratchFile));
    }

    #[Test]
    public function readsAutoRefreshWhenUnmutated(): void {
        $instance = new JsonFile($this->scratchFile);

        $this->writeExternally(['key' => 'external']);

        $this->assertSame('external', $instance->get('key'));
    }

    #[Test]
    public function readsDoNotAutoRefreshWhenMutationsPending(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('key', 'in-memory');

        $this->writeExternally(['key' => 'external']);

        $this->assertSame('in-memory', $instance->get('key'));
    }

    #[Test]
    public function saveThrowsWhenFileMutatedExternallyAndLocalChangesPending(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('key', 'in-memory');

        $this->writeExternally(['key' => 'external']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('modified externally');
        $instance->save();
    }

    #[Test]
    public function saveSucceedsWhenFileUnchangedExternally(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('key', 'in-memory');
        $instance->save();

        $this->assertSame(['key' => 'in-memory'], Json::decodeFile($this->scratchFile));
    }

    #[Test]
    public function saveUpdatesInternalMtimeSoNextSaveDoesNotThrow(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('key', 'first');
        $instance->save();

        // No external change between saves; mtime tracking should follow our own writes.
        $instance->set('key', 'second');
        $instance->save();

        $this->assertSame(['key' => 'second'], Json::decodeFile($this->scratchFile));
    }

    #[Test]
    public function reloadDiscardsLocalMutations(): void {
        $instance = new JsonFile($this->scratchFile);
        $instance->set('key', 'in-memory');

        $this->writeExternally(['key' => 'external']);
        $instance->reload();

        $this->assertSame('external', $instance->get('key'));

        // After reload, mutated flag should be cleared — save is a safe no-op.
        $instance->save();
        $this->assertSame(['key' => 'external'], Json::decodeFile($this->scratchFile));
    }

    /**
     * @param array<string, mixed> $content
     */
    private function writeExternally(array $content): void {
        // Bump mtime by 1 second so the timestamp comparison is reliable
        // (filemtime has 1-second resolution on most filesystems).
        $futureTime = filemtime($this->scratchFile) + 1;
        Json::prettyPrintIntoFile($content, $this->scratchFile);
        touch($this->scratchFile, $futureTime);
        clearstatcache(true, $this->scratchFile);
    }
}
