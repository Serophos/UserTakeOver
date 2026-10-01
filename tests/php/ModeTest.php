<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ILIAS\GlobalScreen\Scope\Layout\Factory\ModificationFactory;
use ILIAS\GlobalScreen\Scope\Layout\Factory\PageBuilderModification;
use ILIAS\GlobalScreen\Scope\Layout\Provider\ModificationProvider;
use ILIAS\GlobalScreen\ScreenContext\Stack\ContextCollection;
use ILIAS\GlobalScreen\ScreenContext\Stack\CalledContexts;

final class ModeTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'no takeover' => [false, false, false],
            'takeover indicator' => [true, false, true],
            'nested core mode wins' => [true, true, false],
        ];
    }

    #[DataProvider('cases')]
    public function testModePrecedence(bool $active, bool $nested, bool $expected): void
    {
        $provider = (new ReflectionClass(ilUserTakeOverImpersonationPageModificationProvider::class))->newInstanceWithoutConstructor();
        $handler = $this->createMock(ilUserTakeOverImpersonationHandler::class);
        $handler->method('isImpersonationActive')->willReturn($active);
        $contexts = $this->createMock(ContextCollection::class);
        $contexts->method('hasMatch')->willReturn(true);
        $core = $this->createMock(ModificationProvider::class);
        $core->method('isInterestedInContexts')->willReturn($contexts);
        $core->method('getPageBuilderDecorator')->willReturn($nested ? new PageBuilderModification() : null);
        foreach ([
            'is_initiaised' => true, 'impersonation_handler' => $handler,
            'conflicting_core_providers' => [$core], 'factory' => new ModificationFactory(),
        ] as $name => $value) {
            (new ReflectionProperty($provider, $name))->setValue($provider, $value);
        }
        $result = $provider->getPageBuilderDecorator((new ReflectionClass(CalledContexts::class))->newInstanceWithoutConstructor());
        self::assertSame($expected, $result !== null);
        if ($result !== null) {
            self::assertTrue($result->hasValidModification());
        }
    }

    public function testMetabarItemSupportsCoreDecoratorsAndRendersSlate(): void
    {
        $previous = $GLOBALS['DIC'] ?? null;
        $GLOBALS['DIC'] = new ILIAS\DI\Container();
        $signals = new ILIAS\UI\Implementation\Component\SignalGenerator();
        $GLOBALS['DIC']['ui.signal_generator'] = $signals;
        try {
            $item = new class (
                $this->createMock(ILIAS\GlobalScreen\Identification\IdentificationInterface::class),
                $this->createMock(ILIAS\UI\Factory::class)
            ) extends srag\Plugins\UserTakeOver\GlobalScreen\LegacySubItem {};
            $item = $item->withTitle('Search')
                ->withSymbol($this->createMock(ILIAS\UI\Component\Symbol\Symbol::class))
                ->withContent(new ILIAS\UI\Implementation\Component\Legacy\Content('Search content', $signals));
            self::assertSame($item, $item->addSymbolDecorator(static fn(ILIAS\UI\Component\Symbol\Symbol $symbol): ILIAS\UI\Component\Symbol\Symbol => $symbol));
            self::assertSame([], $item->getTopics());
            $component = $item->getRenderer()->getComponentForItem($item);
            self::assertInstanceOf(srag\Plugins\UserTakeOver\UI\Component\LegacySubSlate::class, $component);
            self::assertSame('Search content', $component->getContent()->getContent());
        } finally {
            $GLOBALS['DIC'] = $previous;
        }
    }

    public function testPluginOverviewUsesDisplayName(): void
    {
        $default = $this->createMock(ILIAS\UI\Renderer::class);
        $default->expects(self::once())
            ->method('render')
            ->with(self::callback(static function ($component): bool {
                return $component instanceof ILIAS\UI\Implementation\Component\Table\PresentationRow
                    && $component->getHeadline() === ilUserTakeOverPlugin::DISPLAY_NAME
                    && $component->getSubheadline() === 'UserInterfaceHook';
            }))
            ->willReturn('rendered');

        $row = (new ILIAS\UI\Implementation\Component\Table\PresentationRow(
            new ILIAS\UI\Implementation\Component\SignalGenerator(),
            'plugins'
        ))->withHeadline('UserTakeOver')->withSubheadline('UserInterfaceHook');

        $renderer = new srag\Plugins\UserTakeOver\UI\Component\Renderer($default);
        self::assertSame('rendered', $renderer->render($row));
    }
}
