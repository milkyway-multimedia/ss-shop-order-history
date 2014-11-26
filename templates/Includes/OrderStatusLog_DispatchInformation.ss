<dl class="order-statusLog--dispatchInformation">
    <% if $DispatchTicket %>
        <dt>Tracking ID</dt>
        <dd>$DispatchTicket</dd>
    <% end_if %>

    <% if $DispatchBy %>
        <dt>Carrier</dt>
        <dd>$DispatchBy</dd>
    <% end_if %>

    <dt>Date</dt>
    <dd><% if $DispatchedOn %>$DispatchedOn.Long<% else_if $Created %>$Created.Long<% else %>$Now.Long<% end_if %></dd>
</dl>