<?php
	include 'DBFLoader.class.php';
	
	$qweqwe = new DBFLoader('D:\WWW\fgbu02\fias_dbf\NORDOC02.DBF');
	$qweqwe -> Convert(array('MYSQL'=>array('host'=>"localhost",'user'=>"root", 'pass'=>"root", 'base'=>"fias")));
	
	