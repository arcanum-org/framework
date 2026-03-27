# Arcanum Flow: River

River wraps PHP's low-level stream resources into proper objects that implement PSR-7's `StreamInterface`. It handles the messy parts — resource lifecycle, error checking, type safety — so you don't have to.

## Why River exists

Raw PHP streams are error-prone:

```php
// Raw PHP — no type safety, easy to forget cleanup, silent failures
$fp = fopen('file.txt', 'r');
$data = fread($fp, 1024);  // returns false on failure, no exception
fclose($fp);                // hope you don't forget this
```

With River:

```php
// Type-safe, auto-closes, throws on errors
$stream = new Stream(LazyResource::for('file.txt', 'r'));
$data = $stream->read(1024);
// stream closes automatically when $stream goes out of scope
```

## Stream — the core wrapper

`Stream` implements PSR-7's `StreamInterface` and wraps a `ResourceWrapper`. It tracks whether the stream is readable, writable, and seekable based on the resource's mode, and throws typed exceptions when operations fail:

```php
$stream = new Stream(LazyResource::for('php://memory', 'w+'));
$stream->write('hello world');
$stream->rewind();
echo $stream->getContents();  // 'hello world'
echo $stream->getSize();      // 11
```

Stream also implements `Copyable`, which adds a `copyTo()` method for efficiently copying data between streams in chunks.

## LazyResource — deferred opening

`LazyResource` doesn't open the underlying resource until it's actually used. This is useful when you're building stream objects during setup but might not read from them:

```php
// Resource is NOT opened yet
$resource = LazyResource::for('php://input', 'r+');

// Resource opens on first use
$stream = new Stream($resource);
$stream->read(100);  // now it opens
```

You can also create one from any callable:

```php
$resource = LazyResource::from(function () {
    return StreamResource::wrap(fopen('file.txt', 'r'));
});
```

## StreamResource — direct resource wrapper

`StreamResource` wraps a PHP resource handle directly. It proxies all the standard PHP stream functions (`fread`, `fwrite`, `fseek`, `feof`, etc.) through clean method calls:

```php
$resource = StreamResource::wrap(fopen('file.txt', 'r'));
$resource->fread(1024);
$resource->feof();
$resource->fclose();
```

Most of the time you'll use `LazyResource::for()` instead, which creates a `StreamResource` internally.

## CachingStream — seekable wrapper for non-seekable streams

Some streams can't be rewound — HTTP response bodies, `php://input`, piped data. `CachingStream` wraps one of these and caches everything it reads into a local `TemporaryStream`, making the data seekable after the fact:

```php
$body = new Stream(LazyResource::for('php://input', 'r+'));
$cached = CachingStream::fromStream($body);

$first = $cached->read(100);
$cached->rewind();              // works! data was cached
$again = $cached->read(100);    // same data, read from cache
```

Reads pull from the local cache first. When the cache runs out, it reads more from the remote source and caches that too. You can also force-cache everything at once with `consumeEverything()`.

## TemporaryStream — scratch space

`TemporaryStream` extends `Stream` and is backed by `php://temp` — PHP's in-memory temp storage that spills to disk when it gets large:

```php
$temp = TemporaryStream::getNew();
$temp->write('scratch data');
$temp->rewind();
echo $temp->getContents();  // 'scratch data'
```

This is used internally by `CachingStream` for its local cache, and is useful anywhere you need temporary stream storage.

## EmptyStream — the null object

`EmptyStream` implements `StreamInterface` but is always empty. Every read returns an empty string, every write is a no-op, and `eof()` is always true. It's used where a stream is required by the interface but there's no actual content — like the body of a HEAD response:

```php
$empty = new EmptyStream();
$empty->getSize();      // 0
$empty->getContents();  // ''
$empty->eof();          // true
```

## Bank — stream utilities

`Bank` provides static utility methods for working with streams:

```php
// Copy one stream to another
Bank::copyTo($source, $target);

// Delete original file after moving (used by UploadedFile)
Bank::deleteIfMoved($stream, $targetPath);
```

`copyTo()` checks if the source implements `Copyable` (which `Stream` does) for efficient chunk-based copying. Otherwise it falls back to a read loop.

## Exceptions

River defines specific exception types for stream errors:

- **DetachedSource** — the underlying resource was detached (via `detach()`)
- **InvalidSource** — the resource is not a valid stream
- **UnreadableStream** — attempted to read a non-readable stream
- **UnseekableStream** — attempted to seek a non-seekable stream
- **UnwritableStream** — attempted to write a non-writable stream

## At a glance

```
Stream (PSR-7 StreamInterface + Copyable)
|-- StreamResource (direct resource wrapper)
|-- LazyResource (deferred opening)
|-- CachingStream (seekable cache over non-seekable source)
|-- TemporaryStream (php://temp backed)
|-- EmptyStream (null object)
\-- Bank (copy utilities)
```
