<?php

class SnomDeskphone extends IPSModuleStrict {
    public function Create(): void {
        parent::Create();
        $this->RegisterPropertyString("PhoneIP", "");
        $this->RegisterPropertyString("PhoneMac", "000413");
        $this->RegisterPropertyString("PhoneModel", "snomD785");
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

        // Transfer to Phone, better: list to actions in form.json

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
        $this->UpdateFormField("ActionHook", "value", sprintf("/snom/%d", $variableId));
        $this->SendDebug('create', print_r('hook created' . $this->ReadPropertyString("ActionHook"), true), 0);
    }

    public function SetFkeySettings(int $fKey, bool $isRecieveOnly, string $variableHook, string $labelValue): void {
        $fKeyIndex = $fKey-1;
        $this->SendDebug("INFO", print_r("Configuring fkey " . $fKeyIndex, true), 0);
        // $actionUrl = sprintf("ActionURLfKey%d", $fKeyIndex);
        // $actionUrlValue = $this->ReadPropertyString($actionUrl);
        // $isRecieveOnly = $this->ReadPropertyBoolean("RecieveOnly");
        // $isRecieveOnly ? $fkeyType="none" : $fkeyType = "url";
        // $actionUrlValue ? $fkeyType="url" : $fkeyType = "none";
        // $variableHook = $this->ReadPropertyString("ActionHook");

        // $labelValue = urlencode($this->ReadPropertyString("FkeyLabel"));

        if ($isRecieveOnly) {
            $fkeyType="none";
            $fkeyValue = urlencode($fkeyType);
        }
        else {
            $fkeyType = "url";
            $localIp = $this->ReadPropertyString("LocalIP");
            $fkeyValue = urlencode($fkeyType ." " . $localIp . ":3777/hook" . $variableHook);
        }

        $urlQuery = sprintf("settings=save&fkey%d=%s&fkey_label%d=%s", $fKeyIndex, $fkeyValue, $fKeyIndex, $labelValue);

        if ($this->ReadPropertyString("PhoneModel")=="snomD735") {
            $urlQuery = sprintf("%s&fkey_short_label%d=%s", $urlQuery, $fKeyIndex, $labelValue);
        }

        $phoneIp = $this->ReadPropertyString("PhoneIP");
        $baseUrl = sprintf("http://%s/dummy.htm?", $phoneIp);
        $url = sprintf("%s%s", $baseUrl, $urlQuery);
        $this->SendDebug("URL", print_r($url, true), 0);

        file_get_contents($url);
    }


    public function GetConfigurationForm(): string
    {
        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $d735fkeyRange = range(1, 8);

        foreach ($d735fkeyRange as $fkeyNo) {
            $data["actions"][0]["values"][$fkeyNo-1]["FkeyNo"] = $fkeyNo;
            $data["actions"][0]["values"][$fkeyNo-1]["CheckBox"] = false;
            $data["actions"][0]["values"][$fkeyNo-1]["ActionVariableId"] = "";
            $data["actions"][0]["values"][$fkeyNo-1]["ActionValue"] = NULL;
            $data["actions"][0]["values"][$fkeyNo-1]["ActionHook"] = "not set";
            $data["actions"][0]["values"][$fkeyNo-1]["FkeyLabel"] = "not set";
            $data["actions"][0]["values"][$fkeyNo-1]["FkeyColorOn"] = "none";
            $data["actions"][0]["values"][$fkeyNo-1]["FkeyColorOff"] = "none";
        }
        $fkeysFormAction["form"] = "return json_decode(SNMD_UIGetForm(\$id, \$FkeysSettings['ActionVariableId'] ?? 0, \$FkeysSettings['RecieveOnly'] ?? false), true);";
        
        return json_encode($data);
    }

    public function UIGetForm(int $ActionVariableId, bool $recvOnly): string {
        $this->SendDebug("asd", (int)$recvOnly, 0);

        $data = json_decode(file_get_contents(__DIR__ . "/form.json"), true);
        $data["actions"][0]["form"][3]["variableID"] = $ActionVariableId;
        $data["actions"][0]["form"][3]["visible"] = !$recvOnly;

        return json_encode($data["actions"][0]["form"]);
    }

    // has_expanstion_module()
}