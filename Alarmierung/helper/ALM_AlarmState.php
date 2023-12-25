<?php

/**
 * @project       Alarmierung/Alarmierung/helper
 * @file          ALM_AlarmState.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection SpellCheckingInspection */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait ALM_AlarmState
{
    /**
     * Sets the alarming.
     *
     * @param bool $State
     * false =  No alarm
     * true =   Alarm
     *
     * @throws Exception
     */
    public function SetAlarming(bool $State): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $statusText = 'Aus';
        if ($State) {
            $statusText = 'An';
        }
        $this->SendDebug(__FUNCTION__, 'Status: ' . $statusText, 0);
        if ($this->CheckMaintenance()) {
            $this->SetDefault();
            return;
        }
        //Off
        if (!$State) {
            $this->SetDefault();
        }
        //On
        else {
            if ($this->GetValue('AlarmingValue') >= $this->ReadPropertyInteger('MainAlarmMaximumAlarmingValue')) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, die Anzahl der maximalen Auslösungen wurde erreicht!', 0);
                return;
            }
            if (!$this->GetValue('Alarming')) {
                $this->SetPreAlarm();
            }
        }
    }

    /**
     * Executes a panic alarming.
     *
     * @return void
     * @throws Exception
     */
    public function ExecutePanicAlarming(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if ($this->CheckMaintenance()) {
            $this->SetDefault();
            return;
        }
        $this->SetMainAlarm();
    }

    /**
     * Set the alarming to the default values, reset.
     *
     * @throws Exception
     */
    public function SetDefault(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SendDebug(__FUNCTION__, 'Kein Alarm', 0);
        $this->SetValue('Alarming', false);
        $this->SetValue('AlarmStage', 0);
        $this->SetTimerInterval('SetDefault', 0);
        $this->SetTimerInterval('SetMainAlarm', 0);
        $this->SetTimerInterval('SetPostAlarm', 0);
        //Action
        $noAlarmActions = json_decode($this->ReadPropertyString('NoAlarmActions'), true);
        foreach ($noAlarmActions as $noAlarmAction) {
            $action = json_decode($noAlarmAction['Action'], true);
            @IPS_RunAction($action['actionID'], $action['parameters']);
        }
    }

    /**
     * Sets the pre alarm.
     *
     * @throws Exception
     */
    public function SetPreAlarm(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if ($this->CheckMaintenance()) {
            $this->SetDefault();
            return;
        }
        $location = $this->ReadPropertyString('Location');
        if ($this->ReadPropertyBoolean('UsePreAlarm')) {
            $this->SendDebug(__FUNCTION__, 'Voralarm', 0);
            $this->SetValue('Alarming', true);
            $this->SetValue('AlarmStage', 1);
            $this->SetTimerInterval('SetMainAlarm', $this->ReadPropertyInteger('PreAlarmDuration') * 1000);
            $this->SetTimerInterval('SetPostAlarm', 0);
            //Action
            $preAlarmActions = json_decode($this->ReadPropertyString('PreAlarmActions'), true);
            foreach ($preAlarmActions as $preAlarmAction) {
                $action = json_decode($preAlarmAction['Action'], true);
                @IPS_RunAction($action['actionID'], $action['parameters']);
            }
            //Alarm protocol
            if ($this->ReadPropertyBoolean('UseAlarmProtocolPreAlarm')) {
                $id = $this->ReadPropertyInteger('AlarmProtocol');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $text = 'Der Voralarm wurde ausgelöst.';
                    $this->SendDebug(__FUNCTION__, 'Alarmprotokoll: ' . $text, 0);
                    if ($location == '') {
                        $logText = date('d.m.Y, H:i:s') . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                    } else {
                        $logText = date('d.m.Y, H:i:s') . ', ' . $this->ReadPropertyString('Location') . ', Alarmierung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                    }
                    $scriptText = self::ALARMPROTOCOL_MODULE_PREFIX . '_UpdateMessages(' . $id . ', "' . $logText . '", 0);';
                    @IPS_RunScriptText($scriptText);
                }
            }
        } else {
            $this->SetMainAlarm();
        }
    }

    /**
     * Sets the main alarm.
     *
     * @throws Exception
     */
    public function SetMainAlarm(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if ($this->CheckMaintenance()) {
            $this->SetDefault();
            return;
        }
        $location = $this->ReadPropertyString('Location');
        $this->SetTimerInterval('SetMainAlarm', 0);
        if ($this->ReadPropertyBoolean('UseMainAlarm')) {
            $this->SendDebug(__FUNCTION__, 'Hauptalarm', 0);
            if (!$this->GetValue('Alarming')) {
                $this->SetValue('Alarming', true);
            }
            $this->SetValue('AlarmStage', 2);
            $this->SetValue('AlarmingValue', (integer) $this->GetValue('AlarmingValue') + 1);
            $this->SetTimerInterval('SetPostAlarm', $this->ReadPropertyInteger('MainAlarmDuration') * 1000);
            //Action
            $mainAlarmActions = json_decode($this->ReadPropertyString('MainAlarmActions'), true);
            foreach ($mainAlarmActions as $mainAlarmAction) {
                $action = json_decode($mainAlarmAction['Action'], true);
                @IPS_RunAction($action['actionID'], $action['parameters']);
            }
            //Alarm protocol
            if ($this->ReadPropertyBoolean('UseAlarmProtocolMainAlarm')) {
                $id = $this->ReadPropertyInteger('AlarmProtocol');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $text = 'Der Hauptalarm wurde ausgelöst.';
                    $this->SendDebug(__FUNCTION__, 'Alarmprotokoll: ' . $text, 0);
                    if ($location == '') {
                        $logText = date('d.m.Y, H:i:s') . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                    } else {
                        $logText = date('d.m.Y, H:i:s') . ', ' . $this->ReadPropertyString('Location') . ', Alarmierung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                    }
                    $scriptText = self::ALARMPROTOCOL_MODULE_PREFIX . '_UpdateMessages(' . $id . ', "' . $logText . '", 0);';
                    @IPS_RunScriptText($scriptText);
                }
            }
        } else {
            $this->SetPostAlarm();
        }
    }

    /**
     * Sets the post alarm.
     *
     * @throws Exception
     */
    public function SetPostAlarm(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if ($this->CheckMaintenance()) {
            $this->SetDefault();
            return;
        }
        $location = $this->ReadPropertyString('Location');
        $this->SetTimerInterval('SetMainAlarm', 0);
        $this->SetTimerInterval('SetPostAlarm', 0);
        if ($this->ReadPropertyBoolean('UsePostAlarm')) {
            $this->SendDebug(__FUNCTION__, 'Nachalarm', 0);
            if (!$this->GetValue('Alarming')) {
                $this->SetValue('Alarming', true);
            }
            $this->SetValue('AlarmStage', 3);
            $this->SetTimerInterval('SetDefault', $this->ReadPropertyInteger('PostAlarmDuration') * 1000);
            //Action
            $postAlarmActions = json_decode($this->ReadPropertyString('PostAlarmActions'), true);
            foreach ($postAlarmActions as $postAlarmAction) {
                $action = json_decode($postAlarmAction['Action'], true);
                @IPS_RunAction($action['actionID'], $action['parameters']);
            }
            //Alarm protocol
            if ($this->ReadPropertyBoolean('UseAlarmProtocolPostAlarm')) {
                $id = $this->ReadPropertyInteger('AlarmProtocol');
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    $text = 'Der Nachalarm wurde ausgelöst.';
                    $this->SendDebug(__FUNCTION__, 'Alarmprotokoll: ' . $text, 0);
                    if ($location == '') {
                        $logText = date('d.m.Y, H:i:s') . ', ' . $text . ' (ID ' . $this->InstanceID . ')';
                    } else {
                        $logText = date('d.m.Y, H:i:s') . ', ' . $this->ReadPropertyString('Location') . ', Alarmierung, ' . $text . ' (ID ' . $this->InstanceID . ')';
                    }
                    $scriptText = self::ALARMPROTOCOL_MODULE_PREFIX . '_UpdateMessages(' . $id . ', "' . $logText . '", 0);';
                    @IPS_RunScriptText($scriptText);
                }
            }
        } else {
            $this->SetDefault();
        }
    }
}