<?php

namespace OguzhanUmutlu\UHCRun\arena;

use OguzhanUmutlu\UHCRun\events\GameEventEvent;
use OguzhanUmutlu\UHCRun\UHCRun;
use OguzhanUmutlu\UHCRun\utils\PlayerStatus;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;

class ArenaTask extends Task {
    /*** @var Arena */
    public $arena;
    /*** @var int */
    public $countdown;
    public function __construct(Arena $arena) {
        $this->arena = $arena;
        $this->countdown = 0;
    }

    public function eventCalc(): string {
        $time = $this->countdown;
        if($time == 15) return "invincibility";
        if($time == 245) return "bordershrinkhalf";
        if($time == 315) return "bordershrink";
        if($time == 545) return "lasthealhalf";
        if($time == 615) return "lastheal";
        if($time == 855) return "pvphalf";
        if($time == 915) return "pvp";
        if($time == 1555) return "meetuphalf";
        if($time == 1615) return "meetup";
        if($time == 1765) return "endhalf";
        if($time == 1915) return "end";
        return "none";
    }

    public function onRun(int $currentTick) {
        $arena = $this->arena;
        $p = UHCRun::getInstance();
        $pm = $arena->getPlayerManager();
        $arena->level->setTime(6000);
        $arena->level->stopTime();
        if($arena->status == ArenaStatus::STATUS_WAITING) {
            if(count($pm->getAlivePlayers()) > 3) {
                $arena->status = ArenaStatus::STATUS_STARTING;
                $this->countdown = 60;
                foreach($arena->getPlayerManager()->getAlivePlayers() as $player) {
                    $player->setXpLevel($this->countdown);
                    $player->setXpProgress($this->countdown/60);
                }
            }
        } else if($arena->status == ArenaStatus::STATUS_STARTING) {
            if(count($pm->getAlivePlayers()) < 4) {
                $arena->status = ArenaStatus::STATUS_WAITING;
                $this->countdown = 0;
                foreach($arena->getPlayerManager()->getAlivePlayers() as $player) {
                    $player->setXpLevel(0);
                    $player->setXpProgress(0);
                }
            }
            $this->countdown--;
            foreach($arena->getPlayerManager()->getAlivePlayers() as $player) {
                $player->setXpLevel($this->countdown);
                $player->setXpProgress($this->countdown/60);
            }
            if($this->countdown < 1) {
                $ev = new GameEventEvent(
                    $arena,
                    GameEventEvent::EVENT_START
                );
                $ev->call();
                if(!$ev->isCancelled()) {
                    $arena->status = ArenaStatus::STATUS_STARTED;
                    $arena->setFlag("break", true);
                    $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["event-invincibility"]);
                    $this->countdown = 0;
                    foreach($arena->getPlayerManager()->getAlivePlayers() as $player) {
                        $x = random_int(0-$arena->border, $arena->border);
                        $z = random_int(0-$arena->border, $arena->border);
                        $player->teleport(new Position($x, 100, $z, $arena->level));
                    }
                }
            }
        } else if($arena->status == ArenaStatus::STATUS_STARTED) {
            if(count($arena->getPlayerManager()->getAlivePlayers()) < 2) {
                $arena->status = ArenaStatus::STATUS_ENDING;
                $this->countdown = 5;
                $arena->setFlag("pvp", false);
                $arena->setFlag("break", false);
                $arena->setFlag("invincibility", true);
                if(isset(array_keys($arena->getPlayerManager()->getAlivePlayers())[0]) && isset($arena->getPlayerManager()->getAlivePlayers()[array_keys($arena->getPlayerManager()->getAlivePlayers())[0]])) {
                    $winner = $arena->getPlayerManager()->getAlivePlayers()[array_keys($arena->getPlayerManager()->getAlivePlayers())[0]];
                    $winnerName = $winner->getName();
                } else {
                    $winner = null;
                    $winnerName = "no one";
                }
                $arena->getPlayerManager()->broadcast("message", str_replace(
                    "{winner}",
                    $winnerName,
                    UHCRun::getInstance()->messages["player-won-message"]
                ));
                if($winner) $winner->sendTitle(UHCRun::getInstance()->messages["you-won"]);
            } else $this->countdown++;
            if($arena->getFlag("bordershrink")) {
                $arena->border-=0.5;
            }
            if($this->eventCalc() == "invincibility") {
                $ev = new GameEventEvent(
                    $arena,
                    GameEventEvent::EVENT_INVINCIBILITY
                );
                $ev->call();
                if(!$ev->isCancelled()) {
                    $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["invincibility-end"]);
                    $arena->setFlag("invincibility", false);
                }
            } else if($this->eventCalc() == "bordershrinkhalf") {
                $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["border-shrink-last1"]);
            } else if($this->eventCalc() == "bordershrink") {
                $ev = new GameEventEvent(
                    $arena,
                    GameEventEvent::EVENT_BORDER_SHRINK
                );
                $ev->call();
                if(!$ev->isCancelled()) {
                    $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["border-shrink-message"]);
                    $arena->setFlag("bordershrink", true);
                }
            } else if($this->eventCalc() == "lasthealhalf") {
                $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["last-heal-last1"]);
            } else if($this->eventCalc() == "lastheal") {
                $ev = new GameEventEvent(
                    $arena,
                    GameEventEvent::EVENT_LAST_HEAL
                );
                $ev->call();
                if(!$ev->isCancelled()) {
                    $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["last-heal-message"]);
                    foreach($arena->getPlayerManager()->getAlivePlayers() as $player) {
                        $player->setHealth($player->getMaxHealth());
                    }
                }
            } else if($this->eventCalc() == "pvphalf") {
                $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["pvp-last1"]);
            } else if($this->eventCalc() == "pvp") {
                $ev = new GameEventEvent(
                    $arena,
                    GameEventEvent::EVENT_PVP
                );
                $ev->call();
                if(!$ev->isCancelled()) {
                    $arena->setFlag("pvp", true);
                    $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["pvp-message"]);
                }
            } else if($this->eventCalc() == "meetuphalf") {
                $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["meetup-last1"]);
            } else if($this->eventCalc() == "meetup") {
                $ev = new GameEventEvent(
                    $arena,
                    GameEventEvent::EVENT_MEETUP
                );
                $ev->call();
                if(!$ev->isCancelled()) {
                    $arena->setFlag("bordershrink", false);
                    $arena->setFlag("pvp", false);
                    $arena->setFlag("invincibility", true);
                    $arena->setBorder(100);
                    foreach($arena->getPlayerManager()->getAlivePlayers() as $player) {
                        $x = random_int(-100, 100);
                        $z = random_int(-100, 100);
                        $player->teleport(new Vector3($x, 100, $z));
                    }
                    $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["meetup-message"]);
                    $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["pvp-10sec"]);
                    UHCRun::getInstance()->getScheduler()->scheduleDelayedTask(new EnablePvPTask($arena), 200);
                }
            } else if($this->eventCalc() == "endhalf") {
                $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["end-last1"]);
            } else if($this->eventCalc() == "end") {
                $ev = new GameEventEvent(
                    $arena,
                    GameEventEvent::EVENT_END
                );
                $ev->call();
                if(!$ev->isCancelled()) {
                    $arena->status = ArenaStatus::STATUS_ENDING;
                    $this->countdown = 5;
                    $arena->setFlag("pvp", false);
                    $arena->setFlag("break", false);
                    $arena->setFlag("invincibility", true);
                    $arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["end-message"]);
                }
            }
            foreach($arena->getPlayerManager()->getAlivePlayers() as $player) {
                if($this->arena->getPlayerManager()->getPlayerState($player) != PlayerStatus::PLAYER_OFFLINE) {
                    $x = $player->getFloorX() < 0 ? 0-$player->getFloorX() : $player->getFloorX();
                    $z = $player->getFloorZ() < 0 ? 0-$player->getFloorZ() : $player->getFloorZ();
                    if($x > $this->arena->border || $z > $this->arena->border) {
                        $player->addEffect(new EffectInstance(Effect::getEffect(Effect::FATAL_POISON), 0.5, 1, false));
                    }
                }
            }
        } else if($arena->status == ArenaStatus::STATUS_ENDING) {
            $this->countdown--;
            if($this->countdown < 1) {
                UHCRun::getInstance()->manager->removeArena($arena);
            }
        } else {
            UHCRun::getInstance()->manager->removeArena($arena);
        }
        $arena->scoreboardManager->tickScoreboard();
    }
}