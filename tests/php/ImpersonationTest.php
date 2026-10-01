<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use srag\Plugins\UserTakeOver\IGeneralRepository;
use srag\Plugins\UserTakeOver\ITranslator;

final class MemoryTakeOverSession extends ilUserTakeOverSessionWrapper
{
    public array $values = [];
    public int $saves = 0;
    public function has(string $key): bool { return isset($this->values[$key]); }
    public function retrieve(string $key, ILIAS\Refinery\Transformation $transformation) { return $transformation->transform($this->values[$key]); }
    public function set(string $key, $value): void
    {
        if ($value === null) { unset($this->values[$key]); } else { $this->values[$key] = $value; }
    }
    public function save(): void { $this->saves++; }
}

final class ImpersonationTest extends TestCase
{
    private function user(int $id): ilObjUser
    {
        $user = $this->createMock(ilObjUser::class);
        $user->method('getId')->willReturn($id);
        return $user;
    }

    private function handler(MemoryTakeOverSession $session, int $current, bool $allowed, int $original = 100): ilUserTakeOverImpersonationHandler
    {
        $repository = $this->createMock(IGeneralRepository::class);
        $repository->method('getUser')->willReturn($this->user($original));
        $translator = $this->createMock(ITranslator::class);
        $translator->method('txt')->willReturnArgument(0);
        $access = $this->createMock(ilUserTakeOverAccessHandler::class);
        $access->method('canCurrentUserImpersonate')->willReturn($allowed);
        $refinery = new ILIAS\Refinery\Factory(new ILIAS\Data\Factory(), $this->createMock(ILIAS\Language\Language::class));
        return new ilUserTakeOverImpersonationHandler(
            $repository, $translator, $access, $session, $refinery,
            $this->createMock(ilGlobalTemplateInterface::class),
            $this->createMock(ilCtrlInterface::class), $this->user($current)
        );
    }

    public function testTakeoverPersistsTargetAndOriginal(): void
    {
        $session = new MemoryTakeOverSession();
        $this->handler($session, 100, true)->impersonate($this->user(200));
        self::assertSame(['user_take_over_original_user_id' => 100, 'AccountId' => 200, '_authsession_user_id' => 200], $session->values);
        self::assertSame(1, $session->saves);
    }

    public function testRestorationDoesNotRequireTargetPluginPermission(): void
    {
        $session = new MemoryTakeOverSession();
        $session->values = ['user_take_over_original_user_id' => 100, 'AccountId' => 200, '_authsession_user_id' => 200];
        $this->handler($session, 200, false)->impersonate($this->user(100));
        self::assertSame(['AccountId' => 100, '_authsession_user_id' => 100], $session->values);
        self::assertSame(1, $session->saves);
    }

    public function testDeniedTransitionStopsEvenWhenRedirectReturns(): void
    {
        $session = new MemoryTakeOverSession();
        $this->handler($session, 100, false)->impersonate($this->user(200));
        self::assertSame([], $session->values);
        self::assertSame(0, $session->saves);
    }

    public function testNestedTakeoverCannotReplaceOriginal(): void
    {
        $session = new MemoryTakeOverSession();
        $session->values = ['user_take_over_original_user_id' => 100, '_authsession_user_id' => 200];
        $before = $session->values;
        $this->handler($session, 200, true)->impersonate($this->user(300));
        self::assertSame($before, $session->values);
        self::assertSame(0, $session->saves);
    }

    public function testMissingOrAnonymousTargetDoesNotSwitch(): void
    {
        foreach ([0, -1, ANONYMOUS_USER_ID] as $id) {
            $session = new MemoryTakeOverSession();
            $this->handler($session, 100, true)->impersonate($this->user($id));
            self::assertSame([], $session->values);
            self::assertSame(0, $session->saves);
        }
    }

    public function testDeletedOriginalCannotRestoreAnotherUser(): void
    {
        $session = new MemoryTakeOverSession();
        $session->values = ['user_take_over_original_user_id' => 100, '_authsession_user_id' => 200];
        $before = $session->values;
        $this->handler($session, 200, true, ANONYMOUS_USER_ID)->impersonate($this->user(100));
        self::assertSame($before, $session->values);
        self::assertSame(0, $session->saves);
    }
}
