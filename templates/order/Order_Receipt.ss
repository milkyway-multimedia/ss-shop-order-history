<div id="<% if $containerId %>$containerId<% else %>Order-Receipt<% end_if %>" class="order-receipt">
    <div class="order-receipt--summary">
        <h2 class="order-receipt--summary-reference">
            <span class="order-receipt--summary-reference--title">Order Reference: </span>
            <strong class="order-receipt--summary-reference--value">$Reference</strong>
        </h2>
        <aside class="order-receipt--summary-reference--note">
            Please keep a record of your order reference.
        </aside>
        <h5 class="order-receipt--summary-placed">
            <span class="order-receipt--summary-placed--title">Placed on: </span>
            <strong class="order-receipt--summary-placed--value">$Placed.Long at $Placed.Time</strong>
        </h5>
    </div>

    <% if $Notes %>
        <div class="order-receipt--notes">
            <h3 class="order-receipt--title order-receipt--notes--title">
                <span><% _t("ORDER.ORDERNOTES","Notes") %></span></h3>
            $Notes
        </div>
    <% end_if %>

    <% if $ShippingAddress || $BillingAddress %>
        <div class="order-receipt--address">
            <% if $ShippingAddress %>
                <div class="order-receipt--address-item order-receipt--address-item_shipping">
                    <h3 class="order-receipt--title order-receipt--address-item--title">
                        <span><% _t("ORDER.SHIPTO","Ship To") %></span></h3>
                    <% with $ShippingAddress %>
                        <% include Address extraClass='order-item--address-item--details' %>
                    <% end_with %>
                </div>
            <% end_if %>
            <% if $BillingAddress %>
                <div class="order-receipt--address-item order-receipt--address-item_billing">
                    <h3 class="order-receipt--title order-receipt--address-item--title">
                        <span><% _t("ORDER.BILLTO","Bill To") %></span></h3>
                    <% with $BillingAddress %>
                        <% include Address extraClass='order-item--address-item--details' %>
                    <% end_with %>
                </div>
            <% end_if %>
        </div>
    <% end_if %>

    <% if $Items %>
    <div class="order-receipt--items<% if not $ordersItemsTitle %> order-receipt--items_no-title<% end_if %>">
        <% if $ordersItemsTitle %>
            <h3 class="order-receipt--title order-receipt--items--title"><span>$ordersItemsTitle</span></h3>
        <% end_if %>
        <div class="order-receipt--items--list">
            <% if $ReceiptForm %>
                $ReceiptForm
            <% else %>
                <% include Cart %>
            <% end_if %>
        </div>
    </div>
    <% end_if %>

    <% if $Total %>
        <% if $Payments %>
            <div class="order-receipt--payments-holder">
                <h3 class="order-receipt--title order-receipt--payments-holder--title"><span>Payments</span></h3>
                <% include Order_Payments %>
            </div>
        <% end_if %>

        <% if $TotalOutstanding %>
            <h2 class="order-receipt--title order-receipt--outstanding">
                <label><% _t("TOTALOUTSTANDING","Total outstanding") %></label>
                <strong class="order-receipt--outstanding-total">$TotalOutstanding.Nice </strong>
            </h2>
        <% end_if %>
    <% end_if %>
</div>

<% if $orderReceiptWithFooter && $SiteConfig %>
    <div class="order-receipt--footer">

        <% with $SiteConfig %>
            <% include Address extraClass='order-receipt--company' %>
        <% end_with %>

    </div>
<% end_if %>
