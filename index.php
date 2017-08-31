<?php
	include 'DBFLoader.class.php';
	// Загрузка FIAS в локальную базу 
	
	$qweqwe = new DBFLoader('D:\WWW\fgbu02\fias_dbf');
	// $qweqwe -> LogDisplayMSG = false; // не отображать ошибки на экран.
	$MySQLParam = array('host'=>"localhost",'user'=>"root", 'pass'=>"root", 'base'=>"fias");
	$OracleParam = array('user'=>"FIAS", 'pass'=>"qwer!1234", 'sid'=>"DB2_TEST");
	$qweqwe -> Convert(array('MYSQL'=>$MySQLParam,'ORACLE'=> $OracleParam));/**/

	if ($wpdb = new mysqli($MySQLParam['host'], $MySQLParam['user'], $MySQLParam['pass'], $MySQLParam['base'])) {
		$wpdb->multi_query('ALTER TABLE `actstat`  ADD PRIMARY KEY(`ACTSTATID`);');
		$wpdb->multi_query('ALTER TABLE `addrob02` ADD PRIMARY KEY(`AOID`);    ALTER TABLE `addrob02` ADD INDEX(`AOGUID`);');
		$wpdb->multi_query('ALTER TABLE `house02`  ADD PRIMARY KEY(`HOUSEID`); ALTER TABLE `house02` ADD INDEX(`HOUSEGUID`);');
		$wpdb->multi_query('ALTER TABLE `room02`   ADD PRIMARY KEY(`ROOMID`);  ALTER TABLE `room02` ADD INDEX(`ROOMGUID`);');/**/
		$wpdb->multi_query('ALTER TABLE `stead02`  ADD INDEX(`STEADGUID`);');
	}/**/
	
	if ($Ora_Con = oci_connect($OracleParam['user'], $OracleParam['pass'], $OracleParam['sid'],'AL32UTF8')) {
		oci_execute(oci_parse($Ora_Con, "ALTER TABLE FIAS.actstat  ADD CONSTRAINT actstat PRIMARY KEY (ACTSTATID)"));/**/
		
		oci_execute(oci_parse($Ora_Con, "ALTER TABLE FIAS.addrob02 ADD CONSTRAINT addrob02 PRIMARY KEY (AOID)"));
		oci_execute(oci_parse($Ora_Con, "CREATE INDEX AOGUID ON FIAS.addrob02(AOGUID)"));
		
		oci_execute(oci_parse($Ora_Con, "CREATE INDEX HOUSEGUID ON FIAS.house02(HOUSEGUID)"));
		oci_execute(oci_parse($Ora_Con, "ALTER TABLE FIAS.house02  ADD CONSTRAINT house02 PRIMARY KEY (HOUSEID)"));
		
		oci_execute(oci_parse($Ora_Con, "CREATE INDEX ROOMGUID ON FIAS.room02(ROOMGUID)"));
		oci_execute(oci_parse($Ora_Con, "ALTER TABLE FIAS.room02   ADD CONSTRAINT room02 PRIMARY KEY (ROOMID)"));
		
		oci_execute(oci_parse($Ora_Con, "CREATE INDEX STEADGUID ON FIAS.stead02(STEADGUID)"));
		oci_execute(oci_parse($Ora_Con, "ALTER TABLE FIAS.stead02  ADD CONSTRAINT stead02 PRIMARY KEY (STEADID)"));
	}/**/