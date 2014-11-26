<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;

/**
 * Milkyway Multimedia
 * OrderStatusLog.php
 *
 * @package reggardocolaianni.com
 * @author  Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */


class OrderStatusLog extends \DataExtension
{
	private static $singular_name = 'Status Entry';

	private static $db = [
		'Status'       => 'Varchar',

		// This is for dispatch
		'DispatchUri'  => 'Text',

		// This is for public viewing of a status log
		'Public'       => 'Boolean',
		'Unread'       => 'Boolean',
		'FirstRead'    => 'Datetime',

		// This is for emails/notifications to customer
		'Send'         => 'Boolean',
		'Sent'         => 'Datetime',
		'Send_To'      => 'Varchar(256)',
		'Send_From'    => 'Varchar(256)',
		'Send_Subject' => 'Varchar(256)',
		'Send_Body'    => 'HTMLText',
		'Send_HideOrder' => 'Boolean',

		// The versioned table is too messy with relations... Store changes on here instead
		'Changes'      => 'Text',
	];

	private static $default_sort = 'Created DESC';

	private static $defaults = [
		'Unread'       => true,
	];

	/**
	 * This is the map
	 * @var array
	 */
	private static $status_mapping_for_events = [
		'onStartOrder' => 'Started',
		'onPlaceOrder' => 'Placed',
		'onPayment'    => 'Processing',
		'onPaid'       => 'Paid',
		'onCancelled'  => 'Cancelled',
		'onRecovered'  => 'Restarted',
	];

	private static $status_list = [
		'Notified'   => [
			'title' => 'Notified',
			'icon' => '<i class="fa fa-comment order-statusIcon--notified"></i>',
		],
		'Shipped'    => [
			'title' => 'Shipped',
			'icon' => '<i class="fa fa-send order-statusIcon--shipped"></i>',
		],
		'Completed'  => [
			'title' => 'Completed',
			'icon' => '<i class="fa fa-star order-statusIcon--completed"></i>',
		],
		'Archived'  => [
			'title' => 'Archived',
			'icon' => '<i class="fa fa-archive order-statusIcon--archived"></i>',
		],
		'Cancelled'  => [
			'title' => 'Cancelled',
			'icon' => '<i class="fa fa-remove order-statusIcon--cancelled"></i>',
		],
		'Query'      => [
			'title' => 'Query',
			'icon' => '<i class="fa fa-question order-statusIcon--query"></i>',
		],
		'Refunded'   => [
			'title' => 'Refunded',
			'icon' => '<i class="fa fa-undo order-statusIcon--refunded"></i>',
		],
		'Paid'       => [
			'title' => 'Paid',
			'icon' => '<i class="fa fa-money order-statusIcon--paid"></i>',
		],
		'Placed'     => [
			'title' => 'Placed',
			'icon' => '<i class="fa fa-check order-statusIcon--placed"></i>',
		],
		'Processing' => [
			'title' => 'Processing',
			'icon' => '<i class="fa fa-refresh order-statusIcon--processing"></i>',
		],
		'Started'    => [
			'title' => 'Started',
			'icon' => '<i class="fa fa-star-o order-statusIcon--started"></i>',
		],
	];

	private static $ignore_status_as_state = [
		'Notified',
		'Updated',
	];

	private static $disallowed_multiple_statuses = [
		'Shipped',
		'Completed',
		'Cancelled',
		'Placed',
		'Started',
	];

	private static $ignore_events = [];

	private static $shipping_providers = [
		'Australia Post' => 'http://auspost.com.au/track/track.html',
		'TNT Express' => 'http://www.tntexpress.com.au/interaction/asps/trackdtl_tntau.asp',
	];

	/** @var int You can end up with a lot of logs if you are not careful.
	 * This only applies to automatic logs (such as record updates), logs entered manually in the CMS will always be
	 * saved */
	private static $max_records_per_order = 50;

