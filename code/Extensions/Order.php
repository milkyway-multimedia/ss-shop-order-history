<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;
/**
 * Milkyway Multimedia
 * Order.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class Order extends \DataExtension {
	protected $workingLogs = array();

	public function onStartOrder() {
		$this->compileChangesAndLog(__FUNCTION__);
	}

	public function onPlaceOrder() {
		$this->compileChangesAndLog(__FUNCTION__);
	}

	public function onPayment() {
		$this->compileChangesAndLog(__FUNCTION__);
	}

	public function onPaid() {
		$this->compileChangesAndLog(__FUNCTION__);
	}

	public function afterAdd($item, $buyable, $quantity, $filter) {
		$this->compileChangesAndLog('addedAnItem', ['OrderItem' => $item->toMap(), 'Quantity' => $quantity]);
	}

	public function afterRemove($item, $buyable, $quantity, $filter) {
		$this->compileChangesAndLog('removedAnItem', ['OrderItem' => $item->toMap(), 'Quantity' => $quantity]);
	}

	public function afterSetQuantity($item, $buyable, $quantity, $filter) {
		$this->compileChangesAndLog('changedItemQuantity', ['OrderItem' => $item->toMap(), 'Quantity' => $quantity]);
	}

	public function onSetShippingAddress($address) {
		$this->compileChangesAndLog('changedShippingAddress', ['ShippingAddress' => $address]);
	}

	public function onUpdateShippingAddress($address) {
		$this->compileChangesAndLog('changedShippingAddress', ['ShippingAddress' => $address]);
	}

	public function onSetBillingAddress($address) {
		$this->compileChangesAndLog('changedBillingAddress', ['BillingAddress' => $address]);
	}

	public function onUpdateBillingAddress($address) {
		$this->compileChangesAndLog('changedBillingAddress', ['BillingAddress' => $address]);
	}

	public function onSetMember($member) {
		$this->compileChangesAndLog('changedMember', ['Member' => $member]);
	}

	public function onUpdateMember($member) {
		$this->compileChangesAndLog('changedMember', ['Member' => $member]);
	}

	public function onAfterWrite() {
		if($this->owner->isChanged('MemberID'))
			$this->owner->extend('onSetMember', $this->owner->Member());

		$hasOnes = array_merge($this->owner->has_one(), $this->owner->belongs_to());
		$changedRelations = [];

		foreach($hasOnes as $relation => $class) {
			if($this->owner->getComponent($relation)->isChanged()) {
				$changedRelations[$relation] = $this->owner->getComponent($relation);
			}
		}

		if(count($changedRelations) > 2)
			$this->owner->extend('onUpdateComponents', $changedRelations);
		elseif(!empty($changeRelations))
			$this->owner->extend('onUpdate'.key($changedRelations), reset($changedRelations));
	}

	protected function compileChangesAndLog($event, $additionalObjects = []) {
		if(!$this->owner->ID) return;

		$log = \OrderStatusLog::create();

		// Always record a status change
		if($this->owner->isChanged('Status') || ($this->owner->OrderStatusLogs()->count() <= $log->config()->max_records_per_order) && !in_array($event, $log->config()->ignored_events)) {
			$log->OrderID = $this->owner->ID;

			$changes = $this->owner->getChangedFields(false, 2);

			foreach($additionalObjects as $key => $object) {
				if(($object instanceof \DataObject) && $object->isChanged())
					$changes[$key] = $object->getChangedFields(false, 2);
				elseif($object !== null)
					$changes[$key] = $object;
			}

			$log->log($event, ['ChangeLog' => $changes]);

			if($log->ID)
				$this->workingLogs[] = $log;
			else
				$log->destroy();
		}
	}
} 