<?php

class CRM_Roparunbanking_PluginImpl_Importer_XML extends CRM_Banking_PluginImpl_Importer_XML {
	
	function initDocument($file_path, $params ) {
		$temp_file = tempnam(sys_get_temp_dir(), 'roparunbanking');
		$zip = new ZipArchive();
		if (!$zip->open($file_path)) {
			return;
		}
    $document = $zip->getFromIndex(0);
		file_put_contents($temp_file, $document);
		parent::initDocument($temp_file, $params );
	}
	
}
