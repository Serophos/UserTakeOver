<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use srag\Plugins\UserTakeOver\GlobalScreen\SearchItem;

final class TemplateTest extends TestCase
{
    public function testSearchLoadsItsTemplateThroughIlias(): void
    {
        $previous = $GLOBALS['DIC'] ?? null;
        $GLOBALS['DIC'] = new ILIAS\DI\Container();
        $factory = $this->createMock(ILIAS\UI\Factory::class);
        $factory->method('legacy')->willReturn(new ILIAS\UI\Implementation\Component\Legacy\Factory(
            new ILIAS\UI\Implementation\Component\SignalGenerator()
        ));
        $components = $this->createMock(ilComponentFactory::class);
        $components->method('getActivePluginsInSlot')->willReturnCallback(static function (): Generator {
            yield from [];
        });
        $GLOBALS['DIC']['ui.factory'] = $factory;
        $GLOBALS['DIC']['component.factory'] = $components;
        try {
            $item = (new SearchItem(
                $this->createMock(ILIAS\GlobalScreen\Identification\IdentificationInterface::class),
                $factory
            ))->withUrl('/search?a=1&b=2')->withPlaceholder('Find "user"');
            $html = $item->getContent()->getContent();
            self::assertStringContainsString('data-role="uto-search-input-field"', $html);
            self::assertStringContainsString('/search?a=1&amp;b=2', $html);
            self::assertStringContainsString('Find &quot;user&quot;', $html);
        } finally {
            $GLOBALS['DIC'] = $previous;
        }
    }
}
