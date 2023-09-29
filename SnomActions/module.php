<?php

class SnomActions extends IPSModuleStrict {
    public function Create(): void {
        parent::Create();
        $this->RegisterPropertyString("ActionName", "");
        $this->RegisterPropertyInteger("ActionVariableId", 0);
        $this->RegisterPropertyString("ActionHook", "/snom");
        // $this->RegisterPropertyBoolean("ActionValue", true);
        $this->RegisterHook("snom/" . $this->InstanceID);
    }

    public function ApplyChanges(): void {
        parent::ApplyChanges();
    }

    // Usage of public functions (prefix defined in module.json):
    // SNMD_SetVariableId();

    public function SetVariableId(int $variableId): void {
        $this->UpdateFormField("ActionValue", "variableID", $variableId);
        $this->SendDebug('set', print_r('set variable id', true), 0);
    }

    public function CreateHook(int $variableId): void {
        // create hook
        $this->UpdateFormField("ActionHook", "value", sprintf("/snom/%d", $variableId));
        $this->SendDebug('create', print_r('hook created', true), 0);
    }
}