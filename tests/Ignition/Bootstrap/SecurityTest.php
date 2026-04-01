<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Container;
use Arcanum\Gather\Environment;
use Arcanum\Ignition\Bootstrap\Security;
use Arcanum\Toolkit\Encryption\Encryptor;
use Arcanum\Toolkit\Encryption\EncryptionKey;
use Arcanum\Toolkit\Encryption\SodiumEncryptor;
use Arcanum\Toolkit\Hashing\BcryptHasher;
use Arcanum\Toolkit\Hashing\Hasher;
use Arcanum\Toolkit\Signing\Signer;
use Arcanum\Toolkit\Signing\SodiumSigner;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Security::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(Environment::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(EncryptionKey::class)]
#[UsesClass(SodiumEncryptor::class)]
#[UsesClass(BcryptHasher::class)]
#[UsesClass(SodiumSigner::class)]
final class SecurityTest extends TestCase
{
    private function buildContainer(string $appKey): Container
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        $env = new Environment(['APP_KEY' => $appKey]);
        $container->instance(Environment::class, $env);

        return $container;
    }

    private function validBase64Key(): string
    {
        return 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public function testRegistersEncryptorInContainer(): void
    {
        $container = $this->buildContainer($this->validBase64Key());
        $bootstrapper = new Security();

        $bootstrapper->bootstrap($container);

        $this->assertInstanceOf(SodiumEncryptor::class, $container->get(Encryptor::class));
    }

    public function testRegistersSignerInContainer(): void
    {
        $container = $this->buildContainer($this->validBase64Key());
        $bootstrapper = new Security();

        $bootstrapper->bootstrap($container);

        $this->assertInstanceOf(SodiumSigner::class, $container->get(Signer::class));
    }

    public function testRegistersHasherInContainer(): void
    {
        $container = $this->buildContainer($this->validBase64Key());
        $bootstrapper = new Security();

        $bootstrapper->bootstrap($container);

        $this->assertInstanceOf(BcryptHasher::class, $container->get(Hasher::class));
    }

    public function testThrowsOnMissingAppKey(): void
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);
        $container->instance(Environment::class, new Environment([]));

        $bootstrapper = new Security();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY is missing');
        $bootstrapper->bootstrap($container);
    }

    public function testThrowsOnEmptyAppKey(): void
    {
        $container = $this->buildContainer('');

        $bootstrapper = new Security();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY is missing');
        $bootstrapper->bootstrap($container);
    }

    public function testThrowsOnInvalidKeyLength(): void
    {
        $container = $this->buildContainer('base64:' . base64_encode('too-short'));

        $bootstrapper = new Security();

        $this->expectException(\InvalidArgumentException::class);
        $bootstrapper->bootstrap($container);
    }

    public function testAcceptsKeyWithoutBase64Prefix(): void
    {
        $key = base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $container = $this->buildContainer($key);
        $bootstrapper = new Security();

        $bootstrapper->bootstrap($container);

        $this->assertInstanceOf(SodiumEncryptor::class, $container->get(Encryptor::class));
    }

    public function testEncryptorAndSignerAreUsable(): void
    {
        $container = $this->buildContainer($this->validBase64Key());
        $bootstrapper = new Security();
        $bootstrapper->bootstrap($container);

        /** @var Encryptor $encryptor */
        $encryptor = $container->get(Encryptor::class);
        $this->assertSame('hello', $encryptor->decrypt($encryptor->encrypt('hello')));

        /** @var Signer $signer */
        $signer = $container->get(Signer::class);
        $sig = $signer->sign('payload');
        $this->assertTrue($signer->verify('payload', $sig));
    }
}
