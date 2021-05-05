<?php

namespace OguzhanUmutlu\UHCRun\arena;

use OguzhanUmutlu\UHCRun\scoreboard\ScoreboardManager;
use OguzhanUmutlu\UHCRun\UHCRun;
use OguzhanUmutlu\UHCRun\utils\PlayerManager;
use pocketmine\level\Level;
use pocketmine\math\Vector3;

class Arena {
    public $listener;
    public $task;
    public $levelName = "";
    /*** @var Level|null */
    public $level= null;
    public $border = 1000;
    public $id = 0;
    public $playerManager;
    public $scoreboardManager;

    public $status = ArenaStatus::STATUS_WAITING;

    public $flags = [
        "invincibility" => true,
        "pvp" => false,
        "break" => false,
        "bordershrink" => false
    ];

    public function __construct() {
        $this->id = UHCRun::getInstance()->getLastId();
        while(UHCRun::getInstance()->getServer()->isLevelGenerated("uhcrun-".$this->id)) {
            UHCRun::getInstance()->addLastId();
            $this->id = UHCRun::getInstance()->getLastId();
        }
        $this->levelName = "uhcrun-".$this->id;
        $this->level = UHCRun::getInstance()->getServer()->getLevelByName($this->levelName);
        if(!UHCRun::getInstance()->getServer()->isLevelGenerated($this->levelName)) {
            UHCRun::getInstance()->getServer()->generateLevel($this->levelName, random_int(0, 999));
            $this->level = UHCRun::getInstance()->getServer()->getLevelByName($this->levelName);
            $this->level->setSpawnLocation(new Vector3(0, 80, 0));
            $this->listener = new ArenaListener($this);
            $this->task = new ArenaTask($this);
            $this->playerManager = new PlayerManager($this);
            $this->scoreboardManager = new ScoreboardManager($this);
            UHCRun::getInstance()->getServer()->getPluginManager()->registerEvents($this->listener, UHCRun::getInstance());
            UHCRun::getInstance()->getScheduler()->scheduleRepeatingTask($this->task, 20);
        } else {
            UHCRun::getInstance()->manager->removeArena($this);
        }
    }

    public function getFlag(string $flag): bool {
        if(!isset($this->flags[$flag])) return false;
        return $this->flags[$flag];
    }

    public function setFlag(string $flag, bool $value): void {
        if(!isset($this->flags[$flag])) return;
        $this->flags[$flag] = $value;
    }

    public function getPlayerManager(): PlayerManager {
        return $this->playerManager;
    }

    public function removeWorld() {
        if(UHCRun::getInstance()->getServer()->isLevelGenerated($this->levelName)) {
            if(UHCRun::getInstance()->getServer()->isLevelLoaded($this->levelName)) {
                foreach($this->level->getPlayers() as $player) {
                    $player->teleport(UHCRun::getInstance()->getMainLobby());
                }
                UHCRun::getInstance()->getServer()->unloadLevel($this->level);
            }
            $this->rmDir(UHCRun::getInstance()->getServer()->getDataPath()."/worlds/" . $this->levelName);
        }
    }

    private function rmDir(string $dir){
        if(basename($dir) == "." || basename($dir) == ".." || !is_dir($dir)) return;
        foreach (scandir($dir) as $item) {
            if($item != "." || $item != "..") {
                if(is_dir($dir . DIRECTORY_SEPARATOR . $item)) {
                    $this->rmDir($dir . DIRECTORY_SEPARATOR . $item);
                }
                if(is_file($dir . DIRECTORY_SEPARATOR . $item)) {
                    unlink($dir . DIRECTORY_SEPARATOR . $item);
                }
            }

        }
        rmdir($dir);
    }
}