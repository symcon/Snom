<?php

require_once("phoneProperties.php");

class SnomDeskphone extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterVariableString("PhoneModel", "Phone model");
        $this->RegisterVariableString("PhoneMac", "MAC address");
        $this->RegisterHook("snom/" . $this->InstanceID);
        $this->RegisterPropertyString("PhoneIP", "");
        $this->RegisterPropertyString('LocalIP', Sys_GetNetworkInfo()[0]['IP']);
        $this->RegisterPropertyString("FkeysSettings", "[]");
    }

    // public function ApplyChanges(): void
    // {
    //     parent::ApplyChanges();
    // }

    public function instanceIpExists(): bool
    {
        $phone_ip = $this->ReadPropertyString('PhoneIP');
        $module_id = IPS_GetInstance($this->InstanceID)['ModuleInfo']['ModuleID'];
        $deskphones_instances = IPS_GetInstanceListByModuleID($module_id);

        foreach ($deskphones_instances as $deskphone_id) {
            $instance_phone_ip = IPS_GetProperty($deskphone_id, "PhoneIP");

            if ($deskphone_id != $this->InstanceID and $instance_phone_ip === $phone_ip) {
                return true;
            }
        }
        return false;
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $fkeysSettings = json_decode($this->ReadPropertyString("FkeysSettings"), true);
        $fkeysToUpdate = $this->GetFkeysToUpdate($fkeysSettings, $SenderID, $Data);
        $this->UpdateFkeys($fkeysToUpdate, $SenderID, $Data);
    }

    protected function GetFkeysToUpdate(array $fkeysSettings, int $SenderID, array $SenderData): array
    {
        $fkeysToUpdate = array();

        foreach ($fkeysSettings as $settings) {
            if ($settings["StatusVariableId"] == $SenderID) {
                $fkeyNo = (int) $settings["FkeyNo"] - 1;
                $SenderValue = $SenderData[0] ? "On" : "Off";
                $fkeysToUpdate[$fkeyNo] = array(
                    "ledNo" => PhoneProperties::getFkeyLedNo($this->GetValue("PhoneModel"), $fkeyNo),
                    "color" => $settings["FkeyColor" . $SenderValue]
                );
            }
        }

        return $fkeysToUpdate;
    }

    protected function UpdateFkeys(array $fkeysToUpdate, int $SenderID, array $Data): void
    {
        $instanceHook = sprintf("http://%s:3777/hook/snom/%d/", $this->ReadPropertyString("LocalIP"), $this->InstanceID);

        foreach ($fkeysToUpdate as $data) {
            $hookParameters = urlencode(
                $instanceHook .
                "?xml=true&variableId=" . $SenderID .
                "&value=" . (int) $Data[0] .
                "&ledNo=" . $data["ledNo"] .
                "&color=" . $data["color"]
            );
            $RenderRemoteUrl = sprintf("http://%s/minibrowser.htm?url=%s", $this->ReadPropertyString("PhoneIP"), $hookParameters);
            file_get_contents($RenderRemoteUrl);
        }
    }

    /**
     * This function will be called by the hook control. Visibility should be protected!
     */
    protected function ProcessHookData(): void
    {
        if (filter_var($_GET["xml"], FILTER_VALIDATE_BOOLEAN)) {
            $this->UpdatePhonesStatusLed($_GET);
        } else {
            $this->ExecuteAction($_GET["value"]);
        }
    }

    private function UpdatePhonesStatusLed(array $requestParameters): void
    {
        $ledValue = ($requestParameters["color"] === "none") ? "Off" : "On";
        $variableId = $requestParameters["variableId"];
        $value = $requestParameters["value"];
        $text = $variableId . " = " . $value;
        header("Content-Type: text/xml");
        $xml = $this->GetIPPhoneTextItem($text, $ledValue, $requestParameters["ledNo"], $requestParameters["color"]);
        $this->SendDebug("STATUS LED", print_r($xml, true), 0);
        echo $xml;
    }
    private function GetIPPhoneTextItem(string $text, string $ledValue, int $ledNo, string $color, int $timeout = 1): string
    {
        // header("Content-Type: text/xml");
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xmlRoot = $xml->appendChild($xml->createElement("SnomIPPhoneText"));

        //text tag
        $xmlRoot->appendChild($xml->createElement('Text', $text));

        //led tag
        $led = $xml->createElement('LED', $ledValue);
        $ledNumber = $xml->createAttribute('number');
        $ledNumber->value = $ledNo;
        $led->appendChild($ledNumber);
        $ledColor = $xml->createAttribute('color');
        $ledColor->value = $color;
        $led->appendChild($ledColor);
        $xmlRoot->appendChild($led);

        //fetch tag
        $fetch = $xml->createElement('fetch', 'snom://mb_exit');
        $fetchTimeout = $xml->createAttribute('mil');
        $fetchTimeout->value = $timeout;
        $fetch->appendChild($fetchTimeout);
        $xmlRoot->appendChild($fetch);

        $xml->format_output = TRUE;

        return $xml->saveXML();
    }

    private function ExecuteAction(string $action): void
    {
        $action = json_decode($action, true);
        $parameters = $action['parameters'];
        IPS_RunAction($action['actionID'], $parameters);
    }

    // Usage of public functions (prefix defined in module.json):
    // SNMD_PingPhone();

    public function PingPhone(string $phone_ip): void
    {
        if (Sys_Ping($phone_ip, 4000)) {
            echo "IP $phone_ip is reachable";
        } else {
            echo "IP $phone_ip is not reachable";
        }
    }

    public function setFkeyFunctionality(bool $RecieveOnly): void
    {
        $this->UpdateFormField("ActionValue", "visible", !$RecieveOnly);
        $this->UpdateFormField("StatusVariable", "visible", !$RecieveOnly);
        $this->UpdateFormField("StatusVariable", "value", $RecieveOnly);
        $this->UpdateFormField("StatusVariableId", "visible", $RecieveOnly);
    }

    public function SetVariablesIds(string $actionValue, bool $StatusVariable): void
    {
        $action = json_decode($actionValue, true);
        $this->UpdateFormField("ActionVariableId", "value", $action['parameters']['TARGET']);

        if (!$StatusVariable) {
            $this->UpdateFormField("StatusVariableId", "value", $action['parameters']['TARGET']);
        }

        $this->UpdateFormField("StatusVariableId", "visible", $StatusVariable);
    }

    public function SetFkeySettings(): void
    {
        $fkeysSettings = json_decode($this->ReadPropertyString("FkeysSettings"), true);

        foreach ($fkeysSettings as $fkeySettings) {
            $fKeyIndex = ((int) $fkeySettings["FkeyNo"]) - 1;

            if ($fkeySettings["StatusVariable"]) {
                $this->RegisterMessage($fkeySettings["StatusVariableId"], VM_UPDATE);
            } else {
                $this->RegisterMessage($fkeySettings["ActionVariableId"], VM_UPDATE);
            }

            // Move this if/else to a separated method
            if ($fkeySettings["RecieveOnly"]) {
                $fkeyValue = urlencode("none");
            } else {
                $instanceHook = sprintf("http://%s:3777/hook/snom/%d/", $this->ReadPropertyString("LocalIP"), $this->InstanceID);
                $hookParameters = "?xml=false&variableId=" . $fkeySettings["ActionVariableId"] . "&value=" . $fkeySettings["ActionValue"];
                $fkeyValue = urlencode("url " . $instanceHook . $hookParameters);
            }

            $urlQuery = sprintf("settings=save&store_settings=save&fkey%d=%s&fkey_label%d=%s", $fKeyIndex, $fkeyValue, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));

            if (PhoneProperties::hasSmartLabel($this->GetValue("PhoneModel"))) {
                $urlQuery = sprintf("%s&fkey_short_label%d=%s", $urlQuery, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));
            }

            $phoneIp = $this->ReadPropertyString("PhoneIP");
            $baseUrl = sprintf("http://%s/dummy.htm?", $phoneIp);
            $url = sprintf("%s%s", $baseUrl, $urlQuery);

            file_get_contents($url);
        }
    }

    public function fkeysAreUnique(array $fkeys_settings): bool
    {
        $fkeys_are_unique = true;

        foreach ($fkeys_settings as $key => $value) {
            if (str_contains($key, 'array')) {
                $fkeys = [];
                foreach ($value as $fkey_settings) {
                    if (!in_array($fkey_settings['FkeyNo'], $fkeys)) {
                        array_push($fkeys, $fkey_settings["FkeyNo"]);
                    } else {
                        $fkeys_are_unique = false;
                    }
                }
            }
        }

        return $fkeys_are_unique;
    }

    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $device_info = array(
            "is snom phone" => false,
            "mac address" => '00:04:13:',
            "phone model" => '',
        );
        $phone_ip = $this->ReadPropertyString("PhoneIP");

        if ($phone_ip and Sys_Ping($phone_ip, 2000)) {
            $device_info = $this->getDeviceInformation();
        }

        $data["elements"][2]["value"] = $device_info['mac address'];
        $data["elements"][3]["value"] = $device_info['phone model'];

        if (!$phone_ip) {
            $data["elements"][5]["enabled"] = false;
            $data["elements"][6]["visible"] = false;
        } elseif ($device_info['is snom phone']) {
            if ($this->instanceIpExists()) {
                $data["elements"][1]["items"][2]["caption"] = "Instance with IP $phone_ip already exists";
                $data["elements"][1]["items"][2]["visible"] = true;
                $data["elements"][5]["enabled"] = false;
                $data["elements"][6]["visible"] = false;
            } else {
                $this->SetSummary($phone_ip);
                $data["elements"][1]["items"][2]["visible"] = false;
                $data["elements"][5]["enabled"] = true;
                $data["elements"][6]["visible"] = true;
                $this->SetFkeySettings();
            }
        } else {
            $data["elements"][1]["items"][2]["visible"] = true;
            $data["elements"][5]["enabled"] = false;
            $data["elements"][6]["visible"] = false;
        }

        $data["elements"][6]["columns"][0]["edit"]["options"] = $this->getFkeysColumnsOptions();
        $data["elements"][6]["form"] = "return json_decode(SNMD_UpdateForm(\$id, (array) \$FkeysSettings, \$FkeysSettings['RecieveOnly'] ?? false, \$FkeysSettings['StatusVariable'] ?? true), true);";

        return json_encode($data);
    }

    public function getDeviceInformation(): array
    {
        $phone_ip = $this->ReadPropertyString("PhoneIP");

        // symbox 7.0 november 2023
        exec('arp ' . $phone_ip . ' | awk \'{print $4}\'', $output, $exec_status);
        $output_mac = $output[0];

        if (!str_contains($output_mac, ':')) {
            // raspberry os
            exec('arp ' . $phone_ip . ' | awk \'{print $3}\'', $output_raspberrypi, $exec_status);
            $output_mac = $output_raspberrypi[1];
        }

        if (str_contains($output_mac, '00:04:13:')) {
            $phone_settings_xml = simplexml_load_file("http://$phone_ip/settings.xml");
            $phone_model = (string) $phone_settings_xml->{'phone-settings'}->phone_type[0];
            $this->SetValue('PhoneModel', $phone_model);
            $this->SetValue('PhoneMac', $output_mac);

            return array(
                "is snom phone" => true,
                "mac address" => $output_mac,
                "phone model" => $phone_model,
            );
        } else {
            return array(
                "is snom phone" => false,
                "mac address" => '00:04:13:',
                "phone model" => '',
            );
        }

    }

    public function UpdateForm(array $FkeysSettings, bool $recvOnly, bool $StatusVariable): string
    {
        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $data["elements"][6]["form"][0]["options"] = $this->getFkeysFormOptions($FkeysSettings);
        $data["elements"][6]["form"][0]["value"] = $this->getSelectedFkeyNo($FkeysSettings);
        $data["elements"][6]["form"][6]["visible"] = !$recvOnly;
        $data["elements"][6]["form"][7]["visible"] = !$recvOnly;
        $data["elements"][6]["form"][8]["visible"] = $StatusVariable;

        return json_encode($data["elements"][6]["form"]);
    }

    public function getFkeysFormOptions(array $FkeysSettings): array
    {
        // add to options only fkeys that are not already in the list
        $fkeys = [];
        $selected = -2;

        foreach ($FkeysSettings as $key => $value) {
            if (str_contains($key, 'selected')) {
                $selected = $value;
            }
            if (str_contains($key, 'array')) {
                foreach ($value as $fkey_settings) {
                    array_push($fkeys, $fkey_settings["FkeyNo"]);
                }
            }
        }

        $phoneModel = $this->GetValue("PhoneModel");
        $fkeysRange = PhoneProperties::getFkeysRange($phoneModel);
        $options = [];

        //onEdit
        if ($selected != -1) {
            $selected += 1;
            $option = ["caption" => "P$selected" , "value" => $selected];
            array_push($options, $option);
        }

        foreach ($fkeysRange as $fkeyNo) {
            if (!in_array($fkeyNo, $fkeys)) {
                $option = ["caption" => "P$fkeyNo" , "value" => $fkeyNo];
                array_push($options, $option);
            }
        }

        return $options;
    }

    public function getSelectedFkeyNo(array $FkeysSettings): int
    {
        $selected = -2;
        foreach ($FkeysSettings as $key => $value) {
            if (str_contains($key, 'selected')) {
                $selected_index = $value;
            }
            if (str_contains($key, 'array')) {
                foreach ($value as $index => $fkey_settings) {
                    if ($index === $selected_index) {
                        $selected = $fkey_settings["FkeyNo"];
                    }
                }
            }
        }

        return $selected;
    }

    public function getFkeysColumnsOptions(): array
    {
        $phoneModel = $this->GetValue("PhoneModel");
        $fkeysRange = PhoneProperties::getFkeysRange($phoneModel);
        $options = [];

        foreach ($fkeysRange as $fkeyNo) {
            $option = ["caption" => "P$fkeyNo" , "value" => $fkeyNo];
            array_push($options, $option);
        }

        return $options;
    }

    // has_expanstion_module()
}