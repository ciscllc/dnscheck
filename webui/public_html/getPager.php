<?php
	require_once(dirname(__FILE__) . '/../constants.php');
	require_once(dirname(__FILE__) . '/../common.php');
	require_once(dirname(__FILE__) . '/../idna_convert.class.php');
	require_once(dirname(__FILE__) . '/../stripslashes.php');
	
	$status = STATUS_OK;
	$pageNumber = intval($_REQUEST['page']);
	$totalPages = 0;
	$history = array();
	
	$IDN = new idna_convert();
	$domain = $IDN->encode(trim(strtolower($_REQUEST['domain'])));
	
	function getDomainHistory($domain, $pageNumber)
	{
		global $history;
		global $totalPages;
		
		$query = "
			SELECT
							tests.id AS id,
							UNIX_TIMESTAMP(tests.begin) AS time,
							IF(0 < tests.count_error, 'error', IF(0 < tests.count_warning, 'warn', 'ok')) AS status
				FROM	tests
				WHERE	tests.domain = '" . DatabasePackage::escape($domain) . "'
						AND NOT(ISNULL(tests.end))
				ORDER BY time DESC
				LIMIT	" . (($pageNumber - 1) * PAGER_SIZE) . ", " . PAGER_SIZE . "
		";
		
		$result = null;
		$status = DatabasePackage::query($query, $result);
		if (false === $status)
		{
			return false;	
		}
		
		foreach ($result as $resultItem)
		{
			$history[] = array(
				'class' => $resultItem['status'],
				'time' => $resultItem['time'],
				'id' => $resultItem['id']
			);
		}
		
		$query = "
			SELECT
							COUNT(*) AS num_rows
				FROM	tests
				WHERE	tests.domain = '" . DatabasePackage::escape($domain) . "'
						AND NOT(ISNULL(tests.end))
		";
		
		$result = null;
		$status = DatabasePackage::query($query, $result);
		if (false === $status)
		{
			return false;	
		}
		
		$totalPages = intval(ceil($result[0]['num_rows'] / PAGER_SIZE));
		if (0 == $totalPages)
		{
			$totalPages = 1;
		}

		return true;
	}
	
	$allowedChars = "abcdefghijklmnopqrstuvwxyz0123456789.-";
	$domainLength = strlen($domain);
	$validDomainName = true;
	for ($i = 0; $i < $domainLength; $i++)
	{
		if (false === strpos($allowedChars, substr($domain, $i, 1)))
		{
			$validDomainName = false;
			break;
		}
	}
	
	if (($domainLength <= 1) || ($domain[0] == '-'))
	{
		$validDomainName = false;
	}

	if (($validDomainName) && (checkIfDomainExists($domain)))
	{
		if (!getDomainHistory($domain, $pageNumber))
		{
			$status = STATUS_ERROR;	
			$history = array();
		}
	}
	else
	{
		$status = STATUS_DOMAIN_DOES_NOT_EXIST;
	}

	$response = array(
		'status' => $status,
		'pageNumber' => $pageNumber,
		'totalPages' => $totalPages,
		'history' => $history
	);	

	echo(json_encode($response));
?>