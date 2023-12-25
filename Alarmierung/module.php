<?php

/**
 * @project       Alarmierung/Alarmierung/
 * @file          module.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection SpellCheckingInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/ALM_autoload.php';

class Alarmierung extends IPSModule
{
    //Helper
    use ALM_AlarmState;
    use ALM_ConfigurationForm;
    use ALM_TriggerCondition;

    //Constants
    private const LIBRARY_GUID = '{9D16FD4F-37AA-96D0-EC29-8203B09156B2}';
    private const MODULE_GUID = '{2470D8A2-135B-98CA-6A89-70A18DC46CAE}';
    private const MODULE_PREFIX = 'ALM';
    private const ALARMPROTOCOL_MODULE_GUID = '{66BDB59B-E80F-E837-6640-005C32D5FC24}';
    private const ALARMPROTOCOL_MODULE_PREFIX = 'AP';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties

        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('EnableActive', false);
        $this->RegisterPropertyBoolean('EnableAlarming', true);
        $this->RegisterPropertyBoolean('EnableAlarmStage', true);
        $this->RegisterPropertyBoolean('EnableAlarmingValue', true);
        $this->RegisterPropertyBoolean('EnableResetAlarmingValue', true);
        $this->RegisterPropertyString('TriggerList', '[]');
        $this->RegisterPropertyString('NoAlarmActions', '[]');
        $this->RegisterPropertyBoolean('UsePreAlarm', false);
        $this->RegisterPropertyBoolean('UseAlarmProtocolPreAlarm', false);
        $this->RegisterPropertyInteger('PreAlarmDuration', 30);
        $this->RegisterPropertyString('PreAlarmActions', '[]');
        $this->RegisterPropertyBoolean('UseMainAlarm', true);
        $this->RegisterPropertyBoolean('UseAlarmProtocolMainAlarm', false);
        $this->RegisterPropertyInteger('MainAlarmDuration', 180);
        $this->RegisterPropertyInteger('MainAlarmMaximumAlarmingValue', 3);
        $this->RegisterPropertyString('MainAlarmActions', '[]');
        $this->RegisterPropertyBoolean('UsePostAlarm', false);
        $this->RegisterPropertyBoolean('UseAlarmProtocolPostAlarm', false);
        $this->RegisterPropertyInteger('PostAlarmDuration', 300);
        $this->RegisterPropertyString('PostAlarmActions', '[]');
        $this->RegisterPropertyInteger('AlarmProtocol', 0);
        $this->RegisterPropertyString('Location', '');

        ########## Variables

        //Active
        $id = @$this->GetIDForIdent('Active');
        $this->RegisterVariableBoolean('Active', 'Aktiv', '~Switch', 10);
        $this->EnableAction('Active');
        if (!$id) {
            $this->SetValue('Active', true);
        }

        //Alarming
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.Alarming';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
            IPS_SetVariableProfileIcon($profile, 'Alert');
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Alarm', '', 0xFF0000);
        $this->RegisterVariableBoolean('Alarming', 'Alarmierung', $profile, 20);
        $this->EnableAction('Alarming');

        //Alarm stage
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.AlarmStage';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
            IPS_SetVariableProfileIcon($profile, 'Warning');
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', '', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Voralarm', '', 0xFFFF00);
        IPS_SetVariableProfileAssociation($profile, 2, 'Hauptalarm', '', 0xFF0000);
        IPS_SetVariableProfileAssociation($profile, 3, 'Nachalarm', '', 0xFFFF00);
        $this->RegisterVariableInteger('AlarmStage', 'Alarmstufe', $profile, 30);

        //Alarming value
        $id = @$this->GetIDForIdent('AlarmingValue');
        $this->RegisterVariableInteger('AlarmingValue', 'Anzahl der Auslösungen', '', 40);
        if (!$id) {
            IPS_SetIcon(@$this->GetIDForIdent('AlarmingValue'), 'Information');
        }

        //Reset trigger value
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.ResetAlarmingValue';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Reset', 'Repeat', 0xFF0000);
        $this->RegisterVariableInteger('ResetAlarmingValue', 'Rückstellung', $profile, 50);
        $this->EnableAction('ResetAlarmingValue');

        ########## Timers

        $this->RegisterTimer('SetDefault', 0, self::MODULE_PREFIX . '_SetDefault(' . $this->InstanceID . ');');
        $this->RegisterTimer('SetMainAlarm', 0, self::MODULE_PREFIX . '_SetMainAlarm(' . $this->InstanceID . ');');
        $this->RegisterTimer('SetPostAlarm', 0, self::MODULE_PREFIX . '_SetPostAlarm(' . $this->InstanceID . ');');
        $this->RegisterTimer('ResetAlarmingValue', 0, self::MODULE_PREFIX . '_ResetAlarmingValue(' . $this->InstanceID . ');');
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        //Never delete this line!
        parent::ApplyChanges();

        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        //Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        //Delete all update messages
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        //Register references and update messages
        //Trigger list
        $variables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($variables as $variable) {
            if (!$variable['Use']) {
                continue;
            }
            //Primary condition
            if ($variable['PrimaryCondition'] != '') {
                $primaryCondition = json_decode($variable['PrimaryCondition'], true);
                if (array_key_exists(0, $primaryCondition)) {
                    if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                        $id = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                        if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
                            $this->RegisterReference($id);
                            $this->RegisterMessage($id, VM_UPDATE);
                        }
                    }
                }
            }
            //Secondary condition, multi
            if ($variable['SecondaryCondition'] != '') {
                $secondaryConditions = json_decode($variable['SecondaryCondition'], true);
                if (array_key_exists(0, $secondaryConditions)) {
                    if (array_key_exists('rules', $secondaryConditions[0])) {
                        $rules = $secondaryConditions[0]['rules']['variable'];
                        foreach ($rules as $rule) {
                            if (array_key_exists('variableID', $rule)) {
                                $id = $rule['variableID'];
                                if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
                                    $this->RegisterReference($id);
                                }
                            }
                        }
                    }
                }
            }
        }

        //Alarm lists
        $actionLists = ['NoAlarmActions', 'PreAlarmActions', 'MainAlarmActions', 'PostAlarmActions'];
        foreach ($actionLists as $actionList) {
            $variables = json_decode($this->ReadPropertyString($actionList), true);
            foreach ($variables as $variable) {
                if (!$variable['Use']) {
                    continue;
                }
                //Action
                if ($variable['Action'] != '') {
                    $action = json_decode($variable['Action'], true);
                    if (array_key_exists('parameters', $action)) {
                        if (array_key_exists('TARGET', $action['parameters'])) {
                            $id = $action['parameters']['TARGET'];
                            if ($id > 1 && @IPS_ObjectExists($id)) { //0 = main category, 1 = none
                                $this->RegisterReference($id);
                            }
                        }
                    }
                }
            }
        }

        //Alarm protocol
        $names = [];
        $names[] = ['propertyName' => 'AlarmProtocol', 'useUpdate' => false];
        foreach ($names as $name) {
            $id = $this->ReadPropertyInteger($name['propertyName']);
            if ($id > 1 && @IPS_ObjectExists($id)) {
                $this->RegisterReference($id);
                if ($name['useUpdate']) {
                    $this->RegisterMessage($id, VM_UPDATE);
                }
            }
        }

        //WebFront options
        IPS_SetHidden($this->GetIDForIdent('Active'), !$this->ReadPropertyBoolean('EnableActive'));
        IPS_SetHidden($this->GetIDForIdent('Alarming'), !$this->ReadPropertyBoolean('EnableAlarming'));
        IPS_SetHidden($this->GetIDForIdent('AlarmStage'), !$this->ReadPropertyBoolean('EnableAlarmStage'));
        IPS_SetHidden($this->GetIDForIdent('AlarmingValue'), !$this->ReadPropertyBoolean('EnableAlarmingValue'));
        IPS_SetHidden($this->GetIDForIdent('ResetAlarmingValue'), !$this->ReadPropertyBoolean('EnableResetAlarmingValue'));

        $this->SetDefault();
        $this->ResetAlarmingValue();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
        $profiles = ['Alarming', 'AlarmStage', 'ResetAlarmingValue'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profile;
                $this->UnregisterProfile($profileName);
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

            case VM_UPDATE:

                //$Data[0] = actual value
                //$Data[1] = value changed
                //$Data[2] = last value
                //$Data[3] = timestamp actual value
                //$Data[4] = timestamp value changed
                //$Data[5] = timestamp last value

                //Check trigger condition
                $valueChanged = 'false';
                if ($Data[1]) {
                    $valueChanged = 'true';
                }
                $scriptText = self::MODULE_PREFIX . '_CheckTriggerConditions(' . $this->InstanceID . ', ' . $SenderID . ', ' . $valueChanged . ');';
                @IPS_RunScriptText($scriptText);
                break;

        }
    }

    public function CreateAlarmProtocolInstance(): void
    {
        $id = @IPS_CreateInstance(self::ALARMPROTOCOL_MODULE_GUID);
        if (is_int($id)) {
            IPS_SetName($id, 'Alarmprotokoll');
            $infoText = 'Instanz mit der ID ' . $id . ' wurde erfolgreich erstellt!';
        } else {
            $infoText = 'Instanz konnte nicht erstellt werden!';
        }
        $this->UpdateFormField('InfoMessage', 'visible', true);
        $this->UpdateFormField('InfoMessageLabel', 'caption', $infoText);
    }

    public function UIShowMessage(string $Message): void
    {
        $this->UpdateFormField('InfoMessage', 'visible', true);
        $this->UpdateFormField('InfoMessageLabel', 'caption', $Message);
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {

            case 'Active':
                $this->SetValue($Ident, $Value);
                if (!$Value) {
                    $this->SetAlarming(false);
                }
                break;

            case 'Alarming':
                $this->SetAlarming($Value);
                break;

            case 'ResetAlarmingValue':
                $this->ResetAlarmingValue();
                break;

        }
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    /**
     * Unregisters a variable profile.
     *
     * @param string $Name
     * @return void
     */
    private function UnregisterProfile(string $Name): void
    {
        if (!IPS_VariableProfileExists($Name)) {
            return;
        }
        foreach (IPS_GetVariableList() as $VarID) {
            if (IPS_GetParent($VarID) == $this->InstanceID) {
                continue;
            }
            if (IPS_GetVariable($VarID)['VariableCustomProfile'] == $Name) {
                return;
            }
            if (IPS_GetVariable($VarID)['VariableProfile'] == $Name) {
                return;
            }
        }
        foreach (IPS_GetMediaListByType(MEDIATYPE_CHART) as $mediaID) {
            $content = json_decode(base64_decode(IPS_GetMediaContent($mediaID)), true);
            foreach ($content['axes'] as $axis) {
                if ($axis['profile' === $Name]) {
                    return;
                }
            }
        }
        IPS_DeleteVariableProfile($Name);
    }

    private function CheckMaintenance(): bool
    {
        $result = false;
        if (!$this->GetValue('Active')) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, die Instanz ist inaktiv!', 0);
            $result = true;
        }
        return $result;
    }
}