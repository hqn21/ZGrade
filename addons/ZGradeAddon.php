<?php
declare(strict_types = 1);

/**
 * @name ZGradeAddon
 * @version 1.0.0
 * @main JackMD\ScoreHud\Addons\ZGradeAddon
 * @depend ZGrade
 */
namespace JackMD\ScoreHud\Addons
{
	use JackMD\ScoreHud\addon\AddonBase;
	use ZeroK\ZGrade;
	use pocketmine\Player;

	class ZGradeAddon extends AddonBase{

		/** @var ZGrade */
		private $zgrade;

		public function onEnable(): void{
			$this->zgrade = $this->getServer()->getPluginManager()->getPlugin("ZGrade");
		}

		/**
		 * @param Player $player
		 * @return array
		 */
		public function getProcessedTags(Player $player): array{
			return [
				"{level}" => $this->zgrade->getLevel($player->getName()),
                "{exp}" => $this->zgrade->getExp($player->getName()),
                "{maxexp}" => $this->zgrade->getMaxExp($player->getName()),
                "{prefix}" => $this->zgrade->getPrefixByLevel($this->zgrade->getLevel($player->getName()))
			];
		}
	}
}
