<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use srag\Plugins\UserTakeOver\Group\Group;
use srag\Plugins\UserTakeOver\Settings\Settings;

/** Executes the real ILIAS database implementation on disposable, uniquely named tables. */
final class DatabaseTest extends TestCase
{
    private ?ilDBPdoMySQLInnoDB $native = null;
    private ilDBInterface $database;
    private array $tables = [];

    protected function setUp(): void
    {
        $ini_path = getenv('USRTO_TEST_CLIENT_INI');
        if (!$ini_path) {
            self::markTestSkipped('Set USRTO_TEST_CLIENT_INI to run isolated database integration tests.');
        }
        $ini = new ilIniFile($ini_path);
        $ini->read();
        $this->native = new ilDBPdoMySQLInnoDB();
        $this->native->initFromIniFile($ini);
        $this->native->connect();
        $prefix = 'ut' . bin2hex(random_bytes(4));
        foreach (['ui_uihk_usrto_config_n', 'ui_uihk_usrto_config', 'ui_uihk_usrto_member', 'ui_uihk_usrto_grp', 'usrto_config'] as $index => $name) {
            $this->tables[$name] = $prefix . '_' . $index;
        }
        $this->database = $this->createMock(ilDBInterface::class);
        // Only rewrite exact plugin table identifiers. All SQL execution and schema handling use core ILIAS.
        foreach (['tableExists', 'tableColumnExists', 'createTable', 'createSequence', 'addPrimaryKey', 'addTableColumn', 'dropTable', 'dropSequence', 'query', 'queryF', 'manipulate', 'manipulateF', 'insert', 'update', 'nextId', 'fetchAll'] as $method) {
            $this->database->method($method)->willReturnCallback(function (...$args) use ($method) {
                foreach ($args as &$arg) {
                    if (is_string($arg)) { $arg = strtr($arg, $this->tables); }
                }
                return $this->native->{$method}(...$args);
            });
        }
    }

    protected function tearDown(): void
    {
        if ($this->native) {
            foreach ($this->tables as $table) {
                $this->native->manipulate('DROP TABLE IF EXISTS `' . $table . '`');
                $this->native->manipulate('DROP TABLE IF EXISTS `' . $table . '_seq`');
            }
        }
    }

    private function migrate(int $from = 1, int $to = 10): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/sql/dbupdate.php');
        preg_match_all('/<#(\d+)>\s*<\?php(.*?)\?>/s', $source, $steps, PREG_SET_ORDER);
        $ilDB = $this->database;
        foreach ($steps as $step) {
            if ((int) $step[1] >= $from && (int) $step[1] <= $to) {
                eval($step[2]);
            }
        }
    }

    public function testFreshInstallationAndSettingsPersistence(): void
    {
        $this->migrate();
        self::assertTrue($this->database->tableExists('ui_uihk_usrto_grp'));
        self::assertTrue($this->database->tableColumnExists('ui_uihk_usrto_grp', 'allowed_roles'));
        $settings = new ilUserTakeOverSettingsRepository($this->database);
        self::assertFalse($settings->get()->isAllowImpersonationOfAdmins());
        $settings->store(new Settings([4, 5], true));
        $this->migrate();
        self::assertTrue($settings->get()->isAllowImpersonationOfAdmins());
        self::assertSame(['4', '5'], $settings->get()->getAllowedGlobalRoleIds());
    }

    public function testGroupCrudPreservesRestrictionsAndMembers(): void
    {
        $this->migrate();
        $repository = new ilUserTakeOverGroupRepository($this->database);
        $group = new Group(null, 'Support', 'Description', [4, 5], true, [100, 200]);
        $repository->storeGroup($group);
        $loaded = $repository->getGroup($group->getId());
        self::assertSame([100, 200], $loaded->getGroupMembers());
        self::assertSame([4, 5], $loaded->getAllowedRoles());
        self::assertTrue($loaded->isRestrictedToMembers());
        $loaded->setTitle('Updated')->setGroupMembers([200]);
        $repository->storeGroup($loaded);
        self::assertSame([200], $repository->getGroup($group->getId())->getGroupMembers());
        self::assertSame('Updated', $repository->getGroup($group->getId())->getTitle());
        $repository->deleteGroup($loaded);
        self::assertNull($repository->getGroup($group->getId()));
        self::assertSame([], $this->database->fetchAll($this->database->query('SELECT * FROM ui_uihk_usrto_member')));
    }

    public function testHistoricalDemoGroupMigration(): void
    {
        $this->database->createTable('ui_uihk_usrto_config', ['demo_group' => ['type' => 'text', 'length' => 4000]]);
        $this->database->insert('ui_uihk_usrto_config', ['demo_group' => ['text', '[100,200]']]);
        $this->migrate();
        $repository = new ilUserTakeOverGroupRepository($this->database);
        $groups = $repository->getAllGroups();
        self::assertCount(1, $groups);
        self::assertSame([100, 200], $groups[0]->getGroupMembers());
        self::assertTrue($groups[0]->isRestrictedToMembers());
        self::assertFalse($this->database->tableExists('ui_uihk_usrto_config'));
        $this->migrate();
        self::assertCount(1, $repository->getAllGroups());
    }

    public function testExistingVersionNineDataSurvivesUpdate(): void
    {
        $this->migrate();
        $repository = new ilUserTakeOverGroupRepository($this->database);
        $group = new Group(null, 'Existing', '', [4], true, [100, 200]);
        $repository->storeGroup($group);
        $settings = new ilUserTakeOverSettingsRepository($this->database);
        $settings->store(new Settings([4], false));
        // Already-applied steps must not be replayed by a version-only upgrade.
        $this->migrate(11);
        self::assertSame([100, 200], $repository->getGroup($group->getId())->getGroupMembers());
        self::assertSame([4], $repository->getGroup($group->getId())->getAllowedRoles());
        self::assertSame(['4'], $settings->get()->getAllowedGlobalRoleIds());
    }
}
