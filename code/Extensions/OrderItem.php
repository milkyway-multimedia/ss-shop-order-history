<?php
/**
 * Milkyway Multimedia
 * OrderItem.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

namespace Milkyway\SS\Shop\OrderHistory\Extensions;


class OrderItem extends \DataExtension {
	private static $db = [
		'Placed_BuyableHash' => 'Text',
	];

	function onPlacement() {
		if($buyable = $this->owner->Buyable()) {
			$this->owner->Placed_BuyableHash = serialize($buyable);
		}
	}

	public function getPlaced_Buyable() {
		return unserialize($this->owner->Placed_BuyableHash);
	}
} 