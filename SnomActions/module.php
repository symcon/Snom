<?php

class SnomActions extends IPSModuleStrict {
    public function Create(): void {
        parent::Create();
        $this->RegisterPropertyString("ActionName", "");
        $this->RegisterPropertyInteger("ActionVariableId", 47549);
        $this->RegisterPropertyBoolean("ActionValue", true);
    }

    public function ApplyChanges(): void {
        parent::ApplyChanges();
    }

    // Usage of public functions (prefix defined in module.json):
    // SNMD_SetVariableId();

    public function SetVariableId(int $variableId): void {
        $this->UpdateFormField("ActionValue", "VariableID", $variableId);
        $this->SendDebug('change', print_r('changed variable', true), 0);
    }
}