<?php

declare(strict_types=1);

/**
 * This class serves as a dispatcher for all plugin controllers which are
 * accessed in the ILIAS administration context.
 *
 * If a new class is added, please add it to the switch statement and enter¨
 * an according ilCtrl-control-statement.
 *
 * @author            Thibeau Fuhrer <thibeau@sr.solutions>
 *
 * @ilCtrl_IsCalledBy ilUserTakeOverConfigGUI: ilObjComponentSettingsGUI
 * @ilCtrl_Calls      ilUserTakeOverConfigGUI: ilUserTakeOverSettingsGUI
 * @ilCtrl_Calls      ilUserTakeOverConfigGUI: ilUserTakeOverGroupGUI
 *
 * @noinspection      AutoloadingIssuesInspection
 */
class ilUserTakeOverConfigGUI extends ilPluginConfigGUI
{
    /**
     * @inheritDoc
     */
    public function performCommand(string $cmd): void
    {
        global $DIC;

        $DIC->ui()->mainTemplate()->setTitle(
            $DIC->language()->txt('cmps_plugin') . ': ' . ilUserTakeOverPlugin::DISPLAY_NAME
        );

        switch ($DIC->ctrl()->getNextClass()) {
            case strtolower(ilUserTakeOverSettingsGUI::class):
                $DIC->ctrl()->forwardCommand(new ilUserTakeOverSettingsGUI());
                break;
            case strtolower(ilUserTakeOverGroupGUI::class):
                $DIC->ctrl()->forwardCommand(new ilUserTakeOverGroupGUI());
                break;

            default:
                // this is redirect-abuse and should be somehow prevented in the future.
                $DIC->ctrl()->redirectByClass(
                    [ilAdministrationGUI::class, ilObjComponentSettingsGUI::class, self::class, $this->getEntryPoint()],
                    ilUserTakeOverSettingsGUI::CMD_EDIT
                );
        }
    }

    /**
     * Returns which controller should be called first.
     */
    protected function getEntryPoint(): string
    {
        return ilUserTakeOverSettingsGUI::class;
    }
}
