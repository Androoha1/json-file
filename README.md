# json-file

A small PHP utility library for ergonomically reading, mutating, and writing JSON files.

## Requirements

- PHP 8.2+

## Installation

```bash
composer require posternak/json-file
```

## Usage

### `JsonFile` — mutate a file via dot-notation paths

Suppose you have `settings.json`:

```json
{
    "appName": "Acme",
    "version": "0.4.2",
    "server": {
        "host": "localhost",
        "port": 8080,
        "tls": {
            "enabled": false
        }
    },
    "features": {
        "darkMode": true,
        "betaFlags": ["search-v2", "fast-export"]
    }
}
```

You can read, mutate, and persist values addressed by dot-notation paths:

```php
use Posternak\JsonFile\JsonFile;

$file = new JsonFile('/path/to/settings.json');

// Check existence
$file->has('server.tls.enabled');                           // true
$file->has('features.nonexistent');                         // false

// Read — any JSON type comes back as-is
$name      = $file->get('appName');              // "Acme"
$port      = $file->get('server.port');          // 8080
$tlsOn     = $file->get('server.tls.enabled');   // false
$flags     = $file->get('features.betaFlags');   // ["search-v2", "fast-export"]
$tlsBlock  = $file->get('server.tls');           // ["enabled" => false]

// Write — strings, ints, bools, arrays, null, all fine
$file->set('version', '0.5.0');
$file->set('server.port', 9090);
$file->set('server.tls.enabled', true);

// Missing parents are created on the fly
$file->set('server.tls.certPath', '/etc/ssl/acme.pem');

// Remove
$file->remove('features.darkMode');

// Persist back to disk (pretty-printed)
$file->save();
```

Missing paths throw `RuntimeException` on `get`. `set` creates missing parents automatically. `remove` is a no-op when the path is missing.

If a key itself contains a `.` (e.g. an IP address or a version constraint used as a key), pass the path as a list of strings to skip splitting:

```php
$file->get(['servers', '127.0.0.1', 'port']);
```

### `Json` — stateless helpers

```php
use Posternak\JsonFile\Json;

$data = Json::decodeFile('/path/to/file.json');
Json::prettyPrintIntoFile($data, '/path/to/out.json');

// Re-pretty-print an existing file in place
Json::reprintFileContentInPrettyWay('/path/to/messy.json');
```

All `Json` methods throw `RuntimeException` on IO or JSON parse/encode failures.

## License

MIT