	// This is the generic status of an order, and is reserved for automatic updates
	const GENERIC_STATUS = 'Updated';

	// These are reserved statuses that change the mode of the Order, hence cannot be added by the user
	const RESERVED_STATUS = 'Started,Completed,Cancelled';

	const SHIPPED_STATUS = 'Shipped';

	const ARCHIVED_STATUS = 'Archived';

	public function updateCMSFields(\FieldList $fields)
	{
		$fields->removeByName('Status');
		$fields->removeByName('AuthorID');
		$fields->removeByName('Changes');
		$fields->removeByName('FirstRead');

		// Disables the default firing of sent to customer flag
		$fields->removeByName('SentToCustomer');

		// Status Field
		$allSuggestedStatuses = (array)$this->owner->config()->status_list;
		$statusesWithIcons = $otherStatuses = [];

		foreach($allSuggestedStatuses as $status => $options) {
			if(is_array($options) && isset($options['icon']))
				$statusesWithIcons[$status] = isset($options['title']) ? $options['icon'] . ' ' . $options['title'] : $options['icon'] . ' ' . $status;
			else {
				if(is_array($options) && isset($options['title']))
					$otherStatuses[$status] = $options['title'];
				else
					$otherStatuses[$status] = $options;
			}
		}

		$otherStatuses = array_merge($otherStatuses, \OrderStatusLog::get()->filter('Status:not', array_merge(array_keys($allSuggestedStatuses), explode(',', self::RESERVED_STATUS)))->sort('Status', 'ASC')->map('Status', 'Status')->toArray());

		$statuses = [
			'Suggested status (these use an icon and/or special formatting)' => $statusesWithIcons,
		];

		if (count($otherStatuses)) {
			asort($otherStatuses);
			$statuses['Other Status'] = $otherStatuses;
		}

		$fields->insertBefore($statusField = \Select2Field::create('Status', 'Status', '', $statuses)
			->setMinimumSearchLength(0)
			->setEmptyString('You can select from suggested statuses, or create a new status')
			->setAttribute('data-format-searching', _t('OrderStatusLog.SEARCHING-Status', 'Loading statuses...'))
			->setDescription(_t('OrderStatusLog.DESC-Status', 'Note: {updated} is a special status. If there are more than {limit} logs for an order, it will automatically delete statuses classed as {updated}, so use with caution.', ['updated' => static::GENERIC_STATUS, 'limit' => $this->owner->config()->max_records_per_order])), 'Title');

		$statusField->allowHTML = true;
		$statusField->limit = count($statusesWithIcons) + count($otherStatuses);

		$dataFields = $fields->dataFields();

		if(isset($dataFields['Title']))
			$dataFields['Title']->setDescription(_t('OrderStatusLog.DESC-Title', 'If not set, will automatically use the Status above'));

		if(isset($dataFields['Note']) && $dataFields['Note'] instanceof \TextareaField)
			$dataFields['Note']->setRows(2);

		$fieldSet = [];

		foreach(['Public', 'Unread'] as $field) {
			if(isset($dataFields[$field])) {
				$fieldSet[$field] = $dataFields[$field];
				$fields->removeByName($field);
			}
		}

		if(count($fieldSet)) {
			if(isset($fieldSet['Public']))
				$fieldSet['Public']->setTitle($fieldSet['Public']->Title() . ' (' . _t('OrderStatusLog.DESC-Public', 'If checked, user can view this log on the front-end when checking the status of their orders') . ')');

			if($this->owner->FirstRead) {
				$fieldSet['FirstRead'] = \DatetimeField::create('FirstRead');
			}

			$fields->insertAfter(\FieldGroup::create($fieldSet)->setTitle('Public Visibility')->setName('PublicFields')->addExtraClass('hero-unit stacked-items'), 'Note');
			$fieldSet = [];
		}

		foreach(['DispatchTicket', 'DispatchedBy', 'DispatchedOn'] as $field) {
			if(isset($dataFields[$field])) {
				$fieldSet[$field] = $dataFields[$field];
				$fields->removeByName($field);

				if($fieldSet[$field] instanceof \DateField)
					$fieldSet[$field]->setConfig('showcalendar', true);

				if($field == 'DispatchTicket')
					$fieldSet[$field]->setTitle(_t('OrderStatusLog.TRACKING_ID', 'Tracking ID'));
				elseif($field == 'DispatchedBy')
					$fieldSet[$field]->setTitle(_t('OrderStatusLog.VIA', 'via'));
				elseif($field == 'DispatchedOn')
					$fieldSet[$field]->setTitle(_t('OrderStatusLog.ON', 'on'));
			}
		}

		if(count($fieldSet)) {
			$fields->removeByName('DispatchUri');
			$fields->insertAfter(\CompositeField::create(
				\FieldGroup::create($fieldSet)->setTitle('Dispatched')->setName('DispatchedDetails'),
				\TextField::create('DispatchUri', _t('OrderStatusLog.DispatchUri', 'Tracking URL'))
					->setDescription(_t('OrderStatusLog.DESC-DispatchUri', 'If none provided, will attempt to use the URL of the carrier'))
			)->setName('Dispatched')->addExtraClass('hero-unit'), 'PublicFields');

			$fieldSet = [];
		}

		foreach(['PaymentCode', 'PaymentOK'] as $field) {
			if(isset($dataFields[$field])) {
				$fieldSet[$field] = $dataFields[$field];
				$fields->removeByName($field);

				if($field == 'PaymentCode')
					$fieldSet[$field]->setTitle(_t('OrderStatusLog.CODE', 'Code'));
			}
		}

		if(count($fieldSet)) {
			$fields->insertAfter(\FieldGroup::create($fieldSet)->setTitle('Payment')->setName('Payment')->addExtraClass('hero-unit'), 'Dispatched');
			$fieldSet = [];
		}

		// Email Fields

		$fields->addFieldsToTab('Root', [
			\Tab::create(
				'Email',
				_t('OrderStatusLog.EMAIL', 'Email')
			)
		]);

		$fields->removeByName('Send_To');
		$fields->removeByName('Send_Subject');
		$fields->removeByName('Send_Body');
		$fields->removeByName('Send_From');
		$fields->removeByName('Send_HideOrder');
		$fields->removeByName('Send');
		$fields->removeByName('Sent');

		$emailFields = [
			\TextField::create('Send_To', _t('OrderStatusLog.Send_To', 'Send to'))
				->setAttribute('placeholder', $this->owner->Order()->ForEmail),
			\TextField::create('Send_Subject', _t('OrderStatusLog.Send_Subject', 'Subject'))
				->setAttribute('placeholder', _t('Order.RECEIPT_SUBJECT', 'Web Order - {reference}', ['reference' => $this->owner->Order()->Reference])),
			\CheckboxField::create('Send_HideOrder', _t('OrderStatusLog.Send_HideOrder', 'Hide order from email')),
			\HTMLEditorField::create('Send_Body', _t('OrderStatusLog.Send_Body', 'Body'))->setRows(2)
				->setDescription(_t('OrderStatusLog.DESC-Send_Body', 'If no body is provided, will use the log notes (as seen below)')),
			\DataObjectPreviewField::create(
				get_class($this->owner) . '_EmailPreview',
				new OrderStatusLog_EmailPreview($this->owner),
				new \DataObjectPreviewer(new OrderStatusLog_EmailPreview($this->owner))
			),
		];

		if($this->owner->Sent) {
			$readOnlyEmailFields = [];

			foreach($emailFields as $emailField) {
				if($emailField->Name != 'Send_Body' && !($emailField instanceof \DataObjectPreviewField))
					$readOnlyEmailFields[] = $emailField->performReadonlyTransformation();
			}

			unset($emailFields);

			$fields->addFieldsToTab('Root.Email', array_merge([
				\ReadonlyField::create('READONLY_Sent', _t('OrderStatusLog.Sent', 'Sent'), $this->owner->obj('Sent')->Nice()),
			], $readOnlyEmailFields));
		}
		else {
			$fields->addFieldsToTab('Root.Email', [
				\HeaderField::create('HEADER-Send', _t('OrderStatusLog.Send', 'Send as an email to the customer?'), 3),
				\SelectionGroup::create('Send', [
					\SelectionGroup_Item::create(0, \CompositeField::create(), _t('OrderStatusLog.Send-NO', 'No')),
					\SelectionGroup_Item::create(1, \CompositeField::create($emailFields), _t('OrderStatusLog.Send-YES', 'Yes')),
				])->addExtraClass('selectionGroup--minor')
			]);
		}
	}

