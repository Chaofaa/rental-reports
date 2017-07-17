<?php

Class ClientsReports extends ReportsModel {

	public $filter = array();

	public function __construct($filter = array()) {
		$this->filter = $filter;
	}

	public function getInterval()
	{
		$intervals = $this->daysInterval($this->filter['date']['from'], $this->filter['date']['to']);
		$days = count($intervals);
		$use = 'days';

		if($days >= 32) {
			$intervals = $this->monthInterval($this->filter['date']['from'], $this->filter['date']['to']);
			$use = 'month';
		}

		return array(
			'intervals' => $intervals,
			'use' => $use
		);
	}

	public function TotalRezAndSum() {

		$intervals = $this->getInterval();

		$xls[0] = array(
			0 => array('data' => (($intervals['use'] == 'days')?'Datums':'Mēnesis'), 'center' => true, 'wrap' => true, 'border' => true),
			1 => array('data' => 'Darījumu skaits', 'center' => true, 'wrap' => true, 'border' => true),
			2 => array('data' => 'Apgrozījums', 'center' => true, 'wrap' => true, 'border' => true),
			3 => array('data' => 'Vidējie ieņēmumi uz 1 darījumu', 'center' => true, 'wrap' => true, 'border' => true)
		);

		$row = 1;
		$total = array();

		foreach($intervals['intervals'] as $day){

			if($intervals['use'] == 'days') $filter = "( DATE(`from`) = '{$day->format('Y-m-d')}' )";
			else $filter = "( MONTH(`from`) = '{$day->format('m')}' AND YEAR(`from`) = '{$day->format('Y')}' )";

			$filter .= $this->getStatusCountryLegal($this->filter);

			$data = $this->ReportPaymentsSums($filter);

			$xls[$row][0] = array(
				'data' => (($intervals['use'] == 'days')?$day->format('d.m.Y'):$day->format('t.m.Y')),
				'left' => true,
				'date_format' => true,
				'border' => true
			);
			if($intervals['use'] == 'days') $xls[$row][0]['date'] = true;
			else $xls[$row][0]['month'] = true;

			$tmp_row = array();
			if(!empty($data)){
				foreach($data as $k => $v) {

					$tmp_row['total'] += 1;
					$tmp_row['price'] += $v['total_price'];
				}

				$tmp_row['price'] = $this->numFormat($tmp_row['price']);
				$tmp_row['price_per_one'] = $this->numFormat($tmp_row['price'] / count($data));
			}else{
				$tmp_row['total'] = 0;
				$tmp_row['price'] = 0;
				$tmp_row['price_per_one'] = 0;
			}

			$total['total'] += $tmp_row['total'];
			$total['price'] += $tmp_row['price'];

			$xls[$row][1] = array('data' => $tmp_row['total'], 'right' => true, 'border' => true);
			$xls[$row][2] = array('data' => $tmp_row['price'], 'right' => true, 'border' => true);
			$xls[$row][3] = array('data' => $tmp_row['price_per_one'], 'right' => true, 'border' => true);

			$row++;
		}

		$xls[$row] = array(
			0 => array('data' => 'Kopā', 'left' => true, 'border' => true),
			1 => array('data' => $total['total'], 'right' => true, 'border' => true),
			2 => array('data' => $this->numFormat($total['price']), 'right' => true, 'border' => true),
			3 => array('data' => $this->numFormat($total['price'] / $total['total']), 'right' => true, 'border' => true)
		);

		return $xls;
	}


	public function TotalKlients() {

		$intervals = $this->getInterval();

		$xls[0] = array(
			0 => array('data' => (($intervals['use'] == 'days')?'Datums':'Mēnesis'), 'rowspan' => '1', 'center' => true, 'wrap' => true, 'border' => true),
			1 => array('data' => 'Kopējais klientu skaits', 'rowspan' => '1', 'center' => true, 'wrap' => true, 'border' => true),
			2 => array('data' => 'T. sk.', 'colspan' => '6', 'center' => true, 'wrap' => true, 'border' => true),
			3 => array('data' => '', 'border' => true),
			4 => array('data' => '', 'border' => true),
			5 => array('data' => '', 'border' => true),
			6 => array('data' => '', 'border' => true),
			7 => array('data' => '', 'border' => true),
			8 => array('data' => '', 'border' => true),
		);

		$xls[1] = array(
			0 => array('data' => '', 'border' => true),
			1 => array('data' => '', 'border' => true),
			2 => array('data' => 'Fiziskas personas', 'center' => true, 'wrap' => true, 'border' => true),
			3 => array('data' => 'Juridiskas personas', 'center' => true, 'wrap' => true, 'border' => true),
			4 => array('data' => 'Jaunie klienti', 'center' => true, 'wrap' => true, 'border' => true),
			5 => array('data' => 'Pastāvīgie klienti', 'center' => true, 'wrap' => true, 'border' => true),
			6 => array('data' => 'Latvija', 'center' => true, 'wrap' => true, 'border' => true),
			7 => array('data' => 'Krievija', 'center' => true, 'wrap' => true, 'border' => true),
			8 => array('data' => 'Citas v.', 'center' => true, 'wrap' => true, 'border' => true)
		);

		$row = 2;
		$total = array();

		foreach($intervals['intervals'] as $day){

			if($intervals['use'] == 'days') $filter = "( DATE(`from`) = '{$day->format('Y-m-d')}' )";
			else $filter = "( MONTH(`from`) = '{$day->format('m')}' AND YEAR(`from`) = '{$day->format('Y')}' )";

			$filter .= $this->getStatus($this->filter);

			$data = $this->ReportKlientsCount($filter, $this->filter['date']['from']);

			$xls[$row][0] = array(
					'data' => (($intervals['use'] == 'days')?$day->format('d.m.Y'):$day->format('t.m.Y')),
					'left' => true,
					'date_format' => true,
					'border' => true,
			);
			if($intervals['use'] == 'days') $xls[$row][0]['date'] = true;
			else $xls[$row][0]['month'] = true;

			$tmp_row = array();
			if(!empty($data)){
				$tmp_row['total'] = $data[0]['total'];
				$tmp_row['fiz'] = $data[0]['fiz'];
				$tmp_row['jur'] = $data[0]['jur'];
				$tmp_row['new'] = $data[0]['new'];
				$tmp_row['pastavigie'] = $data[0]['pastavigie'];
				$tmp_row['lv'] = $data[0]['lv'];
				$tmp_row['ru'] = $data[0]['ru'];
				$tmp_row['other'] = $data[0]['other'];
			}else{
				$tmp_row['total'] = 0;
				$tmp_row['fiz'] = 0;
				$tmp_row['jur'] = 0;
				$tmp_row['new'] = 0;
				$tmp_row['pastavigie'] = 0;
				$tmp_row['lv'] = 0;
				$tmp_row['ru'] = 0;
				$tmp_row['other'] = 0;
			}

			$total['total'] += $tmp_row['total'];
			$total['fiz'] += $tmp_row['fiz'];
			$total['jur'] += $tmp_row['jur'];
			$total['new'] += $tmp_row['new'];
			$total['pastavigie'] += $tmp_row['pastavigie'];
			$total['lv'] += $tmp_row['lv'];
			$total['ru'] += $tmp_row['ru'];
			$total['other'] += $tmp_row['other'];

			$xls[$row][1] = array('data' => $tmp_row['total'], 'right' => true, 'border' => true);
			$xls[$row][2] = array('data' => $tmp_row['fiz'], 'right' => true, 'border' => true);
			$xls[$row][3] = array('data' => $tmp_row['jur'], 'right' => true, 'border' => true);
			$xls[$row][4] = array('data' => $tmp_row['new'], 'right' => true, 'border' => true);
			$xls[$row][5] = array('data' => $tmp_row['pastavigie'], 'right' => true, 'border' => true);
			$xls[$row][6] = array('data' => $tmp_row['lv'], 'right' => true, 'border' => true);
			$xls[$row][7] = array('data' => $tmp_row['ru'], 'right' => true, 'border' => true);
			$xls[$row][8] = array('data' => $tmp_row['other'], 'right' => true, 'border' => true);

			$row++;
		}

		$xls[$row] = array(
				0 => array('data' => 'Kopā', 'left' => true, 'border' => true),
				1 => array('data' => $total['total'], 'right' => true, 'border' => true),
				2 => array('data' => $total['fiz'], 'right' => true, 'border' => true),
				3 => array('data' => $total['jur'], 'right' => true, 'border' => true),
				4 => array('data' => $total['new'], 'right' => true, 'border' => true),
				5 => array('data' => $total['pastavigie'], 'right' => true, 'border' => true),
				6 => array('data' => $total['lv'], 'right' => true, 'border' => true),
				7 => array('data' => $total['ru'], 'right' => true, 'border' => true),
				8 => array('data' => $total['other'], 'right' => true, 'border' => true)
		);

		return $xls;
	}

	public function TotalKlientsOneDay() {
		$intervals = $this->getInterval();

		if($intervals['use'] == 'month') return array();

		$xls[0] = array(
				0 => array('data' => 'Datums', 'rowspan' => '1', 'center' => true, 'wrap' => true),
				1 => array('data' => 'Klientu sk., t.sk.', 'colspan' => '6', 'center' => true, 'wrap' => true),
				2 => array('data' => '', 'border' => true),
				3 => array('data' => '', 'border' => true),
				4 => array('data' => '', 'border' => true),
				5 => array('data' => '', 'border' => true),
				6 => array('data' => '', 'border' => true),
				7 => array('data' => '', 'border' => true),
		);

		$xls[1] = array(
				0 => array('data' => '', 'border' => true),
				1 => array('data' => 'Fiziskas personas', 'center' => true, 'wrap' => true, 'border' => true),
				2 => array('data' => 'Juridiskas personas', 'center' => true, 'wrap' => true, 'border' => true),
				3 => array('data' => 'Jaunie klienti', 'center' => true, 'wrap' => true, 'border' => true),
				4 => array('data' => 'Pastāvīgie klienti', 'center' => true, 'wrap' => true, 'border' => true),
				5 => array('data' => 'Latvija', 'center' => true, 'wrap' => true, 'border' => true),
				6 => array('data' => 'Krievija', 'center' => true, 'wrap' => true, 'border' => true),
				7 => array('data' => 'Citas v.', 'center' => true, 'wrap' => true, 'border' => true)
		);

		$filter = "( DATE(`from`) <= '{$intervals['intervals'][0]->format('Y-m-d')}' )";

		$filter .= $this->getStatus($this->filter);

		$data = $this->ReportKlientsCount($filter, $this->filter['date']['from']);

		$xls[2][0] = array(
			'data' => $intervals['intervals'][0]->format('d.m.Y'),
			'left' => true,
			'date_format' => true,
			'date' => true,
			'border' => true
		);

		$tmp_row = array();
		if(!empty($data)){
			$tmp_row['fiz'] = $data[0]['fiz'];
			$tmp_row['jur'] = $data[0]['jur'];
			$tmp_row['new'] = $data[0]['new'];
			$tmp_row['pastavigie'] = $data[0]['pastavigie'];
			$tmp_row['lv'] = $data[0]['lv'];
			$tmp_row['ru'] = $data[0]['ru'];
			$tmp_row['other'] = $data[0]['other'];
		}else{
			$tmp_row['fiz'] = 0;
			$tmp_row['jur'] = 0;
			$tmp_row['new'] = 0;
			$tmp_row['pastavigie'] = 0;
			$tmp_row['lv'] = 0;
			$tmp_row['ru'] = 0;
			$tmp_row['other'] = 0;
		}

		$xls[2][1] = array('data' => $tmp_row['fiz'], 'right' => true, 'border' => true);
		$xls[2][2] = array('data' => $tmp_row['jur'], 'right' => true, 'border' => true);
		$xls[2][3] = array('data' => $tmp_row['new'], 'right' => true, 'border' => true);
		$xls[2][4] = array('data' => $tmp_row['pastavigie'], 'right' => true, 'border' => true);
		$xls[2][5] = array('data' => $tmp_row['lv'], 'right' => true, 'border' => true);
		$xls[2][6] = array('data' => $tmp_row['ru'], 'right' => true, 'border' => true);
		$xls[2][7] = array('data' => $tmp_row['other'], 'right' => true, 'border' => true);

		return $xls;
	}

	public function KlientsRez() {
		$intervals = $this->getInterval();

		$xls[0] = array(
			0 => array('data' => (($intervals['use'] == 'days')?'Datums':'Mēnesis'), 'center' => true, 'wrap' => true, 'border' => true),
			1 => array('data' => 'Darījumu skaits', 'center' => true, 'wrap' => true, 'border' => true),
			2 => array('data' => 'Nomas dienas', 'center' => true, 'wrap' => true, 'border' => true),
			3 => array('data' => 'Ieņēmumi', 'center' => true, 'wrap' => true, 'border' => true),
		);

		$row = 1;
		$total = array();

		foreach($intervals['intervals'] as $day) {

			$empty = false;

			if($intervals['use'] == 'days') $filter = "( DATE(`from`) = '{$day->format('Y-m-d')}' )";
			else $filter = "( MONTH(`from`) = '{$day->format('m')}' AND YEAR(`from`) = '{$day->format('Y')}' )";

			$filter .= " AND `t1`.`status` IN('confirmed', 'completed') ";

			if(!empty($this->filter['client'])) {
				$client = $this->filter['client'][0];
				$filter .= " AND `c_email` = '{$client['c_email']}' ";
			}else{

				if($this->filter['user_type'] == 'new_user' || $this->filter['user_type'] == 'old_user'){
					$users_type = $this->getOldNewUser($filter, $this->filter['date']['from']);
					if($this->filter['user_type'] == 'new_user'){
						if(!empty($users_type['new'])){
							$filter .= " AND (`c_email` IN ('".implode("', '", $users_type['new'])."')) ";
						}else{
							$empty = true;
						}
					}
					if($this->filter['user_type'] == 'old_user') {
						if(!empty($users_type['pastavigie'])) {
							$filter .= " AND (`c_email` IN ('".implode("', '", $users_type['pastavigie'])."')) ";
						}else{
							$empty = true;
						}
					}
				}

			}

			if($empty){
				$data = array();
			}else {
				$data = $this->ReportAll($filter);
			}

			$xls[$row][0] = array(
					'data' => (($intervals['use'] == 'days')?$day->format('d.m.Y'):$day->format('t.m.Y')),
					'left' => true,
					'date_format',
					'border' => true
 			);
			if($intervals['use'] == 'days') $xls[$row][0]['date'] = true;
			else $xls[$row][0]['month'] = true;

			$tmp_row = array();
			if(!empty($data)) {
				foreach($data as $value) {
					$tmp_row['total'] += 1;
					$tmp_row['days'] += $value['rental_days'];
					$tmp_row['total_money'] += $value['total_price'];
				}
			}else{
				$tmp_row['total'] = 0;
				$tmp_row['days'] = 0;
				$tmp_row['total_money'] = 0;
			}

			$total['total'] += $tmp_row['total'];
			$total['days'] += $tmp_row['days'];
			$total['total_money'] += $tmp_row['total_money'];

			$xls[$row][1] = array('data' => $tmp_row['total'], 'right' => true, 'border' => true);
			$xls[$row][2] = array('data' => $tmp_row['days'], 'right' => true, 'border' => true);
			$xls[$row][3] = array('data' => $this->numFormat($tmp_row['total_money']), 'right' => true, 'border' => true);

			$row++;

		}

		$xls[$row] = array(
				0 => array('data' => 'Kopā', 'left' => true, 'border' => true),
				1 => array('data' => $total['total'], 'right' => true, 'border' => true),
				2 => array('data' => $total['days'], 'right' => true, 'border' => true),
				3 => array('data' => $this->numFormat($total['total_money']), 'right' => true, 'border' => true)
		);

		return $xls;
	}

	public function TotalCanceled() {
		$intervals = $this->getInterval();

		$xls[0] = array(
				0 => array('data' => (($intervals['use'] == 'days')?'Datums':'Mēnesis'), 'center' => true, 'wrap' => true, 'border' => true),
				1 => array('data' => 'Neapstiprināto darījumu skaits', 'center' => true, 'wrap' => true, 'border' => true)
		);

		$row = 1;
		$total = array();

		foreach($intervals['intervals'] as $day) {

			if($intervals['use'] == 'days') $filter = "( DATE(`from`) = '{$day->format('Y-m-d')}' )";
			else $filter = "( MONTH(`from`) = '{$day->format('m')}' AND YEAR(`from`) = '{$day->format('Y')}' )";

			$filter .= " AND `status` IN('cancelled','pending')";

			$data = $this->ReportAll($filter);

			$xls[$row][0] = array(
					'data' => (($intervals['use'] == 'days')?$day->format('d.m.Y'):$day->format('t.m.Y')),
					'left' => true,
					'date_format' => true,
					'border' => true
			);
			if($intervals['use'] == 'days') $xls[$row][0]['date'] = true;
			else $xls[$row][0]['month'] = true;

			$tmp_row = array();
			if(!empty($data)){
				foreach($data as $value) {
					$tmp_row['total'] += 1;
				}
			}else{
				$tmp_row['total'] = 0;
			}

			$total['total'] += $tmp_row['total'];

			$xls[$row][1] = array('data' => $tmp_row['total'], 'right' => true, 'border' => true);

			$row++;

		}

		$xls[$row] = array(
				0 => array('data' => 'Kopā', 'left' => true, 'border' => true),
				1 => array('data' => $total['total'], 'right' => true, 'border' => true)
		);

		return $xls;
	}
}