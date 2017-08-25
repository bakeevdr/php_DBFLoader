<?php
	header('Content-type: text/html; charset=utf-8');
	class DBFLoader {
		
		private $Files;
		private $HeaderArray;
		private $FileCSV;
		
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
echo '<pre>'.var_export($file, true).'<pre>';
				if ($this ->ConvertDBF2CSV($file))
					if (!Empty($ToBase['MYSQL'])) $this ->Load_CSV4MySQL($ToBase['MYSQL']);
			}
		}
		
		private Function ConvertDBF2CSV($FileDBF, $FileCSV=''){
			if ($dbf = dbase_open($FileDBF, 0)) {
				if (Empty($FileCSV)) {
					$fileParts = explode( '/', $FileDBF );
					$endPart = $fileParts[key( array_slice( $fileParts, -1, 1, true ) )];
					$this ->FileCSV = $this ->TMPFolter.preg_replace( '~\.[a-z]+$~i', '.csv', $endPart );
				}
				else $this ->FileCSV = FileCSV;
				$num_rec = dbase_numrecords( $dbf );
				$NewCSV = '';
				for( $i = 1; $i <= $num_rec; $i++ ){
					$row = @dbase_get_record_with_names( $dbf, $i );
					$firstKey = key( array_slice( $row, 0, 1, true ) );
					foreach( $row as $key => $val ){
						if( $key == 'deleted' ) continue;
						if( $firstKey != $key ) $NewCSV .= '|';
						$NewCSV .= iconv("CP866", "UTF-8", trim( $val ));
					};
					$NewCSV .= "\n";
				};
				file_put_contents($this ->FileCSV, $NewCSV);
				$this -> HeaderArray = dbase_get_header_info($dbf);
				dbase_close($dbf);
				return true;
			} else {
				return False;
			}
		}
		
		private Function Load_CSV4MySQL($param){
// ------------------------------------			echo '<pre>'.var_export($param, true).'<pre>';
			$TypeBase = array (
				'number' => array('INTEGER'),
				'character' => array('VARCHAR', true),
				'date' => array('DATE'),
				'memo' => array('TEXT'),
			);
			$fileParts = explode( '/', $this ->FileCSV );
			
			$TableName=preg_replace( '~\.[a-z]+$~i', '', $fileParts[key( array_slice( $fileParts, -1, 1, true ) )] );
			if ($wpdb = new mysqli($param['host'], $param['user'], $param['pass'], $param['base'])) {
				
				$SQL_CREATE_TABLE = 'CREATE TABLE IF NOT EXISTS '.$TableName.' ( ';
				foreach( $this -> HeaderArray as $key => $val ){
					$SQL_CREATE_TABLE .=	' `'.$val['name'].'` '.
								$TypeBase[$val['type']][0].
								(!empty($TypeBase[$val['type']][1]) ? '('.$val['length'].')' :'').
								((Count($this -> HeaderArray)-1!=$key)?',':'');
				};
				$SQL_CREATE_TABLE .= ') ENGINE=MyISAM';
				
				if ( !$wpdb->query($SQL_CREATE_TABLE) )
					exit('error:'. $wpdb->error);
				
				$sql = "LOAD DATA LOCAL INFILE '".$this ->FileCSV."'"; 
				$sql .= " INTO TABLE ".$TableName." ";
				$sql .= " CHARACTER SET utf8"; 
				$sql .= " FIELDS TERMINATED BY '|'"; 
				
				if ( !$wpdb->query($sql) )
					exit('error:'. $wpdb->error);
			}
		}
	}
