<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

final class SessionPersistenceTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRealSessionWriterReceivesDecodableSessionAndTargetId(): void
    {
        $GLOBALS['DIC'] = new ILIAS\DI\Container();
        $database = $this->createMock(ilDBInterface::class);
        $database->method('query')->willReturn($this->createMock(ilDBStatement::class));
        $database->method('numRows')->willReturn(1);
        $written = null;
        $database->expects(self::once())->method('update')->willReturnCallback(
            static function (string $table, array $fields) use (&$written): int {
                self::assertSame('usr_session', $table);
                self::assertSame(200, $fields['user_id'][1]);
                $written = $fields['data'][1];
                return 1;
            }
        );
        $GLOBALS['DIC']['ilDB'] = $database;
        $ini = $this->createMock(ilIniFile::class);
        $ini->method('readVariable')->willReturn('');
        $GLOBALS['DIC']['ilClientIniFile'] = $ini;
        $cron = $this->createMock(ILIAS\Cron\Job\JobManager::class);
        $cron->method('isJobActive')->willReturn(true);
        $GLOBALS['DIC']['cron.manager'] = $cron;
        ilContext::init(ilContext::CONTEXT_UNITTEST);
        session_start(['use_cookies' => false, 'cache_limiter' => '', 'save_path' => sys_get_temp_dir()]);
        $session = new ilUserTakeOverSessionWrapper();
        $session->set('_authsession_user_id', 200);
        $session->set('user_take_over_original_user_id', 100);
        $session->save();
        self::assertIsString($written);
        $_SESSION = [];
        self::assertTrue(session_decode($written));
        self::assertSame(200, $_SESSION['_authsession_user_id']);
        self::assertSame(100, $_SESSION['user_take_over_original_user_id']);
        self::assertSame(PHP_SESSION_ACTIVE, session_status(), 'Flash messages must still be writable.');
        session_destroy();
    }
}
