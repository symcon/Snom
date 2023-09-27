<?php

class SnomDeskphone extends IPSModuleStrict {
    public function Create(): void {
        parent::Create();
        $this->RegisterPropertyString("PhoneIP", "");
        $this->RegisterPropertyString("PhoneMac", "000413");
        $this->RegisterPropertyString("PhoneModel", "snomD785");
        $this->RegisterPropertyString("LocalIP", "127.0.0.1");
        $this->RegisterPropertyBoolean("FunctionKeys", false);
    }

    public function ApplyChanges(): void {
        parent::ApplyChanges();
    }
    
    /**
    * This function will be called by the hook control. Visibility should be protected!
    */
    // protected function ProcessHookData(): void {
    //     header("Content-Type: text/xml");
    // }


    // Usage of public functions (prefix defined in module.json):
    // SNMD_PingPhone();

    public function PingPhone(): string {
        $phoneIp = $this->ReadPropertyString("PhoneIP");

        if (Sys_Ping($phoneIp, 10000)) {
            return sprintf("Phone with IP %s is reachable", $phoneIp);
        }
        return sprintf("Phone with IP %s is not reachable", $phoneIp);
    }
}