<?php

declare(strict_types=1);

namespace TreeDestroyer;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\block\Wood;
use pocketmine\block\Leaves;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\Axe;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener {

    private const TAG = "treedestroyer";

    /** @var array<string,int> Player cooldowns */
    private array $cooldowns = [];

    /** @var int Command cooldown in seconds */
    private int $cooldownTime = 10;

    /** @var array Messages from config */
    private array $messages = [];

    /** @var string Axe name from config */
    private string $axeName = "";

    /** @var array Axe lore from config */
    private array $axeLore = [];

    public function onEnable() : void {
        $this->saveDefaultConfig();
        $cfg = $this->getConfig();

        $this->cooldownTime = (int) $cfg->get("cooldown", 10);
        $this->messages = $cfg->get("messages", []);
        $this->axeName = TextFormat::colorize($cfg->getNested("axe.name", "&bTreeDestroyer Axe"));
        $this->axeLore = array_map(fn($l) => TextFormat::colorize($l), $cfg->getNested("axe.lore", []));

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
        if($command->getName() !== "treedestroyer") return false;

        if(!$sender instanceof Player){
            $sender->sendMessage("Use this in-game.");
            return true;
        }

        $playerName = $sender->getName();
        $currentTime = time();

        // Cooldown check
        if(isset($this->cooldowns[$playerName]) && $currentTime < $this->cooldowns[$playerName]){
            $remaining = $this->cooldowns[$playerName] - $currentTime;
            $sender->sendMessage(str_replace("{time}", (string)$remaining, $this->messages['cooldown'] ?? "&cWait {time} seconds."));
            return true;
        }

        $item = $sender->getInventory()->getItemInHand();
        if(!$item instanceof Axe){
            $sender->sendMessage($this->messages['hold_axe'] ?? "&cYou must be holding an axe.");
            return true;
        }

        $nbt = $item->getNamedTag();
        $nbt->setByte(self::TAG, 1);
        $item->setNamedTag($nbt);

        // Set custom name & lore
        $item->setCustomName($this->axeName);
        $item->setLore($this->axeLore);

        // Make it glow
        $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING(), 1));

        $sender->getInventory()->setItemInHand($item);

        // Apply cooldown
        $this->cooldowns[$playerName] = $currentTime + $this->cooldownTime;

        $sender->sendMessage($this->messages['enchant_applied'] ?? "&aTreeDestroyer enchant applied!");

        return true;
    }

    public function onBreak(BlockBreakEvent $event) : void {
        $player = $event->getPlayer();
        $item = $player->getInventory()->getItemInHand();

        if(!$item instanceof Axe) return;
        if(!$item->getNamedTag()->getTag(self::TAG)) return;

        $block = $event->getBlock();
        if(!$block instanceof Wood) return;

        $this->breakTree($block->getPosition(), $block->getPosition()->getWorld(), $item);
        $event->cancel();
    }

    private function breakTree(Vector3 $vec, World $world, Axe $tool) : void {
        $pos = $vec instanceof Position ? $vec : new Position($vec->x, $vec->y, $vec->z, $world);
        $block = $world->getBlock($pos);

        if($block instanceof Wood || $block instanceof Leaves){
            foreach($block->getDrops($tool) as $drop){
                $world->dropItem($pos, $drop);
            }

            $world->setBlock($pos, VanillaBlocks::AIR());

            foreach([
                $pos->add(1,0,0), $pos->add(-1,0,0),
                $pos->add(0,1,0), $pos->add(0,-1,0),
                $pos->add(0,0,1), $pos->add(0,0,-1),
                $pos->add(1,1,0), $pos->add(-1,1,0),
                $pos->add(0,1,1), $pos->add(0,1,-1),
                $pos->add(1,1,1), $pos->add(-1,1,-1),
                $pos->add(1,1,-1), $pos->add(-1,1,1),
            ] as $next){
                $this->breakTree($next, $world, $tool);
            }
        }
    }
}
