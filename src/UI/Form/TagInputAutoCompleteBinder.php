<?php

declare(strict_types=1);

namespace srag\Plugins\UserTakeOver\UI\Form;

use ILIAS\Data\URI;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\Component\Input\Field\Tag;

trait TagInputAutoCompleteBinder
{
    protected function withMemberAutocomplete(Tag $input, string $ajax_action): Tag
    {
        [$url, $term] = (new URLBuilder(new URI($ajax_action)))
            ->acquireParameter(['usrto'], 'search_term');

        // Preserve the previous field's support for entered IDs and values from invalid submissions.
        return $input->withAsyncAutocomplete($url, $term);
    }
}
