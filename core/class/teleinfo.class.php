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
class teleinfo extends eqLogic {
    /*     * *************************Attributs****************************** */
    /*     * ***********************Methode static*************************** */
	public static function getTeleinfoInfo($_url){
		return 1;
	}
	public static function cron() {
		if (config::byKey('jeeNetwork::mode') == 'slave') { //Je suis l'esclave
			if (!self::deamonRunning()) {
                self::runExternalDeamon();
            }
		}
		else{	// Je suis le jeedom master			
			self::Calculate_PAPP();
		}
    }
	
	public static function cronHourly() {
		if (config::byKey('jeeNetwork::mode') == 'master') {
			self::Moy_Last_Hour();
		}
	}
	
	
	public static function createFromDef($_def) {
		if (!isset($_def['ADCO'])) {
			log::add('teleinfo', 'info', 'Information manquante pour ajouter l\'équipement : ' . print_r($_def, true));
			return false;
		}
		$teleinfo = teleinfo::byLogicalId($_def['ADCO'], 'teleinfo');
		if (!is_object($teleinfo)) {
			$eqLogic = new teleinfo();
			$eqLogic->setName($_def['ADCO']);
		}
		$eqLogic->setLogicalId($_def['ADCO']);
		$eqLogic->setEqType_name('teleinfo');
		$eqLogic->setIsEnable(1);
		$eqLogic->setIsVisible(1);
		$eqLogic->save();
		//$eqLogic->applyModuleConfiguration();
		return $eqLogic;
	}
	
