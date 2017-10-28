<?php
namespace Ad5001\Spooky;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\item\enchantment\Enchantment;

use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\NBT;


use Ad5001\Spooky\entity\Ghost;
use Ad5001\Spooky\tasks\TickTask;


class Main extends PluginBase{

    public $ghosts = [];

    /**
     * When the plugin enables
     *
     * @return void
     */
    public function onEnable(){
        // Registering some enchants
        Enchantement::registerEnchantment(new Enchantement(Enchantement::SHARPNESS, "%enchantment.attack.sharpness", Enchantement::RARITY_COMMON, Enchantement::SLOT_SWORD));
        // $this->getServer()->getScheduler()->scheduler<Delayed or Repeating>Task(new Task1($this), <TIME>);
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