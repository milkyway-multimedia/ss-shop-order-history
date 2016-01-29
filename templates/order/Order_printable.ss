<% if $asWebPage %>
    <html>
    <head>
        <title>$Title</title>
    </head>
    <body>
<% end_if %>

<div class="order-printable--action-holder">
    <a href="#" class="order-printable--action" data-print="true">Print</a>
</div>

<% include Order_Receipt orderReceiptWithFooter=1 %>

<% if $asWebPage %>
    </body>
    </html>
<% end_if %>
