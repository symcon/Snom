<?php

require_once("deviceProperties.php");
require_once("minibrowser.php");

class SnomDeskphone extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterVariableString("PhoneModel", "Phone model");
        $this->RegisterVariableString("PhoneMac", "MAC address");
        $this->RegisterHook("snom/" . $this->InstanceID);
        $this->RegisterPropertyString("PhoneIP", "");
        $this->RegisterPropertyString("Protocol", "http");
        $this->RegisterPropertyString("Username", "");
        $this->RegisterPropertyString("Password", "");
        $this->RegisterPropertyString('LocalIP', Sys_GetNetworkInfo()[0]['IP']);
        $this->RegisterPropertyString("FkeysSettings", "[]");
    }

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
        $fkeysToUpdate = $this->getFkeysToUpdate($fkeysSettings, $SenderID, $Data);
        $urls = $this->getUrls($fkeysToUpdate, $SenderID, $Data);

        foreach ($urls as $url) {
            $this->httpGetRequest($url);
        }
    }

    protected function getFkeysToUpdate(array $fkeysSettings, int $SenderID, array $SenderData): array
    {
        $fkeysToUpdate = array();

        foreach ($fkeysSettings as $settings) {
            if ($settings["StatusVariableId"] == $SenderID) {
                $fkeyNo = (int) $settings["FkeyNo"] - 1;
                $SenderValue = $SenderData[0] ? "On" : "Off";
                $phoneModel = $this->GetValue("PhoneModel");
                $fkeysToUpdate[$fkeyNo] = array(
                    "ledNo" => DeviceProperties::getFkeyLedNo($phoneModel, $fkeyNo),
                    "color" => $settings["FkeyColor" . $SenderValue]
                );
            }
        }

        return $fkeysToUpdate;
    }


    // build minibrowser url
    protected function getUrls(array $fkeysToUpdate, int $SenderID, array $Data): array
    {
        $protocol = $this->ReadPropertyString("Protocol");
        $instanceHook = sprintf("http://%s:3777/hook/snom/%d/", $this->ReadPropertyString("LocalIP"), $this->InstanceID);
        $urls = array();

        foreach ($fkeysToUpdate as $data) {
            $hookParameters = urlencode(
                $instanceHook .
                "?xml=true&variableId=" . $SenderID . // TODO: rename xml parameter to something meaningful
                "&value=" . (int) $Data[0] .
                "&ledNo=" . $data["ledNo"] .
                "&color=" . $data["color"]
            );
            $url = sprintf("$protocol://%s/minibrowser.htm?url=%s", $this->ReadPropertyString("PhoneIP"), $hookParameters);
            array_push($urls, $url);
        }

        return $urls;
    }

    /**
     * Visibility of functions called by the hook control should be protected!
     */
    protected function ProcessHookData(): void
    {
        if (filter_var($_GET["xml"], FILTER_VALIDATE_BOOLEAN)) {
            $xml = new SnomXmlMinibrowser($_GET);
            $this->SendDebug("SNOM MINIBROWSER", print_r($xml->minibrowser, true), 0);
            $xml->executeMinibrowser();
        } else {
            $action = json_decode($_GET["value"], true);
            IPS_RunAction($action['actionID'], $action['parameters']);
        }
    }

    public function PingPhone(string $phone_ip): string
    {
        if (Sys_Ping($phone_ip, 4000)) {
            return "IP $phone_ip is reachable";
        }

        return "IP $phone_ip is not reachable";
    }

    public function setFkeyFunctionality(bool $UpdateLEDonly): void
    {
        $this->UpdateFormField("ActionValue", "visible", !$UpdateLEDonly);
        $this->UpdateFormField("StatusVariable", "visible", !$UpdateLEDonly);
        $this->UpdateFormField("StatusVariable", "value", $UpdateLEDonly);
        $this->UpdateFormField("StatusVariableId", "visible", $UpdateLEDonly);
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
        $protocol = $this->ReadPropertyString("Protocol");

        foreach ($fkeysSettings as $fkeySettings) {
            $fKeyIndex = ((int) $fkeySettings["FkeyNo"]) - 1;

            if ($fkeySettings["StatusVariable"]) {
                $this->RegisterMessage($fkeySettings["StatusVariableId"], VM_UPDATE);
            } else {
                $this->RegisterMessage($fkeySettings["ActionVariableId"], VM_UPDATE);
            }

            // TODO: Move this if/else to a separated method
            if ($fkeySettings["UpdateLEDonly"]) {
                $fkeyValue = urlencode("none");
            } else {
                $instanceHook = sprintf("http://%s:3777/hook/snom/%d/", $this->ReadPropertyString("LocalIP"), $this->InstanceID);
                $hookParameters = "?xml=false&variableId=" . $fkeySettings["ActionVariableId"] . "&value=" . $fkeySettings["ActionValue"];
                $fkeyValue = urlencode("url " . $instanceHook . $hookParameters);
            }

            $urlQuery = sprintf("settings=save&store_settings=save&fkey%d=%s&fkey_label%d=%s", $fKeyIndex, $fkeyValue, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));

            if (DeviceProperties::hasSmartLabel($this->GetValue("PhoneModel"))) {
                $urlQuery = sprintf("%s&fkey_short_label%d=%s", $urlQuery, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));
            }

            $phoneIp = $this->ReadPropertyString("PhoneIP");
            $baseUrl = sprintf("$protocol://%s/dummy.htm?", $phoneIp);
            $url = sprintf("%s%s", $baseUrl, $urlQuery);
            $this->httpGetRequest($url); // TODO: execute the urls outside of this method (SRP)
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
        $phone_ip = $this->ReadPropertyString("PhoneIP");
        $device_info = $this->getDeviceInformation();

        $data["elements"][3]["value"] = $device_info['mac address'];
        $data["elements"][4]["value"] = $device_info['phone model'];

        if (!$phone_ip) {
            $data["elements"][6]["enabled"] = false;
            $data["elements"][7]["visible"] = false;
        } elseif ($device_info['is snom phone']) {
            $isFullMacAddress = substr_count($device_info["mac address"], ':')  === 5;
            $phone_ip = $this->ReadPropertyString("PhoneIP");
            $protocol = $this->ReadPropertyString("Protocol");
            $url = "$protocol://$phone_ip";
            $message = $this->httpGetRequest($url, return_message: true);

            if (!$isFullMacAddress) {
                if ($message === "401") {
                    $data["elements"][2]["items"][2]["visible"] = true;
                    $data["elements"][2]["items"][3]["visible"] = true;
                    $data["elements"][6]["enabled"] = false;
                    $data["elements"][7]["visible"] = false;
                } elseif ($message === "Login failed") {
                    $data["elements"][2]["items"][2]["visible"] = true;
                    $data["elements"][2]["items"][3]["visible"] = true;
                    $data["elements"][2]["items"][4]["caption"] = $message;
                    $data["elements"][2]["items"][4]["visible"] = true;
                    $data["elements"][6]["enabled"] = false;
                    $data["elements"][7]["visible"] = false;
                } else {
                    $data["elements"][2]["items"][4]["caption"] = $message;
                    $data["elements"][2]["items"][4]["visible"] = true;
                    $data["elements"][6]["enabled"] = false;
                    $data["elements"][7]["visible"] = false;
                }
            } elseif ($this->instanceIpExists()) {
                $data["elements"][2]["items"][4]["caption"] = "Instance with IP $phone_ip already exists";
                $data["elements"][2]["items"][4]["visible"] = true;
                $data["elements"][6]["enabled"] = false;
                $data["elements"][7]["visible"] = false;
            } else {
                $this->SetSummary($phone_ip);
                $needs_credentials = $this->ReadPropertyString("Username") and $this->ReadPropertyString("Password");

                if ($needs_credentials) {
                    $data["elements"][2]["items"][2]["visible"] = true;
                    $data["elements"][2]["items"][3]["visible"] = true;
                }

                $data["elements"][2]["items"][4]["visible"] = false;
                $data["elements"][6]["visible"] = true;
                $this->SetFkeySettings();
            }
        } else {
            $data["elements"][2]["items"][4]["visible"] = true;
            $data["elements"][6]["enabled"] = false;
            $data["elements"][7]["visible"] = false;
        }

        $data["elements"][7]["columns"][0]["edit"]["options"] = $this->getFkeysColumnsOptions();
        $data["elements"][7]["form"] = "return json_decode(SNMD_UpdateForm(\$id, (array) \$FkeysSettings, \$FkeysSettings['UpdateLEDonly'] ?? false, \$FkeysSettings['StatusVariable'] ?? true), true);";

        return json_encode($data);
    }

    public function getDeviceInformation(): array
    {
        $phoneIp = $this->ReadPropertyString("PhoneIP");
        $deviceInfo = array(
            "is snom phone" => false,
            "mac address" => '00:04:13:',
            "phone model" => '',
        );

        if ($phoneIp and Sys_Ping($phoneIp, 2000)) {
            $macAddress = $this->getMacAddress();

            if (str_contains($macAddress, '00:04:13:') or str_contains($macAddress, '0:4:13:')) { // for MacOS '0:4:13:'
                $phoneIp = $this->ReadPropertyString("PhoneIP");
                $protocol = $this->ReadPropertyString("Protocol");
                $url = "$protocol://$phoneIp/settings.xml";
                $response = $this->httpGetRequest($url, return_message: true, headerOutput: false);
                $phoneSettings = @simplexml_load_string($response);

                if ($phoneSettings) {
                    $phoneModel = (string) $phoneSettings->{'phone-settings'}->phone_type[0];
                    $this->SetValue('PhoneModel', $phoneModel);
                    $this->SetValue('PhoneMac', $macAddress);
                    $deviceInfo["is snom phone"] = true;
                    $deviceInfo["mac address"] = $macAddress;
                    $deviceInfo["phone model"] = $phoneModel;
                } else {
                    $deviceInfo["is snom phone"] = true;
                }
            }
        }
        return $deviceInfo;
    }

    public function getMacAddress(): string
    {
        $mac_address = "none";
        $phone_ip = $this->ReadPropertyString("PhoneIP");
        exec('uname', $isLinux, $exec_status);

        if ($isLinux) {
            // symbox 7.0 november 2023
            exec('arp ' . $phone_ip . ' | awk \'{print $4}\'', $output, $exec_status);
            $mac_address = $output[0];

            if (!str_contains($mac_address, ':')) {
                // raspberry os
                exec('arp ' . $phone_ip . ' | awk \'{print $3}\'', $output_raspberrypi, $exec_status);
                $mac_address = $output_raspberrypi[1];
            }
        } else {
            // windows
            exec('arp -a ' . $phone_ip, $output, $exec_status);
            $output_array = explode(' ', $output[3]);
            $mac_address = str_replace("-", ":", $output_array[11]);
        }

        return $mac_address;
    }

    public function httpGetRequest(string $url, bool $return_message = false, bool $headerOutput = true): bool|string
    {
        $protocol = $this->ReadPropertyString("Protocol");
        $this->SendDebug("url", print_r($url, true), 0);

        $handler = curl_init();
        curl_setopt($handler, CURLOPT_URL, $url);
        curl_setopt($handler, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS | CURLPROTO_HTTP);
        curl_setopt($handler, CURLOPT_HEADER, $headerOutput);
        curl_setopt($handler, CURLOPT_RETURNTRANSFER, true);

        // Workaround: minibrowser.htm will delay 8 seconds if we send this header
        // but we need this header for all other requests. Otherwise,
        // we will get curl error code 52 -> no relpy
        if (!str_contains($url, "minibrowser.htm")) {
            curl_setopt($handler, CURLOPT_HTTPHEADER, [
                'Connection: keep-alive',
            ]);
        }

        $username = $this->ReadPropertyString("Username");
        $password = $this->ReadPropertyString("Password");

        if ($username and $password) {
            curl_setopt($handler, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST | CURLAUTH_BASIC);
            curl_setopt($handler, CURLOPT_USERNAME, $username);
            curl_setopt($handler, CURLOPT_PASSWORD, $password);
        }

        if ($protocol === "https") {
            curl_setopt($handler, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($handler, CURLOPT_SSL_VERIFYPEER, false);
        }

        $response = curl_exec($handler);

        if ($return_message) {
            $curl_errno = curl_errno($handler);
            $message = "Curl handle error";

            if (!$curl_errno) {
                switch ($http_code = curl_getinfo($handler, CURLINFO_HTTP_CODE)) {
                    case 200:
                        $isSnomD8xx = str_contains($response, "<title>Phone Manager</title>");
                        $loginFailed = str_contains($response, "Login failed!");

                        if ($isSnomD8xx) {
                            $message = "Snom D8xx not supported. HTTP $http_code";
                        } elseif ($loginFailed) {
                            if (str_contains($response, "unsuccessful login attempts")) {
                                $message = "Unsuccessful login attempts.Wait...";
                            } else {
                                $message = "Login failed";
                            }
                        } else {
                            $message = "";
                        }
                        break;
                    case 303:
                        $message = "Snom M900 not supported. HTTP $http_code";
                        break;
                    case 401:
                        $message = "$http_code";
                        break;
                    default:
                        $message = "$http_code";
                }
            } else {
                switch (curl_errno($handler)) {
                    case 7:
                        $message = "Accepts your Snom phone HTTP or HTTPS?\n" . curl_error($handler);
                        break;
                    default:
                        $message = curl_error($handler) . "\n(Curl error " . curl_errno($handler) . " " .  " HTTP: " . curl_getinfo($handler, CURLINFO_HTTP_CODE) . ")";
                }
            }

            curl_close($handler);

            if ($message) {
                return $message;
            }
            return $response;
        }
        curl_close($handler);

        return $response;
    }

    public function UpdateForm(array $fkeysSettings, bool $recvOnly, bool $StatusVariable): string
    {
        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $data["elements"][7]["form"][0]["options"] = $this->getFkeysFormOptions($fkeysSettings);
        $data["elements"][7]["form"][0]["value"] = $this->getSelectedFkeyNo($fkeysSettings);

        $data["elements"][7]["form"][6]["visible"] = !$recvOnly;
        $data["elements"][7]["form"][7]["visible"] = !$recvOnly;
        $data["elements"][7]["form"][8]["visible"] = $StatusVariable;

        return json_encode($data["elements"][7]["form"]);
    }

    public function getFkeysFormOptions(array $FkeysSettings): array
    {
        $current_fkeys = $this->getCurrentFkeys($FkeysSettings);
        $options = $this->getCurrentFkeyOptionOnEdit($FkeysSettings, $current_fkeys);
        $phoneModel = $this->GetValue("PhoneModel");
        $expansionModule = $this->connectedExpansionModule();
        $phoneFkeysRange = DeviceProperties::getFkeysRange($phoneModel);
        $expansionFkeysRange = DeviceProperties::getExpansionFkeysRange($phoneModel, $expansionModule);
        $fkeysRange = array_merge($phoneFkeysRange, $expansionFkeysRange);

        foreach ($fkeysRange as $fkeyNo) {
            if (!in_array($fkeyNo, $current_fkeys)) {
                $option = $this->getSelectOption($fkeyNo, boolval($expansionModule));
                array_push($options, $option);
            }
        }

        return $options;
    }

    public function getCurrentFkeys($FkeysSettings): array
    {
        $current_fkeys = [];

        foreach ($FkeysSettings as $key => $value) {
            if (str_contains($key, 'array')) {
                foreach ($value as $fkey_settings) {
                    array_push($current_fkeys, $fkey_settings["FkeyNo"]);
                }
            }
        }

        return $current_fkeys;
    }

    public function getCurrentFkeyOptionOnEdit($FkeysSettings, $current_fkeys): array
    {
        $selected = -2;

        foreach ($FkeysSettings as $key => $value) {
            if (str_contains($key, 'selected')) {
                $selected = $value;
            }
        }

        $options = [];

        if ($selected === -2) {
            echo "Invalid selected row $selected";
        } elseif ($selected != -1) {
            $expansionModuleConnected = boolval($this->connectedExpansionModule());
            $option = $this->getSelectOption($current_fkeys[$selected], $expansionModuleConnected);
            array_push($options, $option);
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
                    } else {
                        $selected = $selected_index;
                    }
                }
            }
        }

        return $selected;
    }

    public function getFkeysColumnsOptions(): array
    {
        $phoneModel = $this->GetValue("PhoneModel");
        $phoneFkeysRange = DeviceProperties::getFkeysRange($phoneModel);
        $expansionFkeysRange = DeviceProperties::getMaxExpansionFkeysRange($phoneModel);
        $fkeysRange = array_merge($phoneFkeysRange, $expansionFkeysRange);
        $expansionModuleConnected = boolval($this->connectedExpansionModule());
        $options = [];

        foreach ($fkeysRange as $fkeyNo) {
            $option = $this->getSelectOption($fkeyNo, $expansionModuleConnected);
            array_push($options, $option);
        }

        return $options;
    }

    public function getSelectOption(int $fkeyNo, bool $expansionModule = false): array
    {
        $phoneModel = $this->GetValue("PhoneModel");
        $phoneFkeysNo = DeviceProperties::FKEYS_NO[$phoneModel];
        $caption = "P$fkeyNo (phone)";

        if ($fkeyNo > $phoneFkeysNo) {
            if ($phoneModel === "snomD385") {
                $caption = strval($fkeyNo - $phoneFkeysNo - 126);
            } else {
                $caption = strval($fkeyNo - $phoneFkeysNo);
            }
            if (!$expansionModule) {
                $caption = $caption . " expansion not connected";    
            } else {
                $caption = $caption . " (expansion module)";  
            }
        }

        return ["caption" => $caption, "value" => $fkeyNo];
    }

    public function connectedExpansionModule(): string
    {
        $connectedExpansionModule = "";
        $phoneIp = $this->ReadPropertyString("PhoneIP");
        $protocol = $this->ReadPropertyString("Protocol");
        $url = "$protocol://$phoneIp/info.htm";
        $phoneInfo = $this->httpGetRequest($url);
        $expansionModules = DeviceProperties::EXPANSION_MODULES;

        foreach ($expansionModules as $expansionModel) {
            if (str_contains($phoneInfo, "$expansionModel ")) {
                $connectedExpansionModule = "snom$expansionModel";
                break;
            }
        }

        return $connectedExpansionModule;
    }
}