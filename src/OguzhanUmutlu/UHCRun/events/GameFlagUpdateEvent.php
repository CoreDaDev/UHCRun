<?php

namespace OguzhanUmutlu\UHCRun\events;

use OguzhanUmutlu\UHCRun\arena\Arena;
use OguzhanUmutlu\UHCRun\UHCRun;
use pocketmine\event\plugin\PluginEvent;

class GameFlagUpdateEvent extends PluginEvent {
    /*** @var Arena */
    private $arena;
    /*** @var string */
    private $flag;
    /*** @var bool */
    private $before;
    /*** @var bool */
    private $after;
    public static $handlerList = null;

    public function __construct(Arena $arena, string $flag, bool $before, bool $after) {
        parent::__construct(UHCRun::getInstance());
        $this->arena = $arena;
        $this->flag = $flag;
        $this->before = $before;
        $this->after = $after;
    }

    /*** @return Arena */
    public function getArena(): Arena {
        return $this->arena;
    }

    /*** @return string */
    public function getFlag(): string {
        return $this->flag;
    }

    /*** @param bool $value */
    public function setCancelled(bool $value = true): void {
        if($value) {
            $this->arena->setFlag($this->flag, $this->before);
        } else {
            $this->arena->setFlag($this->flag, $this->after);
        }
    }

    /*** @return bool */
    public function getBefore(): bool {
        return $this->before;
    }

    /*** @return bool */
    public function getAfter(): bool {
        return $this->after;
    }
}