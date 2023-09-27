<?php

class SnomDeskphone extends IPSModuleStrict {
    public function Create(): void {
        parent::Create();
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
    // SNM_D_GetPageContent();
}