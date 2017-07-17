<?php
//error_reporting(E_ALL);
require_once ('ClientsReports.php');
require_once ('CarsReports.php');
require_once ('RevenueReports.php');

Class ReportsTemplates {

	public static $types = array(
		'jada-kl-1' => array('ClientsReports', 'TotalRezAndSum'), //Kopējais darījumu skaits un ieņēmumi izvēlētajā periodā
		'jada-kl-2' => array('ClientsReports', 'TotalKlients'), //Kopējais klientu skaits izvēlētajā periodā
		'jada-kl-3' => array('ClientsReports', 'TotalKlientsOneDay'), //Kopējais reģistrēto  klientu skaits uz izvēlēto datumu
		'jada-kl-4' => array('ClientsReports', 'KlientsRez'), // Darījumu  un nomas dienu skaits uz 1 konkrētu izvēlēto klientu izvēlētajā periodā
		'jada-kl-5' => array('ClientsReports', 'TotalCanceled'), // Neapstiprināto /atteikto nomas darījumu skaits

		'jada-car-1' => array('CarsReports', 'CarsByDay'),
		'jada-car-2' => array('CarsReports', 'CarsByPeriod'), //Auto skaits izvēlētajā periodā)
		'jada-car-3' => array('CarsReports', 'CarsRentSum'), //Nomas dienu skaits izvēlētajā periodā
		'jada-car-4' => array('CarsReports', 'CarsLoad'), //Noslodze

		'jada-rev-1' => array('RevenueReports', 'RevPeriod'), // Ieņēmumi izvēlētajā periodā
		'jada-rev-2' => array('RevenueReports', 'RevAveragePeriod'), // Vidējie ieņēmumi izvēlētajā periodā
		'jada-rev-3' => array('RevenueReports', 'RevAverageOneDayPeriod'), // Vidējie ieņēmumi uz 1 nomas dienu
	);

	public $filter = array();

	public function __construct($filter = array()) {
		$this->filter = $filter;
	}

	public static function checkType($type)
	{
		if(array_key_exists($type, self::$types)) return true;
		return false;
	}

	public function makeTemplate($type) {

		$class = self::$types[$type][0];
		$func = self::$types[$type][1];

		$template = new $class($this->filter);
		$xls = $template->$func();

		return $xls;
	}
	
}

class ReportsModel {

	public function numFormat($number) {
		return number_format($number, 2, '.', '');
	}

	public function daysInterval($from, $to) {
		$period = new DatePeriod(
     		$from,
     		new DateInterval('P1D'),
     		$to
		);

		$res = array();
		
		foreach( $period as $date) { $res[] = $date; }
		
		$res[] = $to; 

		return $res;
	}

	public function monthInterval($from, $to) {
		$period = new DatePeriod(
			$from,
			new DateInterval('P1M'),
			$to
		);

		$res = array();
		
		foreach( $period as $date) { $res[] = $date; }
		
		if(empty($res)) $res[] = $to; 

		return $res;
	}

	public function getStatus($filter) {
		$where = '';

		if($filter['sta']) {
			$arr = array();
			foreach($filter['sta'] as $k => $s) {
				if($s) $arr[] = $k;
			}
			if(!empty($arr)) $where .= " AND `t1`.`status` IN ('".implode("', '", $arr)."')";
		}

		return $where;
	}

	public function getStatusCountryLegal($filter) {
		
		$where = '';

		$where .= $this->getStatus($filter);

		if(strlen($filter['country'])) {
			if($filter['country'] == -1) $where .= ' AND c_country NOT IN (123,183)';
			else $where .= ' AND c_country IN (' . $filter['country'] . ')';
		}

		if($filter['legal']) {
			if($filter['legal'] == 1) $where .= ' AND (`j_address` IS NULL AND `j_city` IS NULL )';
			if($filter['legal'] == 2) $where .= ' AND (`j_address` IS NOT NULL AND `j_city` IS NOT NULL )';
		}

		return $where;
	}


	public function getOldNewUser($filter, $from) {
		$pjBooking = pjBookingModel::factory();
		$emails = $pjBooking->select('c_email, COUNT(c_email) as this_period')->where($filter)->groupBy('c_email')->findAll()->getData();

		$pastavigie = array();
		$new = array();

		if(!empty($emails)) {

			$pjBooking = pjBookingModel::factory();
			$where = " (DATE(`to`) < '{$from->format('Y-m-d')}') ";
			$old_emails = $pjBooking->select('c_email')->where($where)->groupBy('c_email')->findAll()->getData();

			$old_emails_arr = array();
			if (!empty($old_emails)) {
				foreach ($old_emails as $value) {
					$old_emails_arr[] = $value['c_email'];
				}
			}

			foreach($emails as $value) {
				if($value['count_e'] > 1) {
					$pastavigie[] = $value['c_email'];
				}elseif(in_array($value['c_email'], $old_emails_arr)){
					$pastavigie[] = $value['c_email'];
				}else{
					$new[] = $value['c_email'];
				}
			}

		}

		return array(
			'pastavigie' => $pastavigie,
			'new' => $new
		);
	}

