<?php namespace Milkyway\SS\Shop\OrderHistory\Extensions;

/**
 * Milkyway Multimedia
 * OrderStatusLog.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use DataExtension;
use Permission;
use FieldList;
use Member;
use DatetimeField;
use TextareaField;
use FieldGroup;
use CompositeField;
use DateField;
use TextField;
use Tab;
use CheckboxField;
use HTMLEditorField;
use ReadonlyField;
use SelectionGroup_Item;
use SS_Datetime;
use Email;
use SSViewer;
use Object;
use DBField;

use Select2Field;
use TabbedSelectionGroup;

use DataObjectPreviewField;
use DataObjectPreviewer;
use Milkyway\SS\Shop\OrderHistory\Preview\OrderStatusLog as Preview;

use GatewayInfo;
use GatewayMessage;

use Order as OriginalOrder;
use OrderItem as OriginalOrderItem;

class OrderStatusLog extends DataExtension
{
    private static $singular_name = 'Status Entry';

    private static $db = [
        'Status'         => 'Varchar',

        // This is for dispatch
        'DispatchUri'    => 'Text',

        // This is for public viewing of a status log
        'Public'         => 'Boolean',
        'Unread'         => 'Boolean',
        'FirstRead'      => 'Datetime',

        // This is for emails/notifications to customer
        'Send'           => 'Boolean',
        'Sent'           => 'Datetime',
        'Send_To'        => 'Varchar(256)',
        'Send_From'      => 'Varchar(256)',
        'Send_Subject'   => 'Varchar(256)',
        'Send_Body'      => 'HTMLText',
        'Send_HideOrder' => 'Boolean',

        // The versioned table is too messy with relations... Store changes on here instead
        'Changes'        => 'Text',

        // Check whether a log was an automated log via the events system
        'Automated'      => 'Boolean',
    ];

    private static $default_sort = 'Created DESC';

    private static $defaults = [
        'Unread' => true,
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
            'icon'  => '<i class="fa fa-comment order-statusIcon--notified"></i>',
        ],
        'Shipped'    => [
            'title' => 'Shipped',
            'icon'  => '<i class="fa fa-send order-statusIcon--shipped"></i>',
        ],
        'Completed'  => [
            'title' => 'Completed',
            'icon'  => '<i class="fa fa-star order-statusIcon--completed"></i>',
        ],
        'Archived'   => [
            'title' => 'Archived',
            'icon'  => '<i class="fa fa-archive order-statusIcon--archived"></i>',
        ],
        'Cancelled'  => [
            'title' => 'Cancelled',
            'icon'  => '<i class="fa fa-remove order-statusIcon--cancelled"></i>',
        ],
        'Query'      => [
            'title' => 'Query',
            'icon'  => '<i class="fa fa-question order-statusIcon--query"></i>',
        ],
        'Refunded'   => [
            'title' => 'Refunded',
            'icon'  => '<i class="fa fa-undo order-statusIcon--refunded"></i>',
        ],
        'Paid'       => [
            'title' => 'Paid',
            'icon'  => '<i class="fa fa-money order-statusIcon--paid"></i>',
        ],
        'Placed'     => [
            'title' => 'Placed',
            'icon'  => '<i class="fa fa-check order-statusIcon--placed"></i>',
        ],
        'Processing' => [
            'title' => 'Processing',
            'icon'  => '<i class="fa fa-refresh order-statusIcon--processing"></i>',
        ],
        'Started'    => [
            'title' => 'Started',
            'icon'  => '<i class="fa fa-star-o order-statusIcon--started"></i>',
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
        'TNT Express'    => 'http://www.tntexpress.com.au/interaction/asps/trackdtl_tntau.asp',
    ];

    private static $order_status_by_completion = [
        'Completed',
        'Cancelled',
        'Refunded',
        'Query',
        'Paid',
        'Processing',
        'Placed',
        'Updated',
        'Started',
    ];

    private static $email_template = 'Order_StatusEmail';

    /** @var int You can end up with a lot of logs if you are not careful.
     * This only applies to automatic logs (such as record updates), logs entered manually in the CMS will always be
     * saved */
    private static $max_records_per_order = 50;

    // This is the generic status of an order, and is reserved for automatic updates
    const GENERIC_STATUS = [
        'Updated',
    ];

    // These are reserved statuses that change the mode of the Order, hence cannot be added by the user
    const RESERVED_STATUS = [
        'Started',
        'Completed',
        'Cancelled',
    ];

    // Automated status with details attached
    const DETAILED_STATUS = [
        'Paid',
        'Processing',
        'Placed',
    ];

    const SHIPPED_STATUS = [
        'Shipped',
    ];

    const ARCHIVED_STATUS = [
        'Archived',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('Automated');
        $fields->removeByName('AuthorID');
        $fields->removeByName('OrderID');
        $fields->removeByName('Changes');
        $fields->removeByName('FirstRead');

        // Disables the default firing of sent to customer flag
        $fields->removeByName('SentToCustomer');

        // Status Field
        $allSuggestedStatuses = (array)$this->owner->config()->status_list;
        $statusesWithIcons = $otherStatuses = [];

        // Check if readonly
        $editable = $this->owner->canEdit();
        $simplified = !Permission::check('ADMIN') && $this->owner->Automated && !in_array($this->owner->Status,
                self::DETAILED_STATUS);

        $dataFields = $fields->dataFields();

        if ($editable) {
            $fields->removeByName('Status');

            foreach ($allSuggestedStatuses as $status => $options) {
                if (is_array($options) && isset($options['icon'])) {
                    $statusesWithIcons[$status] = isset($options['title']) ? $options['icon'] . ' ' . $options['title'] : $options['icon'] . ' ' . $status;
                } else {
                    if (is_array($options) && isset($options['title'])) {
                        $otherStatuses[$status] = $options['title'];
                    } else {
                        $otherStatuses[$status] = $options;
                    }
                }
            }

            $otherStatuses = array_merge($otherStatuses, $this->owner->get()->filter('Status:not',
                array_merge(array_keys($allSuggestedStatuses)))->sort('Status',
                'ASC')->map('Status', 'Status')->toArray());

            $statuses = [
                'Suggested status (these use an icon and/or special formatting)' => $statusesWithIcons,
            ];

            if (!empty($otherStatuses)) {
                asort($otherStatuses);
                $statuses['Other Status'] = $otherStatuses;
            }

            $fields->insertBefore($statusField = Select2Field::create('Status', 'Status', '', $statuses)
                ->setMinimumSearchLength(0)
                ->setEmptyString('You can select from suggested statuses, or create a new status')
                ->setAttribute('data-format-searching', _t('OrderStatusLog.SEARCHING-Status', 'Loading statuses...')),
                'Title');

            $statusField->disabledOptions = $this->owner->config()->disallowed_multiple_statuses;

            if ($this->owner->exists() && !Permission::check('ADMIN') && ($this->owner->Automated || $this->owner->AuthorID != Member::currentUserID())) {
                $statusField->setAttribute('disabled', 'disabled');
            } elseif ($editable) {
                $statusField->setDescription(_t('OrderStatusLog.DESC-Status',
                    'Note: {updated} is a special status. If there are more than {limit} logs for an order, it will automatically delete statuses classed as {updated}, so use with caution.',
                    [
                        'updated' => implode(', ', (array)static::GENERIC_STATUS),
                        'limit'   => $this->owner->config()->max_records_per_order,
                    ]));
            }

            $statusField->allowHTML = true;
            $statusField->prefetch = true;
        } elseif (isset($dataFields['Status'])) {
            $fields->removeByName('Status');
            $fields->insertBefore('Title', $dataFields['Status']);
        }

        if (isset($dataFields['Title']) && $editable) {
            $dataFields['Title']->setDescription(_t('OrderStatusLog.DESC-Title',
                'If not set, will automatically use the Status above'));
        } elseif (!$editable && $this->owner->Title == $this->owner->Status) {
            $fields->removeByName('Title');
        }

        $lastField = 'Title';

        if (!$editable && !$this->owner->Note) {
            $fields->removeByName('Note');
        } elseif ($editable && isset($dataFields['Note']) && $dataFields['Note'] instanceof TextareaField) {
            $dataFields['Note']->setRows(2);
            $lastField = 'Note';
        } else {
            $lastField = 'Note';
        }

        $fieldSet = [];

        foreach (['Public', 'Unread'] as $field) {
            if (isset($dataFields[$field])) {
                $fieldSet[$field] = $dataFields[$field];
                $fields->removeByName($field);
            }
        }

        if (!empty($fieldSet)) {
            if (isset($fieldSet['Public'])) {
                $fieldSet['Public']->setTitle($fieldSet['Public']->Title() . ' (' . _t('OrderStatusLog.DESC-Public',
                        'If checked, user can view this log on the front-end when checking the status of their orders') . ')');
            }

            if ($this->owner->FirstRead) {
                $fieldSet['FirstRead'] = DatetimeField::create('FirstRead');
            }

            $fields->insertAfter(FieldGroup::create($fieldSet)->setTitle('Public Visibility')->setName('PublicFields')->addExtraClass('hero-unit stacked-items'),
                $lastField);
            $fieldSet = [];
            $lastField = 'PublicFields';
        }

        foreach (['DispatchTicket', 'DispatchedBy', 'DispatchedOn'] as $field) {
            if (($simplified || !$editable) && !$this->owner->$field) {
                $fields->removeByName($field);
                continue;
            }

            if (isset($dataFields[$field])) {
                $fieldSet[$field] = $dataFields[$field];
                $fields->removeByName($field);

                if ($fieldSet[$field] instanceof DateField) {
                    $fieldSet[$field]->setConfig('showcalendar', true);
                }

                if ($field == 'DispatchTicket') {
                    $fieldSet[$field]->setTitle(_t('OrderStatusLog.TRACKING_ID', 'Tracking ID'));
                } elseif ($field == 'DispatchedBy') {
                    $fieldSet[$field]->setTitle(_t('OrderStatusLog.VIA', 'via'));
                } elseif ($field == 'DispatchedOn') {
                    $fieldSet[$field]->setTitle(_t('OrderStatusLog.ON', 'on'));
                }
            }
        }

        if (!empty($fieldSet)) {
            $fields->removeByName('DispatchUri');
            $fields->insertAfter($dispatched = CompositeField::create(
                FieldGroup::create($fieldSet)->setTitle('Dispatched')->setName('DispatchedDetails')
            )->setName('Dispatched')->addExtraClass('hero-unit'), $lastField);

            if ($editable || $this->owner->DispatchUri) {
                $dispatched->push(TextField::create('DispatchUri', _t('OrderStatusLog.DispatchUri', 'Tracking URL'))
                    ->setDescription(_t('OrderStatusLog.DESC-DispatchUri',
                        'If none provided, will attempt to use the URL of the carrier')));
            }

            $fieldSet = [];
            $lastField = 'Dispatched';
        } elseif (($simplified || !$editable) && !$this->owner->DispatchUri) {
            $fields->removeByName('DispatchUri');
        }

        foreach (['PaymentCode', 'PaymentOK'] as $field) {
            if (($simplified || !$editable) && !$this->owner->$field) {
                $fields->removeByName($field);
                continue;
            }

            if (isset($dataFields[$field])) {
                $fieldSet[$field] = $dataFields[$field];
                $fields->removeByName($field);

                if ($field == 'PaymentCode') {
                    $fieldSet[$field]->setTitle(_t('OrderStatusLog.CODE', 'Code'));
                }
            }
        }

        if (!empty($fieldSet)) {
            $fields->insertAfter(FieldGroup::create($fieldSet)->setTitle('Payment')->setName('Payment')->addExtraClass('hero-unit'),
                $lastField);
            $fieldSet = [];
        }

        // Email Fields

        $fields->removeByName('Send_To');
        $fields->removeByName('Send_Subject');
        $fields->removeByName('Send_Body');
        $fields->removeByName('Send_From');
        $fields->removeByName('Send_HideOrder');
        $fields->removeByName('Send');
        $fields->removeByName('Sent');

        if (($simplified || !$editable) && !$this->owner->Sent) {
            return;
        }

        $fields->addFieldsToTab('Root', [
            Tab::create(
                'Email',
                _t('OrderStatusLog.EMAIL', 'Email')
            ),
        ]);

        $emailFields = [
            'Send_To'        => TextField::create('Send_To', _t('OrderStatusLog.Send_To', 'Send to')),
            'Send_From'      => TextField::create('Send_From', _t('OrderStatusLog.Send_From', 'From')),
            'Send_Subject'   => TextField::create('Send_Subject', _t('OrderStatusLog.Send_Subject', 'Subject'))
                ->setAttribute('placeholder', _t('Order.RECEIPT_SUBJECT', 'Web Order - {reference}',
                    ['reference' => $this->owner->Order()->Reference])),
            'Send_HideOrder' => CheckboxField::create('Send_HideOrder',
                _t('OrderStatusLog.Send_HideOrder', 'Hide order from email')),
            'Send_Body'      => HTMLEditorField::create('Send_Body', _t('OrderStatusLog.Send_Body', 'Body'))
                ->setRows(2)
                ->addExtraClass('limited limited-with-source limited-with-links')
                ->setDescription(_t('OrderStatusLog.DESC-Send_Body',
                    'If no body is provided, will use the log notes (as seen below)')),
            'EmailPreview'   => DataObjectPreviewField::create(
                get_class($this->owner) . '_EmailPreview',
                new Preview($this->owner),
                new DataObjectPreviewer(new Preview($this->owner))
            ),
        ];

        if ($this->owner->Sent || !$editable) {
            $readOnlyEmailFields = [];
            unset($emailFields['Send_HideOrder']);

            foreach ($emailFields as $emailField) {
                if ($emailField->Name != 'Send_Body' && !($emailField instanceof DataObjectPreviewField)) {
                    $readOnlyEmailFields[] = $emailField->performReadonlyTransformation();
                } elseif ($emailField instanceof DataObjectPreviewField) {
                    $readOnlyEmailFields[] = $emailField;
                }
            }

            unset($emailFields);

            $fields->addFieldsToTab('Root.Email', array_merge([
                ReadonlyField::create('READONLY_Sent', _t('OrderStatusLog.Sent', 'Sent'),
                    $this->owner->obj('Sent')->Nice()),
            ], $readOnlyEmailFields));
        } else {
            $fields->addFieldsToTab('Root.Email', [
                $selectionGroup = TabbedSelectionGroup::create('Send', [
                    SelectionGroup_Item::create(0, CompositeField::create(), _t('OrderStatusLog.Send-NO', 'No')),
                    SelectionGroup_Item::create(1, CompositeField::create($emailFields),
                        _t('OrderStatusLog.Send-YES', 'Yes')),
                ])
                    ->addExtraClass('selectionGroup--minor')
                    ->showAsDropdown(true)
                    ->setTitle(_t('OrderStatusLog.Send', 'Send as an email?')),
            ]);
        }
    }

    public function getShippedToFields()
    {
        $fields = $this->owner->getCMSFields();

        $fields->removeByName('Payment');

        if ($shipping = $fields->fieldByName('Root.Main.Dispatched')) {
            $fields->removeByName('Dispatched');
            $fields->insertBefore($shipping, 'Status');
        }

        if ($public = $fields->fieldByName('Root.Main.PublicFields')) {
            $public->removeExtraClass('hero-unit');
        }

        return $fields;
    }

    public function getNotificationFields()
    {
        $fields = $this->owner->getCMSFields();

        $fields->removeByName('Payment');
        $fields->removeByName('Dispatched');

        if ($email = $fields->fieldByName('Root.Email')) {
            $fields->removeByName('Email');
            $fields->insertBefore($email, 'Main');

            if ($main = $fields->fieldByName('Root.Main')) {
                $main->setTitle(_t('OTHER', 'Other'));
            }
        }

        return $fields;
    }

    public function setEditFormWithParent($parent, $form, $controller = null)
    {
        if ($parent && ($parent instanceof OriginalOrder)) {
            $dataFields = $form->Fields()->dataFields();
            $email = $this->owner->getEmail($parent);

            if (isset($dataFields['Send_To'])) {
                $dataFields['Send_To']->setAttribute('placeholder', $email->To());
            }

            if (isset($dataFields['Send_From'])) {
                $dataFields['Send_From']->setAttribute('placeholder', $email->From());
            }

            if (isset($dataFields['Send_Subject'])) {
                $dataFields['Send_Subject']->setAttribute('placeholder', $email->Subject());
            }

            $this->owner->OrderID = $parent->ID;

            $form->Fields()->removeByName(get_class($this->owner) . '_EmailPreview');
            $form->Fields()->insertAfter(DataObjectPreviewField::create(
                get_class($this->owner) . '_EmailPreview',
                new Preview($this->owner),
                new DataObjectPreviewer(new Preview($this->owner))
            ), 'Send_Body');
        }

        $this->owner->extend('updateEditFormWithParent', $parent, $form, $controller);
    }

    public function log($event, $params = [], $write = true)
    {
        if (isset($this->owner->config()->status_mapping_for_events[$event])) {
            $this->owner->Status = _t('OrderHistory.STATUS-' . $event,
                $this->owner->config()->status_mapping_for_events[$event]);
        } else {
            $this->owner->Status = self::GENERIC_STATUS[0];
        }

        if (isset($params['ChangeLog'])) {
            $this->owner->ChangeLog = $params['ChangeLog'];
            unset($params['ChangeLog']);
        }

        $this->owner->castedUpdate($params);

        if ($write) {
            $this->owner->write();
        }

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
        if (!$this->owner->Title) {
            $this->owner->Title = $this->owner->Status;
        }

        if (!$this->owner->Sent && $this->owner->Send && $this->owner->Order()->exists() && ($email = $this->owner->Email)) {
            $email->send();

            $this->owner->Sent = SS_Datetime::now()->Rfc2822();
            $this->owner->Send_To = $email->To();
            $this->owner->Send_From = $email->From();
            $this->owner->Send_Subject = $email->Subject();
            $this->owner->Send_Body = $email->Body();
        }
    }

    public function onAfterWrite()
    {
//        if (!$this->owner->Sent && $this->owner->Send && ($email = $this->owner->Email)) {
//            $email->send();
//
//            $this->owner->Sent = \SS_Datetime::now()->Rfc2822();
//            singleton('require')->clear();
//            $this->owner->Send_Body = $email->renderWith($email->getTemplate());
//            singleton('require')->restore();
//
//            $this->owner->write();
//        }
    }

    public function canView($member = null)
    {
        if (singleton('OrdersAdmin')->canView($member) || $this->owner->Order()->canView($member)) {
            return true;
        }
    }

    public function canCreate($member = null)
    {
        if (singleton('OrdersAdmin')->canView($member) || $this->owner->Order()->canView($member)) {
            return true;
        }
    }

    public function getTimelineIcon()
    {
        $statuses = (array)$this->owner->config()->status_list;
        $icon = '';

        if (isset($statuses[$this->owner->Status]) && isset($statuses[$this->owner->Status]['icon'])) {
            $icon = $statuses[$this->owner->Status]['icon'];
        }

        switch ($this->owner->Status) {
            case 'Paid':
                $extraClass = '';

                if (($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter([
                        'Status'           => 'Captured',
                        'Created:LessThan' => $this->owner->Created,
                    ])->first())
                ) {
                    switch ($lastPayment->Gateway) {
                        case 'PayPal_Express':
                            $extraClass = 'fa-paypal';
                            break;
                        default:
                            $extraClass = 'fa-cc-' . strtolower(str_replace(' ', '',
                                    $lastPayment->Gateway)) . ' fa-' . strtolower(str_replace(' ', '',
                                    $lastPayment->Gateway));
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

        if ($this->owner->Note) {
            $details[] = $this->owner->Note;
        }

        switch ($this->owner->Status) {
            case 'Updated':
                $log = $this->owner->ChangeLog;
                $separator = '<br/>';

                if (isset($log['OrderItem'])) {
                    if (is_array($log['OrderItem']) && isset($log['OrderItem']['Quantity']) && isset($log['Quantity'])) {
                        if (isset($log['OrderItem']['_brandnew'])) {
                            $details[] = sprintf('Added %s of %s', $log['Quantity'],
                                isset($log['OrderItem']['Title']) ? $log['OrderItem']['Title'] : 'item',
                                $log['Quantity']);
                        } elseif ($log['Quantity']) {
                            $details[] = sprintf('Set %s to %s',
                                isset($log['OrderItem']['Title']) ? $log['OrderItem']['Title'] : 'item',
                                $log['Quantity'], $log['Quantity']);
                        } else {
                            $details[] = sprintf('Removed %s',
                                isset($log['OrderItem']['Title']) ? $log['OrderItem']['Title'] : 'item');
                        }
                    } elseif (is_object($log['OrderItem']) && ($log['OrderItem'] instanceof OriginalOrderItem) && isset($log['Quantity'])) {
                        if (isset($log['OrderItem']->_brandnew)) {
                            $details[] = sprintf('Added %s of %s', $log['Quantity'],
                                $log['OrderItem']->Buyable()->Title);
                        } elseif ($log['Quantity']) {
                            $details[] = sprintf('Set %s to %s', $log['OrderItem']->Buyable()->Title, $log['Quantity']);
                        } else {
                            $details[] = sprintf('Removed %s', $log['OrderItem']->Buyable()->Title);
                        }
                    }
                }

                if (isset($log['ShippingAddress']) && $log['ShippingAddress']) {
                    $details[] = 'Ship to: ' . implode(', ',
                            array_filter([$log['ShippingAddress']->Name, $log['ShippingAddress']->toString()]));

                    if (!$this->owner->Order()->SeparateBillingAddress) {
                        $details[] = 'Bill to: ' . implode(', ',
                                array_filter([$log['ShippingAddress']->Name, $log['ShippingAddress']->toString()]));
                    }
                }

                if (isset($log['BillingAddress']) && $log['BillingAddress']) {
                    $details[] = 'Bill to: ' . implode(', ',
                            array_filter([$log['BillingAddress']->Name, $log['BillingAddress']->toString()]));
                }

                if (isset($log['Member'])) {
                    $details[] = 'Member: ' . $log['Member']->Name;
                }

                $allowed = ['IPAddress', 'Reference', 'SeparateBillingAddress', 'Notes', 'Referrer'];
                if (!$this->owner->Order()->IsCart()) {
                    $allowed[] = 'Total';
                }
                $log = array_intersect_key($log, array_flip($allowed));

                if (!empty($log)) {
                    foreach ($log as $field => $trans) {
                        if (is_array($trans) && array_key_exists('before', $trans)) {
                            $details[] = $this->owner->Order()->fieldLabel($field) . ' changed from ' . ($trans['before'] ?: '<em class="orderStatusLog-detail--none">none</em>') . ' to ' . $trans['after'];
                        } elseif (is_string($trans)) {
                            $details[] = $this->owner->Order()->fieldLabel($field) . ': ' . $trans;
                        }
                    }
                }

                break;
            case 'Processing':
                if (($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter(['Created:LessThan' => $this->owner->Created])->first())) {
                    $details[] = 'Via ' . GatewayInfo::nice_title($lastPayment->Gateway);
                    $details[] = 'Charging ' . GatewayInfo::nice_title($lastPayment->obj('Money')->Nice());

                    if ($gatewayMessage = GatewayMessage::get()->filter([
                        'PaymentID'     => $lastPayment->ID,
                        'Reference:not' => '',
                    ])->first()
                    ) {
                        if ($gatewayMessage->Reference) {
                            $details[] = 'Reference: ' . $gatewayMessage->Reference;
                        }
                    }
                }

                break;
            case 'Paid':
                if (($component = $this->owner->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->owner->Order()->Payments()->filter([
                        'Status'           => 'Captured',
                        'Created:LessThan' => $this->owner->Created,
                    ])->first())
                ) {
                    $details[] = 'Via ' . GatewayInfo::nice_title($lastPayment->Gateway);
                    $details[] = 'Charged ' . GatewayInfo::nice_title($lastPayment->obj('Money')->Nice());

                    if ($gatewayMessage = GatewayMessage::get()->filter([
                        'PaymentID'     => $lastPayment->ID,
                        'Reference:not' => '',
                    ])->first()
                    ) {
                        if ($gatewayMessage->Reference) {
                            $details[] = 'Reference: ' . $gatewayMessage->Reference;
                        }
                    }
                }
                break;
        }

        $details = [count($details) ? $separator . implode($separator, $details) : ''];

        if ($this->owner->Sent) {
            $details[] = 'Notified customer on: ' . $this->owner->Sent;
        }

        if ($this->owner->Author()->exists()) {
            $details[] = 'Author: ' . $this->owner->Author()->Name;
        }

        return implode('<br/>', $details);
    }

    public function getEmail($order = null)
    {
        $order = $order ?: $this->owner->Order();

        $email = Email::create();
        $email->setTemplate($this->owner->config()->email_template);

        if ($this->owner->Send_To) {
            $email->setTo($this->owner->Send_To);
        } elseif (trim($order->Name)) {
            $email->setTo($order->Name . ' <' . $order->LatestEmail . '>');
        } else {
            $email->setTo($order->LatestEmail);
        }

        if ($this->owner->Send_From) {
            $email->setFrom($this->owner->Send_From);
        } else {
            if (\Config::inst()->get('OrderProcessor', 'receipt_email')) {
                $adminEmail = \Config::inst()->get('OrderProcessor', 'receipt_email');
            } else {
                $adminEmail = \Email::config()->admin_email;
            }

            $email->setFrom($adminEmail);
        }

        $subject = $this->owner->Send_Subject ? str_replace(
            [
                '$Order.Reference',
                '$Order.ShippingAddress',
                '$Order.BillingAddress',
                '$Order.Customer.Name',
                '$Order.Total',
                '$Order.Items.count',
            ],
            [
                $order->Reference,
                (string)$order->ShippingAddress(),
                (string)$order->BillingAddress(),
                $order->Customer() ? $order->Customer()->Name : 'Guest',
                $order->Total(),
                $order->Items()->count(),
            ],
            $this->owner->Send_Subject
        ) : _t('Order.RECEIPT_SUBJECT', 'Web Order - {reference}', ['reference' => $order->Reference]);

        $email->setSubject($subject);

        $note = $this->owner->Send_Body ?: Object::create('BBCodeParser', $this->owner->Note)->parse();

        $email->populateTemplate([
            'Order'     => $order,
            'Member'    => $order->Customer(),
            'Note'      => DBField::create_field('HTMLText',
                SSViewer::execute_string($note, $this->owner, [
                        'Order' => $order,
                    ]
                )
            ),
            'isPreview' => true,
        ]);

        $this->owner->extend('updateEmail', $email);

        return $email;
    }

    public function getDispatchInformation()
    {
        return $this->owner->renderWith('OrderStatusLog_DispatchInformation');
    }
}