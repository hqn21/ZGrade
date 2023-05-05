<?php

namespace ZeroK;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerChatEvent;

use onebone\economyapi\EconomyAPI;

use pocketmine\scheduler\Task;

class ZGrade extends PluginBase implements Listener{
    
    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info(TextFormat::LIGHT_PURPLE . "等級插件 [ZeroK 製作]");
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() ."Players/");
        if (!file_exists($this->getDataFolder() . "/config.yml")) {
            $settings = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
            $defaultsettings = array(
            "# 升等公告" => "設置升級時公告在聊天室的內容",
            "# 可用函數(公告)" => "{player}, {level}",
            "升等公告" => "§8[§e!§8]§e {player} §f很智障的升到了§a {level}等",
            "# 經驗設置" => "設置挖取指定方塊所得到的經驗",
            "鐵礦經驗" => 87,
            "黃金礦經驗" => 87,
            "鑽石礦經驗" => 87,
            "紅石礦經驗" => 87,
            "青金石經驗" => 87,
            "煤礦經驗" => 87,
            "翡翠礦經驗" => 87,
            "地獄石英礦經驗" => 87,
            "石頭經驗" => 78,
            "花崗岩經驗" => 78,
            "閃長岩經驗" => 78,
            "安山岩經驗" => 78,
            "# 升級獎金" => "設置升級所獲得的獎金",
            "升級獎金" => 87,
            "# 聊天格式" => "設置玩家聊天的格式",
            "# 可用函數(聊天)" => "{player}, {level}, {prefix}, {message}",
            "聊天格式" => "[等級{level}] {prefix} {player} 說 {message}",
            "# 底部顯示" => "Tip底部顯示(on/off)",
            "底部顯示" => "on"
            );
            $settings->setAll($defaultsettings);
            $settings->save();
        }
        if (!file_exists($this->getDataFolder() . "/prefixes.yml")) {
            $prefixes_config = new Config($this->getDataFolder() . "/prefixes.yml", Config::YAML);
            $defaultprefixes = array(
            "# 等級稱號" => "為不同等級設置不同的稱號(下方數字代表等級區間)",
            "1~20" => "小小小智障",
            "21~40" => "小小智障",
            "41~60" => "小智障",
            "61~80" => "中智障",
            "81~100" => "中中智障",
            "101~120" => "中中中智障",
            "121~140" => "智障",
            "141~160" => "大智障",
            "161~180" => "大大智障",
            "181~200" => "大大大智障"
            );
            $prefixes_config->setAll($defaultprefixes);
            $prefixes_config->save();
        }
        $this->getScheduler()->scheduleRepeatingTask(new sendingTip($this), 10);
    }
    
    public function onPlayerJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $playername = $player->getName();
        if (!file_exists($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml")) {
            $config = new Config($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml", Config::YAML);
            $defaultdata = array(
            "level" => 1,
            "exp" => 0
            );
            $config->setAll($defaultdata);
            $config->save();
        }
    }
    
    public function onBlockBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $id = $block->getID();
        $damage = $block->getDamage();
        $playername = $player->getName();
        if ($event->isCancelled()) {
			return;
		}
        if ($player->getGamemode() !== 0) {
            return;
        }
        if ($id !== 0) {
            $this->addExp($playername, $this->getExpByID($id, $damage));
        }
    }
    
    public function getLevel($playername){
        if (file_exists($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml")) {
            $config = new Config($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml", Config::YAML);
            $level = $config->get("level");
            if ($config->get("level") == null) {
                $level = 1;
            }else{
                $level = $config->get("level");
            }
            return $level;
        }
    }
     
    public function getExp($playername){
        if (file_exists($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml")) {
            $config = new Config($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml", Config::YAML);
            if ($config->get("exp") == null) {
                $exp = 0;
            }else{
                $exp = $config->get("exp");
            }
            return $exp;
        }
    }
    
    public function getMaxExp($playername) {
        if (file_exists($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml")) {
            $level = $this->getLevel($playername);
            $maxexp = 4 * ($level - 1) + 10;
            return (int)$maxexp;
        }
    }
    
    public function setLevel($playername, $amount) {
        if(file_exists($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml")) {
            if((int)$amount > 0) {
                $config = new Config($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml", Config::YAML);
                $config->set("level", $amount);
                $config->save();
            }
            while ($this->getExp($playername) >= $this->getMaxExp($playername)) {
                $this->upgrade($playername);
            }
        }
    }
    
    public function setExp($playername, $amount) {
        if (file_exists($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml")) {
            if ((int)$amount > 0) {
                $config = new Config($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml", Config::YAML);
                $config->set("exp", $amount);
                $config->save();
            }
            while ($this->getExp($playername) >= $this->getMaxExp($playername)) {
                $this->upgrade($playername);
            }
        }
    }
    
    public function addLevel($playername, $amount) {
        if (file_exists($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml")) {
            if ((int)$amount >= 0) {
                $level_old = $this->getLevel($playername);
                $this->setLevel($playername, (int)$level_old + (int)$amount);
            }
        }
    }
    
    public function addExp($playername, $amount) {
        if (file_exists($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml")) {
            if ((int)$amount >= 0) {
                $exp_old = $this->getExp($playername);
                $this->setExp($playername, (int)$exp_old + (int)$amount);
            }
            while ($this->getExp($playername) >= $this->getMaxExp($playername)) {
                $this->upgrade($playername);
            }
        }
    }
    
    public function upgrade($playername) {
        $settings = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
        if (file_exists($this->getDataFolder() . "/Players/" . strtolower($playername) . ".yml")) {
            $exp_old = $this->getExp($playername);
            $exp = (int)$exp_old - (int)$this->getMaxExp($playername);
            $this->setExp($playername, $exp);
            $level_old = $this->getLevel($playername);
            $level = (int)$level_old + 1;
            if($level > 200) {
                $this->setLevel($playername, 200);
            }else{
                $this->setLevel($playername, $level);
            }
            $msg = $settings->get("升等公告");
            $bcm = str_replace("{level}", $level, str_replace("{player}", $playername, $msg));
            EconomyAPI::getInstance()->addMoney($playername, $settings->get("升級獎金"));
            $this->getServer()->broadcastMessage($bcm);
        }
    }
    
    public function getExpByID ($id, $damage) {
        $settings = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
        if($id !== 1) {
            switch($id){
                //礦物
                case 15:
                return $settings->get("鐵礦經驗");
                
                case 14:
                return $settings->get("黃金礦經驗");
                
                case 56:
                return $settings->get("鑽石礦經驗");
                
                case 73:
                return $settings->get("紅石礦經驗");
                
                case 21:
                return $settings->get("青金石經驗");
                
                case 16:
                return $settings->get("煤礦經驗");
                
                case 129:
                return $settings->get("翡翠礦經驗");
                
                case 153:
                return $settings->get("地獄石英礦經驗");
                
                default:
			    return 0;
            }
       }else{
            switch($damage){
                //石頭
                case 0:
                return $settings->get("石頭經驗");
                    
                case 1:
                return $settings->get("花崗岩經驗");
                    
                case 3:
                return $settings->get("閃長岩經驗");
                    
                case 5:
                return $settings->get("安山岩經驗");

			    default:
			    return 0;
            }
        }
		
    }

    public function getPrefixByLevel ($level) {
        if($level > 0 and $level < 21) {
            $data = 1;
        }
        else if($level > 20 and $level < 41) {
            $data = 2;
        }
        else if($level > 40 and $level < 61) {
            $data = 3;
        }
        else if($level > 60 and $level < 81) {
            $data = 4;
        }
        else if($level > 80 and $level < 101) {
            $data = 5;
        }
        else if($level > 100 and $level < 121) {
            $data = 6;
        }
        else if($level > 120 and $level < 141) {
            $data = 7;
        }
        else if($level > 140 and $level < 161) {
            $data = 8;
        }
        else if($level > 160 and $level < 181) {
            $data = 9;
        }
        else if($level > 180 and $level < 201) {
            $data = 10;
        }
        else{
            $data = 404;
        }
        $prefixes_config = new Config($this->getDataFolder() . "/prefixes.yml", Config::YAML);
        $prefixes = [
                        $prefixes_config->get("1~20") => 1,
                        $prefixes_config->get("21~40") => 2,
                        $prefixes_config->get("41~60") => 3,
                        $prefixes_config->get("61~80") => 4,
                        $prefixes_config->get("81~100") => 5,
                        $prefixes_config->get("101~120") => 6,
                        $prefixes_config->get("121~140") => 7,
                        $prefixes_config->get("141~160") => 8,
                        $prefixes_config->get("161~180") => 9,
                        $prefixes_config->get("181~200") => 10
                    ];
        if(array_search($data, $prefixes) !== false) {
            return array_search($data, $prefixes);
        }else{
            return "未知";
        }
    }
            
    public function onCommand(CommandSender $sender, Command $command, $label, array $args) : bool{
        if($command->getName() == "zg") {
            if($sender->isOp() == false) {
                return false;
            }
            if(!isset($args[0])) {
                $sender->sendMessage("§8[§cX§8]§f 用法錯誤, 請使用§e /zg help §f查詢正確用法");
                return true;
            }
            switch($args[0]) {
                case "help":
                $sender->sendMessage("Z牌等級插件幫助列表\n/zg help: 得到此列表\n/zg setgrade <玩家ID> <等級>\n/zg setexp <玩家ID> <經驗>\n/zg addgrade <玩家ID> <等級>\n/zg addexp <玩家ID> <經驗>");
                return true;
                    
                case "setgrade":
                if(isset($args[1]) == false or isset($args[2]) == false or is_numeric($args[2]) == false) {
                    $sender->sendMessage("§8[§cX§8]§f 用法錯誤, 正確格式為§e /zg setgrade <玩家ID> <等級>");
                    return true;
                }
                if (!file_exists($this->getDataFolder() . "/Players/" . strtolower($args[1]) . ".yml")) {
                    $sender->sendMessage("§8[§cX§8]§f 玩家§e " . $args[1] . " §f沒有出現在此伺服器過");
                    return true;
                }
                $this->setLevel($args[1], $args[2]);
                $sender->sendMessage("§8[§e!§8]§f 成功設置玩家§e " . $args[1] . " §f的等級至§a " . $args[2] . "等");
                return true;
                
                case "setexp":
                if(isset($args[1]) == false or isset($args[2]) == false or is_numeric($args[2]) == false) {
                    $sender->sendMessage("§8[§cX§8]§f 用法錯誤, 正確格式為§e /zg setexp <玩家ID> <經驗>");
                    return true;
                }
                if (!file_exists($this->getDataFolder() . "/Players/" . strtolower($args[1]) . ".yml")) {
                    $sender->sendMessage("§8[§cX§8]§f 玩家§e " . $args[1] . " §f沒有出現在此伺服器過");
                    return true;
                }
                $this->setExp($args[1], $args[2]);
                $sender->sendMessage("§8[§e!§8]§f 成功設置玩家§e " . $args[1] . " §f的等級至§a " . $args[2] . "等");
                return true;
                    
                case "addgrade":
                if(isset($args[1]) == false or isset($args[2]) == false or is_numeric($args[2]) == false) {
                    $sender->sendMessage("§8[§cX§8]§f 用法錯誤, 正確格式為§e /zg addgrade <玩家ID> <等級>");
                    return true;
                }
                if (!file_exists($this->getDataFolder() . "/Players/" . strtolower($args[1]) . ".yml")) {
                    $sender->sendMessage("§8[§cX§8]§f 玩家§e " . $args[1] . " §f沒有出現在此伺服器過");
                    return true;
                }
                $this->addLevel($args[1], $args[2]);
                $sender->sendMessage("§8[§e!§8]§f 成功設置玩家§e " . $args[1] . " §f的等級至§a " . $args[2] . "等");
                return true;
                    
                case "addexp":
                if(isset($args[1]) == false or isset($args[2]) == false or is_numeric($args[2]) == false) {
                    $sender->sendMessage("§8[§cX§8]§f 用法錯誤, 正確格式為§e /zg addexp <玩家ID> <經驗>");
                    return true;
                }
                if (!file_exists($this->getDataFolder() . "/Players/" . strtolower($args[1]) . ".yml")) {
                    $sender->sendMessage("§8[§cX§8]§f 玩家§e " . $args[1] . " §f沒有出現在此伺服器過");
                    return true;
                }
                $this->addExp($args[1], $args[2]);
                $sender->sendMessage("§8[§e!§8]§f 成功設置玩家§e " . $args[1] . " §f的等級至§a " . $args[2] . "等");
                return true;
                    
                default:
                $sender->sendMessage("§8[§cX§8]§f 用法錯誤, 請使用§e /zg help §f查詢正確用法");
                return true;
            }
        }
    }
     
    public function onPlayerChat(PlayerChatEvent $event) {
        $settings = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
        $player = $event->getPlayer();
        $playername = $player->getName();
        $level = $this->getLevel($playername);
        $prefix = $this->getPrefixByLevel($level);
        $message = $event->getMessage();
        $event->setFormat(str_replace("{message}", $message, str_replace("{prefix}", $prefix, str_replace("{level}", $level, str_replace("{player}", $playername, $settings->get("聊天格式"))))));
        
    }        
}

// 發送Tip
class sendingTip extends Task {
    
    public function __construct(ZGrade $plugin) {
        $this->plugin = $plugin;
    }
    
    public function getPlugin(){
        return $this->plugin;
    }
    
    public function onRun(int $currentTick){
        $settings = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
        $plugin = $this->getPlugin();
        if($settings->get("底部顯示") == "on") {
		foreach($plugin->getServer()->getOnlinePlayers() as $player){
            $playername = $player->getName();
            $level = $this->plugin->getLevel($playername);
            $exp = $this->plugin->getExp($playername);
            $maxexp = $this->plugin->getMaxExp($playername);
            $prefix = $this->plugin->getPrefixByLevel($level);
            $player->sendTip("等級: " . $level . " 經驗: " . $exp . "/" . $maxexp . " " . $prefix);
            }
        }else{
            return;
        }
    }
}
