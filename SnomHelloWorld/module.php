<?php

class SnomHelloWorld extends IPSModuleStrict {
    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create(): void {
        // Diese Zeile nicht löschen.
        parent::Create();
        $this->RegisterVariableString("LocalIp", "Local IP address");
        $this->RegisterVariableString("SymconCurrentVersion", "Symcon Current version");
        $this->RegisterVariableString("SnomXml", "Snom XML");
        $this->RegisterHook("snom/" . $this->InstanceID);
    }
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges(): void {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
        $this->SetValue("LocalIp", "127.0.0.1");
        $this->SetValue("SymconCurrentVersion", IPS_GetKernelVersion());
        $this->SetValue("SnomXml", $this->SnomIPPhoneText("Hello you", 10000));
    }
    /**
    * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
    * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
    *
    * SNM_SendText();
    *
    */

    /**
    * This function will be called by the hook control. Visibility should be protected!
    */
    protected function ProcessHookData(): void {
        header("Content-Type: text/xml");
        echo $this->GetValue("SnomXml");
    }

    private function SnomIPPhoneText(string $text, int $timeout): string {
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
    public function SendXml(): void {
        $PhoneIPAddress = "192.168.8.75";
        $HookURL = sprintf("http://%s:3777/hook/snom/%d", $this->GetValue("LocalIp"), $this->InstanceID);
        $URL = sprintf("http://%s/minibrowser.htm?url=%s", $PhoneIPAddress, $HookURL);

        // $this->SnomIPPhoneText("Hello world", 10000);
        file_get_contents($URL);
    }
}