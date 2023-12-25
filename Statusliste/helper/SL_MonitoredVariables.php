<?php

/**
 * @project       Statusliste/Statusliste/helper/
 * @file          SL_MonitoredVariables.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection SpellCheckingInspection */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait SL_MonitoredVariables
{
    /**
     * Checks the determination value.
     *
     * @param int $VariableDeterminationType
     * @return void
     */
    public function CheckVariableDeterminationValue(int $VariableDeterminationType): void
    {
        $profileSelection = false;
        $determinationValue = false;
        //Profile selection
        if ($VariableDeterminationType == 0) {
            $profileSelection = true;
        }
        //Custom ident
        if ($VariableDeterminationType == 5) {
            $this->UpdateFormfield('VariableDeterminationValue', 'caption', 'Identifikator');
            $determinationValue = true;
        }
        $this->UpdateFormfield('VariableDeterminationProfileSelection', 'visible', $profileSelection);
        $this->UpdateFormfield('VariableDeterminationValue', 'visible', $determinationValue);
    }

    /**
     * Determines the variables.
     *
     * @param int $DeterminationType
     * @param string $DeterminationValue
     * @param string $ProfileSelection
     * @return void
     * @throws Exception
     */
    public function DetermineVariables(int $DeterminationType, string $DeterminationValue, string $ProfileSelection = ''): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->SendDebug(__FUNCTION__, 'Auswahl: ' . $DeterminationType, 0);
        $this->SendDebug(__FUNCTION__, 'Identifikator: ' . $DeterminationValue, 0);
        //Set minimum an d maximum of existing variables
        $this->UpdateFormField('VariableDeterminationProgress', 'minimum', 0);
        $maximumVariables = count(IPS_GetVariableList());
        $this->UpdateFormField('VariableDeterminationProgress', 'maximum', $maximumVariables);
        //Determine variables first
        $determineIdent = false;
        $determineProfile = false;
        $determinedVariables = [];
        $passedVariables = 0;
        foreach (@IPS_GetVariableList() as $variable) {
            switch ($DeterminationType) {
                case 0: //Profile: Select profile
                    if ($ProfileSelection == '') {
                        $infoText = 'Abbruch, es wurde kein Profil ausgewählt!';
                        $this->UpdateFormField('InfoMessage', 'visible', true);
                        $this->UpdateFormField('InfoMessageLabel', 'caption', $infoText);
                        return;
                    } else {
                        $determineProfile = true;
                    }
                    break;

                case 1: //Ident: STATE
                case 2: //Ident: MOTION
                case 3: //Ident: SMOKE_DETECTOR_ALARM_STATUS
                case 4: //Ident: ALARMSTATE
                    $determineIdent = true;
                    break;

                case 5: //Custom Ident
                    if ($DeterminationValue == '') {
                        $infoText = 'Abbruch, es wurde kein Identifikator angegeben!';
                        $this->UpdateFormField('InfoMessage', 'visible', true);
                        $this->UpdateFormField('InfoMessageLabel', 'caption', $infoText);
                        return;
                    } else {
                        $determineIdent = true;
                    }
                    break;

            }
            $passedVariables++;
            $this->UpdateFormField('VariableDeterminationProgress', 'visible', true);
            $this->UpdateFormField('VariableDeterminationProgress', 'current', $passedVariables);
            $this->UpdateFormField('VariableDeterminationProgressInfo', 'visible', true);
            $this->UpdateFormField('VariableDeterminationProgressInfo', 'caption', $passedVariables . '/' . $maximumVariables);
            IPS_Sleep(10);

            ##### Profile

            //Determine via profile
            if ($determineProfile && !$determineIdent) {
                //Select profile
                if ($DeterminationType == 0) {
                    $profileNames = $ProfileSelection;
                }
                if (isset($profileNames)) {
                    $profileNames = str_replace(' ', '', $profileNames);
                    $profileNames = explode(',', $profileNames);
                    foreach ($profileNames as $profileName) {
                        $variableData = IPS_GetVariable($variable);
                        if ($variableData['VariableCustomProfile'] == $profileName || $variableData['VariableProfile'] == $profileName) {
                            $location = @IPS_GetLocation($variable);
                            $determinedVariables[] = [
                                'Use'      => false,
                                'ID'       => $variable,
                                'Location' => $location];
                        }
                    }
                }
            }

            ##### Ident

            //Determine via ident
            if ($determineIdent && !$determineProfile) {
                switch ($DeterminationType) {
                    case 1:
                        $objectIdents = 'STATE';
                        break;

                    case 2:
                        $objectIdents = 'MOTION';
                        break;

                    case 3:
                        $objectIdents = 'SMOKE_DETECTOR_ALARM_STATUS';
                        break;

                    case 4:
                        $objectIdents = 'ALARMSTATE';
                        break;

                    case 5: //Custom ident
                        $objectIdents = $DeterminationValue;
                        break;

                }
                if (isset($objectIdents)) {
                    $objectIdents = str_replace(' ', '', $objectIdents);
                    $objectIdents = explode(',', $objectIdents);
                    foreach ($objectIdents as $objectIdent) {
                        $object = @IPS_GetObject($variable);
                        if ($object['ObjectIdent'] == $objectIdent) {
                            $location = @IPS_GetLocation($variable);
                            $determinedVariables[] = [
                                'Use'      => false,
                                'ID'       => $variable,
                                'Location' => $location];
                        }
                    }
                }
            }
        }
        $amount = count($determinedVariables);
        //Get already listed variables
        $listedVariables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($listedVariables as $listedVariable) {
            if (array_key_exists('PrimaryCondition', $listedVariable)) {
                $primaryCondition = json_decode($listedVariable['PrimaryCondition'], true);
                if ($primaryCondition != '') {
                    if (array_key_exists(0, $primaryCondition)) {
                        if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                            $listedVariableID = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                            if ($listedVariableID > 1 && @IPS_ObjectExists($listedVariableID)) {
                                foreach ($determinedVariables as $key => $determinedVariable) {
                                    $determinedVariableID = $determinedVariable['ID'];
                                    if ($determinedVariableID > 1 && @IPS_ObjectExists($determinedVariableID)) {
                                        //Check if variable id is already a listed variable id
                                        if ($determinedVariableID == $listedVariableID) {
                                            unset($determinedVariables[$key]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if (empty($determinedVariables)) {
            $this->UpdateFormField('VariableDeterminationProgress', 'visible', false);
            $this->UpdateFormField('VariableDeterminationProgressInfo', 'visible', false);
            if ($amount > 0) {
                $infoText = 'Es wurden keine weiteren Variablen gefunden!';
            } else {
                $infoText = 'Es wurden keine Variablen gefunden!';
            }
            $this->UpdateFormField('InfoMessage', 'visible', true);
            $this->UpdateFormField('InfoMessageLabel', 'caption', $infoText);
            return;
        }
        $determinedVariables = array_values($determinedVariables);
        $this->UpdateFormField('DeterminedVariableList', 'rowCount', count($determinedVariables));
        $this->UpdateFormField('DeterminedVariableList', 'values', json_encode($determinedVariables));
        $this->UpdateFormField('DeterminedVariableList', 'visible', true);
        $this->UpdateFormField('ApplyPreVariableTriggerValues', 'visible', true);
    }

    /**
     * Applies the determined variables to the trigger list.
     *
     * @param object $ListValues
     * false =  don't overwrite,
     * true =   overwrite
     *
     * @return void
     * @throws ReflectionException
     * @throws Exception
     */
    public function ApplyDeterminedVariables(object $ListValues): void
    {
        $determinedVariables = [];
        $reflection = new ReflectionObject($ListValues);
        $property = $reflection->getProperty('array');
        $property->setAccessible(true);
        $variables = $property->getValue($ListValues);
        foreach ($variables as $variable) {
            if (!$variable['Use']) {
                continue;
            }
            $id = $variable['ID'];
            $name = @IPS_GetName($id);
            $address = '';
            $parent = @IPS_GetParent($id);
            if ($parent > 1 && @IPS_ObjectExists($parent)) {
                $parentObject = @IPS_GetObject($parent);
                if ($parentObject['ObjectType'] == 1) { //1 = instance
                    $name = strstr(@IPS_GetName($parent), ':', true);
                    if (!$name) {
                        $name = @IPS_GetName($parent);
                    }
                    $address = @IPS_GetProperty($parent, 'Address');
                    if (!$address) {
                        $address = '';
                    }
                }
            }
            $value = true;
            if (IPS_GetVariable($id)['VariableType'] == 1) {
                $value = 1;
            }
            $primaryCondition[0] = [
                'id'        => 0,
                'parentID'  => 0,
                'operation' => 0,
                'rules'     => [
                    'variable' => [
                        '0' => [
                            'id'         => 0,
                            'variableID' => $id,
                            'comparison' => 0,
                            'value'      => $value,
                            'type'       => 0
                        ]
                    ],
                    'date'         => [],
                    'time'         => [],
                    'dayOfTheWeek' => []
                ]
            ];
            $determinedVariables[] = [
                'Use'                                   => true,
                'Designation'                           => $name,
                'Comment'                               => $address,
                'PrimaryCondition'                      => json_encode($primaryCondition),
                'SecondaryCondition'                    => '[]'];
        }
        //Get already listed variables
        $listedVariables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($determinedVariables as $determinedVariable) {
            $determinedVariableID = 0;
            if (array_key_exists('PrimaryCondition', $determinedVariable)) {
                $primaryCondition = json_decode($determinedVariable['PrimaryCondition'], true);
                if ($primaryCondition != '') {
                    if (array_key_exists(0, $primaryCondition)) {
                        if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                            $determinedVariableID = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                        }
                    }
                }
            }
            if ($determinedVariableID > 1 && @IPS_ObjectExists($determinedVariableID)) {
                //Check variable id with already listed variable ids
                $add = true;
                foreach ($listedVariables as $listedVariable) {
                    if (array_key_exists('PrimaryCondition', $listedVariable)) {
                        $primaryCondition = json_decode($listedVariable['PrimaryCondition'], true);
                        if ($primaryCondition != '') {
                            if (array_key_exists(0, $primaryCondition)) {
                                if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                                    $listedVariableID = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                                    if ($listedVariableID > 1 && @IPS_ObjectExists($determinedVariableID)) {
                                        if ($determinedVariableID == $listedVariableID) {
                                            $add = false;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                //Add new variable to already listed variables
                if ($add) {
                    $listedVariables[] = $determinedVariable;
                }
            }
        }
        if (empty($determinedVariables)) {
            return;
        }
        //Sort variables by name
        array_multisort(array_column($listedVariables, 'Designation'), SORT_ASC, $listedVariables);
        @IPS_SetProperty($this->InstanceID, 'TriggerList', json_encode(array_values($listedVariables)));
        if (@IPS_HasChanges($this->InstanceID)) {
            @IPS_ApplyChanges($this->InstanceID);
        }
    }

    /**
     * Gets the actual door and window sensor states
     *
     * @return void
     * @throws Exception
     */
    public function GetActualVariableStates(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $this->UpdateStatus();
        $this->UpdateFormField('ActualVariableStateConfigurationButton', 'visible', false);
        $actualVariableStates = [];
        $variables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($variables as $variable) {
            if (!$variable['Use']) {
                continue;
            }
            $conditions = true;
            if ($variable['PrimaryCondition'] != '') {
                $primaryCondition = json_decode($variable['PrimaryCondition'], true);
                if (array_key_exists(0, $primaryCondition)) {
                    if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                        $sensorID = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                        if ($sensorID <= 1 || @!IPS_ObjectExists($sensorID)) {
                            $conditions = false;
                        }
                    }
                }
            }
            if ($variable['SecondaryCondition'] != '') {
                $secondaryConditions = json_decode($variable['SecondaryCondition'], true);
                if (array_key_exists(0, $secondaryConditions)) {
                    if (array_key_exists('rules', $secondaryConditions[0])) {
                        $rules = $secondaryConditions[0]['rules']['variable'];
                        foreach ($rules as $rule) {
                            if (array_key_exists('variableID', $rule)) {
                                $id = $rule['variableID'];
                                if ($id <= 1 || @!IPS_ObjectExists($id)) {
                                    $conditions = false;
                                }
                            }
                        }
                    }
                }
            }
            if ($conditions && isset($sensorID)) {
                $stateName = $this->ReadPropertyString('StatusListDesignationOK');
                if (IPS_IsConditionPassing($variable['PrimaryCondition']) && IPS_IsConditionPassing($variable['SecondaryCondition'])) {
                    $stateName = $this->ReadPropertyString('StatusListDesignationAlarm');
                }
                $variableUpdate = IPS_GetVariable($sensorID)['VariableUpdated']; //timestamp or 0 = never
                $lastUpdate = 'Nie';
                if ($variableUpdate != 0) {
                    $lastUpdate = date('d.m.Y H:i:s', $variableUpdate);
                }
                $actualVariableStates[] = ['ActualStatus' => $stateName, 'SensorID' => $sensorID, 'Designation' =>  $variable['Designation'], 'Comment' => $variable['Comment'], 'LastUpdate' => $lastUpdate];
            }
        }
        $amount = count($actualVariableStates);
        if ($amount == 0) {
            $amount = 1;
        }
        $this->UpdateFormField('ActualVariableStateList', 'rowCount', $amount);
        $this->UpdateFormField('ActualVariableStateList', 'values', json_encode($actualVariableStates));
    }

    /**
     * Creates links of monitored variables.
     *
     * @param int $LinkCategory
     * @param object $ListValues
     * @return void
     * @throws ReflectionException
     */
    public function CreateVariableLinks(int $LinkCategory, object $ListValues): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if ($LinkCategory == 1 || @!IPS_ObjectExists($LinkCategory)) {
            $this->UIShowMessage('Abbruch, bitte wählen Sie eine Kategorie aus!');
            return;
        }
        $reflection = new ReflectionObject($ListValues);
        $property = $reflection->getProperty('array');
        $property->setAccessible(true);
        $variables = $property->getValue($ListValues);
        $amountVariables = 0;
        foreach ($variables as $variable) {
            if ($variable['Use']) {
                $amountVariables++;
            }
        }
        if ($amountVariables == 0) {
            $this->UpdateFormField('InfoMessage', 'visible', true);
            $this->UpdateFormField('InfoMessageLabel', 'caption', 'Es wurden keine Variablen ausgewählt!');
            return;
        }
        $maximumVariables = $amountVariables;
        $this->UpdateFormField('VariableLinkProgress', 'minimum', 0);
        $this->UpdateFormField('VariableLinkProgress', 'maximum', $maximumVariables);
        $passedVariables = 0;
        $targetIDs = [];
        $i = 0;
        foreach ($variables as $variable) {
            if ($variable['Use']) {
                $passedVariables++;
                $this->UpdateFormField('VariableLinkProgress', 'visible', true);
                $this->UpdateFormField('VariableLinkProgress', 'current', $passedVariables);
                $this->UpdateFormField('VariableLinkProgressInfo', 'visible', true);
                $this->UpdateFormField('VariableLinkProgressInfo', 'caption', $passedVariables . '/' . $maximumVariables);
                IPS_Sleep(200);
                $id = $variable['SensorID'];
                if ($id > 1 && @IPS_ObjectExists($id)) {
                    $targetIDs[$i] = ['name' => $variable['Designation'], 'targetID' => $id];
                    $i++;
                }
            }
        }
        //Sort array alphabetically by device name
        sort($targetIDs);
        //Get all existing links (links have not an ident field, so we use the object info field)
        $existingTargetIDs = [];
        $links = @IPS_GetLinkList();
        if (!empty($links)) {
            $i = 0;
            foreach ($links as $link) {
                $linkInfo = @IPS_GetObject($link)['ObjectInfo'];
                if ($linkInfo == self::MODULE_PREFIX . '.' . $this->InstanceID) {
                    //Get target id
                    $existingTargetID = @IPS_GetLink($link)['TargetID'];
                    $existingTargetIDs[$i] = ['linkID' => $link, 'targetID' => $existingTargetID];
                    $i++;
                }
            }
        }
        //Delete dead links
        $deadLinks = array_diff(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
        if (!empty($deadLinks)) {
            foreach ($deadLinks as $targetID) {
                $position = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                $linkID = $existingTargetIDs[$position]['linkID'];
                if (@IPS_LinkExists($linkID)) {
                    @IPS_DeleteLink($linkID);
                }
            }
        }
        //Create new links
        $newLinks = array_diff(array_column($targetIDs, 'targetID'), array_column($existingTargetIDs, 'targetID'));
        if (!empty($newLinks)) {
            foreach ($newLinks as $targetID) {
                $linkID = @IPS_CreateLink();
                @IPS_SetParent($linkID, $LinkCategory);
                $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                @IPS_SetPosition($linkID, $position);
                $name = $targetIDs[$position]['name'];
                @IPS_SetName($linkID, $name);
                @IPS_SetLinkTargetID($linkID, $targetID);
                @IPS_SetInfo($linkID, self::MODULE_PREFIX . '.' . $this->InstanceID);
            }
        }
        //Edit existing links
        $existingLinks = array_intersect(array_column($existingTargetIDs, 'targetID'), array_column($targetIDs, 'targetID'));
        if (!empty($existingLinks)) {
            foreach ($existingLinks as $targetID) {
                $position = array_search($targetID, array_column($targetIDs, 'targetID'));
                $targetID = $targetIDs[$position]['targetID'];
                $index = array_search($targetID, array_column($existingTargetIDs, 'targetID'));
                $linkID = $existingTargetIDs[$index]['linkID'];
                @IPS_SetPosition($linkID, $position);
                $name = $targetIDs[$position]['name'];
                @IPS_SetName($linkID, $name);
                @IPS_SetInfo($linkID, self::MODULE_PREFIX . '.' . $this->InstanceID);
            }
        }
        $this->UpdateFormField('VariableLinkProgress', 'visible', false);
        $this->UpdateFormField('VariableLinkProgressInfo', 'visible', false);
        $infoText = 'Die Variablenverknüpfung wurde erfolgreich erstellt!';
        if ($amountVariables > 1) {
            $infoText = 'Die Variablenverknüpfungen wurden erfolgreich erstellt!';
        }
        $this->UIShowMessage($infoText);
    }

    /**
     * Updates the status.
     *
     * @return bool
     * false    = all doors and windows are closed
     * true     = at least one door or window is open
     *
     * @throws Exception
     */
    public function UpdateStatus(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        if (!$this->CheckForExistingVariables()) {
            return false;
        }

        ##### Update overall status

        $variables = json_decode($this->GetMonitoredVariables(), true);
        $actualOverallStatus = false;
        foreach ($variables as $variable) {
            if ($variable['ActualStatus'] == 1) {
                $actualOverallStatus = true;
            }
        }
        $this->SetValue('Status', $actualOverallStatus);

        $this->SetValue('LastUpdate', date('d.m.Y H:i:s'));

        ##### Update overview list for WebFront

        $string = '';
        if ($this->ReadPropertyBoolean('EnableStatusList')) {
            $string .= "<table style='width: 100%; border-collapse: collapse;'>";
            $string .= '<tr><td><b>Status</b></td><td><b>Name</b></td><td><b>Bemerkung</b></td><td><b>ID</b></td></tr>';
            //Sort variables by name
            array_multisort(array_column($variables, 'Name'), SORT_ASC, $variables);
            //Rebase array
            $variables = array_values($variables);
            $separator = false;
            if (!empty($variables)) {
                //Show open doors and windows first
                if ($this->ReadPropertyBoolean('StatusListEnableAlarm')) {
                    foreach ($variables as $variable) {
                        $id = $variable['ID'];
                        if ($id != 0 && IPS_ObjectExists($id)) {
                            if ($variable['ActualStatus'] == 1) {
                                $separator = true;
                                $string .= '<tr><td>' . $variable['StatusText'] . '</td><td>' . $variable['Name'] . '</td><td>' . $variable['Comment'] . '</td><td>' . $id . '</td></tr>';
                            }
                        }
                    }
                }
                //Closed doors and windows are next
                if ($this->ReadPropertyBoolean('StatusListEnableOK')) {
                    //Check if we have an existing element for a spacer
                    $existingElement = false;
                    foreach ($variables as $variable) {
                        $id = $variable['ID'];
                        if ($id != 0 && IPS_ObjectExists($id)) {
                            if ($variable['ActualStatus'] == 0) {
                                $existingElement = true;
                            }
                        }
                    }
                    //Add spacer
                    if ($separator && $existingElement) {
                        $string .= '<tr><td><b>&#8205;</b></td><td><b>&#8205;</b></td><td><b>&#8205;</b></td><td><b>&#8205;</b></td></tr>';
                    }
                    //Add closed doors and windows
                    foreach ($variables as $variable) {
                        $id = $variable['ID'];
                        if ($id != 0 && IPS_ObjectExists($id)) {
                            if ($variable['ActualStatus'] == 0) {
                                $string .= '<tr><td>' . $variable['StatusText'] . '</td><td>' . $variable['Name'] . '</td><td>' . $variable['Comment'] . '</td><td>' . $id . '</td></tr>';
                            }
                        }
                    }
                }
            }
            $string .= '</table>';
        }
        $this->SetValue('StatusList', $string);
        return $actualOverallStatus;
    }

    #################### Private

    /**
     * Checks for monitored variables.
     *
     * @return bool
     * false =  There are no monitored variables
     * true =   There are monitored variables
     * @throws Exception
     */
    private function CheckForExistingVariables(): bool
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt', 0);
        $monitoredVariables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($monitoredVariables as $variable) {
            if (!$variable['Use']) {
                continue;
            }
            if ($variable['PrimaryCondition'] != '') {
                $primaryCondition = json_decode($variable['PrimaryCondition'], true);
                if (array_key_exists(0, $primaryCondition)) {
                    if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                        $id = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                        if ($id > 1 && @IPS_ObjectExists($id)) {
                            return true;
                        }
                    }
                }
            }
        }
        $this->SendDebug(__FUNCTION__, 'Abbruch, Es werden keine Variablen überwacht!', 0);
        return false;
    }

    /**
     * Gets the monitored variables and their status.
     *
     * @return string
     * @throws Exception
     */
    private function GetMonitoredVariables(): string
    {
        $result = [];
        $monitoredVariables = json_decode($this->ReadPropertyString('TriggerList'), true);
        foreach ($monitoredVariables as $variable) {
            if (!$variable['Use']) {
                continue;
            }
            $id = 0;
            if ($variable['PrimaryCondition'] != '') {
                $primaryCondition = json_decode($variable['PrimaryCondition'], true);
                if (array_key_exists(0, $primaryCondition)) {
                    if (array_key_exists(0, $primaryCondition[0]['rules']['variable'])) {
                        $id = $primaryCondition[0]['rules']['variable'][0]['variableID'];
                        if ($id <= 1 || @!IPS_ObjectExists($id)) {
                            continue;
                        }
                    }
                }
            }
            if ($id > 1 && @IPS_ObjectExists($id)) {
                $actualStatus = 0; //0 = closed
                $statusText = $this->ReadPropertyString('StatusListDesignationOK');
                if (IPS_IsConditionPassing($variable['PrimaryCondition']) && IPS_IsConditionPassing($variable['SecondaryCondition'])) {
                    $actualStatus = 1; //1 = open
                    $statusText = $this->ReadPropertyString('StatusListDesignationAlarm');
                }
                $result[] = [
                    'ID'           => $id,
                    'Name'         => $variable['Designation'],
                    'Comment'      => $variable['Comment'],
                    'ActualStatus' => $actualStatus,
                    'StatusText'   => $statusText];
            }
        }
        return json_encode($result);
    }
}