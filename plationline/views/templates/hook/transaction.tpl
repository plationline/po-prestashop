{**
 * 2009-2020 Plati.Online
 *
 *  @author    Plati.Online <support@plationline.ro>
 *  @copyright 2020 Plati.Online
 *  @license   Plati.Online
 *  @version   Release: $Revision: 6.0.1
 *  @date      17/07/2018
 *}

<ps-panel icon="icon-money" header="{l s='PlatiOnline actions for transaction ID: %s' sprintf=$trans_id d='plationline'}">
    <div id="po6-ajax-loading">
        <span id="po6-wait">{l s='The requested PlatiOnline action is running, please wait...' mod='plationline'}</span>
    </div>
    <div id="po6-response"></div>
    <div class="row po6-row">
        <div class="col-sm-4">
            <button type="button" class="btn btn-primary" id="query-po6">{l s='Query tranzaction' mod='plationline'}</button>
        </div>
        <div class="col-sm-8 text-right">
            {l s='Query PlatiOnline regarding current tranzaction status' mod='plationline'}
        </div>
    </div>
    <div class="row po6-row">
        <div class="col-sm-4">
            <button type="button" class="btn btn-primary" id="void-po6">{l s='Void tranzaction' mod='plationline'}</button>
        </div>
        <div class="col-sm-8 text-right">
            {l s='Send the Void request to PlatiOnline' mod='plationline'}
        </div>
    </div>
    <div class="row po6-row">
        <div class="col-sm-4">
            <button type="button" class="btn btn-primary" id="settle-po6">{l s='Settle tranzaction' mod='plationline'}</button>
        </div>
        <div class="col-sm-8 text-right">
            {l s='Send the Settle request to PlatiOnline' mod='plationline'}
        </div>
    </div>
    <div class="row po6-row">
        <div class="col-sm-4 form-inline">
            <input value="{$amount|floatval}" class="po6-left form-control fixed-width-sm pull-left" id="refund-po6-amount" type="number" step="0.01" min="0.01" max="{$amount|floatval}">
            <span class="po6-margin-10">{$currency|escape:"htmlall":"UTF-8"}</span>
            <button type="button" class="btn btn-primary" id="refund-po6">{l s='Refund specified amount' mod='plationline'}</button>
        </div>
        <div class="col-sm-8 text-right">
            {l s='Send the Refund request to PlatiOnline' mod='plationline'}
        </div>
    </div>
</ps-panel>

