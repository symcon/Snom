<?php

class SnomAlert extends IPSModuleStrict {

    public function Create(): void {
        //Never delete this line!
        parent::Create();

        $this->RegisterHook("snom/" . $this->InstanceID);

        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyString('BindIP', Sys_GetNetworkInfo()[0]['IP']);
    }

    public function GetConfigurationForm(): string {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        foreach (Sys_GetNetworkInfo() as $info) {
            $form['elements'][1]['options'][] = [
                "caption" => $info['IP'],
                "value" => $info['IP']
            ];
        }
        return json_encode($form);
    }

    /**
    * This function will be called by the hook control. Visibility should be protected!
    */
    protected function ProcessHookData(): void {

        $this->SendDebug("GET", print_r($_GET, true), 0);
        $this->SendDebug("SERVER", print_r($_SERVER, true), 0);

        header("Content-Type: text/xml");

        $this->SnomIPPhoneText($_GET["text"], 2000);
    }

    private function SnomIPPhoneText(string $text, int $timeout) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xmlRoot = $xml->appendChild($xml->createElement("SnomIPPhoneText"));

        $xmlRoot->appendChild($xml->createElement('Text', $text));
        $fetch = $xml->createElement('fetch','snom://mb_exit');
        $fetchTimeout = $xml->createAttribute('mil');
        $fetchTimeout->value = $timeout;
        $fetch->appendChild($fetchTimeout);
        $xmlRoot->appendChild($fetch);

        echo $xml->saveXML();
    }

    public function SendAlert(string $Text) {
        $url = sprintf("https://%s:3777/hook/snom/%d?text=%s", $this->ReadPropertyString("BindIP"), $this->InstanceID, urlencode($Text));
        file_get_contents(sprintf("http://%s/minibrowser.htm?url=%s", $this->ReadPropertyString("Host"), urlencode($url)));
    }

}

