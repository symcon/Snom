<?php

class SnomMenu extends IPSModuleStrict {

    public function Create(): void {
        //Never delete this line!
        parent::Create();

        $this->RegisterHook("snom/" . $this->InstanceID);

        $this->RegisterPropertyInteger('BaseID', 0);
    }

    public function GetConfigurationForm(): string {
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        foreach (Sys_GetNetworkInfo() as $info) {
            $form['actions'][1]['options'][] = [
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

        if (isset($_GET['pid']))
            $id = $_GET['pid'];
        else
            $id = $this->ReadPropertyInteger("BaseID");

        header("Content-Type: text/xml");

        // Execute Action
        if (isset($_GET['aid'])) {
            if(@RequestAction($_GET['aid'], $_GET['value'])) {
                $this->SnomIPPhoneText(IPS_GetName($_GET['aid']) . " auf " . GetValueFormattedEx($_GET['aid'], $_GET['value']) . " geschaltet", 2000);
            }
            else {
                $this->SnomIPPhoneText("Fehler beim Schalten!", 2000);
            }
            return;
        }

        $items = [];

        // Schalten
        if (IPS_VariableExists($id)) {
            $v = IPS_GetVariable($id);
            $profile = $v["VariableProfile"];
            if ($v["VariableCustomProfile"]) {
                $profile = $v["VariableCustomProfile"];
            }

            if (!IPS_VariableProfileExists($profile)) {
                $this->SnomIPPhoneText("Variable hat kein Profil!", 2000);
                return;
            }

            $profile = IPS_GetVariableProfile($profile);
            if (trim($profile["Suffix"]) == "%") {
                for($i = 0; $i < 10; $i++) {
                    $items[] = [
                        "Name" => ($i*10) ." %",
                        "URL" => sprintf("%s/?aid=%d&value=%d", $this->GetBaseURL(), $id , $i*10),
                    ];
                }
            }
            else {
                foreach($profile["Associations"] as $asc) {
                    $items[] = [
                        "Name" => $asc["Name"],
                        "URL" => sprintf("%s?aid=%d&value=%d", $this->GetBaseURL(), $id , $asc["Value"]),
                    ];
                }
            }
        }
        // Navigieren
        else {
            foreach(IPS_GetChildrenIDs($id) as $cid) {
                if (IPS_GetObject($cid)["ObjectIsHidden"]) {
                    continue;
                }

                if (IPS_LinkExists($cid)) {
                    $cid = IPS_GetLink($cid)["TargetID"];
                }

                if (IPS_InstanceExists($cid)) {
                    if (IPS_GetInstance($cid)["ModuleInfo"]["ModuleType"] != MODULETYPE_DEVICE) {
                        continue;
                    }
                }

                $item = [
                    "Name" => IPS_GetName($cid),
                ];
                if (IPS_VariableExists($cid)) {
                    if (HasAction($cid)) {
                        $item["URL"] = sprintf("%s?pid=%d", $this->GetBaseURL(), $cid);
                    }
                    $item["extratext"] = GetValueFormatted($cid);
                } else {
                    $item["URL"] = sprintf("%s?pid=%d", $this->GetBaseURL(), $cid);
                }
                $items[] = $item;
            }
        }

        $this->SnomIPPhoneMenu("Symcon Demo", $items);

    }

    private function SnomIPPhoneMenu(string $title, array $items) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xmlRoot = $xml->appendChild($xml->createElement("SnomIPPhoneMenu"));

        $xmlRoot->appendChild($xml->createElement('Title',$title));
        foreach ($items as $item) {
            $xmlItem = $xml->createElement('MenuItem');
            foreach ($item as $key => $value) {
                $xmlItem->appendChild($xml->createElement($key, htmlspecialchars($value)));
            }
            $xmlRoot->appendChild($xmlItem);
        }

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

    private function GetBaseURL() {
        return sprintf("http://%s/hook/snom/%d", $_SERVER['HTTP_HOST'], $this->InstanceID);
    }

    public function ShowActionURL(string $BindIP) {
        return sprintf("http://%s:3777/hook/snom/%d", $BindIP, $this->InstanceID);
    }

}