	public static function createCmdFromDef($_oADCO, $_oKey, $_oValue) {
		if (!isset($_oKey)) {
			log::add('teleinfo', 'error', 'Information manquante pour ajouter l\'équipement : ' . print_r($_oKey, true));
			return false;
		}
		if (!isset($_oADCO)) {
			log::add('teleinfo', 'error', 'Information manquante pour ajouter l\'équipement : ' . print_r($_oADCO, true));
			return false;
		}
		$teleinfo = teleinfo::byLogicalId($_oADCO, 'teleinfo');
		if (!is_object($teleinfo)) {
			//$eqLogic = new teleinfo();
			//$eqLogic->setName($_def['ADCO']);
		}
		if($teleinfo->getConfiguration('AutoCreateFromCompteur') == '1'){
			log::add('teleinfo', 'info', 'Création de la commande ' . $_oKey . ' sur l\'ADCO ' . $_oADCO);
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName($_oKey);
			//$cmd->setEqLogic_id($_oADCO);
			$cmd->setEqLogic_id($teleinfo->id);
			log::add('teleinfo', 'debug', 'EqLogicID');
			$cmd->setLogicalId($_oKey);
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', $_oKey);
			switch ($_oKey) {
							case "PAPP":
								$cmd->setDisplay('generic_type','GENERIC_INFO');
								$cmd->setDisplay('icon','<i class=\"fa fa-tachometer\"><\/i>');
								$cmd->setSubType('string');
								break;
							case "OPTARIF":
							case "HHPHC":
							case "PPOT":
							case "PEJP":
							case "DEMAIN":
								$cmd->setSubType('string');
								$cmd->setDisplay('generic_type','GENERIC_INFO');
								break;
							default:
								$cmd->setSubType('numeric');
								$cmd->setDisplay('generic_type','GENERIC_INFO');
							break;	
						}		
			$cmd->setIsHistorized(1);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
			$cmd->setValue($_oValue);
			$cmd->event($_oValue);
			return $cmd;
		}
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
			//exec("stty -F " . $teleinfo->getPort() . " " . (int) 1200, $out);
			$handle = fopen($teleinfo->getPort(), "r"); // ouverture du flux
			if (!$handle)
				throw new Exception(__($teleinfo->getConfiguration('port')." non trouvé", __FILE__));
			while(true){
				while (fread($handle, 1) != chr(2)); // on attend la fin d'une trame pour commencer a avec la trame suivante
				$char  = '';
				$trame = '';
				while ($char != chr(2)) { // on lit tous les caracteres jusqu'a la fin de la trame
					$char = fread($handle, 1);
					if ($char != chr(2)){
						$trame .= $char;
					}
				}
				//log::add('teleinfo','debug',$teleinfo->getHumanName() . ' ' . $trame);
				$teleinfo->UpdateInfo($trame);
			}
			fclose ($handle); // on ferme le flux	
		}
	}
	public function UpdateInfo($trame) {
		$datas = '';
		$trame = chop(substr($trame,1,-1)); // on supprime les caracteres de debut et fin de trame
		$messages = explode(chr(10), $trame); // on separe les messages de la trame
		foreach ($messages as $key => $message) {
			$message = explode (' ', $message, 3); // on separe l'etiquette, la valeur et la somme de controle de chaque message
			if($this->is_valid($message)){
				if($message[0] == 'ADCO')
					$this->setLogicalId($message[1]);
				else
					$this->checkAndUpdateCmd($message[0],$message[1]);
			}
		}
	}
	public function is_valid($message){
		if(count($message) < 2)
			return false;
		if(count($message) == 2){
			log::add('teleinfo','debug',$this->getHumanName() .$message[0] . '='.$message[1] . ' Aucun checksum');
			return true;
		}
		$my_sum = 0;
		$datas = str_split(' '.$message[0].$message[1]);
		foreach($datas as $cks)
          		$my_sum += ord($cks);
		$computed_checksum = ($my_sum & intval("111111", 2) ) + 0x20;
		if(chr($computed_checksum) == trim($message[2])){
			log::add('teleinfo','debug',$this->getHumanName() .$message[0] . '='.$message[1] . ' Checksum valide');
			return true;
		}else{
			log::add('teleinfo','debug',$this->getHumanName() .$message[0] . '='.$message[1] . ' Checksum invalide');
			return false;
		}
	}
	public function getPort() {
		$port=$this->getConfiguration('port');
		if($port == 'serie')
			$port=$teleinfo->getConfiguration('modem_serie_addr');
		elseif($this->getConfiguration('2cmpt')){
			$nb=substr($port,-1)+1;
			$port='/dev/ttyUSB'.$nb;
		}
		return $port;
	}
	public static function CalculateTodayStats(){
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
		foreach (eqLogic::byType('teleinfo') as $eqLogic){
			foreach ($eqLogic->getCmd('info') as $cmd) {
				if ($cmd->getConfiguration('type')== "stat") {
					if($cmd->getConfiguration('info_conso') == "STAT_TODAY"){
						log::add('teleinfo', 'info', 'Mise à jour de la statistique journalière ==> ' . intval($STAT_TODAY_HP + $STAT_TODAY_HC));
						$cmd->setValue(intval($STAT_TODAY_HP + $STAT_TODAY_HC));
						$cmd->event(intval($STAT_TODAY_HP + $STAT_TODAY_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "TENDANCE_DAY"){
						log::add('teleinfo', 'debug', 'Mise à jour de la tendance journalière ==> ' . '(Hier : '. intval($STAT_YESTERDAY_HC + $STAT_YESTERDAY_HP) . ' Aujourd\'hui : ' . intval($STAT_TODAY_HC + $STAT_TODAY_HP) . ' Différence : ' . (intval($STAT_YESTERDAY_HC + $STAT_YESTERDAY_HP) - intval($STAT_TODAY_HC + $STAT_TODAY_HP)) . ')');
						$cmd->setValue(intval($STAT_YESTERDAY_HC + $STAT_YESTERDAY_HP) - intval($STAT_TODAY_HC + $STAT_TODAY_HP));
						$cmd->event(intval($STAT_YESTERDAY_HC + $STAT_YESTERDAY_HP) - intval($STAT_TODAY_HC + $STAT_TODAY_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_TODAY_HP"){
						log::add('teleinfo', 'info', 'Mise à jour de la statistique journalière (HP) ==> ' . intval($STAT_TODAY_HP));
						$cmd->setValue(intval($STAT_TODAY_HP));
						$cmd->event(intval($STAT_TODAY_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_TODAY_HC"){
						log::add('teleinfo', 'info', 'Mise à jour de la statistique journalière (HC) ==> ' . intval($STAT_TODAY_HC));
						$cmd->setValue(intval($STAT_TODAY_HC));
						$cmd->event(intval($STAT_TODAY_HC));
					}
				}
			}
		}
	
	}
	
	public static function CalculateOtherStats(){
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
		
		foreach (eqLogic::byType('teleinfo') as $eqLogic){
			foreach ($eqLogic->getCmd('info') as $cmd) {
				if ($cmd->getConfiguration('type')== "stat" || $cmd->getConfiguration('type')== "panel") {
					if($cmd->getConfiguration('info_conso') == "STAT_YESTERDAY"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier ==> ' . intval($STAT_YESTERDAY_HC) + intval($STAT_YESTERDAY_HP));
						$cmd->setValue(intval($STAT_YESTERDAY_HC) + intval($STAT_YESTERDAY_HP));
						$cmd->event(intval($STAT_YESTERDAY_HC) + intval($STAT_YESTERDAY_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_YESTERDAY_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (HP) ==> ' . intval($STAT_YESTERDAY_HP));
						$cmd->setValue(intval($STAT_YESTERDAY_HP));
						$cmd->event(intval($STAT_YESTERDAY_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_YESTERDAY_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique hier (HC) ==> ' . intval($STAT_YESTERDAY_HC));
						$cmd->setValue(intval($STAT_YESTERDAY_HC));
						$cmd->event(intval($STAT_YESTERDAY_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_LASTMONTH"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique mois dernier ==> ' . intval($STAT_LASTMONTH));
						$cmd->setValue(intval($STAT_LASTMONTH));
						$cmd->event(intval($STAT_LASTMONTH));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_MONTH"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique mois en cours ==> ' . intval($STAT_MONTH));
						$cmd->setValue(intval($STAT_MONTH));
						$cmd->event(intval($STAT_MONTH));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_YEAR"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique anuelle ==> ' . intval($STAT_YEAR));
						$cmd->setValue(intval($STAT_YEAR));
						$cmd->event(intval($STAT_YEAR));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_JAN_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique janvier (HP) ==> ' . intval($STAT_JAN_HP));
						$cmd->setValue(intval($STAT_JAN_HP));
						$cmd->event(intval($STAT_JAN_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_JAN_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique janvier (HC) ==> ' . intval($STAT_JAN_HC));
						$cmd->setValue(intval($STAT_JAN_HC));
						$cmd->event(intval($STAT_JAN_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_FEV_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique février (HP) ==> ' . intval($STAT_FEV_HP));
						$cmd->setValue(intval($STAT_FEV_HP));
						$cmd->event(intval($STAT_FEV_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_FEV_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique février (HC) ==> ' . intval($STAT_FEV_HC));
						$cmd->setValue(intval($STAT_FEV_HC));
						$cmd->event(intval($STAT_FEV_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_MAR_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique mars (HP) ==> ' . intval($STAT_MAR_HP));
						$cmd->setValue(intval($STAT_MAR_HP));
						$cmd->event(intval($STAT_MAR_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_MAR_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique mars (HC) ==> ' . intval($STAT_MAR_HC));
						$cmd->setValue(intval($STAT_MAR_HC));
						$cmd->event(intval($STAT_MAR_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_AVR_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique avril (HP) ==> ' . intval($STAT_AVR_HP));
						$cmd->setValue(intval($STAT_AVR_HP));
						$cmd->event(intval($STAT_AVR_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_AVR_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique avril (HC) ==> ' . intval($STAT_AVR_HC));
						$cmd->setValue(intval($STAT_AVR_HC));
						$cmd->event(intval($STAT_AVR_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_MAI_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique mai (HP) ==> ' . intval($STAT_MAI_HP));
						$cmd->setValue(intval($STAT_MAI_HP));
						$cmd->event(intval($STAT_MAI_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_MAI_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique mai (HC) ==> ' . intval($STAT_MAI_HC));
						$cmd->setValue(intval($STAT_MAI_HC));
						$cmd->event(intval($STAT_MAI_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_JUIN_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique juin (HP) ==> ' . intval($STAT_JUIN_HP));
						$cmd->setValue(intval($STAT_JUIN_HP));
						$cmd->event(intval($STAT_JUIN_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_JUIN_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique juin (HC) ==> ' . intval($STAT_JUIN_HC));
						$cmd->setValue(intval($STAT_JUIN_HC));
						$cmd->event(intval($STAT_JUIN_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_JUI_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique juillet (HP) ==> ' . intval($STAT_JUI_HP));
						$cmd->setValue(intval($STAT_JUI_HP));
						$cmd->event(intval($STAT_JUI_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_JUI_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique juillet (HC) ==> ' . intval($STAT_JUI_HC));
						$cmd->setValue(intval($STAT_JUI_HC));
						$cmd->event(intval($STAT_JUI_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_AOU_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique août (HP) ==> ' . intval($STAT_AOU_HP));
						$cmd->setValue(intval($STAT_AOU_HP));
						$cmd->event(intval($STAT_AOU_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_AOU_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique août (HC) ==> ' . intval($STAT_AOU_HC));
						$cmd->setValue(intval($STAT_AOU_HC));
						$cmd->event(intval($STAT_AOU_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_SEP_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique septembre (HP) ==> ' . intval($STAT_SEP_HP));
						$cmd->setValue(intval($STAT_SEP_HP));
						$cmd->event(intval($STAT_SEP_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_SEP_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique septembre (HC) ==> ' . intval($STAT_SEP_HC));
						$cmd->setValue(intval($STAT_SEP_HC));
						$cmd->event(intval($STAT_SEP_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_OCT_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique octobre (HP) ==> ' . intval($STAT_OCT_HP));
						$cmd->setValue(intval($STAT_OCT_HP));
						$cmd->event(intval($STAT_OCT_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_OCT_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique octobre (HC) ==> ' . intval($STAT_OCT_HC));
						$cmd->setValue(intval($STAT_OCT_HC));
						$cmd->event(intval($STAT_OCT_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_NOV_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique novembre (HP) ==> ' . intval($STAT_NOV_HP));
						$cmd->setValue(intval($STAT_NOV_HP));
						$cmd->event(intval($STAT_NOV_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_NOV_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique novembre (HC) ==> ' . intval($STAT_NOV_HC));
						$cmd->setValue(intval($STAT_NOV_HC));
						$cmd->event(intval($STAT_NOV_HC));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_DEC_HP"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique décembre (HP) ==> ' . intval($STAT_DEC_HP));
						$cmd->setValue(intval($STAT_DEC_HP));
						$cmd->event(intval($STAT_DEC_HP));
					}
					else if($cmd->getConfiguration('info_conso') == "STAT_DEC_HC"){
						log::add('teleinfo', 'debug', 'Mise à jour de la statistique décembre (HC) ==> ' . intval($STAT_DEC_HC));
						$cmd->setValue(intval($STAT_DEC_HC));
						$cmd->event(intval($STAT_DEC_HC));
					}
				}
			}
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
	
	public function preSave() {
		$this->setCategory('energy',  1);
		$cmd = null;
		$cmd = $this->getCmd('info','HEALTH');
		if (is_object($cmd)) {
            $cmd->remove();
			$cmd->save();
        }
	}
	
	public function postSave() {
		log::add('teleinfo', 'debug', '-------- Sauvegarde de l\'objet --------');
		//$template_name = "";
		/*if($this->getConfiguration('template') == 'bleu'){
                    $template_name = "teleinfo_bleu_";		
		}
        else if($this->getConfiguration('template') == 'base'){
            $template_name = "teleinfo_base_";
        }
        else if($this->getConfiguration('template') == ''){
            goto after_template;
        }
		log::add('teleinfo', 'info', '==> Gestion des templates');
              */  
		foreach ($this->getCmd(null, null, true) as $cmd) {
			 //$replace['#'.$cmd->getLogicalId().'#'] = $cmd->toHtml($_version);
			 switch ($cmd->getConfiguration('info_conso')) {
					case "BASE":
					case "HCHP":
					case "EJPHN":
					case "BBRHPJB":
					case "BBRHPJW":
					case "BBRHPJR":
					case "HCHC":
					case "BBRHCJB":
					case "BBRHCJW":
					case "BBRHCJR":
						log::add('teleinfo', 'debug', '=> index');
						if($cmd->getDisplay('generic_type') == ''){
							$cmd->setDisplay('generic_type','GENERIC_INFO');
						}
						//$cmd->setTemplate('dashboard',  $template_name . 'teleinfo_new_index');
						//$cmd->setTemplate('mobile',  $template_name . 'teleinfo_new_index');
						$cmd->save();
						$cmd->refresh();
						break;
					case "PAPP":
						log::add('teleinfo', 'debug', '=> papp');
						if($cmd->getDisplay('generic_type') == ''){
							$cmd->setDisplay('generic_type','GENERIC_INFO');
							$cmd->setDisplay('icon','<i class=\"fa fa-tachometer\"><\/i>');
						}
						//$cmd->setTemplate('dashboard',  $template_name . 'teleinfo_conso_inst');
						//$cmd->setTemplate('mobile',  $template_name . 'teleinfo_conso_inst');
						$cmd->save();
						$cmd->refresh();
					break;	
					case "PTEC":
						log::add('teleinfo', 'debug', '=> ptec');
						if($cmd->getDisplay('generic_type') == ''){
							$cmd->setDisplay('generic_type','GENERIC_INFO');
						}
						//$cmd->setTemplate('dashboard',  $template_name . 'teleinfo_ptec');
						//$cmd->setTemplate('mobile',  $template_name . 'teleinfo_ptec');
						$cmd->save();
						$cmd->refresh();
						break;
					default :
						log::add('teleinfo', 'debug', '=> ptec');
						if($cmd->getDisplay('generic_type') == ''){
							$cmd->setDisplay('generic_type','GENERIC_INFO');
						}
						break;
				}
		}
		after_template:
		log::add('teleinfo', 'info', '==> Gestion des id des commandes');
		foreach ($this->getCmd('info') as $cmd) {
		//foreach ($this->getCmd(null, null, true) as $cmd) {
			log::add('teleinfo', 'debug', 'Commande : ' . $cmd->getConfiguration('info_conso'));
			$cmd->setLogicalId($cmd->getConfiguration('info_conso'));
			$cmd->save();
		}
		log::add('teleinfo', 'debug', '-------- Fin de la sauvegarde --------');
		if($this->getConfiguration('AutoGenerateFields') == '1'){
			$this->CreateFromAbo($this->getConfiguration('abonnement'));
		}
		
		$this->CreateOtherCmd();
	
		$this->CreatePanelStats();
		
		/*foreach ($this->getCmd(null, null, true) as $cmd) {
			$cmd->setLogicalId($cmd->getConfiguration('info_conso'));
			$cmd->save();
		}*/
	}
	
	public function preRemove() {
		log::add('teleinfo', 'debug', 'Suppression d\'un objet');
	}
	
	public function CreateOtherCmd(){
		$array = array("HEALTH");
		for($ii = 0; $ii < 1; $ii++){
			$cmd = $this->getCmd('info',$array[$ii]);
			if ($cmd == null) {
				$cmd = null;
				$cmd = new teleinfoCmd();
				$cmd->setName($array[$ii]);
				$cmd->setEqLogic_id($this->id);
				$cmd->setLogicalId($array[$ii]);
				$cmd->setType('info');
				$cmd->setConfiguration('info_conso', $array[$ii]);
				$cmd->setConfiguration('type', 'health');
				$cmd->setSubType('numeric');
				$cmd->setUnite('Wh');
				$cmd->setIsHistorized(0);
				$cmd->setEventOnly(1);
				$cmd->setIsVisible(0);
				$cmd->save();
			}
		}
	}
	
	public function CreatePanelStats(){
		$array = array("STAT_JAN_HP","STAT_JAN_HC", "STAT_FEV_HP","STAT_FEV_HC", "STAT_MAR_HP","STAT_MAR_HC", "STAT_AVR_HP","STAT_AVR_HC", "STAT_MAI_HP","STAT_MAI_HC", "STAT_JUIN_HP","STAT_JUIN_HC", "STAT_JUI_HP","STAT_JUI_HC", "STAT_AOU_HP","STAT_AOU_HC", "STAT_SEP_HP","STAT_SEP_HC", "STAT_OCT_HP","STAT_OCT_HC", "STAT_NOV_HP","STAT_NOV_HC", "STAT_DEC_HP","STAT_DEC_HC");
		for($ii = 0; $ii < 24; $ii++){
			$cmd = $this->getCmd('info',$array[$ii]);
			if ($cmd == null) {
				$cmd = null;
				$cmd = new teleinfoCmd();
				$cmd->setName($array[$ii]);
				$cmd->setEqLogic_id($this->id);
				$cmd->setLogicalId($array[$ii]);
				$cmd->setType('info');
				$cmd->setConfiguration('info_conso', $array[$ii]);
				$cmd->setConfiguration('type', 'panel');
				$cmd->setDisplay('generic_type','DONT');
				$cmd->setSubType('numeric');
				$cmd->setUnite('Wh');
				$cmd->setIsHistorized(0);
				$cmd->setEventOnly(1);
				$cmd->setIsVisible(0);
				$cmd->save();
			}
			else{
				$cmd->setDisplay('generic_type','DONT');
				$cmd->save();
			}
		}
	}
	
	public function CreateFromAbo($_abo){
		$this->setConfiguration('AutoGenerateFields','0');
		$this->save();
		if($_abo == 'base'){
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Index');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('BASE');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'BASE');
			$cmd->setDisplay('generic_type','GENERIC_INFO');
			$cmd->setSubType('numeric');
			$cmd->setUnite('Wh');
			$cmd->setIsHistorized(1);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Intensité Instantanée');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('IINST');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'IINST');
			$cmd->setDisplay('generic_type','DONT');
			$cmd->setSubType('numeric');
			$cmd->setUnite('A');
			$cmd->setIsHistorized(0);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Puissance apparente');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('PAPP');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'PAPP');
			$cmd->setDisplay('generic_type','GENERIC_INFO');
			$cmd->setDisplay('icon','<i class=\"fa fa-tachometer\"><\/i>');
			$cmd->setSubType('numeric');
			$cmd->setUnite('VA (~W)');
			$cmd->setIsHistorized(1);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Dépassement');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('ADPS');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'ADPS');
			$cmd->setDisplay('generic_type','DONT');
			$cmd->setSubType('numeric');
			$cmd->setUnite('A');
			$cmd->setIsHistorized(0);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
		}
		else if($_abo == 'bleu'){
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Index HP');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('HCHP');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'HCHP');
			$cmd->setDisplay('generic_type','GENERIC_INFO');
			$cmd->setSubType('numeric');
			$cmd->setUnite('Wh');
			$cmd->setIsHistorized(1);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Index HC');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('HCHC');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'HCHC');
			$cmd->setDisplay('generic_type','GENERIC_INFO');
			$cmd->setSubType('numeric');
			$cmd->setUnite('Wh');
			$cmd->setIsHistorized(1);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Puissance Apparente');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('PAPP');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'PAPP');
			$cmd->setDisplay('generic_type','GENERIC_INFO');
			$cmd->setDisplay('icon','<i class=\"fa fa-tachometer\"><\/i>');
			$cmd->setSubType('numeric');
			$cmd->setUnite('VA (~W)');
			$cmd->setIsHistorized(1);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Intensité');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('IINST');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'IINST');
			$cmd->setDisplay('generic_type','DONT');
			$cmd->setSubType('numeric');
			$cmd->setUnite('A');
			$cmd->setIsHistorized(0);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Dépassement');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('ADPS');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'ADPS');
			$cmd->setDisplay('generic_type','DONT');
			$cmd->setSubType('numeric');
			$cmd->setUnite('A');
			$cmd->setIsHistorized(0);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
			$cmd = null;
			$cmd = new teleinfoCmd();
			$cmd->setName('Plage Horaire');
			$cmd->setEqLogic_id($this->id);
			$cmd->setLogicalId('HHPHC');
			$cmd->setType('info');
			$cmd->setConfiguration('info_conso', 'HHPHC');
			$cmd->setDisplay('generic_type','DONT');
			$cmd->setSubType('string');
			//$cmd->setUnite('');
			$cmd->setIsHistorized(0);
			$cmd->setEventOnly(1);
			$cmd->setIsVisible(1);
			$cmd->save();
		}
	}
}
class teleinfoCmd extends cmd {
    public function execute($_options = null) {
        
    }
	
    /*     * **********************Getteur Setteur*************************** */
}
?>
