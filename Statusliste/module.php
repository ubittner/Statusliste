<?php

/**
 * @project       Statusliste/Statusliste/
 * @file          module.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpUnhandledExceptionInspection */
/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/SL_autoload.php';

class Statusliste extends IPSModule
{
    //Helper
    use SL_ConfigurationForm;
    use SL_MonitoredVariables;

    //Constants
    private const LIBRARY_GUID = '{7970AD78-5D4E-9DF8-6B05-089B56F4D608}';
    private const MODULE_GUID = '{FCC297AA-0414-29FD-DD5E-3A48514D7D4E}';
    private const MODULE_PREFIX = 'SL';

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        ########## Properties

        //Info
        $this->RegisterPropertyString('Note', '');

        //Status designation
        $this->RegisterPropertyString('StatusDesignationAlarm', 'Alarm');
        $this->RegisterPropertyString('StatusDesignationOK', 'OK');

        //List options
        $this->RegisterPropertyString('ListDesignation', 'Statusliste');
        $this->RegisterPropertyBoolean('StatusListEnableAlarm', true);
        $this->RegisterPropertyString('StatusListDesignationAlarm', '🔴 Alarm');
        $this->RegisterPropertyBoolean('StatusListEnableOK', true);
        $this->RegisterPropertyString('StatusListDesignationOK', '🟢 OK');

        //Trigger list
        $this->RegisterPropertyString('TriggerList', '[]');

        //Automatic status update
        $this->RegisterPropertyBoolean('AutomaticStatusUpdate', false);
        $this->RegisterPropertyInteger('StatusUpdateInterval', 60);

        //Visualisation
        $this->RegisterPropertyBoolean('EnableStatus', true);
        $this->RegisterPropertyBoolean('EnableLastUpdate', true);
        $this->RegisterPropertyBoolean('EnableUpdateStatus', true);
        $this->RegisterPropertyBoolean('EnableStatusList', true);

        ########## Variables

        //Status
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.Status';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'OK', 'Ok', 0x00FF00);
        IPS_SetVariableProfileAssociation($profile, 1, 'Alarm', 'Warning', 0x0000FF);
        $this->RegisterVariableBoolean('Status', 'Status', $profile, 10);

        //Last update
        $id = @$this->GetIDForIdent('LastUpdate');
        $this->RegisterVariableString('LastUpdate', 'Letzte Aktualisierung', '', 20);
        if (!$id) {
            IPS_SetIcon($this->GetIDForIdent('LastUpdate'), 'Clock');
        }

        //Update status
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.UpdateStatus';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 1);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aktualisieren', 'Repeat', -1);
        $this->RegisterVariableInteger('UpdateStatus', 'Aktualisierung', $profile, 30);
        $this->EnableAction('UpdateStatus');

        //Status list
        $id = @$this->GetIDForIdent('StatusList');
        $this->RegisterVariableString('StatusList', 'Statusliste', 'HTMLBox', 40);
        if (!$id) {
            IPS_SetIcon($this->GetIDForIdent('StatusList'), 'Database');
        }

        ########## Timer

        //Status update
        $this->RegisterTimer('StatusUpdate', 0, self::MODULE_PREFIX . '_UpdateStatus(' . $this->InstanceID . ');');

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

        //Update status profiles
        $profile = self::MODULE_PREFIX . '.' . $this->InstanceID . '.Status';
        if (IPS_VariableProfileExists($profile)) {
            //Set new values
            IPS_SetVariableProfileAssociation($profile, 0, $this->ReadPropertyString('StatusDesignationOK'), 'Ok', 0x00FF00);
            IPS_SetVariableProfileAssociation($profile, 1, $this->ReadPropertyString('StatusDesignationAlarm'), 'Warning', 0xFF0000);
        }

        //Update status list name
        IPS_SetName($this->GetIDForIdent('StatusList'), $this->ReadPropertyString('ListDesignation'));

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

        $triggerVariables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($triggerVariables as $variable) {
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

        //WebFront options
        IPS_SetHidden($this->GetIDForIdent('Status'), !$this->ReadPropertyBoolean('EnableStatus'));
        IPS_SetHidden($this->GetIDForIdent('LastUpdate'), !$this->ReadPropertyBoolean('EnableLastUpdate'));
        IPS_SetHidden($this->GetIDForIdent('UpdateStatus'), !$this->ReadPropertyBoolean('EnableUpdateStatus'));
        IPS_SetHidden($this->GetIDForIdent('StatusList'), !$this->ReadPropertyBoolean('EnableStatusList'));

        //Set automatic status update timer
        $milliseconds = 0;
        if ($this->ReadPropertyBoolean('AutomaticStatusUpdate')) {
            $milliseconds = $this->ReadPropertyInteger('StatusUpdateInterval') * 1000;
        }
        $this->SetTimerInterval('StatusUpdate', $milliseconds);

        //Update status
        $this->UpdateStatus();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();

        //Delete profiles
        $profiles = ['Status', 'UpdateStatus'];
        foreach ($profiles as $profile) {
            $profileName = self::MODULE_PREFIX . '.' . $this->InstanceID . '.' . $profile;
            if (@IPS_VariableProfileExists($profileName)) {
                IPS_DeleteVariableProfile($profileName);
            }
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
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
                $this->UpdateStatus();
                break;

        }
    }

    public function UIShowMessage(string $Message): void
    {
        $this->UpdateFormField('InfoMessage', 'visible', true);
        $this->UpdateFormField('InfoMessageLabel', 'caption', $Message);
    }

    #################### Request action

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == 'UpdateStatus') {
            $this->UpdateStatus();
        }
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }
}
