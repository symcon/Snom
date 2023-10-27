<?php

class SnomPage extends IPSModuleStrict {
    // Überschreibt die interne IPS_Create($id) Funktion
    public function Create(): void {
        // Diese Zeile nicht löschen.
        parent::Create();

        $this->RegisterPropertyString("PhoneIP", "");
        $this->RegisterPropertyString("LocalIP", "");
        $this->RegisterPropertyString("Message", "");
        $this->RegisterPropertyInteger("Timeout", 1);
        $this->RegisterAttributeString("RenderRemoteUrl", "");
        $this->RegisterAttributeString("PageContent", "");
        $this->RegisterHook("snom/" . $this->InstanceID);
    }
    // Überschreibt die intere IPS_ApplyChanges($id) Funktion
    public function ApplyChanges(): void {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();
        $this->WriteAttributeString("RenderRemoteUrl", $this->GetRenderRemoteUrl());
        $PageContent = $this->GetIPPhoneTextItem($this->ReadPropertyString("Message"), $this->ReadPropertyInteger("Timeout"));
        $this->WriteAttributeString("PageContent", $PageContent);
    }
    
    /**
    * This function will be called by the hook control. Visibility should be protected!
    */
    protected function ProcessHookData(): void {
        header("Content-Type: text/xml");
        echo $this->ReadAttributeString("PageContent");
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
        $xml->format_output = TRUE;

        return $xml->saveXML();
    }

    // Usage of public functions (prefix defined in module.json):
    // SNM_GetPageContent();

    public function GetPageContent(): string {
        $page_content = $this->ReadAttributeString("PageContent");
        $this->SendDebug("MB_CONTENT", print_r($page_content, true), 0);
        $this->UpdateFormField("PageContent", "caption", $page_content);

        return $page_content;
    }

    public function GetRenderRemoteUrl(): string {
        $RenderRemoteUrl = sprintf("http://%s/minibrowser.htm?url=%s", $this->ReadPropertyString("PhoneIP"), $this->GetRenderLocalUrl());
        $this->SendDebug("RENDER_REMOTE_URL", print_r($RenderRemoteUrl, true), 0);
        $this->UpdateFormField("RenderRemoteUrl", "caption", $RenderRemoteUrl);

        return $RenderRemoteUrl;
    }

    public function GetRenderLocalUrl(): string {
        $RenderLocalUrl = sprintf("http://%s:3777/hook/snom/%d", $this->ReadPropertyString("LocalIP"), $this->InstanceID);
        $this->SendDebug("RENDER_LOCAL_URL", print_r($RenderLocalUrl, true), 0);
        $this->UpdateFormField("RenderLocalUrl", "caption", $RenderLocalUrl);

        return $RenderLocalUrl;
    }

    public function SendRenderRemote(string $properties): void {
        $this->SendDebug("PROPERTIES", print_r($this . $properties, true), 0);
        $this->UpdateFormField("PhoneIP", "value", $properties);
        ApplyChanges($this);
        $RenderRemoteUrl = $this->ReadAttributeString("RenderRemoteUrl");
        file_get_contents($RenderRemoteUrl);
        $this->SendDebug("RENDER_REMOTE_REQUEST", print_r($RenderRemoteUrl, true), 0);
    }
}