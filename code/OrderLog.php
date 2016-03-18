<?php

/**
 * Milkyway Multimedia
 * OrderLog.php
 *
 * @package milkyway-multimedia/ss-shop-order-history
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\Shop\OrderHistory\Preview\OrderLog as Preview;

class OrderLog extends OrderStatusLog
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
        'onForwardViaEmail' => 'ForwardedViaEmail',
        'onPrintByCustomer' => 'PrintedByCustomer',
        'onRepeatByCustomer' => 'RepeatedByCustomer',
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
        'ForwardedViaEmail',
        'PrintedByCustomer',
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
    public static $GENERIC_STATUS = [
        'Updated',
    ];

    // These are reserved statuses that change the mode of the Order, hence cannot be added by the user
    public static $RESERVED_STATUS = [
        'Started',
        'Completed',
        'Cancelled',
    ];

    // Automated status with details attached
    public static $DETAILED_STATUS = [
        'Paid',
        'Processing',
        'Placed',
    ];

    public static $SHIPPED_STATUS = [
        'Shipped',
    ];

    public static $ARCHIVED_STATUS = [
        'Archived',
    ];

    public function getCMSFields()
    {
        $this->beforeExtending('updateCMSFields', function($fields) {
            $fields->removeByName('Automated');
            $fields->removeByName('AuthorID');
            $fields->removeByName('OrderID');
            $fields->removeByName('Changes');
            $fields->removeByName('FirstRead');

            // Disables the default firing of sent to customer flag
            $fields->removeByName('SentToCustomer');

            // Status Field
            $allSuggestedStatuses = (array)$this->config()->status_list;
            $statusesWithIcons = $otherStatuses = [];

            // Check if readonly
            $editable = $this->canEdit();
            $simplified = !Permission::check('ADMIN') && $this->Automated && !in_array($this->Status, static::$DETAILED_STATUS);

            $dataFields = $fields->dataFields();

            if ($editable) {
                $fields->removeByName('Status');

                foreach ($allSuggestedStatuses as $status => $options) {
                    if (is_array($options) && !empty($options['icon'])) {
                        $statusesWithIcons[$status] = isset($options['title']) ? $options['icon'] . ' ' . $options['title'] : $options['icon'] . ' ' . $status;
                    } else if (is_array($options) && !empty($options['title'])) {
                        $otherStatuses[$status] = $options['title'];
                    } else {
                        $otherStatuses[$status] = $options;
                    }
                }

                $otherStatuses = array_merge(
                    $otherStatuses,
                    $this->get()
                        ->exclude(
                            'Status',
                            array_merge(array_keys($allSuggestedStatuses))
                        )
                        ->sort('Status', 'ASC')
                        ->map('Status', 'Status')
                        ->toArray()
                );

                $statuses = [
                    'Common Statuses' => $statusesWithIcons,
                ];

                if (!empty($otherStatuses)) {
                    asort($otherStatuses);
                    $statuses['Other Status'] = $otherStatuses;

                    foreach($statuses['Other Status'] as $status => $title) {
                        $statuses['Other Status'][$status] = FormField::name_to_label($title);
                    }
                }

                $fields->insertBefore($statusField = Select2Field::create('Status', 'Status', '', $statuses)
                    ->setMinimumSearchLength(0)
                    ->setEmptyString('You can select from suggested statuses, or create a new status')
                    ->setAttribute('data-format-searching', _t('OrderLog.SEARCHING-Status', 'Loading statuses...')),
                    'Title');

                $statusField->requireSelection = false;

                if(!Permission::check('ADMIN')) {
                    $statusField->disabledOptions = $this->config()->disallowed_multiple_statuses;
                }

                if ($this->exists() && !Permission::check('ADMIN') && ($this->Automated || $this->AuthorID != Member::currentUserID())) {
                    $statusField->setAttribute('disabled', 'disabled');
                } elseif ($editable) {
                    $statusField->setDescription(_t('OrderLog.DESC-Status',
                        'Note: {updated} is a special status. If there are more than {limit} logs for an order, it will automatically delete statuses classed as {updated}, so use with caution.',
                        [
                            'updated' => implode(', ', (array)static::$GENERIC_STATUS),
                            'limit'   => $this->config()->max_records_per_order,
                        ]));
                }

                $statusField->allowHTML = true;
                $statusField->prefetch = true;
            } elseif (isset($dataFields['Status'])) {
                $fields->removeByName('Status');
                $fields->insertBefore('Title', $dataFields['Status']);
            }

            if (isset($dataFields['Title']) && $editable) {
                $dataFields['Title']->setDescription(_t('OrderLog.DESC-Title',
                    'If not set, will automatically use the Status above'));
            } elseif (!$editable && $this->Title == $this->Status) {
                $fields->removeByName('Title');
            }

            $lastField = 'Title';

            if (!$editable && !$this->Note) {
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
                    $fieldSet['Public']->setTitle($fieldSet['Public']->Title() . ' (' . _t('OrderLog.DESC-Public',
                            'If checked, user can view this log on the front-end when checking the status of their orders') . ')');
                }

                if ($this->FirstRead) {
                    $fieldSet['FirstRead'] = DatetimeField::create('FirstRead');
                }

                $fields->insertAfter(FieldGroup::create($fieldSet)->setTitle('Public Visibility')->setName('PublicFields')->addExtraClass('hero-unit stacked-items'),
                    $lastField);
                $fieldSet = [];
                $lastField = 'PublicFields';
            }

            foreach (['DispatchTicket', 'DispatchedBy', 'DispatchedOn'] as $field) {
                if (($simplified || !$editable) && !$this->$field) {
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
                        $fieldSet[$field]->setTitle(_t('OrderLog.TRACKING_ID', 'Tracking ID'));
                    } elseif ($field == 'DispatchedBy') {
                        $fieldSet[$field]->setTitle(_t('OrderLog.VIA', 'via'));
                    } elseif ($field == 'DispatchedOn') {
                        $fieldSet[$field]->setTitle(_t('OrderLog.ON', 'on'));
                    }
                }
            }

            if (!empty($fieldSet)) {
                $fields->removeByName('DispatchUri');
                $fields->insertAfter($dispatched = CompositeField::create(
                    FieldGroup::create($fieldSet)->setTitle('Dispatched')->setName('DispatchedDetails')
                )->setName('Dispatched')->addExtraClass('hero-unit'), $lastField);

                if ($editable || $this->DispatchUri) {
                    $dispatched->push(TextField::create('DispatchUri', _t('OrderLog.DispatchUri', 'Tracking URL'))
                        ->setDescription(_t('OrderLog.DESC-DispatchUri',
                            'If none provided, will attempt to use the URL of the carrier')));
                }

                $fieldSet = [];
                $lastField = 'Dispatched';
            } elseif (($simplified || !$editable) && !$this->DispatchUri) {
                $fields->removeByName('DispatchUri');
            }

            foreach (['PaymentCode', 'PaymentOK'] as $field) {
                if (($simplified || !$editable) && !$this->$field) {
                    $fields->removeByName($field);
                    continue;
                }

                if (isset($dataFields[$field])) {
                    $fieldSet[$field] = $dataFields[$field];
                    $fields->removeByName($field);

                    if ($field == 'PaymentCode') {
                        $fieldSet[$field]->setTitle(_t('OrderLog.CODE', 'Code'));
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

            if (($simplified || !$editable) && !$this->Sent) {
                return;
            }

            $fields->addFieldsToTab('Root', [
                Tab::create(
                    'Email',
                    _t('OrderLog.EMAIL', 'Email')
                ),
            ]);

            $emailFields = [
                'Send_To'        => TextField::create('Send_To', _t('OrderLog.Send_To', 'Send to')),
                'Send_From'      => TextField::create('Send_From', _t('OrderLog.Send_From', 'From')),
                'Send_Subject'   => TextField::create('Send_Subject', _t('OrderLog.Send_Subject', 'Subject'))
                    ->setAttribute('placeholder', _t('Order.RECEIPT_SUBJECT', 'Web Order - {reference}',
                        ['reference' => $this->Order()->Reference])),
                'Send_HideOrder' => CheckboxField::create('Send_HideOrder',
                    _t('OrderLog.Send_HideOrder', 'Hide order from email')),
                'Send_Body'      => HTMLEditorField::create('Send_Body', _t('OrderLog.Send_Body', 'Body'))
                    ->setRows(2)
                    ->addExtraClass('limited limited-with-source limited-with-links')
                    ->setDescription(_t('OrderLog.DESC-Send_Body',
                        'If no body is provided, will use the log notes (as seen below)')),
                'EmailPreview'   => DataObjectPreviewField::create(
                    get_class($this) . '_EmailPreview',
                    new Preview($this),
                    new DataObjectPreviewer(new Preview($this))
                ),
            ];

            if ($this->Sent || !$editable) {
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
                    ReadonlyField::create('READONLY_Sent', _t('OrderLog.Sent', 'Sent'),
                        $this->obj('Sent')->Nice()),
                ], $readOnlyEmailFields));
            } else {
                $fields->addFieldsToTab('Root.Email', [
                    $selectionGroup = TabbedSelectionGroup::create('Send', [
                        SelectionGroup_Item::create(0, CompositeField::create(), _t('OrderLog.Send-NO', 'No')),
                        SelectionGroup_Item::create(1, CompositeField::create($emailFields),
                            _t('OrderLog.Send-YES', 'Yes')),
                    ])
                        ->addExtraClass('selectionGroup--minor')
                        ->showAsDropdown(true)
                        ->setTitle(_t('OrderLog.Send', 'Send as an email?')),
                ]);
            }
        });

        return parent::getCMSFields();
    }

    public function getShippedToFields()
    {
        $fields = $this->getCMSFields();

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
        $fields = $this->getCMSFields();

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
        if ($parent && ($parent instanceof Order)) {
            $dataFields = $form->Fields()->dataFields();
            $email = $this->getEmail($parent);

            if (isset($dataFields['Send_To'])) {
                $dataFields['Send_To']->setAttribute('placeholder', $email->To());
            }

            if (isset($dataFields['Send_From'])) {
                $dataFields['Send_From']->setAttribute('placeholder', $email->From());
            }

            if (isset($dataFields['Send_Subject'])) {
                $dataFields['Send_Subject']->setAttribute('placeholder', $email->Subject());
            }

            $this->OrderID = $parent->ID;

            $form->Fields()->removeByName(get_class($this) . '_EmailPreview');
            $form->Fields()->insertAfter(DataObjectPreviewField::create(
                get_class($this) . '_EmailPreview',
                new Preview($this),
                new DataObjectPreviewer(new Preview($this))
            ), 'Send_Body');
        }

        $this->extend('updateEditFormWithParent', $parent, $form, $controller);
    }

    public function log($event, $params = [], $write = true)
    {
        if (isset($this->config()->status_mapping_for_events[$event])) {
            $this->Status = _t('OrderHistory.STATUS-' . $event,
                $this->config()->status_mapping_for_events[$event]);
        } else {
            $generic = static::$GENERIC_STATUS;
            $this->Status = $generic[0];
        }

        if (isset($params['ChangeLog'])) {
            $this->ChangeLog = $params['ChangeLog'];
            unset($params['ChangeLog']);
        }

        $this->castedUpdate($params);

        if ($write) {
            $this->write();
        }

        return $this;
    }

    public function setChangeLog($data)
    {
        $this->Changes = serialize($data);
    }

    public function getChangeLog()
    {
        return unserialize($this->Changes);
    }

    public function onBeforeWrite()
    {
        if (!$this->Title) {
            $this->Title = $this->Status;
        }

        parent::onBeforeWrite();

        if (!$this->Sent && $this->Send && $this->Order()->exists() && ($email = $this->Email)) {
            $email->send();

            $this->Sent = SS_Datetime::now()->Rfc2822();
            $this->Send_To = $email->To();
            $this->Send_From = $email->From();
            $this->Send_Subject = $email->Subject();
            $this->Send_Body = $email->Body();
        }
    }

//    public function onAfterWrite()
//    {
//        if (!$this->Sent && $this->Send && ($email = $this->Email)) {
//            $email->send();
//
//            $this->Sent = \SS_Datetime::now()->Rfc2822();
//            singleton('require')->clear();
//            $this->Send_Body = $email->renderWith($email->getTemplate());
//            singleton('require')->restore();
//
//            $this->write();
//        }
//    }

    public function getTimelineIcon()
    {
        $statuses = (array)$this->config()->status_list;
        $icon = '';

        if (isset($statuses[$this->Status]) && isset($statuses[$this->Status]['icon'])) {
            $icon = $statuses[$this->Status]['icon'];
        }

        switch ($this->Status) {
            case 'Paid':
                $extraClass = '';

                if (($component = $this->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->Order()->Payments()->filter([
                        'Status'           => 'Captured',
                        'Created:LessThan' => $this->Created,
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

        if ($this->Note) {
            $details[] = $this->Note;
        }

        switch ($this->Status) {
            case 'Updated':
                $log = $this->ChangeLog;
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
                    } elseif (is_object($log['OrderItem']) && ($log['OrderItem'] instanceof OrderItem) && isset($log['Quantity'])) {
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

                    if (!$this->Order()->SeparateBillingAddress) {
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
                if (!$this->Order()->IsCart()) {
                    $allowed[] = 'Total';
                }
                $log = array_intersect_key($log, array_flip($allowed));

                if (!empty($log)) {
                    foreach ($log as $field => $trans) {
                        if (is_array($trans) && array_key_exists('before', $trans)) {
                            $details[] = $this->Order()->fieldLabel($field) . ' changed from ' . ($trans['before'] ?: '<em class="orderStatusLog-detail--none">none</em>') . ' to ' . $trans['after'];
                        } elseif (is_string($trans)) {
                            $details[] = $this->Order()->fieldLabel($field) . ': ' . $trans;
                        }
                    }
                }

                break;
            case 'Processing':
                if (($component = $this->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->Order()->Payments()->filter(['Created:LessThan' => $this->Created])->first())) {
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
                if (($component = $this->Order()->has_many('Payments')) && count($component) && ($lastPayment = $this->Order()->Payments()->filter([
                        'Status'           => 'Captured',
                        'Created:LessThan' => $this->Created,
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

        if ($this->Sent) {
            $details[] = 'Notified customer on: ' . $this->Sent;
        }

        if ($this->Author()->exists()) {
            $details[] = 'Author: ' . $this->Author()->Name;
        }

        return implode('<br/>', $details);
    }

    public function getEmail($order = null)
    {
        $order = $order ?: $this->Order();

        $email = Email::create();
        $email->setTemplate($this->config()->email_template);

        if ($this->Send_To) {
            $email->setTo($this->Send_To);
        } elseif (trim($order->Name)) {
            $email->setTo($order->Name . ' <' . $order->LatestEmail . '>');
        } else {
            $email->setTo($order->LatestEmail);
        }

        if ($this->Send_From) {
            $email->setFrom($this->Send_From);
        } else {
            if (\Config::inst()->get('OrderProcessor', 'receipt_email')) {
                $adminEmail = \Config::inst()->get('OrderProcessor', 'receipt_email');
            } else {
                $adminEmail = \Email::config()->admin_email;
            }

            $email->setFrom($adminEmail);
        }

        $subject = $this->Send_Subject ? str_replace(
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
            $this->Send_Subject
        ) : _t('Order.RECEIPT_SUBJECT', 'Web Order - {reference}', ['reference' => $order->Reference]);

        $email->setSubject($subject);

        $note = $this->Send_Body ?: Object::create('BBCodeParser', $this->Note)->parse();

        $email->populateTemplate([
            'Order'     => $order,
            'Member'    => $order->Customer(),
            'Note'      => DBField::create_field('HTMLText',
                SSViewer::execute_string($note, $this, [
                        'Order' => $order,
                    ]
                )
            ),
            'isPreview' => true,
        ]);

        $this->extend('updateEmail', $email);

        return $email;
    }

    public function getDispatchInformation()
    {
        return $this->renderWith('OrderStatusLog_DispatchInformation');
    }

    public function canDelete($member = null)
    {
        return DataObject::canDelete($member);
    }

    public function canEdit($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return singleton('OrdersAdmin')->canView($member);
    }

    public function canView($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('ADMIN') || singleton('OrdersAdmin')->canView($member)|| $this->Order()->canView($member);
    }

    public function canCreate($member = null)
    {
        $extended = $this->extendedCan(__FUNCTION__, $member);

        if ($extended !== null) {
            return $extended;
        }

        return Permission::check('ADMIN') || singleton('OrdersAdmin')->canView($member) || $this->Order()->canView($member);
    }

    public function requireDefaultRecords() {
        parent::requireDefaultRecords();

        if($this->config()->dont_upgrade_on_build) {
            return;
        }

        // Perform migrations
        DB::query(sprintf(
            'UPDATE "%s" SET "%s" = \'%s\'',
            'OrderStatusLog',
            'ClassName',
            'OrderLog'
        ));

        if(DB::get_schema()->hasField('OrderStatusLog', 'Changes')) {
            $fields = '"' . implode('", "', array_intersect(array_keys(DB::get_schema()->fieldList('OrderLog')), array_keys(DB::get_schema()->fieldList('OrderStatusLog')))) . '"';
            DB::query(sprintf(
                'INSERT INTO "%s" (%s) SELECT %s FROM "%s" ON DUPLICATE KEY UPDATE ID=VALUES(ID)',
                'OrderLog',
                $fields,
                $fields,
                'OrderStatusLog'
            ));
        }

        DB::alteration_message('Migrated order status logs', 'changed');
    }
}
