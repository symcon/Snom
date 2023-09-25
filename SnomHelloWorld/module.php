<?php

class SnomHelloWorld extends IPSModuleStrict {
    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create(): void {
        // Diese Zeile nicht löschen.
        parent::Create();
        $this->RegisterPropertyString("PhoneIP", "");
        $this->RegisterPropertyString("LocalIP", "");
        $this->RegisterVariableString("MbRequestUrl", "Minibrowser request URL");
        $this->RegisterVariableString("MbPage", "Minibrowser page");
        $this->RegisterHook("snom/" . $this->InstanceID);
        $this->RegisterVariableString("SymconCurrentVersion", "Symcon Current version");
    }
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges(): void {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
        $this->SetValue("SymconCurrentVersion", IPS_GetKernelVersion());
        $this->SetValue("MbPage", $this->GetIPPhoneTextItem("Hello you", 10000));
        $this->SetValue("MbRequestUrl", $this->GetMbRequestUrl());
    }

    /**
    * This function will be called by the hook control. Visibility should be protected!
    */
    protected function ProcessHookData(): void {
        header("Content-Type: text/xml");
        echo $this->GetValue("MbPage");
    }

    private function GetIPPhoneTextItem(string $text, int $timeout): string {
        header("Content-Type: text/xml");
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xmlRoot = $xml->appendChild($xml->createElement("SnomIPPhoneText"));

        $xmlRoot->appendChild($xml->createElement('Text', $text));
        $fetch = $xml->createElement('fetch','snom://mb_exit');
        $fetchTimeout = $xml->createAttribute('mil');
        $fetchTimeout->value = $timeout;
        $fetch->appendChild($fetchTimeout);
        $xmlRoot->appendChild($fetch);

        return $xml->saveXML();
    }

    private function GetMbRequestUrl(): string {
        $HookURL = sprintf("http://%s:3777/hook/snom/%d", $this->ReadPropertyString("LocalIP"), $this->InstanceID);
        $URL = sprintf("http://%s/minibrowser.htm?url=%s", $this->ReadPropertyString("PhoneIP"), $HookURL);

        return $URL;
    }

       /**
    * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
    * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
    *
    * SNM_GetMbPage();
    *
    */

    public function GetMbPage(): void {
        file_get_contents($this->GetValue("MbRequestUrl"));
    }
}