<div id="page-loading" class="hidden">
    <div class="msg">
        <div class="c">
            <div class="icon"></div>
            <span class="muted" id="pl-msg">Vă rugăm să aşteptaţi, se încarcă datele</span>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(function() {
        $('#void-po6').on("click", function(e) {
            e.preventDefault();

            $.ajax({
                type: "POST",
                url: '{$absoluteUrl|escape:"htmlall":"UTF-8"}ajax.void_transaction.php',
                data: {
                    'order_id': '{$order_id|intval}',
                    'trans_id': '{$trans_id|intval}',
                    'secure_key': '{$secure_key|escape:"htmlall":"UTF-8"}',
                    'currency': '{$currency|escape:"htmlall":"UTF-8"}',
                },
                dataType: 'json',
                beforeSend: function () {
                    $('#po6-ajax-loading').show();
                    $('#po6-response').html('');
                },
                error: function (tXMLHttpRequest, textStatus, errorThrown) {
                    $('#po6-response').html('<div class="po6-alert danger">' + errorThrown + '</div>');
                    $('#po6-ajax-loading').hide();
                },
                success: function (raspuns) {
                    if (raspuns.status === "success") {
                        $('#po6-response').html('<div class="po6-alert success"><div class="po6-left"></div>' + raspuns.message + '</div>');
                    } else {
                        $('#po6-response').html('<div class="po6-alert danger"><div class="po6-left"></div>' + raspuns.message + '</div>');
                    }
                    $('#po6-ajax-loading').hide();
                }
            });
        });

        $('#query-po6').on("click", function(e) {
            e.preventDefault();

            $.ajax({
                type: "POST",
                url: '{$absoluteUrl|escape:"htmlall":"UTF-8"}ajax.query_transaction.php',
                data: {
                    'order_id': '{$order_id|intval}',
                    'trans_id': '{$trans_id|intval}',
                    'secure_key': '{$secure_key|escape:"htmlall":"UTF-8"}',
                    'currency': '{$currency|escape:"htmlall":"UTF-8"}',
                },
                dataType: 'json',
                beforeSend: function () {
                    $('#po6-ajax-loading').show();
                    $('#po6-response').html('');
                },
                error: function (tXMLHttpRequest, textStatus, errorThrown) {
                    $('#po6-response').html('<div class="po6-alert danger">' + errorThrown + '</div>');
                    $('#po6-ajax-loading').hide();
                },
                success: function (raspuns) {
                    if (raspuns.status === "success") {
                        $('#po6-response').html('<div class="po6-alert success"><div class="po6-left"></div>' + raspuns.message + '</div>');
                    } else {
                        $('#po6-response').html('<div class="po6-alert danger"><div class="po6-left"></div>' + raspuns.message + '</div>');
                    }
                    $('#po6-ajax-loading').hide();
                }
            });
        });

        $('#settle-po6').on("click", function(e){
            e.preventDefault();
            $.ajax({
                type	: "POST",
                url: '{$absoluteUrl|escape:"htmlall":"UTF-8"}ajax.settle_transaction.php',
                data: {
                    'order_id': '{$order_id|intval}',
                    'trans_id': '{$trans_id|intval}',
                    'secure_key': '{$secure_key|escape:"htmlall":"UTF-8"}',
                    'currency': '{$currency|escape:"htmlall":"UTF-8"}',
                },
                dataType: 'json',
                beforeSend: function() {
                    $('#po6-ajax-loading').show();
                    $('#po6-response').html('');
                },
                error	: function(tXMLHttpRequest, textStatus, errorThrown) {
                    $('#po6-response').html('<div class="po6-alert danger">'+errorThrown+'</div>');
                    $('#po6-ajax-loading').hide();
                },
                success	: function(raspuns){
                    if (raspuns.status === "success") {
                        $('#po6-response').html('<div class="po6-alert success"><div class="po6-left dashicons-before dashicons-warning"></div>'+raspuns.message+'</div>');
                    } else {
                        $('#po6-response').html('<div class="po6-alert danger"><div class="po6-left dashicons-before dashicons-warning"></div>'+raspuns.message+'</div>');
                    };
                    $('#po6-ajax-loading').hide();
                }
            });
        });

        $('#refund-po6').on("click", function(e){
            e.preventDefault();
            let amount = parseFloat($('#refund-po6-amount').val());
            $.ajax({
                type	: "POST",
                url: '{$absoluteUrl|escape:"htmlall":"UTF-8"}ajax.refund_transaction.php',
                data: {
                    'order_id': '{$order_id|intval}',
                    'trans_id': '{$trans_id|intval}',
                    'secure_key': '{$secure_key|escape:"htmlall":"UTF-8"}',
                    'currency': '{$currency|escape:"htmlall":"UTF-8"}',
                    'amount': amount,
                },
                dataType: 'json',
                beforeSend: function() {
                    $('#po6-ajax-loading').show();
                    $('#po6-response').html('');
                },
                error	: function(tXMLHttpRequest, textStatus, errorThrown) {
                    $('#po6-response').html('<div class="po6-alert danger">'+errorThrown+'</div>');
                    $('#po6-ajax-loading').hide();
                },
                success	: function(raspuns){
                    if (raspuns.status === "success") {
                        $('#po6-response').html('<div class="po6-alert success"><div class="po6-left dashicons-before dashicons-warning"></div>'+raspuns.message+'</div>');
                    } else {
                        $('#po6-response').html('<div class="po6-alert danger"><div class="po6-left dashicons-before dashicons-warning"></div>'+raspuns.message+'</div>');
                    };
                    $('#po6-ajax-loading').hide();
                }
            });
        });
    });
</script>
