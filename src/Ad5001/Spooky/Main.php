<?php
namespace Ad5001\Spooky;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\event\Listener;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\block\Block;

use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\NBT;


use Ad5001\Spooky\entity\Ghost;
use Ad5001\Spooky\tasks\TickTask;


class Main extends PluginBase implements Listener{

    public $ghosts = [];

    /**
     * When the plugin enables
     *
     * @return void
     */
    public function onEnable(){
        // Registering some enchants
        Enchantment::registerEnchantment(new Enchantment(Enchantment::SHARPNESS, "%enchantment.attack.sharpness", Enchantment::ACTIVATION_HELD, Enchantment::RARITY_COMMON, Enchantment::SLOT_SWORD));
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this), 2);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
     * WHen a command is executed
     *
     * @param CommandSender $sender
     * @param Command $cmd
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
        switch($cmd->getName()){
            case "default":
            break;
        }
     return false;
    }


    public function onBlockPlace(BlockPlaceEvent $event) {
        // Checking the pumpkin at the top
        $found = false;
        if($event->getBlock()->getId() == Block::PUMPKIN){
            $under = $event->getBlock()->asVector3();
            $under->y--;
            // Hay bale for the body
            if($event->getBlock()->getLevel()->getBlock($under)->getId() == Block::HAY_BALE) {
                $under2 = $event->getBlock()->asVector3();
                $under2->y--;
                // Fence for the bottom
                if($event->getBlock()->getLevel()->getBlock($under2)->getId() == Block::FENCE){
                    // Fences for the sides.
                    $side1 = $under->asVector3();
                    $side1->x++;
                    $side2 = $under->asVector3();
                    $side2->x--;
                    if($event->getBlock()->getLevel()->getBlock($side1)->getId() == Block::FENCE && $event->getBlock()->getLevel()->getBlock($side1)->getId() == Block::FENCE) {
                        $found = true;
                    } else {
                        $side1 = $under->asVector3();
                        $side1->z++;
                        $side2 = $under->asVector3();
                        $side2->z--;
                        if($event->getBlock()->getLevel()->getBlock($side1)->getId() == Block::FENCE && $event->getBlock()->getLevel()->getBlock($side1)->getId() == Block::FENCE) {
                            $found = true;
                        }
                    }
                }
            }
        }
        // If everything's right, we can destroy the structure & generate the ghost
        if($found){
            $event->setCancelled();
            $event->getBlock()->getLevel()->setBlock($under, Block::get(Block::AIR));
            $event->getBlock()->getLevel()->setBlock($under2, Block::get(Block::AIR));
            $event->getBlock()->getLevel()->setBlock($side1, Block::get(Block::AIR));
            $event->getBlock()->getLevel()->setBlock($side2, Block::get(Block::AIR));
            if($event->getPlayer()){
                $this->spawnGhost($event->getPlayer());
            }
        }
    }


    /**
     * Spawns a ghost
     *
     * @param Player $p
     * @return void
     */
    public function spawnGhost(Player $p){
        // Getting the skin
        $nbtSkin = new NBT(NBT::BIG_ENDIAN);
        $nbtSkin->readCompressed(fread($this->getResource("ghost_player_data.dat")));
		$nbt = new CompoundTag();
		$nbt->Pos = new ListTag("Pos", [
			new DoubleTag("", $player->getX()),
			new DoubleTag("", $player->getY()),
			new DoubleTag("", $player->getZ())
		]);
		$nbt->Motion = new ListTag("Motion", [
			new DoubleTag("", 0),
			new DoubleTag("", 0),
			new DoubleTag("", 0)
		]);
		$nbt->Rotation = new ListTag("Rotation", [
			new FloatTag("", $player->getYaw()),
			new FloatTag("", $player->getPitch())
		]);
		$nbt->Health = new ShortTag("Health", 20);
        $player->saveNBT();
        $nbt->Skin = clone $nbtSkin->Skin;
        $nbt->Inventory = clone $nbtSkin->Inventory;
        $g = new Ghost($p->getLevel(), $nbt);
        $g->startFight($p);
        $this->ghosts[$p->getName()] = $g;
    }
}