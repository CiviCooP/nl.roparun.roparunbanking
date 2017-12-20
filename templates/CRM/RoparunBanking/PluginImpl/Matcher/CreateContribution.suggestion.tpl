{*-------------------------------------------------------+
| Project 60 - CiviBanking                               |
| Copyright (C) 2013-2014 SYSTOPIA                       |
| Author: B. Endres (endres -at- systopia.de)            |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*}

{assign var=contact_id value=$contact.id}
{capture assign=address_text}{if $contact.city}{$contact.street_address}, {$contact.city}{else}{ts}Address incomplete{/ts}{/if}{/capture}
{capture assign=contact_link}<a title="{$address_text}" href="{crmURL p="civicrm/contact/view" q="reset=1&cid=$contact_id"}">{$contact.display_name} [{$contact.id}]</a>{/capture}

{if $error}
<div>
  {ts}An error has occurred:{/ts} {$error}<br/>
  {ts}This suggestion is possibly outdated. Please try and analyse this transaction again.{/ts}
</div>
{else}
<div>
  {ts}The following contribution will be created:{/ts}
  <br/>
  <div>
    <table border="1" style="empty-cells : hide;">
      <tbody>
        <tr>
          <td>
            <div class="btxlabel">{ts}Donor{/ts}:&nbsp;</div>
            <div class="btxvalue">
            	{if $lookup_contact}
            		<input type="text" class="crm-form-entityref required" placeholder="- selecteer contact -" data-select-params="[]" data-api-params="{literal}{'extra':['email']}{/literal}" data-api-entity="contact" data-create-links="true" name="lookup_contact_id" id="lookup_contact_id" value="" />
          		{else}
            		{$contact_link}
          		{/if}
            </div>
          </td>
        </tr>
        <tr>
          <td>
            <div class="btxlabel">{ts}Type{/ts}:&nbsp;</div>
            <div class="btxvalue">
            	 <select name="roparun_create_contribution_financial_type_id" class="crm-form-select">
                  <option value="">{ts} - Select - {/ts}</option>
                  {foreach from=$financial_types item=financial_type key=financial_type_id}
                      <option value="{$financial_type_id}" {if $financial_type_id == $contribution.financial_type_id}selected="selected"{/if}>{$financial_type}</option>
                  {/foreach}
							</select>
            </div>
          </td>
        </tr>
        <tr>
          <td>
            <div class="btxlabel">{ts}Amount{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.total_amount|crmMoney:$contribution.currency}</div>
          </td>
        </tr>
        <tr>
          <td>
            <div class="btxlabel">{ts}Date{/ts}:&nbsp;</div>
            <div class="btxvalue">{$contribution.receive_date|crmDate:$config->dateformatFull}</div>
          </td>
        </tr>
        {if $campaign}
        <tr>
          <td colspan="">
            <div class="btxlabel">{ts}Campaign{/ts}:&nbsp;</div>
            <div class="btxvalue">{$campaign.title}</div>
          </td>
      	</tr>
      	{/if}
      	{if $source}
    		<tr>
          <td colspan="">
            <div class="btxlabel">{$source_label}:&nbsp;</div>
            <div class="btxvalue">{$source}</div>
          </td>
        </tr>
        {/if}
        <tr>
          <td>
            <div class="btxlabel">{ts}Op naam van Team{/ts}:&nbsp;</div>
            <div class="btxvalue">
            		<input type="text" class="crm-form-entityref required" placeholder="- selecteer contact -" data-select-params="[]" data-api-params="{literal}{'extra':['email']}{/literal}" data-api-entity="contact" data-create-links="true" name="team_contact_id" id="team_contact_id" value="{$contribution.team_contact_id}" />
            </div>
          </td>
        </tr>
        <tr>
          <td>
            <div class="btxlabel">{ts}Op naam van Deelnemer{/ts}:&nbsp;</div>
            <div class="btxvalue">
            		<input type="text" class="crm-form-entityref required" placeholder="- selecteer contact -" data-select-params="[]" data-api-params="{literal}{'extra':['email']}{/literal}" data-api-entity="contact" data-create-links="true" name="teamlid_contact_id" id="teamlid_contact_id" value="" />
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
{/if}