	public function getShippedToFields() {
		$fields = $this->owner->getCMSFields();

		$fields->removeByName('Payment');

		if($shipping = $fields->fieldByName('Root.Main.Dispatched')) {
			$fields->removeByName('Dispatched');
			$fields->insertBefore($shipping, 'Status');
		}

		if($public = $fields->fieldByName('Root.Main.PublicFields')) {
			$public->removeExtraClass('hero-unit');
		}

		return $fields;
	}

	public function setEditFormWithParent($parent, $form, $controller = null) {
		if($parent && ($parent instanceof \Order)) {
			$dataFields = $form->Fields()->dataFields();

			if(isset($dataFields['Send_To']))
				$dataFields['Send_To']->setAttribute('placeholder', $parent->ForEmail);

			if(isset($dataFields['Send_Subject']))
				$dataFields['Send_Subject']->setAttribute('placeholder', _t('Order.RECEIPT_SUBJECT', 'Web Order - {reference}', ['reference' => $parent->Reference]));

			if(isset($dataFields['Status']))
				$dataFields['Status']->disabledOptions = $parent->OrderStatusLogs()->filter('Status', $this->owner->config()->disallowed_multiple_statuses)->column('Status');

			$this->owner->OrderID = $parent->ID;

			$form->Fields()->removeByName(get_class($this->owner) . '_EmailPreview');
			$form->Fields()->insertAfter(\DataObjectPreviewField::create(
				get_class($this->owner) . '_EmailPreview',
				new OrderStatusLog_EmailPreview($this->owner),
				new \DataObjectPreviewer(new OrderStatusLog_EmailPreview($this->owner))
			), 'Send_Body');
		}

		$this->owner->extend('updateEditFormWithParent', $parent, $form, $controller);
	}

