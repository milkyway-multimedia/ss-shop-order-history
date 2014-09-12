<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;
/**
 * Milkyway Multimedia
 * Order.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

class Order extends \DataExtension {
	protected $workingLogs = [];

	public function getState() {
		return $this->owner->OrderStatusLogs()->sort('Created', 'DESC')->first()->Status;
	}

	public function updateCMSFields(\FieldList $fields) {
		if(!$this->owner->IsCart())
			$fields->replaceField('Status', \ReadonlyField::create('READONLY-State', 'State', $this->owner->State)->addExtraClass('important'));

		$fields->addFieldsToTab('Root.Logs', [
			\GridField::create(
				'OrderStatusLogs',
				'History',
				$this->owner->OrderStatusLogs(),
				\GridFieldConfig_RecordViewer::create()
			)
		]);
	}

	public function onStartOrder() {
		$this->compileChangesAndLog(__FUNCTION__, [], true);
	}

	public function onPlaceOrder() {
		$this->compileChangesAndLog(__FUNCTION__, [], true);
	}

	public function onPayment() {
		$this->compileChangesAndLog(__FUNCTION__, [], true);
	}

	public function onPaid() {
		$this->compileChangesAndLog(__FUNCTION__, [], true);
	}

	public function onStatusChange() {
		$this->compileChangesAndLog(__FUNCTION__, [], true);
	}

	public function onCancelled() {
		$this->compileChangesAndLog(__FUNCTION__, [], true);
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
		if($this->owner->isChanged('Status')) {
			if(in_array($this->owner->Status, ['MemberCancelled', 'AdminCancelled']))
				$this->owner->extend('onCancelled');
			else
				$this->owner->extend('onStatusChange');
		}

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

	protected function compileChangesAndLog($event, $additionalObjects = [], $force = false) {
		if(!$this->owner->ID) return;

		$log = \OrderStatusLog::create();

		// Always record a status change
		if($this->owner->isChanged('Status') || !in_array($event, (array)$log->config()->ignored_events)) {
			$max = $log->config()->max_records_per_order;
			$count = $this->owner->OrderStatusLogs()->filter('Status', OrderStatusLog::GENERIC_STATUS)->count();

			// Clean if its over the max limit of history allowed
			if($max && $count >= $max) {
				$items = $this->owner->OrderStatusLogs()->filter('Status', OrderStatusLog::GENERIC_STATUS)->sort('Created DESC')->limit(50)->column('ID');
				$this->owner->OrderStatusLogs()->filter(['Status' => OrderStatusLog::GENERIC_STATUS, 'ID:not' => $items])->removeAll();
			}

			$log->OrderID = $this->owner->ID;

			$changes = $this->owner->getChangedFields(false, 2);

			foreach($additionalObjects as $key => $object) {
				if(($object instanceof \DataObject) && $object->isChanged())
					$changes[$key] = $object->getChangedFields(false, 2);
				elseif($object !== null)
					$changes[$key] = $object;
			}

			if($force || !empty($changes)) {
				$log->log($event, ['ChangeLog' => $changes]);

				if($log->ID)
					$this->workingLogs[] = $log;
			}
			else
				$log->destroy();
		}
	}
} 