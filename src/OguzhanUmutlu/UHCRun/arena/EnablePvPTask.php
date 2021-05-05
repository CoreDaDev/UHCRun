<?php

namespace OguzhanUmutlu\UHCRun\arena;

use OguzhanUmutlu\UHCRun\UHCRun;
use pocketmine\scheduler\Task;

class EnablePvPTask extends Task{
  public $arena;
  public function __construct(Arena $arena) {
    $this->arena = $arena;
  }
  public function onRun(int $currentTick) {
    $this->arena->setFlag("pvp", true);
    $this->arena->setFlag("invincibility", false);
    $this->arena->getPlayerManager()->broadcast("message", UHCRun::getInstance()->messages["pvp-message"]);
  }
}