	public function log($event, $params = [], $write = true)
	{
		if (isset($this->owner->config()->status_mapping_for_events[$event]))
			$this->owner->Status = _t('OrderHistory.STATUS-' . $event, $this->owner->config()->status_mapping_for_events[$event]);
		else
			$this->owner->Status = self::GENERIC_STATUS;

		$this->owner->castedUpdate($params);

		if ($write)
			$this->owner->write();

		return $this->owner;
	}

	public function setChangeLog($data)
	{
		$this->owner->Changes = serialize($data);
	}

	public function getChangeLog()
	{
		return unserialize($this->owner->Changes);
	}

	public function onBeforeWrite()
	{
		if (!$this->owner->Title)
			$this->owner->Title = $this->owner->Status;
	}

	public function onAfterWrite() {
		if(!$this->owner->Sent && $this->owner->Send && ($email = $this->owner->Email) && $email->To) {
			$email->send();

			$this->owner->Sent = \SS_Datetime::now()->Rfc2822();
			\Requirements::clear();
			$this->owner->Send_Body = $email->renderWith($email->getTemplate());
			\Requirements::restore();

			$this->owner->write();
		}
	}

	public function canView($member = null)
	{
		if (singleton('OrdersAdmin')->canView($member) || $this->owner->Order()->canView($member))
			return true;
	}

	public function canCreate($member = null)
	{
		if (singleton('OrdersAdmin')->canView($member)|| $this->owner->Order()->canView($member))
			return true;
	}

