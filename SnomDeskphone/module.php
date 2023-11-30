<?php

require_once("phoneProperties.php");

class SnomDeskphone extends IPSModuleStrict
{
    public function Create(): void
    {
        parent::Create();
        $this->RegisterHook("snom/" . $this->InstanceID);
        $this->RegisterPropertyString("PhoneIP", "");
        $this->RegisterPropertyString("PhoneMac", "000413");
        $this->RegisterPropertyString("PhoneModel", "");
        //TODO: local ip only if symcon runs in docker, otherwise retreive local ip
        $this->RegisterPropertyString("LocalIP", "127.0.0.1");
        $this->RegisterPropertyString("FkeysSettings", "[]");
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetSummary($this->ReadPropertyString("PhoneModel") . "/" . $this->ReadPropertyString("PhoneMac"));
        $this->SetFkeySettings();
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
                    "ledNo" => PhoneProperties::getFkeyLedNo($this->ReadPropertyString("PhoneModel"), $fkeyNo),
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

    public function PingPhone(): string
    {
        $phoneIp = $this->ReadPropertyString("PhoneIP");

        if (Sys_Ping($phoneIp, 4000)) {
            return sprintf("Phone with IP %s is reachable", $phoneIp);
        }

        return sprintf("Phone with IP %s is not reachable", $phoneIp);
    }

    public function setFkeyFunctionality(bool $RecieveOnly): void
    {
        $this->UpdateFormField("ActionVariableId", "visible", $RecieveOnly);
        $this->UpdateFormField("ActionValue", "visible", !$RecieveOnly);
        $this->UpdateFormField("TargetIsStatus", "visible", !$RecieveOnly);
    }

    public function UpdateStatusVariable(bool $TargetIsStatus, int $ActionVariableId): void
    {
        if ($TargetIsStatus)
        {
            $this->UpdateFormField("StatusVariableId", "value", $ActionVariableId);
        }
        
        $this->UpdateFormField("StatusVariableId", "visible", !$TargetIsStatus);
    }

    public function SetVariableId(string $actionValue): void
    {
        $action = json_decode($actionValue, true);
        $this->UpdateFormField("ActionVariableId", "value", $action['parameters']['TARGET']);
        $this->UpdateFormField("StatusVariableId", "value", $action['parameters']['TARGET']);
    }

    public function SetFkeySettings(): void
    {
        $fkeysSettings = json_decode($this->ReadPropertyString("FkeysSettings"), true);

        foreach ($fkeysSettings as $fkeySettings) {
            $fKeyIndex = ((int) $fkeySettings["FkeyNo"]) - 1;

            if ($fkeySettings["TargetIsStatus"]) {
                $this->RegisterMessage($fkeySettings["ActionVariableId"], VM_UPDATE);
            } else {
                $this->RegisterMessage($fkeySettings["StatusVariableId"], VM_UPDATE);
            }

            // Move this if/else to a separated method
            if ($fkeySettings["RecieveOnly"]) {
                $fkeyValue = urlencode("none");
            } else {
                $instanceHook = sprintf("http://%s:3777/hook/snom/%d/", $this->ReadPropertyString("LocalIP"), $this->InstanceID);
                $hookParameters = "?xml=false&variableId=" . $fkeySettings["ActionVariableId"] . "&value=" . $fkeySettings["ActionValue"];
                $fkeyValue = urlencode("url " . $instanceHook . $hookParameters);
            }

            $urlQuery = sprintf("settings=save&fkey%d=%s&fkey_label%d=%s", $fKeyIndex, $fkeyValue, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));
            $phoneModel = $this->ReadPropertyString("PhoneModel");

            if (PhoneProperties::hasSmartLabel($phoneModel)) {
                $urlQuery = sprintf("%s&fkey_short_label%d=%s", $urlQuery, $fKeyIndex, urlencode($fkeySettings["FkeyLabel"]));
            }

            $phoneIp = $this->ReadPropertyString("PhoneIP");
            $baseUrl = sprintf("http://%s/dummy.htm?", $phoneIp);
            $url = sprintf("%s%s", $baseUrl, $urlQuery);

            file_get_contents($url);
        }
    }

    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $phoneModel = $this->ReadPropertyString("PhoneModel");
        $fkeyRange = PhoneProperties::getFkeysRange($phoneModel);

        foreach ($fkeyRange as $fkeyNo) {
            $data["elements"][5]["values"][$fkeyNo - 1] = [
                "FkeyNo" => $fkeyNo,
                "RecieveOnly" => false,
                "ActionVariableId" => 1,
                "ActionValue" => false,
                "TargetIsStatus" => true,
                "StatusVariableId" => 1,
                "FkeyLabel" => "not set", 
                "FkeyColorOn" => "none",
                "FkeyColorOff" => "none",
            ];
        }

        $data["elements"][5]["form"] = "return json_decode(SNMD_UpdateForm(\$id, \$FkeysSettings['RecieveOnly'] ?? false, \$FkeysSettings['TargetIsStatus'] ?? true), true);";

        return json_encode($data);
    }


    public function UpdateForm(bool $recvOnly, bool $targetIsStatusVariable): string
    {
        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $data["elements"][5]["form"][5]["visible"] = $recvOnly;
        $data["elements"][5]["form"][6]["visible"] = !$recvOnly;
        $data["elements"][5]["form"][7]["visible"] = !$recvOnly;
        $data["elements"][5]["form"][8]["visible"] = !$targetIsStatusVariable;

        return json_encode($data["elements"][5]["form"]);
    }

    // has_expanstion_module()
}