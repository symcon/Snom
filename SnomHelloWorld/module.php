<?php

class SnomHelloWorld extends IPSModule {
    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create() {
        // Diese Zeile nicht löschen.
        parent::Create();

        $this->RegisterVariableString("SymconCurrentVersion", "Symcon Current version");
        $this->RegisterVariableString("SnomXml", "Snom XML");
    }
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges() {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
        $this->SetValue("SymconCurrentVersion", IPS_GetKernelVersion());
        $this->SetValue("SnomXml", $this->SnomIPPhoneText("Hello World", 10000));
    }
    /**
    * Die folgenden Funktionen stehen automatisch zur Verfügung, wenn das Modul über die "Module Control" eingefügt wurden.
    * Die Funktionen werden, mit dem selbst eingerichteten Prefix, in PHP und JSON-RPC wiefolgt zur Verfügung gestellt:
    *
    * SNM_SendText();
    *
    */
    // public function SendXml() {
    //     $PhoneIPAddress = "192.168.178.69";
    //     $LocalIPAddress = Sys_GetNetworkInfo()[0]['IP'];
    //     $HookURL = sprintf("http://%s/snom/%d", $LocalIPAddress, $this->InstanceID);
    //     $URL = sprintf("http://%s/minibrowser?url=%s", $PhoneIPAddress, $HookURL);

    //     $this->SnomIPPhoneText("Hello world", 10000);
    //     file_get_contents($URL);
    // }

    private function SnomIPPhoneText(string $text, int $timeout) {
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
}