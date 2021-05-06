<?php

namespace OguzhanUmutlu\UHCRun\utils;

use OguzhanUmutlu\UHCRun\arena\Arena;
use OguzhanUmutlu\UHCRun\arena\ArenaStatus;
use OguzhanUmutlu\UHCRun\events\GamePlayerDeathEvent;
use OguzhanUmutlu\UHCRun\UHCRun;
use pocketmine\item\Item;
use pocketmine\level\format\io\BaseLevelProvider;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\NamedTag;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\Player;

class PlayerManager {
    /*** @var Arena */
    public $arena;
    /*** @var Player[] */
    public $alivePlayers = [];
    /*** @var Player[] */
    public $deadPlayers = [];
    /*** @var Player[] */
    public $spectators = [];

    const BROADCAST_MESSAGE = "message";
    const BROADCAST_TITLE = "title";
    const BROADCAST_POPUP = "popup";

    public function __construct(Arena $arena) {
        $this->arena = $arena;
    }

    public function broadcast(string $type, string $message) {
        foreach($this->getAllPlayers() as $player) {
            if($player instanceof Player) {
                switch($type) {
                    case self::BROADCAST_MESSAGE:
                        $player->sendMessage($message);
                        break;
                    case self::BROADCAST_TITLE:
                        $player->sendTitle($message);
                        break;
                    case self::BROADCAST_POPUP:
                        $player->sendPopup($message);
                        break;
                }
            }
        }
    }

    public function getPlayers(): array {
        return array_merge($this->alivePlayers, $this->deadPlayers);
    }

    public function getAllPlayers(): array {
        return array_merge($this->alivePlayers, $this->deadPlayers, $this->spectators);
    }

    public function getAlivePlayers(): array {
        return $this->alivePlayers;
    }

    public function getDeadPlayers(): array {
        return $this->deadPlayers;
    }

    public function getSpectator(): array {
        return $this->spectators;
    }

    public function getPlayerState(Player $player): int {
        if(!$player->isOnline()) return PlayerStatus::PLAYER_OFFLINE;
        if(in_array($player->getName(), array_map(function($n){return $n->isOnline() ? $n->getName() : "";}, $this->getAlivePlayers()))) return PlayerStatus::PLAYER_ALIVE;
        if(in_array($player->getName(), array_map(function($n){return $n->isOnline() ? $n->getName() : "";}, $this->getDeadPlayers()))) return PlayerStatus::PLAYER_DEAD;
        if(in_array($player->getName(), array_map(function($n){return $n->isOnline() ? $n->getName() : "";}, $this->getSpectator()))) return PlayerStatus::PLAYER_SPECTATOR;
        return 0;
    }

    private function mapNames(array $list): array {
        return array_map(function($n){return $n->isOnline() ? $n->getName() : "";},$list);
    }

    public function addAlivePlayer(Player $player) {
        if(UHCRun::getInstance()->manager->getPlayerArena($player) instanceof Arena) {
            UHCRun::getInstance()->manager->getPlayerArena($player)->getPlayerManager()->removeAlivePlayer($player);
            UHCRun::getInstance()->manager->getPlayerArena($player)->getPlayerManager()->removeDeadPlayer($player);
            UHCRun::getInstance()->manager->getPlayerArena($player)->getPlayerManager()->removeSpectator($player);
        }
        if(!in_array($player->getName(), $this->mapNames($this->alivePlayers)))$this->alivePlayers[] = $player;
        $player->getInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getEnderChestInventory()->clearAll();
        $player->removeAllEffects();
        $player->setGamemode(0);
        $this->arena->scoreboardManager->addPlayer($player);
        if($this->arena->status < ArenaStatus::STATUS_STARTED) {
            $player->teleport(UHCRun::getInstance()->getWaitLobby());
        } else {
            $player->teleport($this->arena->level->getSafeSpawn());
        }
        if(isset($this->rejoin[$player->getName()])) {
            $player->getInventory()->setContents($this->rejoin[$player->getName()]["inventory"]);
            $player->getArmorInventory()->setContents($this->rejoin[$player->getName()]["armor"]);
            $player->teleport($this->rejoin[$player->getName()]["position"]);
            $this->broadcast("message", str_replace(
                ["{player}", "{players}"],
                [$player->getName(), count($this->getAlivePlayers())],
                UHCRun::getInstance()->messages["rejoin-message"]
            ));
        } else {
            $this->broadcast("message", str_replace(
                ["{player}", "{players}"],
                [$player->getName(), count($this->getAlivePlayers())],
                UHCRun::getInstance()->messages["join-message"]
            ));
        }
    }
    /**
     * @var array[]
     */
    public $rejoin = [];
    public function removeAlivePlayer(Player $player, bool $die = false, bool $teleport = true) {
        $player->getInventory()->clearAll();
        $player->getCursorInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getEnderChestInventory()->clearAll();
        $player->removeAllEffects();
        if(!$die && $this->arena->status > ArenaStatus::STATUS_STARTING) {
            $this->rejoin[$player->getName()] = [
                "inventory" => $player->getInventory(),
                "armor" => $player->getArmorInventory(),
                "position" => $player->getPosition()
            ];
        }
        $this->arena->scoreboardManager->removePlayer($player);
        if($die) {
            $ev = new GamePlayerDeathEvent(
                $this->arena,
                $player
            );
            $ev->call();
            if(!$ev->isCancelled()) {
                $this->broadcast("message", str_replace(
                    ["{player}", "{players}"],
                    [$player->getName(), count($this->getAlivePlayers())-1],
                    UHCRun::getInstance()->messages["died-message"]
                ));
                $this->addDeadPlayer($player);
                $this->addSpectator($player);
            }
        } else {
            $this->broadcast("message", str_replace(
                ["{player}", "{players}"],
                [$player->getName(), count($this->getAlivePlayers())-1],
                UHCRun::getInstance()->messages["left-message"]
            ));
            if($teleport)$player->teleport(UHCRun::getInstance()->getMainLobby());
        }
        if(in_array($player->getName(), $this->mapNames($this->alivePlayers)))unset($this->alivePlayers[array_search($player->getName(), $this->mapNames($this->alivePlayers))]);
    }

    public function addDeadPlayer(Player $player) {
        if(in_array($player->getName(), $this->mapNames($this->alivePlayers)))$this->removeAlivePlayer($player, true);
        if(!in_array($player->getName(), $this->mapNames($this->deadPlayers)))$this->deadPlayers[] = $player;
    }

    public function removeDeadPlayer(Player $player) {
        if(in_array($player->getName(), $this->mapNames($this->deadPlayers)))unset($this->deadPlayers[array_search($player->getName(), $this->mapNames($this->deadPlayers))]);
    }

    public function addSpectator(Player $player) {
        if(!in_array($player->getName(), $this->mapNames($this->spectators)))$this->removeSpectator($player);
        $this->spectators[] = $player;
        $player->setGamemode(3);
        $leaveitem = new Item(Item::BED);
        $leaveitem->setCustomName(UHCRun::getInstance()->messages["leave-item"]);
        $leaveitem->getNamedTag()->setInt("uhcrunleaveitem", 1);
        $player->getInventory()->setItem(8, $leaveitem);
    }

    public function removeSpectator(Player $player) {
        if(in_array($player->getName(), $this->mapNames($this->spectators)))unset($this->spectators[array_search($player->getName(), $this->mapNames($this->spectators))]);
    }
}