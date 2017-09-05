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
			//$ret = exec("stty -F " . $teleinfo->getPort() . " " . (int) 1200, $out);
          		$handle = @fopen($teleinfo->getPort(), "r");
			if (!$handle)
				throw new Exception(__($teleinfo->getPort()." non trouvé", __FILE__));
			// on attend la fin d'une trame pour commencer a avec la trame suivante
          		while (@fread($handle, 1) != chr(0x02)); 
			while (!feof($handle)) {
				$char  = '';
				$trame = ''; 
				// on lit tous les caracteres jusqu'a la fin de la trame
				while ($char != chr(0x02)) {
					$char = @fread($handle, 1);
					if ($char != chr(0x02)){
						$trame .= $char;
					}
				}
              			$trame=trim($trame);
				log::add('teleinfo','debug',$teleinfo->getHumanName() . ': ' . $trame);
				$teleinfo->UpdateInfo($trame);
			}
			fclose ($handle);	
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
					break;
					case 'PTEC':
						$value=substr($value,0,2);
					break;
				}
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
		elseif($this->getConfiguration('2cmpt')){
			$nb=substr($port,-1)+1;
			$port='/dev/ttyUSB'.$nb;
		}
		return $port;
	}
}
class teleinfoCmd extends cmd {
    public function execute($_options = null) {
        
    }
}
?>
