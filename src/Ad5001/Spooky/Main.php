<?php
namespace Ad5001\Spooky;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\Player;
use pocketmine\entity\Entity;
use pocketmine\event\Listener;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\block\Block;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\utils\Utils;
use pocketmine\utils\TextFormat;

use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ShortTag;
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
        Entity::registerEntity(Ghost::class, true, ['Ghost', 'minecraft:ghost']);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new TickTask($this), 2);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        // Resource pack
        $downRP = false;
        if(!file_exists($this->getDataFolder() . "Spooky.mcpack")) {
            $downRP = true;
            echo TextFormat::toANSI("§f[Spooky] ⚪ Downloading resource pack...");
            file_put_contents($this->getDataFolder() . "Spooky.mcpack", Utils::getURL("https://download.ad5001.eu/other/Spooky/Spooky.mcpack"));
        }
        echo str_repeat("\010", $downRP ? strlen(TextFormat::toANSI("§f[Spooky] ⚪ Downloading resource pack...")) : 0) . TextFormat::toANSI("§f[Spooky] ⚪ Applying resource pack...   "); // Replacing latest message
        $pack = new ZippedResourcePack($this->getDataFolder() . "Spooky.mcpack");
        $r = new \ReflectionClass("pocketmine\\resourcepacks\\ResourcePackManager");
        if($pack instanceof \pocketmine\resourcepacks\ResourcePack){
            // Reflection because devs thought it was a great idea to not let plugins manage resource packs :/
            $resourcePacks = $r->getProperty("resourcePacks");
            $resourcePacks->setAccessible(true);
            $rps = $resourcePacks->getValue($this->getServer()->getResourceManager());
            $rps[] = $pack;
            $resourcePacks->setValue($this->getServer()->getResourceManager(), $rps);
            $resourceUuids = $r->getProperty("uuidList");
            $resourceUuids->setAccessible(true);
            $uuids = $resourceUuids->getValue($this->getServer()->getResourceManager());
            $uuids[$pack->getPackId()] = $pack;
            $resourceUuids->setValue($this->getServer()->getResourceManager(), $uuids);
            // Forcing resource packs. We want the client to hear the music!
            $forceResources = $r->getProperty("serverForceResources");
            $forceResources->setAccessible(true);
            $forceResources->setValue($this->getServer()->getResourceManager(), true);
        }
        echo str_repeat("\010", strlen("⚪ Applying resource pack... ")) . TextFormat::toANSI("§a✔️ Done! Spooky enabled!    \n");
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
                $under2 = $under->asVector3();
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
            if($event->getPlayer() !== null){
                $this->spawnGhost($event->getPlayer());
                // Spawning an another ghost for the surround players. It's more challenging :p
                foreach($this->getServer()->getOnlinePlayers() as $p) {
                    if($p->getLevel()->getName() == $event->getPlayer()->getLevel()->getName() && $p->getName() !== $event->getPlayer()->getName()) {
                        if($p->distance($event->getPlayer()) <= 10) {
                            $this->spawnGhost($p);
                        }
                    }
                }
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
        $nbtSkin->readCompressed(file_get_contents($this->getFile() . "resources/ghost_player_data.dat"));
		$nbt = new CompoundTag();
		$nbt->Pos = new ListTag("Pos", [
			new DoubleTag("", $p->getX()),
			new DoubleTag("", $p->getY()),
			new DoubleTag("", $p->getZ())
		]);
		$nbt->Motion = new ListTag("Motion", [
			new DoubleTag("", 0),
			new DoubleTag("", 0),
			new DoubleTag("", 0)
		]);
		$nbt->Rotation = new ListTag("Rotation", [
			new FloatTag("", $p->getYaw()),
			new FloatTag("", $p->getPitch())
        ]);
        // var_dump($nbtSkin);
		$nbt->Health = new ShortTag("Health", 20);
        $nbt->Skin = clone $nbtSkin->getData()->Skin;
        $nbt->Inventory = clone $nbtSkin->getData()->Inventory;
        $g = Entity::createEntity("Ghost", $p->getLevel(), $nbt);
        $g->startFight($p);
        $this->ghosts[$p->getName()] = $g;
    }



    public function onEntityDamage(\pocketmine\event\entity\EntityDamageEvent $event){
        if($event instanceof \pocketmine\event\entity\EntityDamageByEntityEvent && $event->getDamager() instanceof Player){
            if(isset($event->getDamager()->getInventory()->getItemInHand()->getNamedTag()->customDamage)) {
                $event->setDamage($event->getDamager()->getInventory()->getItemInHand()->namedtag->customDamage->getValue());
            }
            if(isset($event->getDamager()->getInventory()->getItemInHand()->getNamedTag()->sneakInvisible) && $event->getEntity() instanceof Player) {
		        $pk = new PlaySoundPacket();
                $pk->soundName = "mob.wither.death";
                $pk->x = (int)$event->getEntity()->x;
                $pk->y = (int)$event->getEntity()->y;
                $pk->z = (int)$event->getEntity()->z;
                $pk->volume = 3;
                $pk->pitch = 1;
                $event->getEntity()->dataPacket($pk);
            }
        }
    }
}