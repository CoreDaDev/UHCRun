<?php

namespace OguzhanUmutlu\UHCRun\manager;

use OguzhanUmutlu\UHCRun\arena\Arena;
use OguzhanUmutlu\UHCRun\UHCRun;
use OguzhanUmutlu\UHCRun\utils\PlayerStatus;
use pocketmine\level\Level;
use pocketmine\Player;

class ArenaManager {
    /*** @var Arena[] */
    private $arenas = [];
    public function createArena(): Arena {
        $arena = new Arena();
        $this->arenas[] = $arena;
        UHCRun::getInstance()->addLastId();
        return $arena;
    }

    public function getArenaFromId(int $id): ?Arena {
        $result = null;
        foreach($this->getArenas() as $arena) {
            if($arena->id == $id) $result = $arena;
        }
        return $result;
    }

    public function getPlayerArena(Player $player): ?Arena {
        $result = null;
        foreach($this->getArenas() as $arena) {
            if($arena->playerManager && $arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_ALIVE) $result = $arena;
        }
        return $result;
    }

    public function getArenaFromLevel($level): ?Arena {
        if($level instanceof Level) $level = $level->getFolderName();
        return is_numeric(substr($level, 7)) ? $this->getArenaFromId((int)substr($level, 7)) : null;
    }

    public function removeArena(Arena $arena): bool {
        if(!$this->getArenaFromId($arena->id)) {
            if(UHCRun::getInstance()->getServer()->isLevelGenerated($arena->levelName)) {
                $arena->removeWorld();
            }
            if(isset($this->arenas[array_search($arena, $this->arenas)])) {
                unset($this->arenas[array_search($arena, $this->arenas)]);
            }
            return false;
        }
        foreach($arena->getPlayerManager()->getAllPlayers() as $x) {
            $arena->getPlayerManager()->removeAlivePlayer($x);
            $arena->getPlayerManager()->removeDeadPlayer($x);
            $arena->getPlayerManager()->removeSpectator($x);
        }
        foreach($arena->scoreboardManager->getPlayers() as $player) {
            $player = UHCRun::getInstance()->getServer()->getPlayerExact($player);
            if($player instanceof Player && $player->isOnline())$arena->scoreboardManager->removePlayer($player);
        }
        $arena->removeWorld();
        if($arena->playerManager) {
            foreach($arena->getPlayerManager()->getAllPlayers() as $player) {
                $player->teleport(UHCRun::getInstance()->getMainLobby());
            }
        }
        $arena->task->getHandler()->cancel();
        unset($this->arenas[array_search($arena, $this->arenas)]);
        return true;
    }

    public function getArenas(): array {
        return $this->arenas;
    }
}