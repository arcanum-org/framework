# Benchmarking Guide

Methodology for measuring per-component PHP code paths in Arcanum. Use this when you want to know "how fast is *this class / method / pipeline*?", not "how many requests/second can the framework serve?" Those are different questions and need different tools.

**Why per-script (and not HTTP).** The HTTP-level approach (nginx + FPM + `ab`/`hey`) suffers from systemic noise sources that take more effort to control than the code-under-test does to measure: TIME_WAIT exhaustion, FPM worker scheduling, opcache warmup curves, JIT trace cache settling, FastCGI overhead, macOS thermal management, and a bimodal slow/fast-band signal we never fully explained. Per-script benches dodge all of that by launching a fresh `php` process per measurement and timing the whole invocation. Trade-off: you can't measure sustained-process effects, only per-component cost. For framework-level questions that's the right trade.

## Tool

**hyperfine** (`brew install hyperfine`) — runs each command many times, reports `mean ± stddev`, flags outliers and "first run was significantly slower" warnings. Trust its statistics; don't average runs by hand.

## Required environment guards

Every bench script must start with this block. The guards catch the silent failure modes *before* measurement starts:

```php
<?php
declare(strict_types=1);

if (!extension_loaded('Zend OPcache') || !ini_get('opcache.enable_cli')) {
    throw new RuntimeException('Bench requires opcache + opcache.enable_cli=1');
}
if (extension_loaded('xdebug')) {
    throw new RuntimeException('Bench must run without xdebug loaded');
}
$status = opcache_get_status(false);
if (!is_array($status) || ($status['jit']['enabled'] ?? false) !== true) {
    throw new RuntimeException('Bench requires JIT enabled — some extension is hooking zend_execute_ex (pcov? blackfire?)');
}
```

The **JIT-enabled check** is the load-bearing one. Any extension that overrides `zend_execute_ex` (xdebug, pcov, blackfire, ...) silently disables JIT, which means every measurement is wrong by ~30%+ before you even start. The check catches all of them at once. Without it the methodology is unsound.

## Required php flags on every invocation

```
php -d opcache.enable_cli=1 \
    -d opcache.jit=tracing \
    -d opcache.jit_buffer_size=64M \
    -d pcov.enabled=0 \
    bench/foo.php
```

`pcov.enabled=0` is the critical one for this machine — pcov ships enabled for `composer phpunit` coverage and it hooks `zend_execute_ex`, killing JIT. The flag disables it per-invocation; system state is untouched. If a different machine has blackfire or some other offender, add the equivalent disable flag.

**Don't disable pcov system-wide.** The fail-loud JIT guard means forgetting the flag throws immediately, so per-invocation is always safe and "remember to re-enable" is never a risk.

**zsh gotcha.** zsh doesn't word-split unquoted `$VAR` like bash does. Stashing the flags in a `PHP_OPTS=...` variable and then writing `php $PHP_OPTS bench/foo.php` will pass *one* big string argument and PHP will silently ignore most of it — the JIT guard will then catch it, but you'll waste time debugging. Use an array, `${=PHP_OPTS}`, or just inline the flags. Inline is simplest and what hyperfine wants anyway.

## Iteration tuning

PHP startup is ~50ms per `php script.php` invocation. To make startup negligible, **each bench must run for 800–1000ms or longer**. At 1000ms total, 50ms of startup is 5% — small enough to ignore.

Tune iteration count by running hyperfine itself with a tiny iteration set:

```
hyperfine --runs 3 --warmup 1 \
  'php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M -d pcov.enabled=0 bench/foo.php'
```

Read the mean, scale `$iterations` in the script up or down, repeat until you land in the band. **Do not use `/usr/bin/time` for tuning** — it's a different measurement environment from the real run. Use hyperfine for both tuning and measurement so both happen under identical conditions.

## Defeating opcache optimization

Opcache will inline trivial returns and dead-code-eliminate any work whose result isn't observed. Two rules:

1. **Functions with constant returns must include a non-foldable expression.** Example:
   ```php
   function foo(): int {
       ['opcache cannot inline this'][0]; // breaks constant folding
       return 1;
   }
   ```
   Without this, opcache turns `$num += foo()` into `$num += 1` and you measure addition, not function-call overhead.
2. **The accumulator must be observed at the end.** `var_dump($accumulator)` works. Without it, opcache will dead-code-eliminate the entire loop body and you'll measure ~nothing.

Both are easy to forget, both fail silently — the bench just runs ~10x faster than reality and you don't notice until the numbers stop making sense.

## Reading hyperfine output

- **Trust the mean +/- stddev.** No more averaging-by-hand or picking medians from 5 runs. One hyperfine invocation per bench.
- **Stop on warnings.** If hyperfine prints "Statistical outliers detected" or "The first benchmark run was significantly slower," the dev machine was under load. Stop, wait, rerun until clean. Never report numbers from a run that warned.
- **Comparing benches with different iteration counts:** hyperfine's `X times faster than Y` summary line is **meaningless** in that case. It compares total wall time, not per-iteration cost. Either pin all benches to the same iteration count, or compute per-iteration cost yourself: `(mean - ~50ms PHP startup) / iterations`.

## What this method can NOT measure

- **Sustained-process effects.** FPM worker warmup, opcache hit-rate over time, JIT trace cache settling, anything that takes hundreds of requests to stabilize. One process per measurement means cold-start every time.
- **HTTP/transport overhead.** No FastCGI, no nginx, no socket churn. If a real request's bottleneck lives in the FPM pipeline, this won't see it.
- **Code paths cheaper than ~50-100 ns per iteration.** Below that you can't get above the noise floor without absurd iteration counts.

For HTTP-level questions you'd need a different methodology — and any such methodology has to control for the bimodal slow/fast-band signal first. We don't have a good answer for that yet.

## Examples

`bench/heavy_validation.php`, `bench/many_params.php`, `bench/full_pipeline.php` — three working bench scripts covering the validator, hydrator, and the full Conveyor middleware pipeline (`Hydrator -> ValidationGuard -> AuthorizationGuard -> TransportGuard -> Handler`). Use them as templates. The full pipeline lands at ~8.6 us per dispatch on this machine, which means the framework's per-command overhead is essentially free compared to any I/O a real handler will do.
