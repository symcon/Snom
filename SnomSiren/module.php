<?php

class SnomSiren extends IPSModuleStrict {

    public function Create(): void {
        //Never delete this line!
        parent::Create();

        $this->RegisterHook("snom/" . $this->InstanceID);
        $this->RegisterHook("snom/media/" . $this->InstanceID);
        $this->RegisterHook("snom/shout/" . $this->InstanceID);

        $this->RegisterPropertyString('Host', '');
        $this->RegisterPropertyString('BindIP', Sys_GetNetworkInfo()[0]['IP']);
        
        $this->RegisterPropertyInteger('PropertyMediaID', 0);

        //IPS_EnableDebugFile($this->InstanceID);

    }

    public function IPS_ApplyChanges() {

        // Don't delete this line
        parent::ApplyChanges();
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

        $instanceID = strval($this->InstanceID);

        // Determine the path to the real file here, depending on the called hook address
        $path = '';
        if ($_SERVER['SCRIPT_NAME'] === '/hook/snom/' . $instanceID ) {
            // return XML
            header("Content-Type: text/xml");
            $this->SnomIPPhonePlay($_GET["file"], 5000);
        }
        else if (($_SERVER['SCRIPT_NAME'] === '/hook/snom/shout/' . $instanceID) && IPS_MediaExists($this->ReadPropertyInteger('PropertyMediaID'))) {
            // return media data
            file_get_contents('http://192.168.30.1/audio.php');
        }
        else if (($_SERVER['SCRIPT_NAME'] === '/hook/snom/shout/' . $instanceID) && IPS_MediaExists($this->ReadPropertyInteger('PropertyMediaID'))) {
            // return media data
            header("Content-Type: audio/wav");
            echo base64_decode(IPS_GetMediaContent($this->ReadPropertyInteger('PropertyMediaID')));
        }
        else {
            // Fail if there is nothing there
            http_response_code(404);
            die("File not found!");
        }
    }

    private function SnomIPPhonePlay(string $wavefile, int $timeout) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xmlRoot = $xml->appendChild($xml->createElement("SnomIPPhoneText"));

        //$xmlRoot->appendChild($xml->createElement('Text', $wavefile));
        $xmlRoot->appendChild($xml->createElement('Text', 'load wav'));
       
        $fetch = $xml->createElement('fetch','phone://mb_nop#action_ifc:pui=play_wav,url='. urlencode($wavefile) .''); 
        $fetchTimeout = $xml->createAttribute('mil');
        $fetchTimeout->value = $timeout;
        $fetch->appendChild($fetchTimeout);
        $xmlRoot->appendChild($fetch);

        echo $xml->saveXML();
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

    public function PlaySiren(string $Text) {
        $MediaID = $this->ReadPropertyInteger('PropertyMediaID');
        $mediaInfo = IPS_GetMedia($MediaID);

        $wavhost = $this->ReadPropertyString('BindIP');

        $mediaFilePath = IPS_GetKernelDir () . $mediaInfo["MediaFile"];
        //still on another webserver!
        //http://192.168.8.83/media/CivilProtectionAirRaidSirensTe-PEHD101201.wav
        $mediaFilePath = sprintf("http://%s:3777/hook/snom/media/%d", $this->ReadPropertyString("BindIP"), $this->InstanceID);
        $this->SendDebug("MEDIA_HOOK", print_r($mediaFilePath, true), 0);

        
        $url = sprintf("http://%s:3777/hook/snom/%d?file=%s", $this->ReadPropertyString("BindIP"), $this->InstanceID, $mediaFilePath);
        $this->SendDebug("XML_HOOK", print_r($url, true), 0);
        // http://admin:sn0m@192.168.30.109/minibrowser.htm?url=
        file_get_contents(sprintf("http://admin:sn0m@%s/minibrowser.htm?url=%s", $this->ReadPropertyString("Host"), urlencode($url)));
    }

}

