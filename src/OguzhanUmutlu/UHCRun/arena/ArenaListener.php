<?php

namespace OguzhanUmutlu\UHCRun\arena;

use OguzhanUmutlu\UHCRun\UHCRun;
use OguzhanUmutlu\UHCRun\utils\PlayerStatus;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\Sword;
use pocketmine\item\Tool;
use pocketmine\network\mcpe\protocol\SetHealthPacket;
use pocketmine\Player;

class ArenaListener implements Listener {
    public $arena;
    public function __construct(Arena $arena) {
        $this->arena = $arena;
    }

    public function LevelChangeEvent(EntityLevelChangeEvent $e) {
        $player = $e->getEntity();
        if(!$player instanceof Player) return;
        if($this->arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_OFFLINE) return;
        if($e->getTarget()->getFolderName() != $this->arena->levelName && $e->getTarget()->getFolderName() != UHCRun::getInstance()->getWaitLobby()->getLevel()->getFolderName()) {
            $this->arena->scoreboardManager->removePlayer($player);
            $this->arena->getPlayerManager()->removeAlivePlayer($player, false, false);
            $this->arena->getPlayerManager()->removeSpectator($player);
            $this->arena->getPlayerManager()->removeDeadPlayer($player);
        }
    }

    public function OnInteract(PlayerInteractEvent $e) {
        $player = $e->getPlayer();
        $item = $e->getItem();
        if($this->arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_OFFLINE) return;
        if($item->getNamedTag()->hasTag("uhcrunleaveitem")) {
            $player->getInventory()->clearAll();
            $this->arena->getPlayerManager()->removeAlivePlayer($player);
            $this->arena->getPlayerManager()->removeDeadPlayer($player);
            $this->arena->getPlayerManager()->removeSpectator($player);
            $player->setGamemode(0);
            $player->teleport(UHCRun::getInstance()->getMainLobby());
            $this->arena->scoreboardManager->removePlayer($player);
        }
    }

    public function OnDeath(PlayerDeathEvent $e) {
        $player = $e->getPlayer();
        if($this->arena->getPlayerManager()->getPlayerState($player) != PlayerStatus::PLAYER_ALIVE) return;
        $this->arena->getPlayerManager()->removeAlivePlayer($player, true);
    }

    public function OnQuit(PlayerQuitEvent $e) {
        $player = $e->getPlayer();
        if($this->arena->getPlayerManager()->getPlayerState($player) != PlayerStatus::PLAYER_ALIVE) return;
        $this->arena->scoreboardManager->removePlayer($player);
        $this->arena->getPlayerManager()->removeAlivePlayer($player);
        $this->arena->getPlayerManager()->removeDeadPlayer($player);
        $this->arena->getPlayerManager()->removeSpectator($player);
    }

    public function OnDamage(EntityDamageEvent $e) {
        $player = $e->getEntity();
        if(!$player instanceof Player) return;
        if($this->arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_OFFLINE) return;
        if($this->arena->getFlag("invincibility")) {
            $e->setCancelled(true);
        } else if(!$this->arena->getFlag("pvp") && $e instanceof EntityDamageByEntityEvent && $e->getDamager() instanceof Player) {
            $e->setCancelled(true);
        }
        if(!$e->isCancelled() && $player->getGamemode() == 0) {
            if($player->getHealth()-$e->getBaseDamage() < 0.1) {
                $e->setCancelled(true);
                $this->arena->listener->OnDeath(new PlayerDeathEvent($player, []));
            }
        }
    }

    public function OnExhaust(PlayerExhaustEvent $e) {
        $player = $e->getPlayer();
        if(!$player instanceof Player) return;
        if($this->arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_OFFLINE) return;
        if($this->arena->getFlag("invincibility")) {
            $player->setFood($player->getMaxFood());
            $player->setSaturation(20);
            $player->setHealth($player->getMaxHealth());
            $e->setCancelled(true);
        }
    }

    public function OnHeldItem(PlayerItemHeldEvent $e) {
        $player = $e->getPlayer();
        if($this->arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_OFFLINE) return;
        if(!UHCRun::getInstance()->getConfig()->getNested("auto-efficiency5", true)) return;
        $contents = $player->getInventory()->getContents();
        foreach($contents as $i => $item) {
            if($item instanceof Tool && !$item instanceof Sword && !in_array(Enchantment::EFFICIENCY, array_map(function($n){return $n->getId();},$item->getEnchantments()))) {
                $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::EFFICIENCY), 5));
                $contents[$i] = $item;
                $e->setCancelled(true);
                $player->getInventory()->setContents($contents);
            }
        }
    }

    public function OnBreak(BlockBreakEvent $e) {
        $player = $e->getPlayer();
        if(!$player instanceof Player) return;
        if($this->arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_OFFLINE) return;
        if(!$this->arena->getFlag("break")) $e->setCancelled(true);
        $silktouch = false;
        foreach($e->getItem()->getEnchantments() as $enchantment) {
            if($enchantment->getId() == Enchantment::SILK_TOUCH) $silktouch = true;
        }
        if(UHCRun::getInstance()->getConfig()->getNested("ore-to-ingot") && !$silktouch) {
            $drops = $e->getDrops();
            foreach($drops as $index => $drop) {
                if($drop->getId() == Item::IRON_ORE) $drops[$index] = Item::get(Item::IRON_INGOT, 0, $drop->getCount());
                if($drop->getId() == Item::GOLD_ORE) $drops[$index] = Item::get(Item::GOLD_INGOT, 0, $drop->getCount());
            }
            $e->setDrops($drops);
        }
        if(!$e->isCancelled() && UHCRun::getInstance()->getConfig()->getNested("tree-capitator", true)) {
            $block = $e->getBlock();
            if($block->getId() == Item::LOG || $block->getId() == Item::LOG2) {
                for($i=-5;$i<10;$i++) {
                    $bl = $block->getLevel()->getBlock($block->add(0, $i));
                    if(($bl->getId() == Item::LOG || $bl->getId() == Item::LOG2) && $i != 0) {
                        $item = $player->getInventory()->getItemInHand();
                        $bl->getLevel()->useBreakOn($bl, $item);
                    }
                }
            }
        }
    }

    public function OnPlace(BlockPlaceEvent $e) {
        $player = $e->getPlayer();
        if(!$player instanceof Player) return;
        if($this->arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_OFFLINE) return;
        if(!$this->arena->getFlag("break")) $e->setCancelled(true);
    }

    public function OnRegen(EntityRegainHealthEvent $e) {
        $player = $e->getEntity();
        if(!$player instanceof Player) return;
        if($this->arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_OFFLINE) return;
        $player->setHealth($player->getHealth()-$e->getAmount());
    }

    public function OnMove(PlayerMoveEvent $e) {
        $player = $e->getPlayer();
        if($this->arena->getPlayerManager()->getPlayerState($player) == PlayerStatus::PLAYER_OFFLINE) return;
        if($this->arena->status < ArenaStatus::STATUS_STARTED && $player->getY() < 0) {
            $player->teleport(UHCRun::getInstance()->getWaitLobby());
        }
    }
}