	public function getTimelineIcon()
	{
		$statuses = (array)$this->owner->config()->status_list;
		$icon = '';

		if(isset($statuses[$this->owner->Status]) && isset($statuses[$this->owner->Status]['icon']))
			$icon = $statuses[$this->owner->Status]['icon'];

		switch ($this->owner->Status) {
			case 'Paid':
				$extraClass = '';

				if (($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter(['Status' => 'Captured', 'Created:LessThan' => $this->owner->Created])->first())) {
					switch ($lastPayment->Gateway) {
						case 'PayPal_Express':
							$extraClass = 'fa-paypal';
							break;
						default:
							$extraClass = 'fa-cc-' . strtolower(str_replace(' ', '', $lastPayment->Gateway)) . ' fa-' . strtolower(str_replace(' ', '', $lastPayment->Gateway));
							break;
					}
				}

				$icon = str_replace('order-statusIcon--paid', 'order-statusIcon--paid ' . $extraClass, $icon);

				break;
		}

		return $icon ? $icon : '<i class="fa order-statusIcon--minor icon-timeline--minor"></i>';
	}

	public function getDetailsForDataGrid($separator = ' - ')
	{
		$details = [];

		switch ($this->owner->Status) {
			case 'Updated':
				$log = $this->owner->ChangeLog;
				$separator = '<br/>';

				if (isset($log['OrderItem'])) {
					if (is_array($log['OrderItem']) && isset($log['OrderItem']['Quantity']) && isset($log['Quantity'])) {
						if (isset($log['OrderItem']['_brandnew']))
							$details[] = sprintf('Added %s of %s', $log['Quantity'], isset($log['OrderItem']['Title']) ? $log['OrderItem']['Title'] : 'item', $log['Quantity']);
						elseif ($log['Quantity'])
							$details[] = sprintf('Set %s to %s', isset($log['OrderItem']['Title']) ? $log['OrderItem']['Title'] : 'item', $log['Quantity'], $log['Quantity']);
						else
							$details[] = sprintf('Removed %s', isset($log['OrderItem']['Title']) ? $log['OrderItem']['Title'] : 'item');
					} elseif (is_object($log['OrderItem']) && ($log['OrderItem'] instanceof \OrderItem) && isset($log['Quantity'])) {
						if (isset($log['OrderItem']->_brandnew))
							$details[] = sprintf('Added %s of %s', $log['Quantity'], $log['OrderItem']->TableTitle());
						elseif ($log['Quantity'])
							$details[] = sprintf('Set %s to %s', $log['OrderItem']->TableTitle(), $log['Quantity']);
						else
							$details[] = sprintf('Removed %s', $log['OrderItem']->TableTitle());
					}
				}

				if (isset($log['ShippingAddress']) && $log['ShippingAddress']) {
					$details[] = 'Ship to: ' . implode(', ', array_filter([$log['ShippingAddress']->Name, $log['ShippingAddress']->toString()]));

					if (!$this->owner->Order()->SeparateBillingAddress)
						$details[] = 'Bill to: ' . implode(', ', array_filter([$log['ShippingAddress']->Name, $log['ShippingAddress']->toString()]));
				}

				if (isset($log['BillingAddress']) && $log['BillingAddress']) {
					$details[] = 'Bill to: ' . implode(', ', array_filter([$log['BillingAddress']->Name, $log['BillingAddress']->toString()]));
				}

				if (isset($log['Member'])) {
					$details[] = 'Member: ' . $log['Member']->Name;
				}

				$allowed = ['IPAddress', 'Reference', 'SeparateBillingAddress', 'Notes', 'Total', 'Referrer'];
				$log = array_intersect_key($log, array_flip($allowed));

				if (count($log)) {
					foreach ($log as $field => $trans) {
						if (is_array($trans) && array_key_exists('before', $trans))
							$details[] = $this->owner->Order()->fieldLabel($field) . ' changed from ' . ($trans['before'] ?: '<em class="orderStatusLog-detail--none">none</em>') . ' to ' . $trans['after'];
						elseif (is_string($trans))
							$details[] = $this->owner->Order()->fieldLabel($field) . ': ' . $trans;
					}
				}

				break;
			case 'Processing':
				if (($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter(['Created:LessThan' => $this->owner->Created])->first())) {
					$details[] = 'Via ' . \GatewayInfo::nice_title($lastPayment->Gateway);
					$details[] = 'Charging ' . \GatewayInfo::nice_title($lastPayment->obj('Money')->Nice());

					if ($gatewayMessage = \GatewayMessage::get()->filter(['PaymentID' => $lastPayment->ID, 'Reference:not' => ''])->first()) {
						if ($gatewayMessage->Reference)
							$details[] = 'Reference: ' . $gatewayMessage->Reference;
					}
				}

				break;
			case 'Paid':
				if (($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter(['Status' => 'Captured', 'Created:LessThan' => $this->owner->Created])->first())) {
					$details[] = 'Via ' . \GatewayInfo::nice_title($lastPayment->Gateway);
					$details[] = 'Charged ' . \GatewayInfo::nice_title($lastPayment->obj('Money')->Nice());

					if ($gatewayMessage = \GatewayMessage::get()->filter(['PaymentID' => $lastPayment->ID, 'Reference:not' => ''])->first()) {
						if ($gatewayMessage->Reference)
							$details[] = 'Reference: ' . $gatewayMessage->Reference;
					}
				}
				break;
		}

		$details = [count($details) ? $separator . implode($separator, $details) : ''];

		if ($this->owner->Sent)
			$details[] = 'Notified customer on: ' . $this->owner->Sent;

		if ($this->owner->Author()->exists())
			$details[] = 'Author: ' . $this->owner->Author()->Name;

		if ($this->owner->Note)
			$details[] = '<strong>Note</strong><br/>' . $this->owner->Note;

		return implode('<br/>', $details);
	}

