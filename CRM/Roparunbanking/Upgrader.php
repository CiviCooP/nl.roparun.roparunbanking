<?php
use CRM_Roparunbanking_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Roparunbanking_Upgrader extends CRM_Roparunbanking_Upgrader_Base {

  public function install() {
  	// Do nothing  
  }

  /**
   * Example: Work with entities usually not available during the install step.
   *
   * This method can be used for any post-install tasks. For example, if a step
   * of your installation depends on accessing an entity that is itself
   * created during the installation (e.g., a setting or a managed entity), do
   * so here to avoid order of operation problems.
   */
  public function postInstall() {
    // Do nothing
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled.
   */
  public function uninstall() {
   // Remove banking options
   $this->removeBankingOptions($this->bankingOptions());
  }

  /**
   * Example: Run a simple query when a module is enabled.
   */
  public function enable() {
    // Install banking options
    banking_civicrm_install_options($this->bankingOptions());
  }

  /**
   * Example: Run a simple query when a module is disabled.
   */
  public function disable() {
    // Do nothing
  }
	
	private function bankingOptions() {
		return array(
      'civicrm_banking.plugin_types' => array(
          'title' => 'CiviBanking plugin types',
          'description' => 'The set of possible CiviBanking plugin types',
          'values' => array(
          	'importer_zip' => array(
                  'label' => 'Configurable XML (ZIP) Importer',
                  'value' => 'CRM_Roparunbanking_PluginImpl_Importer_XML',
                  'description' => 'This importer should be configurable to import a variety of XML based data compressed in a zip.',
                  'is_default' => 0,
              ),
              'analyser_campaign' => array(
                  'label' => 'Campaign Analyser',
                  'value' => 'CRM_Roparunbanking_PluginImpl_Matcher_CampaignAnalyser',
                  'description' => 'Enriches the transactions information with the current campaign.',
                  'is_default' => 0,
              ),
              'matcher_create_contribution_roparun' => array(
              	'label' => 'Create contribution (Roparun specific)',
              	'value' => 'CRM_Roparunbanking_PluginImpl_Matcher_CreateContribution',
              	'description' => 'Create a contribution with the possibility to add extra information such as financial type',
              	'is_default' => 0,
            	),
          ),
      ),
    );
	}

	/**
 	 * Remove the option values
 	 */
	private function removeBankingOptions($data) {
  	foreach ($data as $groupName => $group) {
    	// check group existence
    	$result = civicrm_api('option_group', 'getsingle', array('version' => 3, 'name' => $groupName));
    	if (isset($result['id']) || !$result['id']) {
      	$group_id = $result['id'];

    		if (is_array($group['values'])) {
      		$groupValues = $group['values'];
      		foreach ($groupValues as $valueName => $value) {
        		// find option value
        		$result = civicrm_api3('OptionValue', 'get', array(
          		'name'            => $valueName,
          		'option_group_id' => $group_id
          	));
        		if (count($result['values']) != 0) {
          		// update existing entry
          		$value = reset($result['values']); // update
          		$params['id'] = $value['id']; 
        			$result = civicrm_api3('option_value', 'delete', $params);
      			}
    			}
  			}
			}
		}
	}
	
}
