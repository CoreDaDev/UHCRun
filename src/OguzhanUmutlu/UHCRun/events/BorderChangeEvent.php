<?php

namespace OguzhanUmutlu\UHCRun\events;

use OguzhanUmutlu\UHCRun\arena\Arena;
use OguzhanUmutlu\UHCRun\UHCRun;
use pocketmine\event\Cancellable;
use pocketmine\event\plugin\PluginEvent;

class BorderChangeEvent extends PluginEvent implements Cancellable {
    /*** @var Arena */
    private $arena;
    /*** @var int */
    private $beforeBorder;
    /*** @var int */
    private $afterBorder;
    public static $handlerList = null;

    public function __construct(Arena $arena, int $beforeBorder, int $afterBorder) {
        parent::__construct(UHCRun::getInstance());
        $this->arena = $arena;
        $this->beforeBorder = $beforeBorder;
        $this->afterBorder = $afterBorder;
    }

    /*** @return Arena */
    public function getArena(): Arena {
        return $this->arena;
    }

    /*** @return int */
    public function getBeforeBorder(): int {
        return $this->beforeBorder;
    }

    /*** @return int */
    public function getAfterBorder(): int {
        return $this->afterBorder;
    }

    /*** @param int $border */
    public function setBorder(int $border): void {
        $this->arena->setBorder($border);
    }
}