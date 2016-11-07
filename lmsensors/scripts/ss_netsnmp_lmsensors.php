<?php

#
# ss_netsnmp_lmsensors.php
# version 0.9a
# November 11, 2010
#
# Copyright (C) 2006-2010, Eric A. Hall
# http://www.eric-a-hall.com/
#
# This software is licensed under the same terms as Cacti itself
#

#
# load the Cacti configuration settings if they aren't already present
#
if (isset($config) == FALSE) {

	if (file_exists(dirname(__FILE__) . "/../include/config.php")) {
		include_once(dirname(__FILE__) . "/../include/config.php");
	}

	if (file_exists(dirname(__FILE__) . "/../include/global.php")) {
		include_once(dirname(__FILE__) . "/../include/global.php");
	}

	if (isset($config) == FALSE) {
		echo ("FATAL: Unable to load Cacti configuration files \n");
		return;
	}
}

#
# load the Cacti SNMP libraries if they aren't already present
#
if (defined('SNMP_METHOD_PHP') == FALSE) {

	if (file_exists(dirname(__FILE__) . "/../lib/snmp.php")) {
		include_once(dirname(__FILE__) . "/../lib/snmp.php");
	}

	if (defined('SNMP_METHOD_PHP') == FALSE) {
		echo ("FATAL: Unable to load SNMP libraries \n");
		return;
	}
}

#
# call the main function manually if executed outside the Cacti script server
#
if (isset($GLOBALS['called_by_script_server']) == FALSE) {

	array_shift($_SERVER["argv"]);
	print call_user_func_array("ss_netsnmp_lmsensors", $_SERVER["argv"]);
}

