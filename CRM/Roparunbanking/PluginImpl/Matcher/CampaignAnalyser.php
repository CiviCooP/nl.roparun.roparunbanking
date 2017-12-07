<?php
/*-------------------------------------------------------+
| Author: Jaap Jansma (jaap.jansma -at- civicoop.org)    |
| http://www.civicoop.org/                               |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL v3 license. You can redistribute it and/or  |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * This matcher use adds the current Roparun campaign to the transaction.
 */
class CRM_Roparunbanking_PluginImpl_Matcher_CampaignAnalyser extends CRM_Banking_PluginModel_Analyser {
	
	/**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);

    // read config, set defaults
    $config = $this->_plugin_config;

    if (!isset($config->campaign_id_attribute))     $config->campaign_id_attribute      = 'campaign_id'; 
    if (!isset($config->campaign_title_attribute))   $config->campaign_title_attribute    = 'campaign_title'; 
  }
	
	/** 
   * this matcher does not really create suggestions, but rather enriches the parsed data
   */
  public function analyse(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
  	try {
  		$currentEventId = CRM_Generic_CurrentEvent::getCurrentRoparunEventId();
			$currentCampaignId = CRM_Generic_CurrentEvent::getRoparunCampaignId($currentEventId);
			$currentCampaignTitle = CRM_Generic_CurrentEvent::getRoparunCampaignTitle($currentCampaignId);
			
			$campaign_id_attr = $this->_plugin_config->campaign_id_attribute;
			$campaign_title_attr = $this->_plugin_config->campaign_title_attribute;
			
			$data = $btx->getDataParsed();
			$data[$campaign_id_attr] = $currentCampaignId;
			$data[$campaign_title_attr] = $currentCampaignTitle;
			$btx->setDataParsed($data);
			$btx->save();
		} catch (Exception $ex) {
			
		}
	}
	
	
}