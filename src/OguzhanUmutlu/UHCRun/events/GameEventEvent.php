<?php

namespace OguzhanUmutlu\UHCRun\events;

use OguzhanUmutlu\UHCRun\arena\Arena;
use OguzhanUmutlu\UHCRun\UHCRun;
use pocketmine\event\Cancellable;
use pocketmine\event\plugin\PluginEvent;

class GameEventEvent extends PluginEvent implements Cancellable {
    /*** @var Arena */
    private $arena;
    /*** @var int */
    private $event;
    public static $handlerList = null;

    const EVENT_START = 0;
    const EVENT_INVINCIBILITY = 1;
    const EVENT_BORDER_SHRINK = 2;
    const EVENT_LAST_HEAL = 3;
    const EVENT_PVP = 4;
    const EVENT_MEETUP = 5;
    const EVENT_END = 6;

    public function __construct(Arena $arena, int $event) {
        parent::__construct(UHCRun::getInstance());
        $this->arena = $arena;
        $this->event = $event;
    }

    /*** @return Arena */
    public function getArena(): Arena {
        return $this->arena;
    }

    /*** @return int */
    public function getEvent(): int {
        return $this->event;
    }
}