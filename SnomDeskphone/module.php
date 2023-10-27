<?php

require_once("phoneProperties.php");

class SnomDeskphone extends IPSModuleStrict {
    public function Create(): void {
        parent::Create();
        $this->RegisterPropertyString("PhoneIP", "");
        $this->RegisterPropertyString("PhoneMac", "000413");
        $this->RegisterPropertyString("PhoneModel", "");
        $this->RegisterPropertyString("LocalIP", "127.0.0.1");
        $this->RegisterFkeysProperties();
    }

    private function RegisterFkeysProperties(): void {
        $this->RegisterPropertyInteger("FkeyNo", -1);
        $this->RegisterPropertyInteger("ActionVariableId", -1);
        $this->RegisterPropertyBoolean("RecieveOnly", false);
        $this->RegisterPropertyInteger("ActionValue", -1);
        $this->RegisterPropertyString("ActionHook", "/snom/myVarID");
        $this->RegisterPropertyString("FkeyLabel", "my label");
        $this->RegisterPropertyString("FkeyColorOn", "red");
        $this->RegisterPropertyString("FkeyColorOff", "green");
        $this->SendDebug('INFO', print_r('Fkey properties registered', true), 0);
    }

    public function ApplyChanges(): void {
        parent::ApplyChanges();
        $this->SetSummary($this->ReadPropertyString("PhoneModel") . "/" . $this->ReadPropertyString("PhoneMac"));
        // Transfer to Phone, better: list to actions in form.json

    }

    /**
    * This function will be called by the hook control. Visibility should be protected!
    */
    protected function ProcessHookData(): void {
        $value = $_GET["value"];
        $this->SendDebug("GET", print_r($value, true), 0);
        $urlPathItems = explode("/", $_SERVER["PHP_SELF"]);
        $variableId = (int)$urlPathItems["3"];
        $this->SendDebug("HOOK", print_r($variableId, true), 0);
        RequestAction($variableId, $value);
    }

    // Usage of public functions (prefix defined in module.json):
    // SNMD_PingPhone();

    public function PingPhone(): string {
        $phoneIp = $this->ReadPropertyString("PhoneIP");

        if (Sys_Ping($phoneIp, 4000)) {
            return sprintf("Phone with IP %s is reachable", $phoneIp);
        }
        return sprintf("Phone with IP %s is not reachable", $phoneIp);
    }

    public function SetValueFieldVisibility(bool $RecieveOnly): void {
        $this->UpdateFormField("ActionValue", "visible", !$RecieveOnly);
    }

    public function SetVariableId(int $variableId): void {
        if ($this->ReadPropertyBoolean("RecieveOnly")) {
            $this->SendDebug('SET', print_r('Recieve only fkey', true), 0);
        }
        else {
            $this->UpdateFormField("ActionValue", "variableID", $variableId);
            $this->SendDebug('SET', print_r('set variable id', true), 0);     
        }
    }

    public function CreateHook(int $variableId): void {
        $this->RegisterHook("snom/" . $variableId);
        $this->UpdateFormField("ActionHook", "value", sprintf("/snom/%d/", $variableId));
        $this->SendDebug('create', print_r(sprintf("hook created /snom/%d", $variableId), true), 0);
    }

    public function SetFkeySettings(int $fKey, bool $isRecieveOnly, string $variableHook, string $variableValue, string $labelValue): void {
        $fKeyIndex = $fKey-1;
        $this->SendDebug("INFO", print_r("Configuring fkey " . $fKeyIndex, true), 0);

        if ($isRecieveOnly) {
            $fkeyType="none";
            $fkeyValue = urlencode($fkeyType);
        }
        else {
            $fkeyType = "url";
            $localIp = $this->ReadPropertyString("LocalIP");
            $fkeyValue = urlencode($fkeyType ." " . $localIp . ":3777/hook" . $variableHook . "?value=" . $variableValue);
        }

        $urlQuery = sprintf("settings=save&fkey%d=%s&fkey_label%d=%s", $fKeyIndex, $fkeyValue, $fKeyIndex, urlencode($labelValue));
        $phoneModel =$this->ReadPropertyString("PhoneModel");
    
        if (PhoneProperties::hasSmartLabel($phoneModel)) {
            $urlQuery = sprintf("%s&fkey_short_label%d=%s", $urlQuery, $fKeyIndex, urlencode($labelValue));
        }

        $phoneIp = $this->ReadPropertyString("PhoneIP");
        $baseUrl = sprintf("http://%s/dummy.htm?", $phoneIp);
        $url = sprintf("%s%s", $baseUrl, $urlQuery);
        $this->SendDebug("URL", print_r($url, true), 0);

        file_get_contents($url);
    }

    public function SetLedAction(int $triggerVarId): void {
        $this->SendDebug("LED[TRIGGER ID]", print_r($triggerVarId, true), 0);

        $eventId = IPS_CreateEvent(0);                  //Ausgelöstes Ereignis
        IPS_SetEventTrigger($eventId, 1,  $triggerVarId);       //Bei Änderung von Variable mit ID 
        $target = 23983;
        IPS_SetParent($eventId, $target);         //Ereignis zuordnen
        IPS_SetEventActive($eventId, true);             //Ereignis aktivieren

        // Seit IP-Symcon 6.0 erforderlich, sofern das Ereignis eine Automation ausführen soll (z.B. ein PHP-Skript)
        IPS_SetEventAction($eventId, '{D2CB15CB-958A-AA5C-89AB-49F55CDC1DEA}', ["TARGET" => $target, "IP" => $this->ReadPropertyString("PhoneIP")]);
    }

    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $phoneModel =$this->ReadPropertyString("PhoneModel");
        $fkeyRange = PhoneProperties::getFkeysRange($phoneModel);

        foreach ($fkeyRange as $fkeyNo) {
            $data["actions"][0]["values"][$fkeyNo-1] = [
                "FkeyNo" => $fkeyNo,
                "CheckBox" => false,
                "ActionVariableId" => -1,
                "ActionValue" => false,
                "ActionHook" => "not set",
                "FkeyLabel" => "not set",
                "FkeyColorOn" => "none",
                "FkeyColorOff" => "none",
            ];
        }

        $data["actions"][0]["form"] = "return json_decode(SNMD_UIGetForm(\$id, \$FkeysSettings['ActionVariableId'] ?? 0, \$FkeysSettings['RecieveOnly'] ?? false, \$FkeysSettings['ActionValue']), true);";
        
        return json_encode($data);
    }

    public function UIGetForm(int $ActionVariableId, bool $recvOnly, bool $value): string {
        $this->SendDebug("SETTING", print_r((int)$value, true), 0);
        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $data["actions"][0]["form"][3]["variableID"] = $ActionVariableId;
        $data["actions"][0]["form"][3]["visible"] = !$recvOnly;

        return json_encode($data["actions"][0]["form"]);
    }

    // has_expanstion_module()
}