<?php
	for ($i=1;$i<100;$i++)
	{
	if (!file_exists("DROID_SignatureFile_V$i.xml")) {
		$cmd = "wget http://www.nationalarchives.gov.uk/documents/DROID_SignatureFile_V$i.xml";
		exec($cmd);
	}
	}
?>
