<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use srag\Plugins\UserTakeOver\Group\Group;
use srag\Plugins\UserTakeOver\ITranslator;
use srag\Plugins\UserTakeOver\UI\Form\Group\GroupFormBuilder;
use ILIAS\UI\Implementation\Component\Input\Field;

final class CompatibilityTest extends TestCase
{
    public function testEveryPluginClassLoadsAgainstCore(): void
    {
        $map = require dirname(__DIR__, 2) . '/vendor/composer/autoload_classmap.php';
        $count = 0;
        foreach ($map as $class => $file) {
            if (str_contains($file, '/UserTakeOver/') && !str_contains($file, '/vendor/')) {
                self::assertTrue(class_exists($class) || interface_exists($class) || trait_exists($class), $class);
                $count++;
            }
        }
        self::assertGreaterThanOrEqual(46, $count);
    }

    public function testGroupFormUsesSupportedAutocompleteAndPreservesMembers(): void
    {
        $language = $this->createMock(ILIAS\Language\Language::class);
        $language->method('txt')->willReturnArgument(0);
        $data = new ILIAS\Data\Factory();
        $refinery = new ILIAS\Refinery\Factory($data, $language);
        $signals = new ILIAS\UI\Implementation\Component\SignalGenerator();
        $fields = new Field\Factory(
            new Field\Node\Factory(),
            $this->createMock(ILIAS\UI\Implementation\Component\Input\UploadLimitResolver::class),
            $signals, $data, $refinery, $language
        );
        $forms = new ILIAS\UI\Implementation\Component\Input\Container\Form\Factory($fields, $signals);
        $translator = $this->createMock(ITranslator::class);
        $translator->method('txt')->willReturnArgument(0);
        $builder = new GroupFormBuilder(
            new Group(1, 'Support', '', [4], true, [42, 43]), [4 => 'Support'],
            $translator, $forms, $fields, $refinery,
            'https://example.org/ilias.php?cmd=save', 'https://example.org/ilias.php?cmd=findMembers',
            ['42' => 'first (First User)', '43' => 'second (Second User)']
        );
        $form = $builder->getForm();
        $members = $form->getInputs()[ITranslator::GROUP]->getInputs()[ITranslator::GROUP_MEMBERS];
        self::assertSame(['42', '43'], $members->getValue());
        self::assertSame('usrto_search_term', $members->getAsyncAutocompleteToken()->getName());
        self::assertTrue($members->areUserCreatedTagsAllowed());
        self::assertNotNull($members->getAsyncAutocompleteEndpoint());
    }

    public function testLegacySlateAcceptsCoreContent(): void
    {
        $content = new ILIAS\UI\Implementation\Component\Legacy\Content('<p>Search</p>', new ILIAS\UI\Implementation\Component\SignalGenerator());
        $slate = new srag\Plugins\UserTakeOver\UI\Component\LegacySubSlate(
            new ILIAS\UI\Implementation\Component\SignalGenerator(), 'Search',
            $this->createMock(ILIAS\UI\Component\Symbol\Symbol::class), $content
        );
        self::assertSame($content, $slate->getContent());
    }
}
