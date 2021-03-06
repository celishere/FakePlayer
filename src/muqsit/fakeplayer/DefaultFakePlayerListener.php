<?php

declare(strict_types=1);

namespace muqsit\fakeplayer;

use muqsit\fakeplayer\listener\FakePlayerListener;
use muqsit\fakeplayer\network\FakePlayerNetworkSession;
use muqsit\fakeplayer\network\listener\ClosureFakePlayerPacketListener;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\ClientboundPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\RespawnPacket;
use pocketmine\network\mcpe\protocol\SetLocalPlayerAsInitializedPacket;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;

final class DefaultFakePlayerListener implements FakePlayerListener{

	/** @var Loader */
	private $plugin;

	public function __construct(Loader $plugin){
		$this->plugin = $plugin;
	}

	public function onPlayerAdd(Player $player) : void{
		$session = $player->getNetworkSession();
		assert($session instanceof FakePlayerNetworkSession);

		$entity_runtime_id = $player->getId();
		$session->registerSpecificPacketListener(PlayStatusPacket::class, new ClosureFakePlayerPacketListener(function(ClientboundPacket $packet, NetworkSession $session) use($entity_runtime_id) : void{
			assert($packet instanceof PlayStatusPacket);
			if($packet->status === PlayStatusPacket::PLAYER_SPAWN){
				$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(static function() use($session, $entity_runtime_id) : void{
					if($session->isConnected()){
						$packet = new SetLocalPlayerAsInitializedPacket();
						$packet->entityRuntimeId = $entity_runtime_id;
						$packet->encode();
						$session->handleDataPacket($packet);
					}
				}), 40);
			}
		}));

		$session->registerSpecificPacketListener(RespawnPacket::class, new ClosureFakePlayerPacketListener(function(ClientboundPacket $packet, NetworkSession $session) : void{
			$this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use($session) : void{
				if($session->isConnected()){
					/** @var Player $player */
					$player = $session->getPlayer();
					$player->respawn();
					foreach($this->plugin->getFakePlayer($player)->getBehaviours() as $behaviour){
						$behaviour->onRespawn($player);
					}
				}
			}), 40);
		}));
	}

	public function onPlayerRemove(Player $player) : void{
		// not necessary to unregister listeners because they'll automatically
		// be gc-d as nothing holds ref to player object?
	}
}