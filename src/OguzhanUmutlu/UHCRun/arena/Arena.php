<?php

namespace OguzhanUmutlu\UHCRun\arena;

use OguzhanUmutlu\UHCRun\events\BorderChangeEvent;
use OguzhanUmutlu\UHCRun\events\GameFlagUpdateEvent;
use OguzhanUmutlu\UHCRun\scoreboard\ScoreboardManager;
use OguzhanUmutlu\UHCRun\UHCRun;
use OguzhanUmutlu\UHCRun\utils\PlayerManager;
use pocketmine\event\Event;
use pocketmine\level\format\io\BaseLevelProvider;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;

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
        $ev = new GameFlagUpdateEvent(
            $this,
            $flag,
            $this->getFlag($flag),
            $value
        );
        $ev->call();
        if(!$ev->isCancelled()) {
            $this->flags[$flag] = $value;
        }
    }

    public function setBorder(int $size): void {
        $ev = new BorderChangeEvent(
            $this,
            $this->border,
            $size
        );
        $ev->call();
        if(!$ev->isCancelled()) {
            $this->border = $size;
        }
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

    /*
     * https://github.com/CzechPMDevs/MultiWorld
     * */

    public function showCoordinatesOnWorld(): bool {
        $levelProvider = $this->level->getProvider();
        if(!$levelProvider instanceof BaseLevelProvider) {
            return false;
        }
        $compound = $levelProvider->getLevelData()->getCompoundTag("GameRules");
        $compound->setString("showcoordinates", "showcoordinates");

        $levelProvider->saveLevelData();
        foreach ($this->level->getPlayers() as $player) {
            $pk = new GameRulesChangedPacket();
            $pk->gameRules = self::getLevelGameRules($this->level);
            $player->dataPacket($pk);
        }
        return true;
    }

    public static function getLevelGameRules(Level $level): array {
        $levelProvider = $level->getProvider();
        $defgmrs = [
            "doDaylightCycle" => [1, true],
            "doMobLoot" => [1, true],
            "doTileDrops" => [1, true],
            "keepInventory" => [1, false],
            "naturalRegeneration" => [1, true],
            "pvp" => [1, true],
            "showcoordinates" => [1, false],
            "tntexplodes" => [1, true]
        ];
        if(!$levelProvider instanceof BaseLevelProvider) {
            return $defgmrs;
        }

        $compound = $levelProvider->getLevelData()->getCompoundTag("GameRules");

        if(!$compound instanceof CompoundTag) {
            $levelProvider->getLevelData()->setTag(new CompoundTag("GameRules", []));
            $compound = $levelProvider->getLevelData()->getCompoundTag("GameRules");
            foreach ($defgmrs as $rule => [$type, $value]) {
                $compound->setString($rule, $value ? "true" : "false");
            }
        }

        if($compound->count() == 0) {
            foreach ($defgmrs as $rule => [$type, $value]) {
                $compound->setString($rule, $value);
            }
        }
        $gameRules = [];

        foreach (array_keys($defgmrs) as $rule) {
            if($compound->offsetExists($rule)) {
                $value = $compound->getString($rule) ? "true" : "false";
                $gameRules[$rule] = [2, $value];
            }
        }

        return $gameRules;
    }
}