	public function getAllCars() {
		$pjCarModel = pjCarModel::factory();
		$pjCarModel->select('DISTINCT ( CONCAT(make,"__",model)) AS makemodel, `make`, `model`');
		$makes = $pjCarModel->findAll()->getData();
		return (!empty($makes))?$makes:array();
	}

	public function ReportCarAvailable($filter, $car, $date) {
		$pjCarModel = pjCarModel::factory();
		$pjCarModel->select('*')->where($filter)->where("`make` = '{$car['make']}' AND `model` = '{$car['model']}'");

		$all_cars = $pjCarModel->findAll()->getData();
		$total = count($all_cars);

		if($total == 0) return 0;

		$car_ids = array();
		foreach($all_cars as $car){
			$car_ids[] = $car['id'];
		}

		$pjBookingModel = pjBookingModel::factory();
		$rez_where = "DATE(`from`) <= '{$date->format('Y-m-d')}' AND DATE(`to`) >= '{$date->format('Y-m-d')}' AND `car_id` IN(".implode(', ', $car_ids).") ";
		$rez = $pjBookingModel->select('*')->where($rez_where)->findAll()->getData();

		$total_rez = count($rez);
		return $total - $total_rez;
	}

	public function ReportCarAvailableMonth($car, $month) {
		$pjCarModel = pjCarModel::factory();
		$f_month_one = clone $month;
		$filter = "( DATE(`bought`) <= '{$f_month_one->format('Y-m-t')}' AND  (`sold` > '{$f_month_one->format('Y-m-t')}' OR `sold` = '0000-00-00') AND `bought` != '0000-00-00' )";
		$pjCarModel->select('*')->where($filter)->where("`make` = '{$car['make']}' AND `model` = '{$car['model']}'");

		$all_cars = $pjCarModel->findAll()->getData();
		$total = count($all_cars);

		if($total == 0) return 0;

		$car_ids = array();
		foreach($all_cars as $car){
			$car_ids[] = $car['id'];
		}

		$pjBookingModel = pjBookingModel::factory();
		$f_month_two = clone $month;
		$rez_where = "DATE(`from`) <= '{$f_month_two->format('Y-m-t')}' AND DATE(`to`) > '{$f_month_two->format('Y-m-t')}' AND `car_id` IN(".implode(', ', $car_ids).") ";
		$rez_where .= 'AND `t1`.`status` IN("confirmed","pending")';
		$rez = $pjBookingModel->select('*')->where($rez_where)->findAll()->getData();

		$total_rez = count($rez);

		return $total - $total_rez;
	}

	public function ReportNewCarMonth($car, $month) {
		$pjCarModel = pjCarModel::factory();
		$filter = "( MONTH(`bought`) = '{$month->format('m')}' AND  YEAR(`bought`) = '{$month->format('Y')}' )";
		$pjCarModel->select('*')->where($filter)->where("`make` = '{$car['make']}' AND `model` = '{$car['model']}'");

		$all_cars = $pjCarModel->findAll()->getData();
		return count($all_cars);
	}

	public function ReportRemovedCarMonth($car, $month) {
		$pjCarModel = pjCarModel::factory();
		$filter = "( MONTH(`sold`) = '{$month->format('m')}' AND  YEAR(`sold`) = '{$month->format('Y')}' )";
		$pjCarModel->select('*')->where($filter)->where("`make` = '{$car['make']}' AND `model` = '{$car['model']}'");

		$all_cars = $pjCarModel->findAll()->getData();
		return count($all_cars);
	}

	public function ReportCarsCount($filter, $day, $use) {
		$pjCarModel = pjCarModel::factory();

		$f_day = clone $day;

		if($use == 'month') {
			$new_filter = " ( DATE(`bought`) <= '{$f_day->format('Y-m')}-01' AND  (`sold` >= '{$f_day->modify('+1 month')->format('Y-m')}-01' OR `sold` = '0000-00-00') AND `bought` != '0000-00-00' )";
		}else{
			$new_filter = " ( DATE(`bought`) <= '{$f_day->format('Y-m-d')}' AND  (`sold` >= '{$f_day->format('Y-m-d')}' OR `sold` = '0000-00-00') AND `bought` != '0000-00-00' )";
		}
		$new_filter .= $filter;

		$pjCarModel->select("t1.make,t1.model,t1.registration_number,
				 t3.rent_type
				,t3.make as typeMake ,t3.model as typeModel
				"
				)
				->join('pjCarType', "t2.car_id = t1.id", 'left')
				->join('pjType', "t3.id = t2.type_id", 'left')
				->where($new_filter);

		$all_cars = $pjCarModel->findAll()->getData();
		return count($all_cars);
	}

