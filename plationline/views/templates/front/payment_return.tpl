{**
 * 2009-2023 Plati.Online
 *
 *  @author    Plati.Online <support@plationline.ro>
 *  @copyright 2023 Plati.Online
 *  @license   Plati.Online
 *  @version   Release: $Revision: 6.0.6
 *  @date      06/03/2023
 *}

{extends file='page.tpl'}

{block name="content"}
<section id="content-payment-return" class="card definition-list">
	<div class="card-block">
      <div class="row">
        <div class="col-md-12">
            <p>
                <h3 class="{$text_color|escape:'htmlall':'UTF-8'}">{$text|escape:'htmlall':'UTF-8'}</h3>
                <div class="row-fluid">
                	<a class="btn btn-info" href="{$url_redirect|escape:'htmlall':'UTF-8'}">{$see_order|escape:'htmlall':'UTF-8'}</a>
                </div>
            </p>
        </div>
      </div>
    </div>
</section>
{/block}
