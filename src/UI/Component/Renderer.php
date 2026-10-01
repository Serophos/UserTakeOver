<?php

namespace srag\Plugins\UserTakeOver\UI\Component;

use ILIAS\UI\Component\Table\PresentationRow;
use ILIAS\UI\Implementation\Render\DecoratedRenderer;

/**
 * @author Fabian Schmid <fabian@sr.solutions>
 */
class Renderer extends DecoratedRenderer
{
    protected function manipulateRendering($component, \ILIAS\UI\Renderer $root): ?string
    {
        if ($component instanceof LegacySubSlate) {
            return $root->render($component->getContent());
        }

        if (
            $component instanceof PresentationRow
            && method_exists($component, 'getHeadline')
            && method_exists($component, 'getSubheadline')
            && $component->getHeadline() === 'UserTakeOver'
            && $component->getSubheadline() === 'UserInterfaceHook'
        ) {
            return $this->renderDefault(
                $component->withHeadline(\ilUserTakeOverPlugin::DISPLAY_NAME),
                $root
            );
        }

        return null;
    }
}