	public function getEmail()
	{
		$order = $this->owner->Order();

		$email = \Order_statusEmail::create();

		if($this->owner->Send_To)
			$email->setTo($this->owner->Send_To);
		elseif(trim($order->Name))
			$email->setTo($order->Name . ' <' . $order->LatestEmail . '>');
		else
			$email->setTo($order->LatestEmail);

		if($this->owner->Send_From) {
			$email->setFrom($this->owner->Send_From);
		}
		else {
			if (\Config::inst()->get('OrderProcessor', 'receipt_email')) {
				$adminEmail = \Config::inst()->get('OrderProcessor', 'receipt_email');
			} else {
				$adminEmail = \Email::config()->admin_email;
			}

			$email->setFrom($adminEmail);
		}

		$email->setSubject($this->owner->Send_Subject);

		$note = $this->owner->Send_Body ?: with(new \BBCodeParser($this->owner->Note))->parse();

		$email->populateTemplate([
			'Order'  => $order,
			'Member' => $order->Member(),
			'Note'   => \DBField::create_field('HTMLText',
					\SSViewer::execute_string($note, $this->owner, [
							'Order' => $order,
						]
					)
				),
			'isPreview' => true,
		]);

		$this->owner->extend('updateEmail', $email);

		return $email;
	}

	public function getDispatchInformation() {
		return $this->owner->renderWith('OrderStatusLog_DispatchInformation');
	}
}

class OrderStatusLog_EmailPreview implements \DataObjectPreviewInterface
{
	protected $record;

	public function __construct(\OrderStatusLog $record)
	{
		$this->record = $record;
	}

	public function getPreviewHTML()
	{
		if($this->record->Sent)
			return $this->record->Send_Body;

		$email = $this->record->Email;

		\Requirements::clear();
		$preview = $email->renderWith($email->getTemplate());
		\Requirements::restore();
		return $preview;
	}
}