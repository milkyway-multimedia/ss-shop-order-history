<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>$Subject</title>
    <style>$inlineFile('css/email.css,shop-order-history/css/email.css',1)</style>
</head>
<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" bgcolor="#f4f4f4"
      style="-ms-text-size-adjust: none; -webkit-text-size-adjust: none; height: 100% !important; margin: 0; padding: 0; width: 100% !important">

<table cellpadding="0" cellspacing="0" border="0" height="100%" width="100%" bgcolor="#f5f5f5" id="body"
       style="-ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; background: #f5f5f5; border-collapse: collapse; border-spacing: 0; height: 100% !important; margin: 30px auto;margin-bottom:0; mso-table-lspace: 0; mso-table-rspace: 0; padding: 0; table-layout: fixed; width: 100% !important"
       class="email-holder">
    <tr>
        <td style="-ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0">

            <table border="0" width="600" cellpadding="0" cellspacing="0" align="center" bgcolor="#ffffff"
                   style="-ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; border-collapse: collapse; border-color: #e5e5e5; border-spacing: 0; border-style: solid; border-width: 0; margin: auto; mso-table-lspace: 0; mso-table-rspace: 0"
                   class="email-container">
                <tr>
                    <td style="-ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; border-bottom-color: #e5e5e5; border-bottom-style: solid; border-bottom-width: 1px; border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0">
                        <table border="0" width="100%" cellpadding="0" cellspacing="0" align="center"
                               style="-ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; border-collapse: collapse; border-spacing: 0; mso-table-lspace: 0; mso-table-rspace: 0">
                            <tr>
                                <td style="-ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; border-collapse: collapse; color: #444444; font-family: 'Helvetica Neue LT Std', 'Helvetic Neue', 'Helvetica', 'Arial', sans-serif; font-size: 14px; font-weight: 300; line-height: 20px; mso-table-lspace: 0; mso-table-rspace: 0;">

                                    <% if $Content %>
                                        <div class="order-receipt--message">
                                            $Content
                                        </div>
                                    <% end_if %>

                                    <% if $Order %>
                                        <% with $Order %>
                                            <% include Order_Receipt %>
                                        <% end_with%>
                                    <% end_if %>

                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table border="0" width="600" cellpadding="0" cellspacing="0" align="center" class="email-container"
                   style="-ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; border-collapse: collapse; border-spacing: 0; margin: auto; mso-table-lspace: 0; mso-table-rspace: 0">
                <tr>
                    <td class="force-col-center" valign="middle"
                        style="-ms-text-size-adjust: 100%; -webkit-text-size-adjust: 100%; border-collapse: collapse; mso-table-lspace: 0; mso-table-rspace: 0; padding: 20px 0; text-align: center; color: #CCCCCC; font-family: 'Helvetica Neue LT Std', 'Helvetic Neue', 'Helvetica', 'Arial', sans-serif; font-size: 12px; font-weight: 300;text-align: center;"
                        align="center">
                        <% if $SiteConfig %>
                            <div class="order-receipt--footer">

                                <% with $SiteConfig %>
                                    <% include Address extraClass='order-receipt--company' %>
                                <% end_with %>

                            </div>
                        <% end_if %>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
