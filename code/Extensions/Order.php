<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;

use Milkyway\SS\Assets;
use Milkyway\SS\Director;
use Milkyway\SS\GridFieldUtils\DisplayAsTimeline;

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
        if($log = $this->owner->LogsByStatusPriority()->first())
		    return $log->Status;
        else
            return $this->owner->Status;
	}

	public function updateCMSFields(\FieldList $fields) {
		if(!$this->owner->IsCart())
			$fields->replaceField('Status', \ReadonlyField::create('READONLY-State', 'Status', $this->owner->State)->addExtraClass('important')->setDescription(_t('Order.DESC-State', 'The status of your order is controlled by the order logging system. <a href="{logTab}">You can add a new log here.</a>', ['logTab' => Director::url('#tab-Root_Logs')])));

		Assets::include_font_css();

		$fields->addFieldsToTab('Root.Logs', [
			\GridField::create(
				'OrderStatusLogs',
				'History',
				$this->owner->LogsByStatusPriority(),
				$gfc = \GridFieldConfig_RecordEditor::create(\OrderStatusLog::config()->max_records_per_order)
				->addComponents(
					new DisplayAsTimeline
				)
			)
		]);

		if($this->owner->OrderStatusLogs()->count() <= 50) {
			$gfc->removeComponentsByType('GridFieldPageCount');
			$gfc->removeComponentsByType('GridFieldPaginator');
		}

		if($df = $gfc->getComponentByType('GridFieldDataColumns')) {
			$df->setDisplayFields([
				'Title' => 'Status',
				'Created' => 'Date',
			]);

			$df->setFieldFormatting([
				'Title' => '<strong>$Title</strong> $DetailsForDataGrid',
				'Created' => '<strong>Logged $Created</strong>',
			]);
		}
	}

	public function onStartOrder() {
        // Destroy working logs since the first log should always be the order started log
        foreach($this->owner->OrderStatusLogs() as $log) {
	        if($log->exists()) {
		        $log->delete();
		        $log->destroy();
	        }
        }
		$this->compileChangesAndLog(__FUNCTION__, ['Referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''], true);
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

	public function onRecovered() {
		$this->compileChangesAndLog(__FUNCTION__, ['Referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''], true);
	}

	public function afterAdd($item, $buyable, $quantity, $filter) {
		$this->compileChangesAndLog('addedAnItem', ['OrderItem' => $item, 'Quantity' => $quantity]);
	}

	public function afterRemove($item, $buyable, $quantity, $filter) {
		$this->compileChangesAndLog('removedAnItem', ['OrderItem' => $item, 'Quantity' => 0]);
	}

	public function afterSetQuantity($item, $buyable, $quantity, $filter) {
		$this->compileChangesAndLog('changedItemQuantity', ['OrderItem' => $item, 'Quantity' => $quantity]);
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

		if($this->owner->isChanged('MemberID')) {
			$member = $this->owner->Member();
			$this->owner->extend('onSetMember', $member);
		}

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
				// Here we find the latest items with generic status, so we can filter them out later
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

	public function LogsByStatusPriority() {
		$order = $this->owner;

		return $this->owner->OrderStatusLogs()->alterDataQuery(function($query, $list) use($order) {
			// Only compatible with MySQL at the moment... waiting for new ORM
			$query->sort("FIELD(Status,'Completed','Cancelled','Paid','Processing','Placed','Updated','Started')", null, false);

//			$statuses = ['Completed', 'Cancelled', 'Paid', 'Processing', 'Placed', 'Updated', 'Started'];
//
//			foreach($statuses as $status) {
//				$query->sort('"Status" != \'' . $status . "'", null, false);
//			}
		});
	}
} 