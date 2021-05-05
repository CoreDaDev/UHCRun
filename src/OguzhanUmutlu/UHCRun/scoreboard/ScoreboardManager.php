<?php

namespace OguzhanUmutlu\UHCRun\scoreboard;

use OguzhanUmutlu\UHCRun\arena\Arena;
use OguzhanUmutlu\UHCRun\arena\ArenaStatus;
use OguzhanUmutlu\UHCRun\UHCRun;
use pocketmine\Player;

class ScoreboardManager {
    private const EMPTY_CACHE = ["§0\e", "§1\e", "§2\e", "§3\e", "§4\e", "§5\e", "§6\e", "§7\e", "§8\e", "§9\e", "§a\e", "§b\e", "§c\e", "§d\e", "§e\e"];
    private $scoreboards = [];
    private $arena;
    private $networkBound = [];
    private $lastState = [];

    public function __construct(Arena $arena) {
        $this->arena = $arena;
    }

    public function setStatus(string $status): void {
        $this->status = $status;
    }

    public function addPlayer(Player $pl): void {
        $this->scoreboards[$pl->getName()] = $pl;
        Scoreboard::setScore($pl, "§e§lUHC Run", Scoreboard::SORT_ASCENDING);
        $this->updateScoreboard($pl);
    }

    private function updateScoreboard(Player $pl): void {
        if (!isset($this->scoreboards[$pl->getName()])) {
            $this->addPlayer($pl);
            return;
        } elseif (!$pl->isOnline()) {
            $this->removePlayer($pl);
            return;
        }
        $data = [" "];
        if($this->arena->status == ArenaStatus::STATUS_WAITING) {
            $data = array_merge($data, array_map(function($n){
                return str_replace([
                    "{players}"
                ], [
                    count($this->arena->getPlayerManager()->getAlivePlayers())
                ], $n);
            },UHCRun::getInstance()->getConfig()->getNested("scoreboard.waiting")));
        } else if($this->arena->status == ArenaStatus::STATUS_STARTING) {
            $data = array_merge($data, array_map(function($n){
                return str_replace([
                    "{players}",
                    "{seconds}"
                ], [
                    count($this->arena->getPlayerManager()->getAlivePlayers()),
                    $this->arena->task->countdown
                ], $n);
            },UHCRun::getInstance()->getConfig()->getNested("scoreboard.starting")));
        } else if($this->arena->status == ArenaStatus::STATUS_STARTED) {
            $mt = 1915-$this->arena->task->countdown;
            $min = 0;
            if($mt/60 > 0) {
                $min = floor($mt/60);
                $mt-= $min*60;
            }
            $sec = $mt;
            $event = "";
            $time = $this->arena->task->countdown;
            $evtype = "none";
            if($time < 1915) $evtype = "end";
            if($time < 1765) $evtype = "endhalf";
            if($time < 1615) $evtype = "meetup";
            if($time < 1555) $evtype = "meetuphalf";
            if($time < 915) $evtype = "pvp";
            if($time < 855) $evtype = "pvphalf";
            if($time < 615) $evtype = "lastheal";
            if($time < 545) $evtype = "lasthealhalf";
            if($time < 315) $evtype = "bordershrink";
            if($time < 245) $evtype = "bordershrinkhalf";
            if($time < 15) $evtype = "invincibility";
            switch($evtype) {
                case "invincibility":
                    $event = str_replace("{seconds}", (string)(15-$this->arena->task->countdown), UHCRun::getInstance()->messages["scoreboard-invincibility"]);
                    break;
                case "bordershrink":
                case "bordershrinkhalf":
                    $event = str_replace("{seconds}", (string)(315-$this->arena->task->countdown), UHCRun::getInstance()->messages["scoreboard-bordershrink"]);
                    break;
                case "lastheal":
                case "lasthealhalf":
                    $event = str_replace("{seconds}", (string)(615-$this->arena->task->countdown), UHCRun::getInstance()->messages["scoreboard-lastheal"]);
                    break;
                case "pvp":
                case "pvphalf":
                    $event = str_replace("{seconds}", (string)(915-$this->arena->task->countdown), UHCRun::getInstance()->messages["scoreboard-pvp"]);
                    break;
                case "meetup":
                case "meetuphalf":
                    $event = str_replace("{seconds}", (string)(1615-$this->arena->task->countdown), UHCRun::getInstance()->messages["scoreboard-meetup"]);
                    break;
                case "end":
                case "endhalf":
                    $event = str_replace("{seconds}", (string)(1915-$this->arena->task->countdown), UHCRun::getInstance()->messages["scoreboard-end"]);
                    break;
                default:
                    $event = "Nothing";
            }
            $data = array_merge($data, array_map(function($n)use($min,$sec,$event,$pl){
                return str_replace([
                    "{players}",
                    "{endsin}",
                    "{event}",
                    "{border}",
                    "{pvp}"
                ], [
                    count($this->arena->getPlayerManager()->getAlivePlayers()),
                    $min.":".$sec,
                    $event,
                    $this->arena->border,
                    $this->arena->getFlag("pvp") ? "Disabled" : "Enabled"
                ], $n);
            },UHCRun::getInstance()->getConfig()->getNested("scoreboard.started")));
        } else if($this->arena->status == ArenaStatus::STATUS_ENDING) {
            $data = array_merge($data, array_map(function($n){
                return str_replace([
                    "{seconds}"
                ], [
                    $this->arena->task->countdown
                ], $n);
            },UHCRun::getInstance()->getConfig()->getNested("scoreboard.ending")));
        } else {
            $data = ["", "An error occured."];
        }
            foreach ($data as $scLine => $message) {
            Scoreboard::setScoreLine($pl, $scLine, $message);
            $line = $scLine + 1;
            if (($this->networkBound[$pl->getName()][$line] ?? -1) === $message) {
                continue;
            }
            Scoreboard::setScoreLine($pl, $line, $message);
            $this->networkBound[$pl->getName()][$line] = $message;
        }
    }

    public function tickScoreboard(): void {
        foreach ($this->arena->getPlayerManager()->getAllPlayers() as $player) $this->updateScoreboard($player);
    }

    public function resetScoreboard(): void {
        foreach ($this->scoreboards as $player) $this->removePlayer($player);
        $this->networkBound = [];
    }

    public function removePlayer(Player $pl): void {
        unset($this->scoreboards[$pl->getName()]);
        unset($this->networkBound[$pl->getName()]);
        Scoreboard::removeScore($pl);
    }
}