# Arcanum Toolkit

Toolkit is the string utility package. It provides case conversion and ASCII transliteration — the small building blocks that other packages rely on for convention-based naming.

## Strings

All methods are static on the `Strings` class. No instantiation needed.

### Case conversion

```php
use Arcanum\Toolkit\Strings;

Strings::pascal('submit-payment');  // 'SubmitPayment'
Strings::camel('submit-payment');   // 'submitPayment'
Strings::kebab('SubmitPayment');    // 'submit-payment'
Strings::snake('SubmitPayment');    // 'submit_payment'
Strings::title('submit payment');   // 'Submit Payment'
```

These are used throughout the framework:
- **Atlas** uses `pascal()` to convert URL path segments to PascalCase class names
- **Atlas** uses `kebab()` to convert PascalCase page filenames to URL paths
- **Linked** is the general form — `kebab()` and `snake()` both delegate to it with different delimiters

### Custom delimiters

```php
Strings::linked('SubmitPayment', '.');  // 'submit.payment'
Strings::linked('SubmitPayment', '/');  // 'submit/payment'
```

`linked()` detects word boundaries from uppercase letters, inserts the delimiter, and lowercases the result. If the string is already all lowercase, it returns early without processing.

### ASCII transliteration

```php
Strings::ascii('äöüÄÖÜß');          // 'aouAOUss'
Strings::ascii('café', 'en');        // 'cafe'
Strings::ascii('Ñoño', 'es');        // 'Nono'
```

Converts Unicode characters to their ASCII equivalents using the `voku/portable-ascii` library. The optional language parameter selects language-specific transliteration rules.

## Encryption

Authenticated symmetric encryption using libsodium's XSalsa20-Poly1305.

```php
use Arcanum\Toolkit\Encryption\EncryptionKey;
use Arcanum\Toolkit\Encryption\SodiumEncryptor;
use Arcanum\Toolkit\Encryption\DecryptionFailed;

// Create a key (32 bytes, typically from APP_KEY in .env)
$key = EncryptionKey::fromBase64($base64EncodedKey);

$encryptor = new SodiumEncryptor($key);

// Encrypt — produces a self-contained base64 envelope
$ciphertext = $encryptor->encrypt('sensitive data');

// Decrypt — throws DecryptionFailed on any error
$plaintext = $encryptor->decrypt($ciphertext);
```

Each encryption generates a fresh 24-byte nonce. Two encryptions of the same plaintext produce different envelopes. Decryption verifies integrity via Poly1305 before returning plaintext.

The `Encryptor` interface allows swapping implementations:

```php
use Arcanum\Toolkit\Encryption\Encryptor;

function processSecret(Encryptor $encryptor, string $data): string
{
    return $encryptor->encrypt($data);
}
```

In a bootstrapped application, `Encryptor` is automatically available from the container via `Bootstrap\Security`.

## Hashing

Password hashing wrapping PHP's `password_hash` / `password_verify`.

```php
use Arcanum\Toolkit\Hashing\BcryptHasher;
use Arcanum\Toolkit\Hashing\Argon2Hasher;

// Bcrypt (default, cost 12)
$hasher = new BcryptHasher();
$hash = $hasher->hash('my-password');
$hasher->verify('my-password', $hash);   // true
$hasher->verify('wrong', $hash);          // false

// Argon2id (recommended when available)
$hasher = new Argon2Hasher(memoryCost: 65536, timeCost: 4);
$hash = $hasher->hash('my-password');
```

Transparent algorithm migration via `needsRehash()`:

```php
if ($hasher->verify($password, $storedHash)) {
    if ($hasher->needsRehash($storedHash)) {
        $newHash = $hasher->hash($password);
        // Update storage with $newHash
    }
}
```

The `Hasher` interface is registered in the container by `Bootstrap\Security` (defaults to `BcryptHasher` — override to use `Argon2Hasher`).

## Random

Cryptographically secure random value generation via PHP's `random_bytes()`.

```php
use Arcanum\Toolkit\Random;

Random::bytes(32);      // 32 raw random bytes
Random::hex(32);        // 64-char hex string (32 bytes)
Random::hex();          // default: 32 bytes → 64 hex chars
Random::base64url(32);  // URL-safe base64, no padding (43 chars)
```

Use these for CSRF tokens, API keys, session IDs, nonces, and any other random value.

## HMAC Signing

Message authentication using libsodium's `crypto_auth` (HMAC-SHA512/256).

```php
use Arcanum\Toolkit\Signing\SodiumSigner;

$signer = new SodiumSigner($keyBytes); // 32-byte key

$signature = $signer->sign('payload');           // hex string
$signer->verify('payload', $signature);          // true
$signer->verify('tampered', $signature);         // false
```

Verification is constant-time (via `sodium_crypto_auth_verify`) to prevent timing attacks. Signatures are hex-encoded for safe use in URLs, headers, and cookies.

The `Signer` interface is registered in the container by `Bootstrap\Security`.

## At a glance

```
Strings (final, all static methods)
|-- ascii(string, language)   — Unicode → ASCII
|-- pascal(string)            — PascalCase
|-- camel(string)             — camelCase
|-- kebab(string)             — kebab-case (via linked)
|-- snake(string)             — snake_case (via linked)
|-- linked(string, delimiter) — custom-delimiter-case
\-- title(string)             — Title Case

Encryption/
|-- Encryptor (interface)        — encrypt/decrypt contract
|-- SodiumEncryptor              — XSalsa20-Poly1305 via libsodium
|-- EncryptionKey                — 32-byte key value object
\-- DecryptionFailed             — thrown on any decryption error

Hashing/
|-- Hasher (interface)           — hash/verify/needsRehash contract
|-- BcryptHasher                 — PASSWORD_BCRYPT wrapper
\-- Argon2Hasher                 — PASSWORD_ARGON2ID wrapper

Random (final, all static methods)
|-- bytes(length)                — raw random bytes
|-- hex(bytes)                   — hex-encoded random string
\-- base64url(bytes)             — URL-safe base64 random string

Signing/
|-- Signer (interface)           — sign/verify contract
\-- SodiumSigner                 — HMAC-SHA512/256 via libsodium
```
