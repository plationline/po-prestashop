{**
 * 2009-2020 Plati.Online
 *
 *  @author    Plati.Online <support@plationline.ro>
 *  @copyright 2021 Plati.Online
 *  @license   Plati.Online
 *  @version   Release: $Revision: 6.0.1
 *  @date      17/07/2018
 *}

<div class="alert">
	<h2>
		<img src="../modules/plationline/logo.png" style="float:left; margin-right:15px;" width="58" height="22">
		<p><strong>{l s='Online payment by card and Login with Plati.Online account' d='plationline'}</strong></p>
	</h2>
	<p>{l s='The settings needed to setup Plati.Online module can be obtained from merchants account by accesing the following link: ' mod='plationline'} <a rel="noopener norefferer" href="https://merchants.plationline.ro" target="_blank"><strong>https://merchants.plationline.ro</strong></a></p>
	<p>
		{capture assign="itsnurl"}
			{l s='Copy the ITSN URL: [strong]%s[/strong] to your merchant account in the Settings section' sprintf=$itsn_url mod='plationline'}
		{/capture}
		{$itsnurl|replace:'[strong]':'<strong>'|replace:'[/strong]':'</strong>'}
	</p>
</div>
