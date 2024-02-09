<?php

require_once("deviceProperties.php");
require_once("minibrowser.php");

const DISPLAY_STATUS = 1;
const UPDATE_LED = 2;
const UPDATE_LED_AND_ACTION = 3;

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
		$valueChanged = $Data[1];

		if ($valueChanged) {	
			$fkeysSettings = json_decode($this->ReadPropertyString("FkeysSettings"), true);
			$fkeysToUpdate = $this->getFkeysToUpdate($fkeysSettings, $SenderID, $Data);
			$minibrowserUrls = $this->getMinibrowserUrls($fkeysToUpdate, $SenderID, $Data);
			
			foreach ($minibrowserUrls as $minibrowserUrl) {
				$this->httpGetRequest($minibrowserUrl);
			}
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


    protected function getMinibrowserUrls(array $fkeysToUpdate, int $SenderID, array $Data): array
    {
        $protocol = $this->ReadPropertyString("Protocol");
        $instanceHook = sprintf("http://%s:3777/hook/snom/%d/", $this->ReadPropertyString("LocalIP"), $this->InstanceID);
        $minibrowserUrls = array();

        foreach ($fkeysToUpdate as $data) {
            $hookParameters = urlencode(
                $instanceHook .
                "?xml=true&variableId=" . $SenderID .
                "&value=" . GetValueFormatted($SenderID) .
                "&ledNo=" . $data["ledNo"] .
                "&color=" . $data["color"] .
                "&timeout=1"
            );
            $minibrowserUrl = sprintf("$protocol://%s/minibrowser.htm?url=%s", $this->ReadPropertyString("PhoneIP"), $hookParameters);
            array_push($minibrowserUrls, $minibrowserUrl);
        }

        return $minibrowserUrls;
    }

    /**
     * Visibility of functions called by the hook control should be protected!
     */
    protected function ProcessHookData(): void
    {
        if (filter_var($_GET["xml"], FILTER_VALIDATE_BOOLEAN)) {
            $variableId = $_GET["variableId"];

            if ($_GET["value"] === "read") {
                $_GET["value"] = GetValueFormatted($variableId);
            }

            $_GET["variable"] = IPS_GetName($variableId);
            $parentId = IPS_GetParent($_GET["variableId"]);
            $_GET["variable parent"] = IPS_GetName($parentId);
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

    public function setFkeyFunctionality(int $functionality): void
    {
        switch ($functionality) {
            case DISPLAY_STATUS:
                $this->UpdateFormField("FkeyColorOn", "visible", false);
                $this->UpdateFormField("FkeyColorOff", "visible", false);
                $this->UpdateFormField("ActionValue", "visible", false);
                $this->UpdateFormField("StatusVariable", "visible", false);
                $this->UpdateFormField("StatusVariable", "value", true);
                $this->UpdateFormField("StatusVariableId", "visible", true);
                break;
            case UPDATE_LED:
                $this->UpdateFormField("ActionValue", "visible", false);
                $this->UpdateFormField("StatusVariable", "visible", false);
                $this->UpdateFormField("StatusVariable", "value", true);
                $this->UpdateFormField("StatusVariableId", "visible", true);
                break;
            case UPDATE_LED_AND_ACTION;
                $this->UpdateFormField("ActionValue", "visible", true);
                $this->UpdateFormField("StatusVariable", "visible", true);
                $this->UpdateFormField("StatusVariable", "value", false);
                $this->UpdateFormField("StatusVariableId", "visible", false);
                break;
            default:
                $this->UpdateFormField("ActionValue", "visible", true);
                $this->UpdateFormField("StatusVariable", "visible", true);
                $this->UpdateFormField("StatusVariable", "value", false);
                $this->UpdateFormField("StatusVariableId", "visible", false);
        }
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

            $actionUrl = $this->getFkeyActionUrl($fkeySettings);
            $urlQuery = sprintf("settings=save&store_settings=save&fkey%d=%s&fkey_label%d=%s", $fKeyIndex, $actionUrl, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));

            if (DeviceProperties::hasSmartLabel($this->GetValue("PhoneModel"))) {
                $urlQuery = sprintf("%s&fkey_short_label%d=%s", $urlQuery, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));
            }

            $phoneIp = $this->ReadPropertyString("PhoneIP");
            $baseUrl = sprintf("$protocol://%s/dummy.htm?", $phoneIp);
            $url = sprintf("%s%s", $baseUrl, $urlQuery);
            $this->httpGetRequest($url); // TODO: execute the urls outside of this method (SRP)
        }
    }

    public function getFkeyActionUrl(array $fkeySettings): string
    {
        $instanceHook = sprintf("http://%s:3777/hook/snom/%d/", $this->ReadPropertyString("LocalIP"), $this->InstanceID);

        switch ($fkeySettings["Functionality"]) {
            case DISPLAY_STATUS:
                $timeout = 5000;
                $variableIdToDisplay = $fkeySettings["StatusVariableId"];
                $hookParameters = "?xml=true&variableId=$variableIdToDisplay&value=read&timeout=$timeout";
                return urlencode("url $instanceHook$hookParameters");
            case UPDATE_LED:
                return urlencode("none");
            case UPDATE_LED_AND_ACTION:
                $variableIdToWrite = $fkeySettings["ActionVariableId"];
                $valueToWrite = $fkeySettings["ActionValue"];
                $hookParameters = "?xml=false&variableId=$variableIdToWrite&value=$valueToWrite";
                return urlencode("url $instanceHook$hookParameters");
            default:
                return urlencode("none");
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
        $data["elements"][7]["form"] = "return json_decode(SNMD_UpdateForm(\$id, (array) \$FkeysSettings, \$FkeysSettings['Functionality'] ?? false, \$FkeysSettings['StatusVariable'] ?? true), true);";

        return json_encode($data);
    }

    public function getDeviceInformation(): array
    {
        $mac_address = $this->getMacAddress();

        if (str_contains($mac_address, '00:04:13:') or str_contains($mac_address, '0:4:13:')) { // for MacOS '0:4:13:'
            $phone_ip = $this->ReadPropertyString("PhoneIP");
            $protocol = $this->ReadPropertyString("Protocol");
            $url = "$protocol://$phone_ip/settings.xml";
            $response = $this->httpGetRequest($url, headerOutput: false);
            $phone_settings_xml = @simplexml_load_string($response);

            if ($phone_settings_xml) {
                $phone_model = (string) $phone_settings_xml->{'phone-settings'}->phone_type[0];
                $this->SetValue('PhoneModel', $phone_model);
                $this->SetValue('PhoneMac', $mac_address);

                return array(
                    "is snom phone" => true,
                    "mac address" => $mac_address,
                    "phone model" => $phone_model,
                );
            } else {
                return array(
                    "is snom phone" => true,
                    "mac address" => '00:04:13:',
                    "phone model" => '',
                );
            }
        } else {
            return array(
                "is snom phone" => false,
                "mac address" => '00:04:13:',
                "phone model" => '',
            );
        }
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
                            $message = "$http_code $response";
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

            return $message;
        }
        curl_close($handler);

        return $response;
    }

    public function UpdateForm(array $fkeysSettings, int $functionality, bool $StatusVariable): string
    {
        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $data["elements"][7]["form"][0]["options"] = $this->getFkeysFormOptions($fkeysSettings);
        $data["elements"][7]["form"][0]["value"] = $this->getSelectedFkeyNo($fkeysSettings);
        $data["elements"][7]["form"][2]["visible"] = !($functionality === DISPLAY_STATUS);
        $data["elements"][7]["form"][3]["visible"] = !($functionality === DISPLAY_STATUS);
        $data["elements"][7]["form"][6]["visible"] = $functionality === UPDATE_LED_AND_ACTION ? true : false;
        $data["elements"][7]["form"][7]["visible"] = $functionality === UPDATE_LED_AND_ACTION ? true : false;
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