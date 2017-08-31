<?php
	header('Content-type: text/html; charset=utf-8');
	class DBFLoader {
		
		private $Files;
		private $HeaderArray;
		private $FileCSV;
		private $TMPFolter;
		private $TableName;
		
		public $LogDisplayMSG = true;   // Выводить сообщения в Браузер 
		public $DelTable = true;   // Удалять таблицу перед импортом
		
		public function __construct($DBF){
			if (!extension_loaded('dbase')) throw new Exception('Не найдено расширение dbase. подключите библиотеку');
			if(!$this -> FindDBF($DBF)) throw new Exception('Не найдено DBF файлов для обработки');
			$this ->TMPFolter = str_replace('\\', '/', sys_get_temp_dir().'/DBFLoader/');
			if (!is_dir($this ->TMPFolter)) mkdir($this ->TMPFolter);
		}
		
		public function __destruct() {
			foreach ($this -> files as $file) {
				$fileParts = explode( '/', $file);
				@unlink($this ->TMPFolter.preg_replace( '~\.[a-z]+$~i', '.csv', $fileParts[key( array_slice( $fileParts, -1, 1, true ) )] ));
			}
			@unlink($this->TMPFolter.'temp.ctl');
		}
		
		private Function Logs($MSG, $Type=''){
			if (strtoupper ($Type) == "ERROR"){
				//trigger_error(htmlentities($MSG), E_USER_ERROR);
			}
			if ($this->LogDisplayMSG)
				Echo date("m/d/Y H:i:s").' - '. $MSG.'<br>';
		}
		
		private Function FindDBF($Paths){
			if (!is_array($Paths)) 
				$Paths = explode (';', str_replace('\\', '/', $Paths));
			$this -> files =array();
			foreach($Paths as $Path_K => $Path_V){
				if (is_dir($Path_V))
					$this -> files = array_merge($this -> files, glob( $Path_V.'/*.DBF'));
				elseif (!empty($Path_V))
						$this -> files [] = $Path_V;
			}
			return ((count($this -> files)!=0)?True:false);
		}
		
		public Function Convert($ToBase){
			ini_set( 'memory_limit', '-1' );
			set_time_limit(0);
			foreach ($this -> files as $file) {
				if ($this ->ConvertDBF2CSV($file)) {
					if (!Empty($ToBase['MYSQL'])) 	$this ->Load_CSV4MySQL($ToBase['MYSQL']);
					if (!Empty($ToBase['ORACLE'])) 	$this ->Load_CSV4Oracle($ToBase['ORACLE']);
				}
			}
		}
		
		private Function ConvertDBF2CSV($FileDBF, $FileCSV=''){
			$this -> Logs("Конвертация файла $FileDBF");
			ini_set( 'memory_limit', '-1' );
			set_time_limit(0);
			if ($dbf = dbase_open($FileDBF, 0)) {
				$this -> HeaderArray = dbase_get_header_info($dbf);
				$HeaderArray = array();
				foreach( $this -> HeaderArray as $key => $val )
					$HeaderArray[$val['name']]='';
				if (Empty($FileCSV)) {
					$fileParts = explode( '/', $FileDBF );
					$this -> TableName = preg_replace( '~\.[a-z]+$~i', '', $fileParts[key( array_slice( $fileParts, -1, 1, true ) )] );
					$this -> FileCSV = $this ->TMPFolter.$this -> TableName.'.csv';
				}
				else $this ->FileCSV = FileCSV;
				$num_rec = dbase_numrecords( $dbf );
				$fp = fopen($this ->FileCSV, "a");
				for( $i = 1; $i <= $num_rec; $i++ ){
					$NewCSV = $HeaderArray;
					$row = @dbase_get_record_with_names( $dbf, $i );
					foreach( $row as $key => $val ){
						if( $key == 'deleted' ) continue;
						$NewCSV[$key] = trim( $val );
					};
					fwrite($fp, iconv("CP866", "UTF-8", implode ($NewCSV,'|'))."\r\n");
				};
				fclose($fp);
				dbase_close($dbf);
				return true;
			} else {
				return False;
			}
		}
		
		private Function Load_CSV4MySQL($param){
// ------------------------------------			echo '<pre>'.var_export($param, true).'</pre>';
			$this -> Logs("Экспорт в MySQL ".$this ->FileCSV);
			$TypeBase = array (
				'number' => array('INTEGER'),
				'character' => array('VARCHAR', true),
				'date' => array('DATE'),
				'memo' => array('TEXT'),
			);
			if ($wpdb = new mysqli($param['host'], $param['user'], $param['pass'], $param['base'])) {
				// удаляем старую таблицу
				if (($this -> DelTable) && (!$wpdb->query('drop table IF EXISTS '.$this -> TableName) ))
					$this -> Logs($wpdb->error,"ERROR");
				// Создаем таблицу
				$SQL_CREATE_TABLE = 'CREATE TABLE IF NOT EXISTS '.$this -> TableName.' ( ';
				foreach( $this -> HeaderArray as $key => $val ){
					$SQL_CREATE_TABLE .=	' `'.$val['name'].'` '.
								$TypeBase[$val['type']][0].
								(!empty($TypeBase[$val['type']][1]) ? '('.$val['length'].')' :'').
								((Count($this -> HeaderArray)-1!=$key)?',':'');
				};
				$SQL_CREATE_TABLE .= ') ENGINE=MyISAM DEFAULT CHARSET=utf8;';
				if ( !$wpdb->query($SQL_CREATE_TABLE) )
					$this -> Logs($wpdb->error,"ERROR");
				// Запускаем за выполнение импорта 
				$SQL_LOAD_DATA = "LOAD DATA LOCAL INFILE '".$this ->FileCSV."'"; 
				$SQL_LOAD_DATA .= " INTO TABLE ".$this -> TableName." ";
				$SQL_LOAD_DATA .= " CHARACTER SET utf8"; 
				$SQL_LOAD_DATA .= " FIELDS TERMINATED BY '|'"; 
				
				if ( !$wpdb->query($SQL_LOAD_DATA) )
					$this -> Logs($wpdb->error,"ERROR");
				// Подчищаем за собой
				unset($SQL_LOAD_DATA);
				unset($SQL_CREATE_TABLE);
			}
		}
		
		private function OracleQuery($conn, $SQL, $Params=array()){
			
			if ($Ora_P = oci_parse($conn, $SQL)) {
				//oci_bind_by_name($stmt, ':id', $id, -1);
				if (@oci_execute($Ora_P)) {
					//return oci_fetch_arry($Ora_P, OCI_ASSOC);
				}
				Else{
					$m = oci_error($Ora_P);
					$this -> Logs($m['message'],"ERROR");
				}
			}
			Else {
				$m = oci_error($Ora_P);
				$this -> Logs($m['message'],"ERROR");
			}
		} 
		
		private Function Load_CSV4Oracle($param){
			$this -> Logs("Экспорт в Oracle ".$this ->FileCSV);
			$TypeBase = array (
				'number' => array('INTEGER',false),
				'character' => array('VARCHAR', true),
				'date' => array('DATE',false , 'DATE  "yyyymmdd"'),
				'memo' => array('CLOB',false),
			);
			if ($Ora_Con = oci_connect($param['user'], $param['pass'], $param['sid'],'AL32UTF8')) {
				// удаляем старую таблицу
				if ($this -> DelTable)
					$this -> OracleQuery($Ora_Con,'drop table '.$this -> TableName);
				// Создаем таблицу
				$SQL_CREATE_TABLE = 'CREATE TABLE '.$param['user'].'.'.$this -> TableName.' ( ';
				$ctl_file = '';
				foreach($this -> HeaderArray as $key => $val ){
					$SQL_CREATE_TABLE .=	' "'.$val['name'].'" '.
											$TypeBase[$val['type']][0].
											(!empty($TypeBase[$val['type']][1]) ? '('.$val['length'].')' :'').
											((Count($this -> HeaderArray)-1!=$key)?',':'');
					$ctl_file .=			' "'.$val['name'].'" '.
											(!empty($TypeBase[$val['type']][2]) ? $TypeBase[$val['type']][2] :'').
											((Count($this -> HeaderArray)-1!=$key)?',':'');
				}
				$SQL_CREATE_TABLE .= ')';
				$this -> OracleQuery($Ora_Con,$SQL_CREATE_TABLE);
				// Запускаем за выполнение импорта 
				$SQL_LOAD_DATA = "LOAD DATA CHARACTERSET UTF8 INFILE '".$this ->FileCSV."' \r\n"; 
					$SQL_LOAD_DATA .= " INTO TABLE ".$param['user'].'.'.$this -> TableName." \r\n";
					$SQL_LOAD_DATA .= " truncate \r\n"; 
					$SQL_LOAD_DATA .= " FIELDS TERMINATED BY '|' \r\n"; 
					$SQL_LOAD_DATA .= " TRAILING NULLCOLS \r\n"; 
					$SQL_LOAD_DATA .= " ( ".$ctl_file." ) \r\n"; 
				file_put_contents($this->TMPFolter.'temp.ctl', $SQL_LOAD_DATA);
				exec ('sqlldr '.$param['user'].'/'.$param['pass'].'@'.$param['sid'].' control="'.$this->TMPFolter.'temp.ctl"', $returnValue);
				// Подчищаем за собой
				unset($SQL_LOAD_DATA);
				unset($SQL_CREATE_TABLE);
				oci_close($Ora_Con);
			}
			Else{
				$m = oci_error();
				$this -> Logs($m['message'],"ERROR");
			}
		}
	}
