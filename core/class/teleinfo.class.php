<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'PhpSerial', 'class', 'teleinfo');

class teleinfo extends eqLogic {
	public function AddCommande($Name,$_logicalId,$Type="info", $SubType='binary') {
		$Commande = $this->getCmd(null,$_logicalId);
		if (!is_object($Commande))
		{
			$Commande = new VoletsCmd();
			$Commande->setId(null);
			$Commande->setName($Name);
			$Commande->setIsVisible(true);
			$Commande->setLogicalId($_logicalId);
			$Commande->setEqLogic_id($this->getId());
			$Commande->setType($Type);
			$Commande->setSubType($SubType);
			$Commande->save();
		}
		return $Commande;
	}
	public static function getTeleinfoInfo($_url){
		return 1;
	}
	public static function cron() {
		self::Calculate_PAPP();
	}
	
	public static function cronHourly() {
		self::Moy_Last_Hour();
	}
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'teleinfo';	
		$return['launchable'] = 'ok';
		$return['state'] = 'ok';
		foreach(eqLogic::byType('teleinfo') as $teleinfo){
			$cron = cron::byClassAndFunction('teleinfo', 'pull', array('id' => $teleinfo->getId()));
			if(!is_object($cron) )
				$return['state'] = 'nok';
		}
		return $return;
	}
	public static function deamon_start($_debug = false) {
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		log::remove('teleinfo');
		self::deamon_stop();
		foreach(eqLogic::byType('teleinfo') as $teleinfo){
			$cron = cron::byClassAndFunction('teleinfo', 'pull', array('id' => $teleinfo->getId()));
			if (!is_object($cron)) {
				$cron = new cron();
				$cron->setClass('teleinfo');
				$cron->setFunction('pull');				
				$cron->setOption(array('id' => $teleinfo->getId()));
				$cron->setEnable(1);
				$cron->setDeamon(1);
				$cron->setSchedule('* * * * *');
				$cron->setTimeout('999999');
				$cron->save();
			}
			$cron->start();
			$cron->run();
		}
	}
	public static function deamon_stop() {
		foreach(eqLogic::byType('teleinfo') as $teleinfo){
			$cron = cron::byClassAndFunction('teleinfo', 'pull', array('id' => $teleinfo->getId()));
			if (is_object($cron)) {
				$cron->stop();
				$cron->remove();
			}
		}
	}
	
	public static function pull($_options) {
		$teleinfo = eqLogic::byId($_options['id']);
		if (is_object($teleinfo) && $teleinfo->getIsEnable()) {
			log::add('teleinfo','debug',$teleinfo->getHumanName() . ': Lancement du démon de lecture des trames Téléinfo');
			$cmd="stty -F " . $teleinfo->getPort() . " speed 1200 cs7 parenb parodd";
			$cmd .= ' >> ' . log::getPathToLog('teleinfo') . ' 2>&1 &';
			exec($cmd);
			$handle = fopen($teleinfo->getPort(), "r");
			if (!$handle)
				throw new Exception(__($teleinfo->getPort()." non trouvé", __FILE__));
			stream_set_blocking($handle, 0);
			// on attend la fin d'une trame pour commencer a avec la trame suivante
			while (fread($handle, 1) != chr(0x02)); 
			while (!feof($handle)) {
				$char  = '';
				$trame = ''; 
				// on lit tous les caracteres jusqu'a la fin de la trame
				while ($char != chr(0x02)) {
					$char = fread($handle, 1);
					if ($char != chr(0x02)){
						$trame .= $char;
					}
				}
				$trame=trim($trame);
				log::add('teleinfo','debug',$teleinfo->getHumanName() . ': ' . $trame);
				$teleinfo->UpdateInfo($trame);
			}
			fclose ($handle);
		/*	$serial = new PhpSerial;

			// First we must specify the device. This works on both linux and windows (if
			// your linux serial device is /dev/ttyS0 for COM1, etc)
			$serial->deviceSet($teleinfo->getPort());

			// We can change the baud rate, parity, length, stop bits, flow control
			$serial->confBaudRate(1200);
			$serial->confParity("odd");
			$serial->confCharacterLength(7);
			$serial->confStopBits(0);
			$serial->confFlowControl("none");

			// Then we need to open it
			$serial->deviceOpen();

			while (true) {
				$trame = ''; 
				$char  = '';
				$trame = ''; 
				// on lit tous les caracteres jusqu'a la fin de la trame
				while ($char != chr(0x02)) {
					$char=$serial->readPort(1);
					if ($char != chr(0x02)){
						$trame .= $char;
					}
				}
				$trame=trim($trame);
				log::add('teleinfo','debug',$teleinfo->getHumanName() . ': ' . $trame);
				$teleinfo->UpdateInfo($trame);
			}
			//$serial->sendMessage("Hello !");
			$serial->deviceClose();*/
		}
	}
	public function UpdateInfo($trame) {
		$datas = '';
		$trame=str_replace (chr(0x03),'',$trame);
		foreach (explode(chr(0x0A), $trame) as $key => $message) {
			$message = explode (' ', $message, 3);
			if($this->is_valid($message)){
				$param=trim($message[0]);
				$value=trim($message[1]);
				switch($param){
					case 'ADCO':
						$this->setLogicalId($value);
					continue;
					case 'PTEC':
						$value=substr($value,0,2);
					break;
				}
				$this->AddCommande($param,$param,$Type="info", $SubType='numeric') ;
				$this->checkAndUpdateCmd($param,$value);
           			log::add('teleinfo','debug',$this->getHumanName() . ': '. $param . ' = '.$value);
			}
		}
	}
	public function is_valid($message){
		if(count($message) < 3)
			return false;
		$my_sum = 0;
		$datas = str_split(' '.$message[0].$message[1]);
		foreach($datas as $cks)
          		$my_sum += ord($cks);
		$computed_checksum = ($my_sum & intval("111111", 2) ) + 0x20;
		if(chr($computed_checksum) == trim($message[2]))
			return true;
		return false;
	}
	public function getPort() {
		$port=$this->getConfiguration('port');
		if($port == 'serie')
			$port=$teleinfo->getConfiguration('modem_serie_addr');
		/*elseif($this->getConfiguration('2cmpt')){
			$nb=substr($port,-1)+1;
			$port='/dev/ttyUSB'.$nb;
		}*/
		return $port;
	}
	public static function CalculateTodayStats($stat){
		$STAT_TODAY_HP = 0;
		$STAT_TODAY_HC = 0;
		$STAT_TENDANCE = 0;
		$STAT_YESTERDAY_HP = 0;
		$STAT_YESTERDAY_HC = 0;
		$TYPE_TENDANCE = 0;
		$stat_hp_to_cumul = array();
		$stat_hc_to_cumul = array();
		foreach (eqLogic::byType('teleinfo') as $eqLogic){
			foreach ($eqLogic->getCmd('info') as $cmd) {
				if ($cmd->getConfiguration('type')== "data" || $cmd->getConfiguration('type')== "") {
					switch ($cmd->getConfiguration('info_conso')) {
						case "BASE":
						case "HCHP":
						case "EJPHN":
						case "BBRHPJB":
						case "BBRHPJW":
						case "BBRHPJR":
						array_push($stat_hp_to_cumul, $cmd->getId()); 
						break;	
					}
					switch ($cmd->getConfiguration('info_conso')) {
						case "HCHC":
						case "BBRHCJB":
						case "BBRHCJW":
						case "BBRHCJR":
						array_push($stat_hc_to_cumul, $cmd->getId()); 
						break;	
					}
				}
				if($cmd->getConfiguration('info_conso') == "TENDANCE_DAY"){
					$TYPE_TENDANCE = $cmd->getConfiguration('type_calcul_tendance');
				}
			}
		}
		
		log::add('teleinfo', 'info', '----- Calcul des statistiques temps réel -----');
		log::add('teleinfo', 'info', 'Date de début : ' . date("Y-m-d H:i:s" ,mktime(0, 0, 0, date("m")  , date("d"), date("Y"))));
		log::add('teleinfo', 'info', 'Date de fin   : ' . date("Y-m-d H:i:s" ,mktime(date("H"), date("i"),date("s"), date("m")  , date("d"), date("Y"))));
		log::add('teleinfo', 'info', '----------------------------------------------');
		//log::add('teleinfo', 'info', '----- Calcul des statistiques horraires -----');
		//log::add('teleinfo', 'info', 'StartDateLastHour : ' . date("Y-m-d H:i:s" ,mktime(date("H")-1, date("i"),date("s"), date("m")  , date("d"), date("Y")));
		//log::add('teleinfo', 'info', 'EndDateLastHour : ' . date("Y-m-d H:i:s" ,mktime(date("H"), date("i"),date("s"), date("m")  , date("d"), date("Y")));
		
		$startdatetoday = date("Y-m-d H:i:s" ,mktime(0, 0, 0, date("m")  , date("d"), date("Y")));
		$enddatetoday = date("Y-m-d H:i:s" ,mktime(date("H"), date("i"),date("s"), date("m")  , date("d"), date("Y")));
		$startdateyesterday = date("Y-m-d H:i:s" ,mktime(0, 0, 0, date("m")  , date("d")-1, date("Y")));
		
		//$startdatelasthour = date("Y-m-d H:i:s" ,mktime(date("H")-1, date("i"),date("s"), date("m")  , date("d"), date("Y")));
		//$enddatelasthour = date("Y-m-d H:i:s" ,mktime(date("H"), date("i"),date("s"), date("m")  , date("d"), date("Y")));
		if($TYPE_TENDANCE == 1){
			$enddateyesterday = date("Y-m-d H:i:s" ,mktime(23, 59,59, date("m")  , date("d")-1, date("Y")));
		}
		else{
			$enddateyesterday = date("Y-m-d H:i:s" ,mktime(date("H"), date("i"),date("s"), date("m")  , date("d")-1, date("Y")));
		}
		
		foreach ($stat_hc_to_cumul as $key => $value){
			log::add('teleinfo', 'debug', 'Commande HC N°' . $value);
			//$cache = cache::byKey('teleinfo::stats::' . $value, false, true);
			$cmd = cmd::byId($value);
			/*log::add('teleinfo', 'info', 'HC : ');
			foreach($cmd->getStatistique($startdatetoday,$enddatetoday) as $key => $value){
				log::add('teleinfo', 'info', '[' . $key . '] ' . $value );
			}*/
			log::add('teleinfo', 'debug', ' ==> Valeur HC MAX : ' . $cmd->getStatistique($startdatetoday,$enddatetoday)['max']);
			log::add('teleinfo', 'debug', ' ==> Valeur HC MIN : ' . $cmd->getStatistique($startdatetoday,$enddatetoday)['min']);
			
			$STAT_TODAY_HC += intval($cmd->getStatistique($startdatetoday,$enddatetoday)['max']) - intval($cmd->getStatistique($startdatetoday,$enddatetoday)['min']);
			$STAT_YESTERDAY_HC += intval($cmd->getStatistique($startdateyesterday,$enddateyesterday)['max']) - intval($cmd->getStatistique($startdateyesterday,$enddateyesterday)['min']);
			log::add('teleinfo', 'debug', 'Total HC --> ' . $STAT_TODAY_HC);
		}
		foreach ($stat_hp_to_cumul as $key => $value){
			//log::add('teleinfo', 'debug', 'ID HP --> ' . $value);
			log::add('teleinfo', 'debug', 'Commande HP N°' . $value);
			$cmd = cmd::byId($value);
			/*log::add('teleinfo', 'info', 'HP : ');
			foreach($cmd->getStatistique($startdatetoday,$enddatetoday) as $key => $value){
				log::add('teleinfo', 'info', '[' . $key . '] ' . $value );
			}*/
			log::add('teleinfo', 'debug', ' ==> Valeur HP MAX : ' . $cmd->getStatistique($startdatetoday,$enddatetoday)['max']);
			log::add('teleinfo', 'debug', ' ==> Valeur HP MIN : ' . $cmd->getStatistique($startdatetoday,$enddatetoday)['min']);
			
			$STAT_TODAY_HP += intval($cmd->getStatistique($startdatetoday,$enddatetoday)['max']) - intval($cmd->getStatistique($startdatetoday,$enddatetoday)['min']);
			$STAT_YESTERDAY_HP += intval($cmd->getStatistique($startdateyesterday,$enddateyesterday)['max']) - intval($cmd->getStatistique($startdateyesterday,$enddateyesterday)['min']);
			log::add('teleinfo', 'debug', 'Total HP --> ' . $STAT_TODAY_HP);
		}
		
		/*if(($STAT_MAX_YESTERDAY - $STAT_TODAY) > 100){
			$STAT_TENDANCE = -1;
		}
		else if (($STAT_MAX_YESTERDAY - $STAT_TODAY) < 100){
			$STAT_TENDANCE = 1;
		}*/
		switch($stat){
			case "STAT_TODAY":
				log::add('teleinfo', 'info', 'Mise à jour de la statistique journalière ==> ' . intval($STAT_TODAY_HP + $STAT_TODAY_HC));
				return intval($STAT_TODAY_HP + $STAT_TODAY_HC);
			case "TENDANCE_DAY":
				log::add('teleinfo', 'debug', 'Mise à jour de la tendance journalière ==> ' . '(Hier : '. intval($STAT_YESTERDAY_HC + $STAT_YESTERDAY_HP) . ' Aujourd\'hui : ' . intval($STAT_TODAY_HC + $STAT_TODAY_HP) . ' Différence : ' . (intval($STAT_YESTERDAY_HC + $STAT_YESTERDAY_HP) - intval($STAT_TODAY_HC + $STAT_TODAY_HP)) . ')');
				return intval($STAT_YESTERDAY_HC + $STAT_YESTERDAY_HP) - intval($STAT_TODAY_HC + $STAT_TODAY_HP);
			case "STAT_TODAY_HP":
				log::add('teleinfo', 'info', 'Mise à jour de la statistique journalière (HP) ==> ' . intval($STAT_TODAY_HP));
				return intval($STAT_TODAY_HP);
			case"STAT_TODAY_HC":
				log::add('teleinfo', 'info', 'Mise à jour de la statistique journalière (HC) ==> ' . intval($STAT_TODAY_HC));
				return intval($STAT_TODAY_HC);
		}
	
	}
	public static function CalculateOtherStats($stat){
		$STAT_YESTERDAY = 0;
		$STAT_YESTERDAY_HC = 0;
		$STAT_YESTERDAY_HP = 0;
		$STAT_LASTMONTH = 0;
		$STAT_MONTH = 0;
		$STAT_YEAR = 0;
		$STAT_JAN_HP = 0;
		$STAT_JAN_HC = 0;
		$STAT_FEV_HP = 0;
		$STAT_FEV_HC = 0;
		$STAT_MAR_HP = 0;
		$STAT_MAR_HC = 0;
		$STAT_AVR_HP = 0;
		$STAT_AVR_HC = 0;
		$STAT_MAI_HP = 0;
		$STAT_MAI_HC = 0;
		$STAT_JUIN_HP = 0;
		$STAT_JUIN_HC = 0;
		$STAT_JUI_HP = 0;
		$STAT_JUI_HC = 0;
		$STAT_AOU_HP = 0;
		$STAT_AOU_HC = 0;
		$STAT_SEP_HP = 0;
		$STAT_SEP_HC = 0;
		$STAT_OCT_HP = 0;
		$STAT_OCT_HC = 0;
		$STAT_NOV_HP = 0;
		$STAT_NOV_HC = 0;
		$STAT_DEC_HP = 0;
		$STAT_DEC_HC = 0;
		$stat_hp_to_cumul = array();
		$stat_hc_to_cumul = array();
		log::add('teleinfo', 'info', '----- Calcul des statistiques de la journée -----');
		foreach (eqLogic::byType('teleinfo') as $eqLogic){
			foreach ($eqLogic->getCmd('info') as $cmd) {
				if ($cmd->getConfiguration('type')== "data" || $cmd->getConfiguration('type')== "") {
					switch ($cmd->getConfiguration('info_conso')) {
						case "BASE":
						case "HCHP":
						case "EJPHN":
						case "BBRHPJB":
						case "BBRHPJW":
						case "BBRHPJR":
						array_push($stat_hp_to_cumul, $cmd->getId()); 
						break;	
					}
					switch ($cmd->getConfiguration('info_conso')) {
						case "HCHC":
						case "BBRHCJB":
						case "BBRHCJW":
						case "BBRHCJR":
						array_push($stat_hc_to_cumul, $cmd->getId()); 
						break;	
					}
				}
			}
		}
		$startdateyesterday = date("Y-m-d H:i:s" ,mktime(0, 0, 0, date("m"), date("d")-1, date("Y")));
		$enddateyesterday = date("Y-m-d H:i:s" ,mktime(23, 59, 59, date("m"), date("d")-1, date("Y")));
		
		$startdateyear = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 1, 1, date("Y")));
		$enddateyear = date("Y-m-d H:i:s" ,mktime(23, 59, 59, date("m"), date("d")-1, date("Y")));
		/*$startdateyesterday = date("Y-m-d H:i:s" ,mktime(0, 0, 0, date("m"), date("d"), date("Y")));
		$enddateyesterday = date("Y-m-d H:i:s" ,mktime(23, 59, 59, date("m"), date("d"), date("Y")));*/
		$startdatemonth = date("Y-m-d H:i:s" ,mktime(0, 0, 0, date("m")  , 1, date("Y")));
		$enddatemonth = date("Y-m-d H:i:s" ,mktime(23, 59, 59, date("m")  , date("d"), date("Y")));
		$startdatelastmonth = date("Y-m-d H:i:s" ,mktime(0, 0, 0, date("m")-1  , 1, date("Y")));
		$enddatelastmonth = date("Y-m-d H:i:s" ,mktime(23, 59, 59, date("m")-1  , date("t", mktime(0, 0, 0, date("m")-1  , date("d"), date("Y"))), date("Y")));
		
		
		$startdate_jan = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 1, 1, date("Y")));	$enddate_jan = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 1  , 31, date("Y")));
		$startdate_fev = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 2, 1, date("Y")));	$enddate_fev = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 2  , 28, date("Y")));
		$startdate_mar = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 3, 1, date("Y")));	$enddate_mar = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 3  , 31, date("Y")));
		$startdate_avr = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 4, 1, date("Y")));	$enddate_avr = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 4  , 30, date("Y")));
		$startdate_mai = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 5, 1, date("Y")));	$enddate_mai = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 5  , 31, date("Y")));
		$startdate_juin = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 6, 1, date("Y")));	$enddate_juin = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 6  , 30, date("Y")));
		$startdate_jui = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 7, 1, date("Y")));	$enddate_jui = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 7  , 31, date("Y")));
		$startdate_aou = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 8, 1, date("Y")));	$enddate_aou = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 8  , 31, date("Y")));
		$startdate_sep = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 9, 1, date("Y")));	$enddate_sep = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 9  , 30, date("Y")));
		$startdate_oct = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 10, 1, date("Y")));	$enddate_oct = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 10  , 31, date("Y")));
		$startdate_nov = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 11, 1, date("Y")));	$enddate_nov = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 11  , 30, date("Y")));
		$startdate_dec = date("Y-m-d H:i:s" ,mktime(0, 0, 0, 12, 1, date("Y")));	$enddate_dec = date("Y-m-d H:i:s" ,mktime(23, 59, 59, 12  , 31, date("Y")));
		
		foreach ($stat_hc_to_cumul as $key => $value){
			log::add('teleinfo', 'debug', 'Commande HC N°' . $value);
			//$cache = cache::byKey('teleinfo::stats::' . $value, false, true);
			$cmd = cmd::byId($value);
			//$STAT_TODAY_HC += intval($cmd->getStatistique($startdatetoday,$enddatetoday)[max]) - intval($cmd->getStatistique($startdatetoday,$enddatetoday)[min]);
			$STAT_YESTERDAY_HC += intval($cmd->getStatistique($startdateyesterday,$enddateyesterday)['max']) - intval($cmd->getStatistique($startdateyesterday,$enddateyesterday)['min']);
			$STAT_MONTH += intval($cmd->getStatistique($startdatemonth,$enddatemonth)['max']) - intval($cmd->getStatistique($startdatemonth,$enddatemonth)['min']);
			$STAT_YEAR += intval($cmd->getStatistique($startdateyear,$enddateyear)['max']) - intval($cmd->getStatistique($startdateyear,$enddateyear)['min']);
			$STAT_LASTMONTH += intval($cmd->getStatistique($startdatelastmonth,$enddatelastmonth)['max']) - intval($cmd->getStatistique($startdatelastmonth,$enddatelastmonth)['min']);
			$STAT_JAN_HC += intval($cmd->getStatistique($startdate_jan,$enddate_jan)['max']) - intval($cmd->getStatistique($startdate_jan,$enddate_jan)['min']);
			$STAT_FEV_HC += intval($cmd->getStatistique($startdate_fev,$enddate_fev)['max']) - intval($cmd->getStatistique($startdate_fev,$enddate_fev)['min']);
			$STAT_MAR_HC += intval($cmd->getStatistique($startdate_mar,$enddate_mar)['max']) - intval($cmd->getStatistique($startdate_mar,$enddate_mar)['min']);
			$STAT_AVR_HC += intval($cmd->getStatistique($startdate_avr,$enddate_avr)['max']) - intval($cmd->getStatistique($startdate_avr,$enddate_avr)['min']);
			$STAT_MAI_HC += intval($cmd->getStatistique($startdate_mai,$enddate_mai)['max']) - intval($cmd->getStatistique($startdate_mai,$enddate_mai)['min']);
			$STAT_JUIN_HC += intval($cmd->getStatistique($startdate_juin,$enddate_juin)['max']) - intval($cmd->getStatistique($startdate_juin,$enddate_juin)['min']);
			$STAT_JUI_HC += intval($cmd->getStatistique($startdate_jui,$enddate_jui)['max']) - intval($cmd->getStatistique($startdate_jui,$enddate_jui)['min']);
			$STAT_AOU_HC += intval($cmd->getStatistique($startdate_aou,$enddate_aou)['max']) - intval($cmd->getStatistique($startdate_aou,$enddate_aou)['min']);
			$STAT_SEP_HC += intval($cmd->getStatistique($startdate_sep,$enddate_sep)['max']) - intval($cmd->getStatistique($startdate_sep,$enddate_sep)['min']);
			$STAT_OCT_HC += intval($cmd->getStatistique($startdate_oct,$enddate_oct)['max']) - intval($cmd->getStatistique($startdate_oct,$enddate_oct)['min']);
			$STAT_NOV_HC += intval($cmd->getStatistique($startdate_nov,$enddate_nov)['max']) - intval($cmd->getStatistique($startdate_nov,$enddate_nov)['min']);
			$STAT_DEC_HC += intval($cmd->getStatistique($startdate_dec,$enddate_dec)['max']) - intval($cmd->getStatistique($startdate_dec,$enddate_dec)['min']);
			
			//log::add('teleinfo', 'info', 'Conso HC --> ' . $STAT_TODAY_HC);
		}
		foreach ($stat_hp_to_cumul as $key => $value){
			log::add('teleinfo', 'debug', 'Commande HP N°' . $value);
			$cmd = cmd::byId($value);
			$STAT_YESTERDAY_HP += intval($cmd->getStatistique($startdateyesterday,$enddateyesterday)['max']) - intval($cmd->getStatistique($startdateyesterday,$enddateyesterday)['min']);			
			$STAT_MONTH += intval($cmd->getStatistique($startdatemonth,$enddatemonth)['max']) - intval($cmd->getStatistique($startdatemonth,$enddatemonth)['min']);
			$STAT_YEAR += intval($cmd->getStatistique($startdateyear,$enddateyear)['max']) - intval($cmd->getStatistique($startdateyear,$enddateyear)['min']);
			$STAT_LASTMONTH += intval($cmd->getStatistique($startdatelastmonth,$enddatelastmonth)['max']) - intval($cmd->getStatistique($startdatelastmonth,$enddatelastmonth)['min']);
			$STAT_JAN_HP += intval($cmd->getStatistique($startdate_jan,$enddate_jan)['max']) - intval($cmd->getStatistique($startdate_jan,$enddate_jan)['min']);
			$STAT_FEV_HP += intval($cmd->getStatistique($startdate_fev,$enddate_fev)['max']) - intval($cmd->getStatistique($startdate_fev,$enddate_fev)['min']);
			$STAT_MAR_HP += intval($cmd->getStatistique($startdate_mar,$enddate_mar)['max']) - intval($cmd->getStatistique($startdate_mar,$enddate_mar)['min']);
			$STAT_AVR_HP += intval($cmd->getStatistique($startdate_avr,$enddate_avr)['max']) - intval($cmd->getStatistique($startdate_avr,$enddate_avr)['min']);
			$STAT_MAI_HP += intval($cmd->getStatistique($startdate_mai,$enddate_mai)['max']) - intval($cmd->getStatistique($startdate_mai,$enddate_mai)['min']);
			$STAT_JUIN_HP += intval($cmd->getStatistique($startdate_juin,$enddate_juin)['max']) - intval($cmd->getStatistique($startdate_juin,$enddate_juin)['min']);
			$STAT_JUI_HP += intval($cmd->getStatistique($startdate_jui,$enddate_jui)['max']) - intval($cmd->getStatistique($startdate_jui,$enddate_jui)['min']);
			$STAT_AOU_HP += intval($cmd->getStatistique($startdate_aou,$enddate_aou)['max']) - intval($cmd->getStatistique($startdate_aou,$enddate_aou)['min']);
			$STAT_SEP_HP += intval($cmd->getStatistique($startdate_sep,$enddate_sep)['max']) - intval($cmd->getStatistique($startdate_sep,$enddate_sep)['min']);
			$STAT_OCT_HP += intval($cmd->getStatistique($startdate_oct,$enddate_oct)['max']) - intval($cmd->getStatistique($startdate_oct,$enddate_oct)['min']);
			$STAT_NOV_HP += intval($cmd->getStatistique($startdate_nov,$enddate_nov)['max']) - intval($cmd->getStatistique($startdate_nov,$enddate_nov)['min']);
			$STAT_DEC_HP += intval($cmd->getStatistique($startdate_dec,$enddate_dec)['max']) - intval($cmd->getStatistique($startdate_dec,$enddate_dec)['min']);
			//log::add('teleinfo', 'info', 'Conso HP --> ' . $STAT_TODAY_HP);
		}
		switch($stat){
			case "STAT_YESTERDAY":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier ==> ' . intval($STAT_YESTERDAY_HC) + intval($STAT_YESTERDAY_HP));
				return intval($STAT_YESTERDAY_HC) + intval($STAT_YESTERDAY_HP);
			case "STAT_YESTERDAY_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (HP) ==> ' . intval($STAT_YESTERDAY_HP));
				return intval($STAT_YESTERDAY_HP);
			case "STAT_YESTERDAY_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (HC) ==> ' . intval($STAT_YESTERDAY_HC));
				return intval($STAT_YESTERDAY_HC);
			case "STAT_LASTMONTH":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique mois dernier ==> ' . intval($STAT_LASTMONTH));
				return intval($STAT_LASTMONTH);
			case "STAT_MONTH":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique mois en cours ==> ' . intval($STAT_MONTH));
				return intval($STAT_MONTH);
			case "STAT_YEAR":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique anuelle ==> ' . intval($STAT_YEAR));
				return intval($STAT_YEAR);
			case "STAT_JAN_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique janvier (HP) ==> ' . intval($STAT_JAN_HP));
				return intval($STAT_JAN_HP);
			case "STAT_JAN_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique janvier (HC) ==> ' . intval($STAT_JAN_HC));
				return intval($STAT_JAN_HC);
			case "STAT_FEV_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique février (HP) ==> ' . intval($STAT_FEV_HP));
				return intval($STAT_FEV_HP);
			case "STAT_FEV_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique février (HC) ==> ' . intval($STAT_FEV_HC));
				return intval($STAT_FEV_HC);
			case "STAT_MAR_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique mars (HP) ==> ' . intval($STAT_MAR_HP));
				return intval($STAT_MAR_HP);
			case "STAT_MAR_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique mars (HC) ==> ' . intval($STAT_MAR_HC));
				return intval($STAT_MAR_HC);
			case  "STAT_AVR_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique avril (HP) ==> ' . intval($STAT_AVR_HP));
				return intval($STAT_AVR_HP);
			case "STAT_AVR_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique avril (HC) ==> ' . intval($STAT_AVR_HC));
				return intval($STAT_AVR_HC);
			case "STAT_MAI_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique mai (HP) ==> ' . intval($STAT_MAI_HP));
				return intval($STAT_MAI_HP);
			case "STAT_MAI_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique mai (HC) ==> ' . intval($STAT_MAI_HC));
				return intval($STAT_MAI_HC);
			case  "STAT_JUIN_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique juin (HP) ==> ' . intval($STAT_JUIN_HP));
				return intval($STAT_JUIN_HP);
			case  "STAT_JUIN_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique juin (HC) ==> ' . intval($STAT_JUIN_HC));
				return intval($STAT_JUIN_HC);
			case "STAT_JUI_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique juillet (HP) ==> ' . intval($STAT_JUI_HP));
				return intval($STAT_JUI_HP);
			case "STAT_JUI_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique juillet (HC) ==> ' . intval($STAT_JUI_HC));
				return intval($STAT_JUI_HC);
			case "STAT_AOU_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique août (HP) ==> ' . intval($STAT_AOU_HP));
				return intval($STAT_AOU_HP);
			case  "STAT_AOU_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique août (HC) ==> ' . intval($STAT_AOU_HC));
				return intval($STAT_AOU_HC);
			case "STAT_SEP_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique septembre (HP) ==> ' . intval($STAT_SEP_HP));
				return intval($STAT_SEP_HP);
			case "STAT_SEP_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique septembre (HC) ==> ' . intval($STAT_SEP_HC));
				return intval($STAT_SEP_HC);
			case "STAT_OCT_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique octobre (HP) ==> ' . intval($STAT_OCT_HP));
				return intval($STAT_OCT_HP);
			case "STAT_OCT_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique octobre (HC) ==> ' . intval($STAT_OCT_HC));
				return intval($STAT_OCT_HC);
			case "STAT_NOV_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique novembre (HP) ==> ' . intval($STAT_NOV_HP));
				return intval($STAT_NOV_HP);
			case "STAT_NOV_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique novembre (HC) ==> ' . intval($STAT_NOV_HC));
				return intval($STAT_NOV_HC);
			case "STAT_DEC_HP":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique décembre (HP) ==> ' . intval($STAT_DEC_HP));
				return intval($STAT_DEC_HP);
			case "STAT_DEC_HC":
				log::add('teleinfo', 'debug', 'Mise à jour de la statistique décembre (HC) ==> ' . intval($STAT_DEC_HC));
				return intval($STAT_DEC_HC);
		}
	}
	public static function Moy_Last_Hour(){
		$ppap_hp = 0;
		$ppap_hc = 0;
		$cmd_ppap = null;
		foreach (eqLogic::byType('teleinfo') as $eqLogic){
			foreach ($eqLogic->getCmd('info') as $cmd) {
				if ($cmd->getConfiguration('type')== 'stat') {
					if($cmd->getConfiguration('info_conso') == 'STAT_MOY_LAST_HOUR'){
						log::add('teleinfo', 'debug', '----- Calcul de la consommation moyenne sur la dernière heure -----');
						$cmd_ppap = $cmd;
					}
				}
			}
			if($cmd_ppap != null){
				//log::add('teleinfo', 'debug', 'Cmd trouvée');
				foreach ($eqLogic->getCmd('info') as $cmd) {
					if ($cmd->getConfiguration('type')== "data" || $cmd->getConfiguration('type')== "") {
						switch ($cmd->getConfiguration('info_conso')) {
							case "BASE":
							case "HCHP":
							case "BBRHPJB":
							case "BBRHPJW":
							case "BBRHPJR":
							$ppap_hp += $cmd->execCmd();
							log::add('teleinfo', 'debug', 'Cmd : ' . $cmd->getId() . ' / Value : ' . $cmd->execCmd());
							break;	
						}
						switch ($cmd->getConfiguration('info_conso')) {
							case "HCHC":
							case "BBRHCJB":
							case "BBRHCJW":
							case "BBRHCJR":
							$ppap_hc += $cmd->execCmd();
							log::add('teleinfo', 'debug', 'Cmd : ' . $cmd->getId() . ' / Value : ' . $cmd->execCmd());
							break;	
						}
					}
				}
			
				$cache_hc = cache::byKey('teleinfo::stat_moy_last_hour::hc', false);
				$cache_hp = cache::byKey('teleinfo::stat_moy_last_hour::hp', false);
				$cache_hc = $cache_hc->getValue();
				$cache_hp = $cache_hp->getValue();
				
				log::add('teleinfo', 'debug', 'Cache HP : ' . $cache_hp);
				log::add('teleinfo', 'debug', 'Cache HC : ' . $cache_hc);
				log::add('teleinfo', 'debug', 'Conso Wh : ' . (($ppap_hp - $cache_hp) + ($ppap_hc - $cache_hc)) );
				$cmd_ppap->event(intval((($ppap_hp - $cache_hp) + ($ppap_hc - $cache_hc))));
				
				cache::set('teleinfo::stat_moy_last_hour::hc',$ppap_hc , 7200);
				cache::set('teleinfo::stat_moy_last_hour::hp',$ppap_hp, 7200);
			}
			else{
				log::add('teleinfo', 'debug', 'Pas de calcul');
			}
		}
	}
	public static function Calculate_PAPP(){
		$ppap_hp = 0;
		$ppap_hc = 0;
		$cmd_ppap = null;
		foreach (eqLogic::byType('teleinfo') as $eqLogic){
			foreach ($eqLogic->getCmd('info') as $cmd) {
				if ($cmd->getConfiguration('type')== 'stat') {
					if($cmd->getConfiguration('info_conso') == 'PPAP_MANUELLE'){
						log::add('teleinfo', 'debug', '----- Calcul de la puissance apparante moyenne -----');
						$cmd_ppap = $cmd;
					}
				}
			}
			if($cmd_ppap != null){
				log::add('teleinfo', 'debug', 'Cmd trouvée');
				foreach ($eqLogic->getCmd('info') as $cmd) {
					if ($cmd->getConfiguration('type')== "data" || $cmd->getConfiguration('type')== "") {
						switch ($cmd->getConfiguration('info_conso')) {
							case "BASE":
							case "HCHP":
							case "BBRHPJB":
							case "BBRHPJW":
							case "BBRHPJR":
							$ppap_hp += $cmd->execCmd();
							break;	
						}
						switch ($cmd->getConfiguration('info_conso')) {
							case "HCHC":
							case "BBRHCJB":
							case "BBRHCJW":
							case "BBRHCJR":
							$ppap_hc += $cmd->execCmd();
							break;	
						}
					}
				}
			
				$cache_hc = cache::byKey('teleinfo::ppap_manuelle::hc', false);
				$datetime_mesure = date_create($cache_hc->getDatetime());
				$cache_hp = cache::byKey('teleinfo::ppap_manuelle::hp', false);
				$cache_hc = $cache_hc->getValue();
				$cache_hp = $cache_hp->getValue();
				
				$datetime_mesure = $datetime_mesure->getTimestamp();
				$datetime2 = time();
				$interval = $datetime2 - $datetime_mesure;
				log::add('teleinfo', 'debug', 'Intervale depuis la dernière valeur : ' . $interval );
				// log::add('teleinfo', 'debug', 'Conso calculée : ' . (($ppap_hp - $cache_hp) + ($ppap_hc - $cache_hc)) . ' Wh' );
				log::add('teleinfo', 'debug', 'Conso calculée : ' . ((($ppap_hp - $cache_hp) + ($ppap_hc - $cache_hc)) / $interval) * 3600 . ' Wh' );
				// $cmd_ppap->setValue(intval((($ppap_hp - $cache_hp) + ($ppap_hc - $cache_hc))* $interval));
				$cmd_ppap->setValue(intval(((($ppap_hp - $cache_hp) + ($ppap_hc - $cache_hc)) / $interval) * 3600));
				// $cmd_ppap->event(intval((($ppap_hp - $cache_hp) + ($ppap_hc - $cache_hc))* $interval));
				$cmd_ppap->event(intval(((($ppap_hp - $cache_hp) + ($ppap_hc - $cache_hc)) / $interval) * 3600));
				
				cache::set('teleinfo::ppap_manuelle::hc',$ppap_hc , 150);
				cache::set('teleinfo::ppap_manuelle::hp',$ppap_hp, 150);
			}
			else{
				log::add('teleinfo', 'debug', 'Pas de calcul');
			}
		}
	}
}
class teleinfoCmd extends cmd {
    public function execute($_options = null) {
        
    }
}
?>
