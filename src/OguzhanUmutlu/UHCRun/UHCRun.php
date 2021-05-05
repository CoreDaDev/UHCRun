<?php

namespace OguzhanUmutlu\UHCRun;

use OguzhanUmutlu\UHCRun\arena\ArenaStatus;
use OguzhanUmutlu\UHCRun\commands\UHCRunCommand;
use OguzhanUmutlu\UHCRun\manager\ArenaManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class UHCRun extends PluginBase {
    static $instance;
    /*** @var ArenaManager */
    public $manager;
    public $id = 0;
    /*** @var UHCRunCommand */
    public $command;
    public $messages = [];

    public function onEnable() {
        $this->saveResource("messages.yml");
        $this->messages = (new Config($this->getDataFolder() . "messages.yml"))->getAll();
        self::$instance = $this;
        $this->manager = new ArenaManager();
        $this->command = new UHCRunCommand();
        $this->saveDefaultConfig();
        if(!UHCRun::getInstance()->getConfig()->getNested("ore-to-ingot")) {
            UHCRun::getInstance()->getConfig()->setNested("ore-to-ingot", true);UHCRun::getInstance()->getConfig()->save();UHCRun::getInstance()->getConfig()->reload();
        }
        if(!UHCRun::getInstance()->getConfig()->getNested("scoreboard.waiting")) {
            UHCRun::getInstance()->getConfig()->setNested("scoreboard.waiting", [
                " ",
                " §cWaiting players...",
                " §ePlayers: §b{players}§e/§b16",
                "                                          "
            ]);UHCRun::getInstance()->getConfig()->save();UHCRun::getInstance()->getConfig()->reload();
        }
        if(!UHCRun::getInstance()->getConfig()->getNested("scoreboard.starting")) {
            UHCRun::getInstance()->getConfig()->setNested("scoreboard.starting", [
                " ",
                " §aGame is starting in§b {seconds}§a seconds",
                " §ePlayers: §b{players}§e/§b16",
                "                                          "
            ]);UHCRun::getInstance()->getConfig()->save();UHCRun::getInstance()->getConfig()->reload();
        }
        if(!UHCRun::getInstance()->getConfig()->getNested("scoreboard.started")) {
            UHCRun::getInstance()->getConfig()->setNested("scoreboard.started", [
                " ",
                " §eEvent:",
                " §b§l * §r§a{event}",
                " §eAlive Players: §b{players}§e/§b16",
                " §cBorder: {border}x{border}",
                " §cPvP: {pvp}",
                "                                          "
            ]);UHCRun::getInstance()->getConfig()->save();UHCRun::getInstance()->getConfig()->reload();
        }
        if(!UHCRun::getInstance()->getConfig()->getNested("scoreboard.ending")) {
            UHCRun::getInstance()->getConfig()->setNested("scoreboard.ending", [
                " ",
                " §cGame restarts in §b{seconds}§c seconds",
                "                                          "
            ]);UHCRun::getInstance()->getConfig()->save();UHCRun::getInstance()->getConfig()->reload();
        }
    }
    public static function getInstance(): UHCRun {
        return self::$instance;
    }

    public function getMainLobby(): Position {
        $a = $this->getConfig()->get("mainlobby");
        if($this->getServer()->isLevelGenerated(explode(":",$a)[0]) && !$this->getServer()->isLevelLoaded(explode(":",$a)[0])) {
            $this->getServer()->loadLevel(explode(":",$a)[0]);
        }
        return $a ? (new Position(
                (int)explode(":",$a)[1],
                (int)explode(":",$a)[2],
                (int)explode(":",$a)[3],
                ($this->getServer()->isLevelGenerated(explode(":",$a)[0]) ? $this->getServer()->getLevelByName(explode(":",$a)[0]) : $this->getServer()->getDefaultLevel())
            ) ?? $this->getServer()->getDefaultLevel()->getSpawnLocation()) : $this->getServer()->getDefaultLevel()->getSpawnLocation();
    }

    public function getWaitLobby(): Position {
        $a = $this->getConfig()->getNested("waitlobby");
        if($this->getServer()->isLevelGenerated(explode(":",$a)[0]) && !$this->getServer()->isLevelLoaded(explode(":",$a)[0])) {
            $this->getServer()->loadLevel(explode(":",$a)[0]);
        }
        return $a ? (new Position(
            (int)explode(":",$a)[1],
            (int)explode(":",$a)[2],
            (int)explode(":",$a)[3],
            ($this->getServer()->getLevelByName(explode(":",$a)[0]) ?? $this->getServer()->getDefaultLevel())
            ) ?? $this->getServer()->getDefaultLevel()->getSpawnLocation()) : $this->getServer()->getDefaultLevel()->getSpawnLocation();
    }

    public function getLastId(): int{return $this->id;}
    public function addLastId(): void{$this->id++;}
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if($command->getName() == "uhcrun") {
            $this->command->onCommand($sender, $args);
        } else if($command->getName() == "uhcrunjoin") {
            if(!$sender instanceof Player) {
                $sender->sendMessage($this->messages["use-ingame"]);
                return true;
            }
            if($this->manager->getPlayerArena($sender)) {
                $sender->sendMessage($this->messages["use-notinarena"]);
                return true;
            }
            $result = null;
            foreach($this->manager->getArenas() as $arena) {
                if($arena->status < ArenaStatus::STATUS_STARTED && $arena) $result = $arena;
            }
            if(!$result) $result = $this->manager->createArena();
            if($result->playerManager) {
                $result->getPlayerManager()->addAlivePlayer($sender);
            } else {
                $sender->chat("/".$command->getName());
            }
        } else if($command->getName() == "uhcrunleave") {
            if(!$sender instanceof Player) {
                $sender->sendMessage($this->messages["use-ingame"]);
                return true;
            }
            $arena = $this->manager->getPlayerArena($sender);
            if(!$arena) {
                $sender->sendMessage($this->messages["use-inarena"]);
                return true;
            }
            $sender->teleport($this->getMainLobby());
        } else if($command->getName() == "uhcrunrejoin") {
            if(!$sender instanceof Player) {
                $sender->sendMessage($this->messages["use-ingame"]);
                return true;
            }
            if($this->manager->getPlayerArena($sender)) {
                $sender->sendMessage($this->messages["use-notinarena"]);
                return true;
            }
            $result = null;
            foreach($this->manager->getArenas() as $arena) {
                if(isset($arena->getPlayerManager()->rejoin[$sender->getName()])) $result = $arena;
            }
            if(!$result) {
                $sender->sendMessage($this->messages["never-in-arena"]);
                return true;
            }
            $result->getPlayerManager()->addAlivePlayer($sender);
        }
        return true;
    }
    public function onDisable() {
        foreach($this->manager->getArenas() as $arena) {
            $this->manager->removeArena($arena);
        }
    }
}