#
# main function
#
function ss_netsnmp_lmsensors($protocol_bundle="", $sensor_type="",
	$cacti_request="", $data_request="", $data_request_key="") {

	#
	# 1st function argument contains the protocol-specific bundle
	#
	# use '====' matching for strpos in case colon is 1st character
	#
	if ((trim($protocol_bundle) == "") || (strpos($protocol_bundle, ":") === FALSE)) {

		echo ("FATAL: No SNMP parameter bundle provided\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	$protocol_array = explode(":", $protocol_bundle);

	if (count($protocol_array) < 11) {

		echo ("FATAL: Not enough elements in SNMP parameter bundle\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	if (count($protocol_array) > 11) {

		echo ("FATAL: Too many elements in SNMP parameter bundle\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	#
	# 1st bundle element is $snmp_hostname
	#
	$snmp_hostname = trim($protocol_array[0]);

	if ($snmp_hostname == "") {

		echo ("FATAL: Hostname not specified in SNMP parameter bundle\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	#
	# 2nd bundle element is $snmp_version
	#
	$snmp_version = trim($protocol_array[1]);

	if ($snmp_version == "") {

		echo ("FATAL: SNMP version not specified in SNMP parameter bundle\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	if (($snmp_version != 1) and ($snmp_version != 2) and ($snmp_version != 3)) {

		echo ("FATAL: \"$snmp_version\" is not a valid SNMP version\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	#
	# 3rd bundle element is $snmp_community
	#
	$snmp_community = trim($protocol_array[2]);

	if (($snmp_version != 3) and ($snmp_community == "")) {

		echo ("FATAL: SNMP v$snmp_version community not specified in SNMP parameter bundle\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	#
	# 4th bundle element is $snmp_v3_username
	#
	$snmp_v3_username = trim($protocol_array[3]);

	#
	# 5th bundle element is $snmp_v3_password
	#
	$snmp_v3_password = trim($protocol_array[4]);

	#
	# 6th bundle element is $snmp_v3_authproto
	#
	$snmp_v3_authproto = trim($protocol_array[5]);

	#
	# 7th bundle element is $snmp_v3_privpass
	#
	$snmp_v3_privpass = trim($protocol_array[6]);

	#
	# 8th bundle element is $snmp_v3_privproto
	#
	$snmp_v3_privproto = trim($protocol_array[7]);

	#
	# 9th bundle element is $snmp_v3_context
	#
	$snmp_v3_context = trim($protocol_array[8]);

	#
	# 10th bundle element is $snmp_port
	#
	$snmp_port = trim($protocol_array[9]);

	if ($snmp_port == "") {

		#
		# if the value was omitted use the default port number
		#
		$snmp_port = 161;
	}

	if (is_numeric($snmp_port) == FALSE) {

		echo ("FATAL: Non-numeric SNMP port \"$snmp_port\" specified in SNMP parameter bundle\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	#
	# 11th bundle element is $snmp_timeout
	#
	$snmp_timeout = trim($protocol_array[10]);

	if ($snmp_timeout == "") {

		#
		# if the value was omitted use the global default timeout
		#
		$snmp_timeout = read_config_option("snmp_timeout");
	}

	if (is_numeric($snmp_timeout) == FALSE) {

		echo ("FATAL: Non-numeric SNMP timeout \"$snmp_timeout\" specified in SNMP parameter bundle\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	#
	# these aren't parameters, but go ahead and out $snmp_retries and $snmp_maxoids
	# from the global settings
	#
	$snmp_retries = read_config_option("snmp_retries");
	$snmp_maxoids = read_config_option("max_get_size");

	#
	# 2nd function argument is $sensor_type
	#
	$sensor_type = strtolower(trim($sensor_type));

	if (($sensor_type != "fan") &&
		($sensor_type != "temperature") &&
		($sensor_type != "voltage")) {

		echo ("FATAL: $sensor_type is not a valid sensor type\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	#
	# 3rd function argument is $cacti_request
	#
	$cacti_request = strtolower(trim($cacti_request));

	if ($cacti_request == "") {

		echo ("FATAL: No Cacti request provided\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	if (($cacti_request != "index") &&
		($cacti_request != "query") &&
		($cacti_request != "get")) {

		echo ("FATAL: \"$cacti_request\" is not a valid Cacti request\n");
		ss_netsnmp_lmsensors_syntax();
		return;
	}

	#
	# remaining function arguments are $data_request and $data_request_key
	#
	if (($cacti_request == "query") || ($cacti_request == "get")) {

		$data_request = strtolower(trim($data_request));

		if ($data_request == "") {

			echo ("FATAL: No data requested for Cacti \"$cacti_request\" request\n");
			ss_netsnmp_lmsensors_syntax();
			return;
		}

		if (($data_request != "sensordevice") &&
			($data_request != "sensorname") &&
			($data_request != "sensorreading")) {

			echo ("FATAL: \"$data_request\" is not a valid data request\n");
			ss_netsnmp_lmsensors_syntax();
			return;
		}

		#
		# get the index variable
		#
		if ($cacti_request == "get") {

			$data_request_key = strtolower(trim($data_request_key));

			if ($data_request_key == "") {

				echo ("FATAL: No index value provided for \"$data_request\" data request\n");
				ss_netsnmp_lmsensors_syntax();
				return;
			}
		}

		#
		# clear out spurious command-line parameters on query requests
		#
		else {
			$data_request_key = "";
		}
	}

	#
	# build a nested array of data elements for future use
	#
	switch ($sensor_type) {

		case "temperature":

			$oid_array = array ("sensorIndex" => ".1.3.6.1.4.1.2021.13.16.2.1.1",
				"sensorName" => ".1.3.6.1.4.1.2021.13.16.2.1.2",
				"sensorReading" => ".1.3.6.1.4.1.2021.13.16.2.1.3");

			break;

		case "fan":

			$oid_array = array ("sensorIndex" => ".1.3.6.1.4.1.2021.13.16.3.1.1",
				"sensorName" => ".1.3.6.1.4.1.2021.13.16.3.1.2",
				"sensorReading" => ".1.3.6.1.4.1.2021.13.16.3.1.3");

			break;

		case "voltage":

			$oid_array = array ("sensorIndex" => ".1.3.6.1.4.1.2021.13.16.4.1.1",
				"sensorName" => ".1.3.6.1.4.1.2021.13.16.4.1.2",
				"sensorReading" => ".1.3.6.1.4.1.2021.13.16.4.1.3");

			break;
	}

	#
	# build the snmp_arguments array for future use
	#
	# note that the array structure varies according to the version of Cacti in use
	#
	if (isset($GLOBALS['config']['cacti_version']) == FALSE) {

		echo ("FATAL: Unable to determine Cacti version\n");
		return;
	}

	elseif (substr($GLOBALS['config']['cacti_version'],0,5) == "0.8.6") {

		$snmp_arguments = array(
			$snmp_hostname,
			$snmp_community,
			"",
			$snmp_version,
			$snmp_v3_username,
			$snmp_v3_password,
			$snmp_port,
			$snmp_timeout);

		#
		# Cacti 0.8.6 SNMP timeout used milliseconds, while PHP uses Net-SNMP foormat, which
		# is typically microseconds. Normalize by multiplying the timeout value by 1000.
		#
		$snmp_timeout = ($snmp_timeout * 1000);
	}

	elseif (substr($GLOBALS['config']['cacti_version'],0,5) >= "0.8.7") {

		$snmp_arguments = array(
			$snmp_hostname,
			$snmp_community,
			"",
			$snmp_version,
			$snmp_v3_username,
			$snmp_v3_password,
			$snmp_v3_authproto,
			$snmp_v3_privpass,
			$snmp_v3_privproto,
			$snmp_v3_context,
			$snmp_port,
			$snmp_timeout,
			$snmp_retries,
			$snmp_maxoids);
	}

	else {
		echo ("FATAL: \"" . $GLOBALS['config']['cacti_version'] .
			"\" is not a supported Cacti version\n");
		return;
	}

	#
	# if they want data for just one sensor, use the input data to seed the array
	#
	if ($cacti_request == "get") {

		#
		# set snmp_arguments to sensorIndex plus the requested index value and query
		#
		$snmp_arguments[2] = $oid_array['sensorIndex'] . "." . $data_request_key;
		$snmp_test = trim(call_user_func_array("cacti_snmp_get", $snmp_arguments));

		#
		# the snmp response should contain a numeric counter (NOT the device index)
		#
		if ((isset($snmp_test) == FALSE) ||
			(substr($snmp_test, 0, 16) == "No Such Instance") ||
			(is_numeric($snmp_test) == FALSE) ||
			($snmp_test == "")) {

			echo ("FATAL: No sensor data was returned from SNMP\n");
			return;
		}

		#
		# response looks okay, so assume the requested index value is valid
		#
		$sensor_array[0]['index'] = $data_request_key;
	}

	#
	# if they want data for all sensors, use snmpwalk to seed the array
	#
	else {
		#
		# set the snmp_arguments array to the sensor Index OID
		#
		$snmp_arguments[2] = $oid_array['sensorIndex'];

		#
		# walk the tree and capture the resulting array of sensors
		#
		$snmp_array = call_user_func_array("cacti_snmp_walk", $snmp_arguments);

		#
		# verify that the response contains expected data structures
		#
		if ((isset($snmp_array) == FALSE) ||
			(count($snmp_array) == 0) ||
			(array_key_exists('oid', $snmp_array[0]) == FALSE) || 
			(array_key_exists('value', $snmp_array[0]) == FALSE) ||
			(substr($snmp_array[0]['value'],0,16) == "No Such Instance") ||
			(is_numeric($snmp_array[0]['value']) == FALSE) ||
			(trim($snmp_array[0]['value']) == "")) {

			echo ("FATAL: No sensor data was returned from SNMP\n");
			return;
		}

		#
		# create the array entries
		#
		$sensor_count = 0;

		foreach ($snmp_array as $snmp_response) {

			#
			# the trailing block of digits in each response OID identifies the sensor index
			#
			# remove whitespace from around the OIDs in $snmp_array so we can match the digits
			#
			$snmp_response['oid'] = trim($snmp_response['oid']);

			#
			# use regex to locate the relative OIDs
			#
			# exit if no match found
			#
			if (preg_match('/(\d+)$/', $snmp_response['oid'], $scratch) == 0) {

				echo ("FATAL: Unable to determine sensor index number from SNMP results\n");
				return;
			}

			else {
				#
				# match was found so use relative OID for sensor index
				#
				$sensor_array[$sensor_count]['index'] = $scratch[1];
			}

			#
			# increment the sensor counter
			#
			$sensor_count++;
		}
	}

	#
	# verify that the sensor_array exists and has data
	#
	if ((isset($sensor_array) == FALSE) ||
		(count($sensor_array) == 0)) {

		echo ("FATAL: No matching sensors were returned from SNMP\n");
		return;
	}

	#
	# requests for data other than index values require additional processing
	#
	if ((($cacti_request == "query") || ($cacti_request == "get")) &&
		($data_request != "sensordevice")) {

		#
		# cycle through the sensors and populate the data
		#
		$sensor_count = 0;

		foreach ($sensor_array as $sensor) {

			#
			# only fill in the requested data
			#
			# updates MUST reference the canonical array because foreach refs are just copies
			#
			switch ($data_request) {

				case "sensordevice":

					#
					# no additional data is needed for index requests
					#
					break;

				case "sensorname":

					#
					# set the snmp_arguments array to the sensorname value and query
					#
					$snmp_arguments[2] = ($oid_array['sensorName'] . "." . $sensor['index']);
					$scratch = trim(call_user_func_array("cacti_snmp_get", $snmp_arguments));

					#
					# snmp response should contain the sensor name
					#
					if ((isset($scratch) == FALSE) ||
						(substr($scratch, 0, 16) == "No Such Instance") ||
						($scratch == "")) {

						#
						# sensor name unknown, so call it "sensor N"
						#
						$scratch = $sensor_type . " " . $sensor['index'];
					}

					#
					# if the name is long and has dashes, trim it down
					#
					while ((strlen($scratch) > 18) && (strrpos($scratch, "-") > 12)) {

						$scratch = (substr($scratch,0, (strrpos($scratch, "-"))));
					}

					#
					# if the name is long and has spaces, trim it down
					#
					while ((strlen($scratch) > 18) && (strrpos($scratch, " ") > 12)) {

						$scratch = (substr($scratch,0, (strrpos($scratch, " "))));
					}

					#
					# if the name is still long, chop it manually
					#
					if (strlen($scratch) > 18) {

						$scratch = (substr($scratch,0,18));
					}

					#
					# store the sensor name
					#
					$sensor_array[$sensor_count]['name'] = $scratch;

					break;

				case "sensorreading":

					#
					# get the sensor reading for each entry
					#
					$snmp_arguments[2] = ($oid_array['sensorReading'] . "." . $sensor['index']);
					$scratch = trim(call_user_func_array("cacti_snmp_get", $snmp_arguments));

					#
					# if no useful data was returned, null the results
					#
					if ((isset($scratch) == FALSE) ||
						(substr($scratch, 0, 16) == "No Such Instance") ||
						(is_numeric($scratch) == FALSE) ||
						($scratch == "")) {

						$scratch = "";
					}

					#
					# negative voltage readings must be converted to negative numbers
					#
					if (($sensor_type == "voltage") &&
						($scratch > 2147483647)) {

						$scratch = ($scratch - 4294967294);
					}

					#
					# move the voltage and thermal decimal place left by three places
					#
					if (($sensor_type == "voltage") ||
						($sensor_type == "temperature")) {

						$scratch = ($scratch / 1000);
					}

					#
					# remove impossibly-high temperature and voltage readings
					#
					if ((($sensor_type == "voltage") ||
						($sensor_type == "temperature")) &&
						($scratch >= "255")) {

						$scratch = "";
					}

					#
					# store the sensor reading
					#
					$sensor_array[$sensor_count]['reading'] = $scratch;

					break;
			}

			#
			# increment the sensor counter
			#
			$sensor_count++;
		}
	}

	#
	# generate output
	#
	foreach ($sensor_array as $sensor) {

		#
		# return output data according to $cacti_request
		#
		switch ($cacti_request) {

			#
			# for "index" requests, dump the device column
			#
			case "index":

				echo ($sensor['index'] . "\n");
				break;

			#
			# for "query" requests, dump the requested columns
			#
			case "query":

				switch ($data_request) {

					case "sensordevice":

						echo ($sensor['index'] . ":" . $sensor['index'] . "\n");
						break;

					case "sensorname":

						echo ($sensor['index'] . ":" . $sensor['name'] . "\n");
						break;

					case "sensorreading":

						echo ($sensor['index'] . ":" . $sensor['reading'] . "\n");
						break;
				}

				break;

			#
			# for "get" requests, dump the requested data for the requested sensor
			#
			case "get":

				#
				# skip the current row if it isn't the requested sensor
				#
				if (strtolower($sensor['index']) != $data_request_key) {

					break;
				}

				switch ($data_request) {

					case "sensordevice":

						echo ($sensor['index'] . "\n");
						break;

					case "sensorname":

						echo ($sensor['name'] . "\n");
						break;

					case "sensorreading":

						if (isset($GLOBALS['called_by_script_server']) == TRUE) {

							return($sensor['reading']);
						}

						else {
							echo ($sensor['reading'] . "\n");
						}

						break;
				}

				break;
		}
	}
}

#
# display the syntax
#
function ss_netsnmp_lmsensors_syntax() {

	echo ("Syntax: ss_netsnmp_lmsensors.php <hostname>:<snmp_version>:[<snmp_community>]:\ \n" .
	"      [<snmp3_username>]:[<snmp3_password>]:[<snmp3_auth_protocol>]:[<snmp3_priv_password>]:\ \n" .
	"      [<snmp3_priv_protocol>]:[<snmp3_context>]:[<snmp_port>}:[<snmp_timeout>] \ \n" .
	"      (FAN|TEMPERATURE|VOLTAGE) (index|query <fieldname>|get <fieldname> <sensor>)\n");
}

?>
