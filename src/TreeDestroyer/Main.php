<?php

declare(strict_types=1);

namespace TreeDestroyer;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\block\Wood;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\Axe;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {

    private const TAG = "treedestroyer";

    public function onEnable() : void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
        if($command->getName() === "treedestroyer"){

            if(!$sender instanceof Player){
                $sender->sendMessage("Use this in-game.");
                return true;
            }

            $item = $sender->getInventory()->getItemInHand();

            if(!$item instanceof Axe){
                $sender->sendMessage(TextFormat::RED . "You must be holding an axe.");
                return true;
            }

            $nbt = $item->getNamedTag();
            $nbt->setByte(self::TAG, 1);
            $item->setNamedTag($nbt);

            $item->setCustomName(TextFormat::GREEN . "TreeDestroyer Axe");
            $sender->getInventory()->setItemInHand($item);

            $sender->sendMessage(TextFormat::GREEN . "TreeDestroyer enchant applied!");
            return true;
        }

        return false;
    }

    public function onBreak(BlockBreakEvent $event) : void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if(!$item instanceof Axe){
            return;
        }

        if(!$item->getNamedTag()->getTag(self::TAG)){
            return;
        }

        $block = $event->getBlock();

        if(!$block instanceof Wood){
            return;
        }

        $this->breakConnectedLogs($block->getPosition(), $block->getPosition()->getWorld());
    }

    /**
     * Recursively breaks connected wood blocks (trees)
     * Fixed Vector3 vs Position issue for PMMP 5
     */
    private function breakConnectedLogs(Vector3 $vec, World $world) : void {
        // Ensure $pos is a Position object
        if(!$vec instanceof Position){
            $pos = new Position($vec->x, $vec->y, $vec->z, $world);
        } else {
            $pos = $vec;
        }

        $block = $world->getBlock($pos);

        if(!$block instanceof Wood){
            return;
        }

        $world->setBlock($pos, VanillaBlocks::AIR());

        // Recursively check neighboring blocks
        foreach([
            $pos->add(1,0,0),
            $pos->add(-1,0,0),
            $pos->add(0,1,0),
            $pos->add(0,-1,0),
            $pos->add(0,0,1),
            $pos->add(0,0,-1),
        ] as $next){
            $this->breakConnectedLogs($next, $world);
        }
    }
}
