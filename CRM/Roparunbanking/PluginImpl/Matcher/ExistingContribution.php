<?php

class CRM_Roparunbanking_PluginImpl_Matcher_ExistingContribution extends CRM_Banking_PluginImpl_Matcher_ExistingContribution {
  
  /**
   * class constructor
   */ 
  function __construct($config_name) {
    parent::__construct($config_name);
    $config = $this->_plugin_config;
    if (!isset($config->contact_fields))         $config->contact_fields = array('contact_id' => 0.9);
  }
	
	protected function findContacts(CRM_Banking_BAO_BankTransaction $btx) {
	  $config = $this->_plugin_config;
		$data_parsed = $btx->getDataParsed();
		$contacts = array();
		// then look up potential contacts
    foreach($config->contact_fields as $field => $probability) {
      if (!empty($data_parsed[$field])) {
        $contacts[$data_parsed[$field]] = $probability;
      }  
    }
		if (isset($data_parsed['team_nr']) && !empty($data_parsed['team_nr'])) {		
			$config = CRM_Generic_Config::singleton();
			$accepted_status_ids = implode(", ", $this->getAcceptedContributionStatusIDs());
			$sql = "SELECT DISTINCT contribution.contact_id
						FROM `civicrm_contribution` `contribution`
						INNER JOIN `{$config->getDonatedTowardsCustomGroupTableName()}` `towards` ON `contribution`.`id` = `towards`.`entity_id`
						INNER JOIN `civicrm_participant` ON `civicrm_participant`.`contact_id` = `towards`.`{$config->getTowardsTeamCustomFieldColumnName()}`
						INNER JOIN `{$config->getTeamDataCustomGroupTableName()}` `team_data` ON `civicrm_participant`.`id` = `team_data`.`entity_id`
						WHERE `contribution`.`contribution_status_id` IN ({$accepted_status_ids}) AND contribution.is_test = 0
						AND `team_data`.`{$config->getTeamNrCustomFieldColumnName()}` = %1";
			$sqlParams[1] = array($data_parsed['team_nr'], 'String');
			$dao = CRM_Core_DAO::executeQuery($sql, $sqlParams);
			while($dao->fetch()) {
				if (!isset($contacts_found[$dao->contact_id])) {
					$contacts[$dao->contact_id] = 0.5;
				}
			}
		}
		
		return $contacts;
	}

  public function getPotentialContributionsForCampaign($campaign_id, CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;
    $data_parsed = $btx->getDataParsed();
    
    $accepted_status_ids = implode(", ", $this->getAcceptedContributionStatusIDs());
    $booking_date = DateTime::createFromFormat('YmdHis', $btx->booking_date);
    $booking_date = $booking_date->format('Y-m-d');
    $value_date = DateTime::createFromFormat('YmdHis', $btx->value_date);
    $value_date = $value_date->format('Y-m-d');
    $date_interval = 1;

    // check in cache
    $cache_key = "_contributions_campaign_${campaign_id}_".base64_encode($accepted_status_ids);
    $contributions = $context->getCachedEntry($cache_key);
    
    if ($contributions != NULL) return $contributions;

    $contributions = array();  
    
    $sql = "SELECT *
            FROM `civicrm_contribution` `contribution`
            WHERE `contribution`.`contribution_status_id` IN ({$accepted_status_ids}) 
            AND `contribution`.`campaign_id` = %1
            AND is_test = '0'
            AND (
                  (
                    receive_date > (DATE('{$booking_date}') - INTERVAL {$date_interval} DAY) 
                    AND 
                    receive_date < (DATE('{$booking_date}') + INTERVAL {$date_interval} DAY)
                  )
                  OR
                  (
                    receive_date > (DATE('{$value_date}') - INTERVAL {$date_interval} DAY) 
                    AND 
                    receive_date < (DATE('{$value_date}') + INTERVAL {$date_interval} DAY)
                  )
                )";

    $sqlParams[1] = array($campaign_id, 'Integer');
    $contribution = CRM_Contribute_DAO_Contribution::executeQuery($sql, $sqlParams);
    while ($contribution->fetch()) {
      array_push($contributions, $contribution->toArray());
    }

    // cache result and return
    $context->setCachedEntry($cache_key, $contributions);
    return $contributions;
  }
	
