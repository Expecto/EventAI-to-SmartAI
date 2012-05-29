<?php

class NPC
{
    public function __construct($pdo, $npcId) { $this->pdo = $pdo; $this->npcId = $npcId; }

    private $sai = array();
    private $eai = array();

    public function addSAI($sai) { $this->sai[] = $sai; }
    public function addEAI($eai) { $this->eai[] = new EAI($eai); }
    
    public function convertAllToSAI() {
        $oldDate = microtime(true);
        foreach ($this->eai as $eaiItem)
            $this->addSAI($eaiItem->toSAI($this->pdo));
    }
    
    public function toSQL() {
        $output   = '';
        foreach ($this->sai as $itr => $item)
            $output .= $item->toSQL($itr);
        return substr($output, 0, - strlen(PHP_EOL) - 1) . ';' . PHP_EOL . PHP_EOL;
    }
}

class SAI
{
    public function __construct($array) {
        $this->data = $array;
    }
    
    public function toSQL($index) {
        $outputString = '-- SAI: ' . $this->data['npcName'] . PHP_EOL;
        $outputString .= 'SET @ENTRY := ' . $this->data['entryorguid'] . ';' . PHP_EOL;
        $outputString .= 'UPDATE creature_template SET AIName="SmartAI" WHERE entry = @ENTRY;' . PHP_EOL;
        $outputString .= 'DELETE FROM creature_ai_scripts WHERE creature_id = @ENTRY;' . PHP_EOL;
        if ($index == 0)
            $outputString .= 'INSERT INTO `smart_scripts` (`entryorguid`,`source_type`,`id`,`link`,`event_type`,`event_phase_mask`,`event_chance`,`event_flags`,`event_param1`,`event_param2`,`event_param3`,`event_param4`,`action_type`,`action_param1`,`action_param2`,`action_param3`,`action_param4`,`action_param5`,`action_param6`,`target_type`,`target_param1`,`target_param2`,`target_param3`,`target_x`,`target_y`,`target_z`,`target_o`,`comment`) VALUES' . PHP_EOL;
        
        foreach ($this->data['actions'] as $i => $action) {
            // Found an empty action. Means no action's following.
            if (count($action) == 0)
                break;

            $outputString .= '(@ENTRY, ';
            $outputString .= $this->data['source_type'] . ', ';
            $outputString .= ($index + $i - 1) . ', ';
            
            $link = 0;
            if (isset($this->data['actions'][$i + 1]) && count($this->data['actions'][$i + 1]) != 0)
                $link = ($index + $i);
            
            $outputString .= $link . ', ';

            if ($i == 1) $outputString .= $this->data['event_type'] . ', ';
            else         $outputString .= SMART_EVENT_LINK . ', ';

            $outputString .= $this->data['event_phase'] . ', ';
            $outputString .= $this->data['event_chance'] . ', ';
            $outputString .= $this->data['event_flags'] . ', ';
            
            if ($i == 1) {
                for ($j = 1; $j <= 4; $j++)
                    $outputString .= $this->data['event_params'][$j] . ', ';
            }
            else
                $outputString .= '0, 0, 0, 0, ';
            $outputString .= $this->data['actions'][$i]['SAIAction'] . ', ';
            
            for ($j = 0; $j < 6; $j++)
                $outputString .= $this->data['actions'][$i]['params'][$j] . ', ';
            
            $outputString .= '),' . PHP_EOL;
        }
        
        return $outputString;
    }
}

class EAI
{
    public function __construct($pdoObj) {
        $this->_eaiItem = $pdoObj;
    }
    
    public function toSAI($pdoDriver) {
        $saiData = array();
        $saiData['entryorguid']  = intval($this->_eaiItem->npcId);
        $saiData['npcName']      = $this->_eaiItem->npcName;
        $saiData['source_type']  = 0;
        
        $saiData['event_type']   = Utils::convertEventToSAI($this->_eaiItem->event_type);
        $saiData['event_chance'] = intval($this->_eaiItem->event_chance);
        $saiData['event_flags']  = Utils::SAI2EAIFlag($this->_eaiItem->event_flags);
        
        $saiData['event_params'] = Utils::convertParamsToSAI($this->_eaiItem);
        $saiData['actions']      = array();

        for ($i = 1; $i < 4; $i++)
            $saiData['actions'][$i] = Utils::buildSAIAction($this->_eaiItem->{"action".$i."_type"},
                                        $this->_eaiItem->{"action".$i."_param1"}, $this->_eaiItem->{"action".$i."_param2"}, $this->_eaiItem->{"action".$i."_param3"}, $pdoDriver);

        $saiData['event_phase'] = Utils::generateSAIPhase($this->_eaiItem->event_inverse_phase_mask);
        
        $saiData['saiEntries'] = 0;
        for ($i = 1; $i < 4; $i++)
            if (count($saiData['actions'][$i]) != 0)
                $saiData['saiEntries']++;
        
        return new SAI($saiData);
    }
}

class TextsCollection
{
    private $items = array();
    
    public function getStore() { return $this->items; }

    public function addItem($pdoObj) {
        $this->items[] = new CreatureText($pdoObj);
    }
}

class sLog
{
    private function __construct() { }
    
    static function outString($msg) {
        if ($handle = fopen('dbErrors.log', 'w+')) {
            fwrite($handle, date('d/m/Y H:i:s :: ') . $msg . PHP_EOL);
            fclose($handle);
        }
    }
    
    static function outInfo($msg) {
        if ($handle = fopen('workProgress.log', 'w+')) {
            fwrite($handle, date('d/m/Y H:i:s :: ') . $msg . PHP_EOL);
            fclose($handle);
        }
    }
    
    static function outSpecificFile($file, $msg) {
        if ($handle = fopen($file, 'a')) {
            fwrite($handle, date('d/m/Y H:i:s :: ') . $msg . PHP_EOL);
            fclose($handle);
        }
    }
}