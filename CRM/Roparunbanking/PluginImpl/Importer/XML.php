<?php

class CRM_Roparunbanking_PluginImpl_Importer_XML extends CRM_Banking_PluginImpl_Importer_XML {
	
	protected $extractedFiles = array();
	
	function extractZip($file_path ) {
		if (count($this->extractedFiles)) {
			// The file is already extracted
			return;
		}
		
		$zip = new ZipArchive();
		if (!$zip->open($file_path)) {
			return;
		}
		for ($i = 0; $i<$zip->numFiles; $i++) {
			$name = $zip->getNameIndex($i);
			$temp_file = tempnam(sys_get_temp_dir(), $name);
    	$document = $zip->getFromIndex($i);
			file_put_contents($temp_file, $document);
			$this->extractedFiles[] = $temp_file;
		}
		asort($this->extractedFiles);
	}
	
	/**
   * Test if the given file can be imported
   *
   * @var
   * @return TODO: data format?
   */
  function probe_file( $file_path, $params )
  {
  	$this->extractZip($file_path);
		foreach($this->extractedFiles as $file) {
	    $this->initDocument($file, $params);
	    // TODO: error handling
	
	    if (isset($this->_plugin_config->probe)) {
	      $value = $this->xpath->query($this->_plugin_config->probe);
	      if (get_class($value)=='DOMNodeList') {
	        $result = $value->length > 0;
	      } else {
	        $result = !empty($value);
	      }
	    } else {
	      // no probe string set -> done.
	      $result = true;
	    }
			
			if (!$result) {
				return false;
			}
		}
		return true;
  }
	
	/**
   * Imports the given XML file
   *
   */
  function import_file( $file_path, $params )
  {
    // Init
    $config = $this->_plugin_config;
    $this->reportProgress(0.0, sprintf("Starting to read file '%s'...", $params['source']));
		
		$this->extractZip($file_path);
		foreach($this->extractedFiles as $file) {
			$batch = $this->openTransactionBatch();
			$this->initDocument($file, $params);
	    // execute rules for statement
	    $data = array();
	    foreach ($config->rules as $rule) {
	      $this->apply_rule($rule, NULL, $data);
	    }
	
	    // collect payment indentifier specs
	    //  $config->payments is a single, deprecated spec
	    $payment_specs = array();
	    if (!empty($config->payments)) {
	      $payment_specs[] = $config->payments;
	    }
	    foreach ($config->payment_lines as $payment_line) {
	      $payment_specs[] = $payment_line;
	    }
	
	    $index = 0;
	    foreach ($payment_specs as $payment_spec) {
	      // compile filter list
	      if (!empty($payment_spec->filters)) {
	        $filters = $payment_spec->filters;
	      } else {
	        $filters = array();
	      }
	      if (!empty($payment_spec->filter)) {
	        $filters[] = $payment_spec->filter;
	      }
	
	      // iterate nodes
	      $payments = $this->xpath->query($payment_spec->path);
	      foreach ($payments as $payment_node) {
	        $index += 1;
	
	        // evaluate filters
	        $node_accepted = TRUE;
	        foreach ($filters as $filter) {
	          $filter_maches = $this->filterMatches($payment_node, $filter);
	          if ($filter_maches) {
	            $node_accepted = FALSE;
	            break;
	          }
	        }
	        if (!$node_accepted) continue;
	
	        // import the line
	        $this->import_payment($payment_spec, $payment_node, $data, $index, $payments->length, $params);
	      }
	    }
	
	    // finish statement object
	    if ($this->getCurrentTransactionBatch()->tx_count) {
	      // copy all data entries starting with tx.batch into the batch
	      if (!empty($data['tx_batch.reference'])) {
	        $this->getCurrentTransactionBatch()->reference = $data['tx_batch.reference'];
	      } else {
	        $this->getCurrentTransactionBatch()->reference = "XML-File {md5}";
	      }
	
	      if (!empty($data['tx_batch.sequence']))
	        $this->getCurrentTransactionBatch()->sequence = $data['tx_batch.sequence'];
	      if (!empty($data['tx_batch.starting_date']))
	        $this->getCurrentTransactionBatch()->starting_date = $data['tx_batch.starting_date'];
	      if (!empty($data['tx_batch.ending_date']))
	        $this->getCurrentTransactionBatch()->ending_date = $data['tx_batch.ending_date'];
	
	      $this->closeTransactionBatch(TRUE);
	    } else {
	      $this->closeTransactionBatch(FALSE);
	    }
    }
    $this->reportDone();
  }
	
}