	/** 
   * Generate a set of suggestions for the given bank transaction
   * 
   * @return array(match structures)
   */
  public function match(CRM_Banking_BAO_BankTransaction $btx, CRM_Banking_Matcher_Context $context) {
    $config = $this->_plugin_config;
    $threshold   = $this->getThreshold();
    $penalty     = $this->getPenalty($btx);
    $data_parsed = $btx->getDataParsed();

    // first see if all the required values are there
    if (!$this->requiredValuesPresent($btx)) return null;

    // resolve accepted states
    $accepted_status_ids = $this->getAcceptedContributionStatusIDs();

    $contributions = array();
    $contribution2contact = array();
    $contribution2totalamount = array();
    $contributions_identified = array();

    // check if this is actually enabled
    if ($config->contribution_search) {
      // find contacts    
      $contacts_found = $this->findContacts($btx);

      // with the identified contacts, look up contributions
      foreach ($contacts_found as $contact_id => $contact_probabiliy) {
        if ($contact_probabiliy < $threshold) continue;

        $potential_contributions = $this->getPotentialContributionsForContact($contact_id, $context);
        foreach ($potential_contributions as $contribution) {
          // check for expected status
          if (!in_array($contribution['contribution_status_id'], $accepted_status_ids)) continue;

          $contribution_probability = $this->rateContribution($contribution, $context);

          // apply penalty
          $contribution_probability -= $penalty;

          if ($contribution_probability > $threshold) {
            $contributions[$contribution['id']] = $contribution_probability;
            $contribution2contact[$contribution['id']] = $contact_id;
            $contribution2totalamount[$contribution['id']] = $contribution['total_amount'];
          }        
        }
      }
      
      if (empty($contributions) && !empty($data_parsed['campaign_id'])) {
        $potential_contributions = $this->getPotentialContributionsForCampaign($data_parsed['campaign_id'], $btx, $context);
        foreach ($potential_contributions as $contribution) {
          // check for expected status
          if (!in_array($contribution['contribution_status_id'], $accepted_status_ids)) continue;

          $contribution_probability = $this->rateContribution($contribution, $context);

          // apply penalty
          $contribution_probability -= 0.6;

          if ($contribution_probability > $threshold) {
            $contributions[$contribution['id']] = $contribution_probability;
            $contribution2contact[$contribution['id']] = $contact_id;
            $contribution2totalamount[$contribution['id']] = $contribution['total_amount'];
          }        
        }
      }
    }

    // add the contributions coming in from a list (if any)
    if (!empty($config->contribution_list)) {
      if (!empty($data_parsed[$config->contribution_list])) {
        $id_list = explode(',', $data_parsed[$config->contribution_list]);
        foreach ($id_list as $contribution_id_string) {
          $contribution_id = (int) $contribution_id_string;
          if ($contribution_id) {
            $contribution_bao = new CRM_Contribute_DAO_Contribution();
            if ($contribution_bao->get('id', $contribution_id)) {
              $contribution = $contribution_bao->toArray();

              // check for expected status
              if (!in_array($contribution['contribution_status_id'], $accepted_status_ids)) continue;

              $contribution_probability = $this->rateContribution($contribution, $context);

              // apply penalty
              $contribution_probability -= $penalty;

              if ($contribution_probability > $threshold) {
                $contributions[$contribution['id']] = $contribution_probability;
                $contribution2contact[$contribution['id']] = $contribution['contact_id'];
                $contribution2totalamount[$contribution['id']] = $contribution['total_amount'];
                $contacts_found[$contribution['contact_id']] = 1.0;
                $contributions_identified[] = $contribution['id'];
              }
            }
          }
        }
      }
    }

    // transform all of the contributions found into suggestions
    foreach ($contributions as $contribution_id => $contribution_probability) {
      $contact_id = $contribution2contact[$contribution_id];
      $suggestion = new CRM_Banking_Matcher_Suggestion($this, $btx);
      if (!in_array($contribution_id, $contributions_identified)) {
        if ($contacts_found[$contact_id]>=1.0) {
          $suggestion->addEvidence(1.0, ts("Contact was positively identified."));
        } else {
          $suggestion->addEvidence($contacts_found[$contact_id], ts("Contact was likely identified."));
        }        
      }
      
      if ($contribution_probability>=1.0) {
        $suggestion->setTitle(ts("Matching contribution found"));
        if ($config->mode != "cancellation") {
          $suggestion->addEvidence(1.0, ts("A pending contribution matching the transaction was found."));
        } else {
          $suggestion->addEvidence(1.0, ts("This transaction is the <b>cancellation</b> of the below contribution."));
        }
      } else {
        $suggestion->setTitle(ts("Possible matching contribution found"));
        if ($config->mode != "cancellation") {
          $suggestion->addEvidence($contacts_found[$contact_id], ts("A pending contribution partially matching the transaction was found."));
        } else {
          $suggestion->addEvidence($contacts_found[$contact_id], ts("This transaction could be the <b>cancellation</b> of the below contribution."));
        }
      }

      $suggestion->setId("existing-$contribution_id");
      $suggestion->setParameter('contribution_id', $contribution_id);
      $suggestion->setParameter('contact_id', $contact_id);
      $suggestion->setParameter('mode', $config->mode);

      // generate cancellation extra parameters
      if ($config->mode == 'cancellation') {
        if ($config->cancellation_cancel_reason) {
          // determine the cancel reason
          if (empty($data_parsed[$config->cancellation_cancel_reason_source])) {
            $suggestion->setParameter('cancel_reason', $config->cancellation_cancel_reason_default);
          } else {
            $suggestion->setParameter('cancel_reason', $data_parsed[$config->cancellation_cancel_reason_source]);
          }
        }
        if ($config->cancellation_cancel_fee) {
          // calculate / determine the cancellation fee
          try {
            $meval = new EvalMath();
            // first initialise variables 'difference' and 'source'
            $meval->evaluate("difference = -{$btx->amount} - {$contribution2totalamount[$contribution_id]}");
            if (empty($config->cancellation_cancel_fee_source) || empty($data_parsed[$config->cancellation_cancel_fee_source])) {
              $meval->evaluate("source = 0.0");
            } else {
              $meval->evaluate("source = {$data_parsed[$config->cancellation_cancel_fee_source]}");
            }
            $suggestion->setParameter('cancel_fee', $meval->evaluate($config->cancellation_cancel_fee_default));
          } catch (Exception $e) {
            error_log("org.project60.banking.matcher.existing: Couldn't calculate cancellation_fee. Error was: $e");
          }
        }
      }

      // set probability manually, I think the automatic calculation provided by ->addEvidence might not be what we need here
      $suggestion->setProbability($contribution_probability*$contacts_found[$contact_id]);

      // update title if requested
      if (!empty($config->title)) $suggestion->setTitle($config->title);

      $btx->addSuggestion($suggestion);
    }

    // that's it...
    return empty($this->_suggestions) ? null : $this->_suggestions;
  }
	
	
} 