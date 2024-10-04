<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Automatic Payment Redirect</title>
</head>
<body>
    <form id="paymentForm" method="post" action="https://test.authorize.net/gateway/transact.dll">
        <input type="text" name="x_login" value="{{ env('AUTHORIZE_NET_LOGIN_ID') }}">
        <input type="text" name="x_amount" value="{{ $data['payment_amount'] }}">
        <input type="text" name="x_description" value="Payment for Order #{{ $data['attribute_id'] }}">
        <input type="text" name="x_invoice_num" value="{{ $data['id'] }}">
        <input type="text" name="x_fp_sequence" value="{{ mt_rand() }}">
        <input type="text" name="x_fp_timestamp" value="{{ time() }}">
        <input type="text" name="x_fp_hash" value="{{ hash_hmac('md5', env('AUTHORIZE_NET_LOGIN_ID') . '^' . mt_rand() . '^' . time() . '^' . $data['payment_amount'] . '^', env('AUTHORIZE_NET_TRANSACTION_KEY')) }}">
        <input type="text" name="x_show_form" value="PAYMENT_FORM">
        <input type="text" name="x_relay_url" value="{{ $data['external_redirect_link'] }}">
    </form>


</body>
</html>