	public function ReportPaymentsSums($filter)
	{
		$pjBookingModel = pjBookingModel::factory();

		$sel = '`t1`.`id`, t1.booking_id, `t1`.`total_price`, `car_id`, `from`, `to`,`coupon`,
		DATEDIFF( `to`,`from`) AS order_days,
		SUM(  `t2`.`amount`) AS payments_sum,
		ROUND(SUM( DISTINCT `t2`.`amount`)/DATEDIFF( `to`,`from`),2) AS per_day
		';

		$pjBookingModel
				->select($sel)
				->join('pjBookingPayment', "t1.id = t2.booking_id", 'left')
		;

		$pjBookingModel->where($filter);

		$rows = $pjBookingModel
						->groupBy('t1.id')
						->findAll()->getData();

		return $rows;
	}

	public function ReportAll($filter) {
		$pjBooking = pjBookingModel::factory();
		$data = $pjBooking->select("*")->where($filter)->findAll()->getData();

		return (empty($data))?array():$data;

	}

	public function ReportKlientsCount($filter, $from) {

		// Count All
		$pjBooking = pjBookingModel::factory();
		$count_all = $pjBooking->select("c_email")->where($filter)->groupBy('c_email, j_address')->findAll()->getData();
		$count_all[0]['total'] = count($count_all);

		//Juridiska persona
		$pjBooking = pjBookingModel::factory();
		$jur = $pjBooking->select("c_email")->where($filter)->where('LENGTH(j_address) > 1 ')->groupBy('c_email, j_address')->findAll()->getData();
		$count_all[0]['jur'] = count($jur);

		//Fiziska persona
		$pjBooking = pjBookingModel::factory();
		$fiz = $pjBooking->select("c_email")->where($filter)->where(' `j_address` IS NULL  AND `j_city` IS NULL  ')
				->groupBy('c_email, j_address')->findAll()->getData();
		$count_all[0]['fiz'] = count($fiz);

		//Country
		$pjBooking = pjBookingModel::factory();
		$country_where = $filter.' AND (`c_country` IS NOT NULL OR `j_country` IS NOT NULL) ';
		$country = $pjBooking->select("IF(`c_country` IS NOT NULL, c_country, j_country) as country")->where($country_where)
				->groupBy('c_email, j_address')->findAll()->getData();

		$lv = 0;
		$ru = 0;
		$other = 0;

		if(!empty($country)) {
			foreach ($country as $value) {
				if($value['country'] == '123'){
					$lv++;
				}elseif($value['country'] == '183'){
					$ru++;
				}elseif(!empty($value['country']) && $value['country'] != 'NULL'){
					$other++;
				}
			}
		}

		$count_all[0]['lv'] = $lv;
		$count_all[0]['ru'] = $ru;
		$count_all[0]['other'] = $other;

		//Pastavigie un Jaunie

		$pjBooking = pjBookingModel::factory();
		$emails = $pjBooking->select('c_email, COUNT(c_email) as this_period')->where($filter)->groupBy('c_email, j_address')->findAll()->getData();

		$pastavigie = 0;
		$new = 0;

		if(!empty($emails)) {

			$pjBooking = pjBookingModel::factory();
			$where = " (DATE(`from`) < '{$from->format('Y-m-d')}') ";
			$old_emails = $pjBooking->select('c_email')->where($where)->groupBy('c_email, j_address')->findAll()->getData();

			$old_emails_arr = array();
			if (!empty($old_emails)) {
				foreach ($old_emails as $value) {
					$old_emails_arr[] = $value['c_email'];
				}
			}

			foreach($emails as $value) {
				if($value['count_e'] > 1) {
					$pastavigie++;
				}elseif(in_array($value['c_email'], $old_emails_arr)){
					$pastavigie++;
				}else{
					$new++;
				}
			}

		}

		$count_all[0]['pastavigie'] = $pastavigie;
		$count_all[0]['new'] = $new;

		return $count_all;
	}

	public function ReportCarRez($filter)
	{
		$pjBookingModel = pjBookingModel::factory();
		$data = $pjBookingModel
				->select("t1.*, DATE_FORMAT(DATE_ADD(`t1`.`from`, INTERVAL rental_days DAY), '%Y-%m-%d') as toWork, DATE_FORMAT(`t1`.`from`, '%Y-%m-%d') as fromWork
				, t2.make,t2.model,t2.registration_number,
				 t3.rent_type
				,t3.make as typeMake ,t3.model as typeModel
				"
				)
				->join('pjCar', 't2.id=t1.car_id', 'left')
				->join('pjType', "t3.id = t1.type_id", 'left')
				->where($filter)
				->findAll()->getData();
		return (empty($data))?array():$data;
	}
}