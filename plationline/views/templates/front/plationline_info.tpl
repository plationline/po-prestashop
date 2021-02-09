{**
 * 2009-2020 Plati.Online
 *
 *  @author    Plati.Online <support@plationline.ro>
 *  @copyright 2021 Plati.Online
 *  @license   Plati.Online
 *  @version   Release: $Revision: 6.0.1
 *  @date      17/07/2018
 *}

<section>
    {if $logos}
        {html_image alt="PlatiOnline" file="{$logos}"}
    {/if}
        
    <p>{$redirect_message|escape:'htmlall':'UTF-8'}</p>
</section>
