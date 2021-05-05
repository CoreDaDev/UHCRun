<?php


namespace OguzhanUmutlu\UHCRun\events;


use OguzhanUmutlu\UHCRun\arena\Arena;
use OguzhanUmutlu\UHCRun\UHCRun;
use pocketmine\event\Cancellable;
use pocketmine\event\plugin\PluginEvent;
use pocketmine\Player;

class GamePlayerDeathEvent extends PluginEvent {
    /*** @var Arena */
    private $arena;
    /*** @var Player */
    private $player;
    public static $handlerList = null;

    public function __construct(Arena $arena, Player $player) {
        parent::__construct(UHCRun::getInstance());
        $this->arena = $arena;
        $this->player = $player;
    }

    /*** @return Arena */
    public function getArena(): Arena {
        return $this->arena;
    }

    /*** @return Player */
    public function getPlayer(): Player {
        return $this->player;
    }
}