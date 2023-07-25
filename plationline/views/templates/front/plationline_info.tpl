{**
 * 2009-2023 Plati.Online
 *
 *  @author    Plati.Online <support@plationline.ro>
 *  @copyright 2023 Plati.Online
 *  @license   Plati.Online
 *  @version   Release: $Revision: 6.0.6
 *  @date      06/03/2023
 *}

<section>
    {if $logos}
        {html_image alt="PlatiOnline" file="{$logos}"}
    {/if}
        
    <p>{$redirect_message|escape:'htmlall':'UTF-8'}</p>
</section>
