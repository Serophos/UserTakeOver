<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use srag\Plugins\UserTakeOver\Group\Group;
use srag\Plugins\UserTakeOver\Group\IGroupRepository;
use srag\Plugins\UserTakeOver\Settings\Settings;

final class AccessTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'administrator to regular user' => [true, false, false, false, [], false, true],
            'administrator target forbidden' => [true, false, true, false, [], false, false],
            'administrator target enabled' => [true, false, true, true, [], false, true],
            'unprivileged user' => [false, false, false, false, [], false, false],
            'allowed global role' => [false, true, false, false, [], false, true],
            'member allowed' => [false, true, false, false, [new Group(1, 'A', '', [], true, [100, 200])], false, true],
            'non-member denied' => [false, true, false, false, [new Group(1, 'A', '', [], true, [100])], false, false],
            'member and role allowed' => [false, true, false, false, [new Group(1, 'A', '', [4], true, [100, 200])], true, true],
            'member without required role denied' => [false, true, false, false, [new Group(1, 'A', '', [4], true, [100, 200])], false, false],
            // Retain the existing cross-group accumulation of permissions.
            'permissions combined across groups' => [false, true, false, false, [new Group(1, 'A', '', [4], true, [200]), new Group(2, 'B', '', [5], true, [100])], true, true],
            'unrestricted group' => [false, true, false, false, [new Group(1, 'A', '', [], false, [100])], false, true],
        ];
    }

    #[DataProvider('cases')]
    public function testPermissions(bool $admin, bool $allowedRole, bool $targetAdmin, bool $allowAdmins, array $groups, bool $targetRole, bool $expected): void
    {
        $repository = $this->createMock(IGroupRepository::class);
        $repository->method('getGroupsOfUser')->willReturn($groups);
        $review = $this->createMock(ilRbacReview::class);
        $review->method('isAssigned')->willReturnCallback(static fn(int $id): bool => $id === 100 ? $admin : $targetAdmin);
        $review->method('isAssignedToAtLeastOneGivenRole')->willReturnCallback(static fn(int $id): bool => $id === 100 ? $allowedRole : $targetRole);
        $current = $this->createMock(ilObjUser::class);
        $current->method('getId')->willReturn(100);
        $target = $this->createMock(ilObjUser::class);
        $target->method('getId')->willReturn(200);
        $handler = new ilUserTakeOverAccessHandler(
            $repository, new Settings([4], $allowAdmins), new ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper([]),
            new ILIAS\Refinery\Factory(new ILIAS\Data\Factory(), $this->createMock(ILIAS\Language\Language::class)),
            $current, $review, $this->createMock(ilRbacSystem::class)
        );
        self::assertSame($expected, $handler->canCurrentUserImpersonate($target));
    }
}
