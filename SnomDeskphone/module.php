<?php

class SnomDeskphone extends IPSModuleStrict {
    public function Create(): void {
        parent::Create();
        $this->RegisterPropertyString("PhoneIP", "");
        $this->RegisterPropertyString("PhoneMac", "000413");
        $this->RegisterPropertyString("PhoneModel", "snomD785");
        $this->RegisterPropertyString("LocalIP", "127.0.0.1");
        $this->RegisterPropertyBoolean("FunctionKeys", false);
        $this->RegisterFkeysProperties();
    }

    private function RegisterFkeysProperties(): void {
        $fKeyIndexes = range(0, 1);
        foreach ($fKeyIndexes as $fkeyIndex) {
            $this->RegisterFkeyProperties($fkeyIndex);
        }
    }
    private function RegisterFkeyProperties(int $fkeyIndex): void {
        $this->RegisterPropertyString(sprintf("ActionURLfKey%d", $fkeyIndex), "");
        $this->RegisterPropertyString(sprintf("LabelFkey%d", $fkeyIndex), "");
        $this->RegisterPropertyString(sprintf("LedfKey%d", $fkeyIndex), "");
        $this->RegisterPropertyString(sprintf("ColorFkey%dOn", $fkeyIndex), "green");
        $this->RegisterPropertyString(sprintf("ColorFkey%dOff", $fkeyIndex), "red");
    }

    public function ApplyChanges(): void {
        parent::ApplyChanges();
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

    public function SetFkeySettings(int $fKeyIndex): void {
        $actionUrl = sprintf("ActionURLfKey%d", $fKeyIndex);
        $actionUrlValue = $this->ReadPropertyString($actionUrl);
        $actionUrlValue ? $fkeyType="url" : $fkeyType = "none";

        $label = sprintf("LabelFkey%d", $fKeyIndex);
        $labelValue = urlencode($this->ReadPropertyString($label));
        $fkeyValue = urlencode($fkeyType ." " . $actionUrlValue);
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

    // has_expanstion_module()
}