<?php

require_once("deviceProperties.php");
require_once("minibrowser.php");

// Fkey functionality
const DISPLAY_STATUS = 1;
const UPDATE_LED = 2;
const TRIGGER_ACTION_UPDATE_LED = 3;

// Phone informations
const MAC_ADDRESS = 1;
const PHONE_MODEL = 2;

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
            case TRIGGER_ACTION_UPDATE_LED;
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

    public function setFkeysSettings(): void 
    {
        $settingsUrls = $this->getFkeySettingsUrls();

        foreach ($settingsUrls as $settingsUrl) {
            $this->httpGetRequest($settingsUrl);
        }
    }

    public function getFkeySettingsUrls(): array
    {
        $fkeysSettings = json_decode($this->ReadPropertyString("FkeysSettings"), true);
        $protocol = $this->ReadPropertyString("Protocol");
        $settingsUrls = array();

        foreach ($fkeysSettings as $fkeySettings) {
            $fKeyIndex = ((int) $fkeySettings["FkeyNo"]) - 1;

            if ($fkeySettings["StatusVariable"]) {
                $this->RegisterMessage($fkeySettings["StatusVariableId"], VM_UPDATE);
            } else {
                $this->RegisterMessage($fkeySettings["ActionVariableId"], VM_UPDATE);
            }

            $actionUrl = $this->getFkeyActionUrl($fkeySettings);
            $urlParameters = sprintf("settings=save&store_settings=save&fkey%d=%s&fkey_label%d=%s", $fKeyIndex, $actionUrl, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));

            if (DeviceProperties::hasSmartLabel($this->GetValue("PhoneModel"))) {
                $urlParameters = sprintf("%s&fkey_short_label%d=%s", $urlParameters, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));
            }

            $phoneIp = $this->ReadPropertyString("PhoneIP");
            array_push($settingsUrls, "$protocol://$phoneIp/dummy.htm?$urlParameters");
        }

        return $settingsUrls;
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
            case TRIGGER_ACTION_UPDATE_LED:
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
        $phoneIp = $this->ReadPropertyString("PhoneIP");
        $message = $phoneIp ? $this->PingPhone($phoneIp) : "";
        $data["elements"][2]["items"][4]["visible"] = true;

        if ($this->instanceIpExists()) {
            $message = "Instance with IP $phoneIp already exists";
            $data["elements"][2]["items"][4]["caption"] = $message;
            $data["elements"][6]["enabled"] = false;
            $data["elements"][7]["visible"] = false;
        } elseif (str_contains($message, "is reachable")) {
            $protocol = $this->ReadPropertyString("Protocol");
            $url = "$protocol://$phoneIp/info.htm";
            $response = $this->httpGetRequest($url, return_message:true, headerOutput: false);
            $httpCode = key($response);
            $httpContent = $response[$httpCode];
            $message = "Only " . implode(", ", DeviceProperties::PHONE_MODELS) . " supported.\nNot found $httpCode";
            $isSnomD8xx = str_contains($httpContent, "<title>Phone Manager</title>");

            if ($isSnomD8xx) { 
                $data["elements"][2]["items"][2]["visible"] = false;
                $data["elements"][2]["items"][3]["visible"] = false;
                $data["elements"][6]["enabled"] = false;
                $data["elements"][7]["visible"] = false;
            } else {
                switch ($httpCode) {
                    case 0:
                        $message = $httpContent;
                        $data["elements"][2]["items"][2]["visible"] = false;
                        $data["elements"][2]["items"][3]["visible"] = false;
                        $data["elements"][6]["enabled"] = false;
                        $data["elements"][7]["visible"] = false;
                        break;
                    case 200:
                        $message = $httpCode;
                        $data["elements"][2]["items"][2]["visible"] = true;
                        $data["elements"][2]["items"][3]["visible"] = true;
                        $macAddress = $this->getPhoneInformation($httpContent, MAC_ADDRESS);
                        $this->SetValue('PhoneMac', $macAddress);
                        $data["elements"][3]["value"] = $macAddress;
                        $phoneModel = $this->getPhoneInformation($httpContent, PHONE_MODEL);
                        $this->SetValue('PhoneModel', $phoneModel);
                        $this->SetSummary($phoneIp);
                        $data["elements"][4]["value"] = $phoneModel;
                        $data["elements"][6]["enabled"] = true;
                        $data["elements"][7]["visible"] = true;
                        break;
                    case 307:
                        $message = "Accepts your Snom phone HTTP or HTTPS?\nRedirect $httpCode";
                        $data["elements"][2]["items"][2]["visible"] = false;
                        $data["elements"][2]["items"][3]["visible"] = false;
                        $data["elements"][6]["enabled"] = false;
                        $data["elements"][7]["visible"] = false;
                        break;
                    case 401:
                        $message = "Needs credentials. $httpCode";
                        $data["elements"][2]["items"][2]["visible"] = true;
                        $data["elements"][2]["items"][3]["visible"] = true;
                        $data["elements"][6]["enabled"] = false;
                        $data["elements"][7]["visible"] = false;
                        break;
                    case 404:
                        $data["elements"][2]["items"][2]["visible"] = false;
                        $data["elements"][2]["items"][3]["visible"] = false;
                        $data["elements"][6]["enabled"] = false;
                        $data["elements"][7]["visible"] = false;
                        break;
                    default:
                        $data["elements"][2]["items"][2]["visible"] = false;
                        $data["elements"][2]["items"][3]["visible"] = false;
                        $data["elements"][6]["enabled"] = false;
                        $data["elements"][7]["visible"] = false;
                        echo $httpContent;
                }
            }
            $data["elements"][2]["items"][4]["caption"] = $message;
        } else {
            $data["elements"][2]["items"][2]["visible"] = false;
            $data["elements"][2]["items"][3]["visible"] = false;
            $data["elements"][2]["items"][4]["caption"] = $message;
            $data["elements"][6]["enabled"] = false;
            $data["elements"][7]["visible"] = false;
        }

        $data["elements"][7]["columns"][0]["edit"]["options"] = $this->getFkeysColumnsOptions();
        $data["elements"][7]["form"] = "return json_decode(SNMD_UpdateForm(\$id, (array) \$FkeysSettings, \$FkeysSettings['Functionality'] ?? false, \$FkeysSettings['StatusVariable'] ?? true), true);";
        $this->setFkeysSettings();

        return json_encode($data);
    }

    public function getPhoneInformation(string $response, int $property): string
    {
        $information = "Searching phone information...";

        switch ($property) {
            case MAC_ADDRESS:
                $information = "searching MAC";
                $pattern = "/MAC Address<\/TD><TD>[0-9A-F]{12}/i";
                preg_match($pattern, $response, $matches);
                $information = str_replace("MAC Address</TD><TD>","",$matches[0]);
                break;
            case PHONE_MODEL:
                $information = "searching phone model...";
                $pattern = "/<TITLE>snom D[0-9]{3}/i";
                preg_match($pattern, $response, $matches);
                $phoneModel = str_replace(["<TITLE>", " "],"",$matches[0]);
                $isValidPhoneModel = in_array($phoneModel, DeviceProperties::PHONE_MODELS);
                $information = $isValidPhoneModel ? $phoneModel : "Not found";
                break;
            default:
                $information = "Not able to get information";
        }

        return $information;
    }

    public function httpGetRequest(string $url, bool $return_message = false, bool $headerOutput = true): bool|string|array
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
                $message = [curl_getinfo($handler, CURLINFO_HTTP_CODE) => $response];
            } else {
                switch (curl_errno($handler)) {
                    case 7:
                        $message = [0 => "Accepts your Snom phone HTTP or HTTPS?\n" . curl_error($handler)];
                        break;
                    default:
                        $message = [0 => curl_error($handler) . "\n(Curl error " . curl_errno($handler) . " " .  " HTTP: " . curl_getinfo($handler, CURLINFO_HTTP_CODE) . ")"];
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
        $data["elements"][7]["form"][6]["visible"] = $functionality === TRIGGER_ACTION_UPDATE_LED ? true : false;
        $data["elements"][7]["form"][7]["visible"] = $functionality === TRIGGER_ACTION_UPDATE_LED ? true : false;
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