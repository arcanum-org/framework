<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Helpers;

use Arcanum\Session\ActiveSession;
use Arcanum\Session\CsrfToken;
use Arcanum\Session\Session;
use Arcanum\Session\SessionId;
use Arcanum\Shodo\Helpers\CsrfHelper;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CsrfHelper::class)]
#[UsesClass(ActiveSession::class)]
#[UsesClass(Session::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(CsrfToken::class)]
#[UsesClass(Random::class)]
final class CsrfHelperTest extends TestCase
{
    private function helper(): CsrfHelper
    {
        $session = new Session(SessionId::generate());
        $active = new ActiveSession();
        $active->set($session);

        return new CsrfHelper($active);
    }

    public function testFieldReturnsHiddenInput(): void
    {
        $helper = $this->helper();

        $result = $helper->field();

        $this->assertStringStartsWith('<input type="hidden" name="_token" value="', $result);
        $this->assertStringEndsWith('">', $result);
    }

    public function testTokenReturnsRawString(): void
    {
        $helper = $this->helper();

        $result = $helper->token();

        $this->assertSame(64, strlen($result));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result);
    }

    public function testTokenMatchesField(): void
    {
        $helper = $this->helper();

        $token = $helper->token();
        $html = $helper->field();

        $this->assertStringContainsString($token, $html);
    }
}
