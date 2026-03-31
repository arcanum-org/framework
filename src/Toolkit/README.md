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
```
