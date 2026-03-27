# Arcanum Parchment

Parchment is the filesystem layer. It provides a clean API for reading, writing, and managing files, built on top of Symfony's Filesystem and Finder components. Where Symfony handles the heavy lifting (atomic writes, cross-platform compatibility, error handling), Parchment provides a simpler surface and consistent exception handling with `RuntimeException`.

## Reader — getting data out of files

`Reader` reads file contents in several formats:

```php
$reader = new Reader();

$reader->read('/path/to/file.txt');     // string contents
$reader->lines('/path/to/file.txt');    // string[] (one per line, newlines stripped)
$reader->json('/path/to/data.json');    // decoded JSON (arrays/scalars)
$reader->require('/path/to/config.php'); // executes PHP file, returns result
```

`read()` delegates to Symfony's `Filesystem::readFile()`. `lines()` uses PHP's native `file()` function through Glitch's `SafeCall` for better error capture. `json()` reads then decodes, throwing `RuntimeException` with a clear message if the JSON is invalid. `require()` wraps PHP's `require` with an existence check.

All methods throw `RuntimeException` on failure.

## Writer — putting data into files

`Writer` handles file output with automatic directory creation:

```php
$writer = new Writer();

$writer->write('/path/to/file.txt', 'content');     // atomic write
$writer->append('/path/to/log.txt', "new line\n");  // append to file
$writer->json('/path/to/data.json', ['key' => 'value']); // encode + write
```

`write()` delegates to Symfony's `Filesystem::dumpFile()`, which writes to a temp file then renames — so writes are atomic and you'll never get a half-written file. `append()` delegates to `Filesystem::appendToFile()`. Both create parent directories automatically.

`json()` encodes with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES` by default (configurable via the `$flags` parameter) and appends a trailing newline.

## FileSystem — managing files and directories

`FileSystem` wraps Symfony's Filesystem for common file operations:

```php
$fs = new FileSystem();

$fs->copy('/source.txt', '/target.txt');
$fs->move('/old/path.txt', '/new/path.txt');
$fs->delete('/path/to/file_or_directory');   // recursive for directories
$fs->mkdir('/path/to/new/directory');         // creates parents

$fs->exists('/some/path');      // true if file or directory exists
$fs->isFile('/some/path');      // true if regular file
$fs->isDirectory('/some/path'); // true if directory
```

All mutating operations throw `RuntimeException` on failure.

## TempFile — temporary files with auto-cleanup

`TempFile` creates a temporary file that deletes itself when the object is destroyed:

```php
$temp = new TempFile();
$temp->write('some data');
echo $temp->read();     // 'some data'
echo $temp->path();     // /tmp/arcanum_xxxxx

// File is deleted when $temp goes out of scope.
// Or delete it explicitly:
$temp->delete();
```

You can specify a custom directory and prefix:

```php
$temp = new TempFile(directory: '/var/cache', prefix: 'myapp_');
```

The destructor calls `delete()` automatically, and deletion is idempotent and best-effort — it won't throw if the file is already gone or can't be removed.

## Searcher — finding files by pattern

`Searcher` finds files matching a glob pattern in a directory, powered by Symfony Finder:

```php
$files = Searcher::findAll('*.php', '/path/to/config');

foreach ($files as $file) {
    echo $file->getFilenameWithoutExtension(); // 'app', 'log', etc.
    echo $file->getRealPath();                 // full path
}
```

This is used by the Ignition Configuration bootstrapper to discover config files.

## Path utilities

For path manipulation (normalizing, joining, resolving relative paths, extracting extensions), use Symfony's `Path` class directly:

```php
use Symfony\Component\Filesystem\Path;

Path::canonicalize('/foo/bar/../baz');        // '/foo/baz'
Path::join('/foo', 'bar', 'baz');            // '/foo/bar/baz'
Path::makeAbsolute('file.txt', '/home/user'); // '/home/user/file.txt'
Path::getExtension('/src/Foo.php');           // 'php'
```

## Error handling

Parchment catches Symfony's `IOException` and PHP warnings, wrapping them in `RuntimeException` with clear, path-specific messages. The original exception is always available via `$e->getPrevious()`. Reader's `lines()` method uses Glitch's `SafeCall` to capture PHP warnings from native `file()` calls rather than suppressing them with `@`.

## The classes at a glance

```
Reader    — read(), lines(), json(), require()
Writer    — write() [atomic], append(), json()
FileSystem — copy(), move(), delete(), mkdir(), exists(), isFile(), isDirectory()
TempFile  — auto-cleaning temporary file with read/write
Searcher  — glob-based file discovery via Symfony Finder
```
