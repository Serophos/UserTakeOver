<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use srag\Plugins\UserTakeOver\IGeneralRepository;

final class SearchTest extends TestCase
{
    public function testMissingAndBlankTermsReturnEmptyJsonWithoutSearching(): void
    {
        foreach ([[], ['usrto_search_term' => ''], ['usrto_search_term' => '   ']] as $query) {
            $gui = (new ReflectionClass(ilUserTakeOverGroupGUI::class))->newInstanceWithoutConstructor();
            $repository = $this->createMock(IGeneralRepository::class);
            $repository->expects(self::never())->method('findUsers');
            $http = $this->createMock(ILIAS\HTTP\GlobalHttpState::class);
            $http->method('response')->willReturn(new GuzzleHttp\Psr7\Response());
            $http->expects(self::once())->method('saveResponse')->willReturnCallback(static function ($response): void {
                self::assertSame('[]', (string) $response->getBody());
                self::assertSame('application/json; charset=utf-8', $response->getHeaderLine('Content-Type'));
            });
            $http->expects(self::once())->method('sendResponse');
            $http->expects(self::once())->method('close')->willThrowException(new RuntimeException('response closed'));
            foreach ([
                'general_repository' => $repository, 'http' => $http,
                'get_request' => new ILIAS\HTTP\Wrapper\ArrayBasedRequestWrapper($query),
                'refinery' => new ILIAS\Refinery\Factory(new ILIAS\Data\Factory(), $this->createMock(ILIAS\Language\Language::class)),
            ] as $property => $value) {
                (new ReflectionProperty($gui, $property))->setValue($gui, $value);
            }
            try {
                (new ReflectionMethod($gui, 'findTargets'))->invoke($gui);
                self::fail('The response must close.');
            } catch (RuntimeException $e) {
                self::assertSame('response closed', $e->getMessage());
            }
        }
    }
}
