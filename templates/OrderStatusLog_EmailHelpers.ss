<h3 class="order-status-log--email-helpers--title">Email Helpers</h3>
<dl class="order-status-log--email-helpers">
    <dt class="order-status-log--email-helpers--title">\$Order</dt>
    <dd class="order-status-log--email-helpers--value">
        The order related to this log. Some common fields are outlined below.
        <ul class="order-status-log--email-helpers--list">
            <li class="order-status-log--email-helpers--list-item">\$Order.Reference</li>
            <li class="order-status-log--email-helpers--list-item">\$Order.ShippingAddress</li>
            <li class="order-status-log--email-helpers--list-item">\$Order.BillingAddress</li>
            <li class="order-status-log--email-helpers--list-item">\$Order.Customer.Name</li>
            <li class="order-status-log--email-helpers--list-item">\$Order.Total</li>
            <li class="order-status-log--email-helpers--list-item">\$Order.Items.count</li>
        </ul>
    </dd>
    <dt class="order-status-log--email-helpers--title">\$DispatchInformation</dt>
    <dd class="order-status-log--email-helpers--value">The dispatch information for this order</dd>
    <dt class="order-status-log--email-helpers--title">\$State</dt>
    <dd class="order-status-log--email-helpers--value">The current state of the order</dd>
</dl>