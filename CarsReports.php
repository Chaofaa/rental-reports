<?php

Class CarsReports extends ReportsModel
{

    public $filter = array();

    public function __construct($filter = array())
    {
        $this->filter = $filter;
    }

    public function getInterval()
    {
        $intervals = $this->daysInterval($this->filter['date']['from'], $this->filter['date']['to']);
        $days = count($intervals);
        $use = 'days';

        if ($days >= 32) {
            $intervals = $this->monthInterval($this->filter['date']['from'], $this->filter['date']['to']);
            $use = 'month';
        }

        return array(
            'intervals' => $intervals,
            'use' => $use
        );
    }

    public function CarsByDay()
    {
        $intervals = $this->getInterval();

        if ($intervals['use'] == 'month') return array();

        $xls[0] = array(
            0 => array('data' => 'Marka, modelis', 'center' => true, 'wrap' => true, 'border' => true),
            1 => array('data' => 'Skaits', 'center' => true, 'wrap' => true, 'border' => true)
        );

        $cars = $this->getAllCars();
        $row = 1;
        $total = array();
        foreach ($cars as $car) {
            $filter = "( `bought` <= '{$intervals['intervals'][0]->format('Y-m-d')}' AND  (`sold` >= '{$intervals['intervals'][0]->format('Y-m-d')}' OR `sold` = '0000-00-00') AND `bought` != '0000-00-00' )";
            $sum = $this->ReportCarAvailable($filter, $car, $intervals['intervals'][0]);

            $xls[$row] = array(
                0 => array('data' => $car['make'] . ' ' . $car['model'], 'left' => true, 'border' => true),
                1 => array('data' => $sum, 'right' => true, 'border' => true)
            );

            $total['total'] += $sum;

            $row++;
        }

        $xls[$row] = array(
            0 => array('data' => 'Pavisam', 'left' => true, 'border' => true),
            1 => array('data' => $total['total'], 'right' => true, 'border' => true),
        );

        return $xls;
    }

    public function CarsByPeriod()
    {
        $intervals = $this->monthInterval($this->filter['date']['from'], $this->filter['date']['to']);

        $xls[0] = array(
            0 => array('data' => 'Marka, modelis', 'rowspan' => 2, 'center' => true, 'wrap' => true, 'border' => true),
        );

        $cars = $this->getAllCars();
        $row = 3;

        $total = array();
        foreach ($cars as $car) {

            $xls[$row][0] = array('data' => $car['make'] . ' ' . $car['model'], 'left' => true, 'border' => true);

            $col = 1;
            foreach ($intervals as $month_use) {

                $xls[0][$col] = array('data' => $month_use->format('t.m.Y'), 'colspan' => 2, 'center' => true, 'date_format' => true, 'month' => true, 'border' => true);
                $xls[0][$col+1] = array('data' => '', 'border' => true);
                $xls[0][$col+2] = array('data' => '', 'border' => true);

                $xls[1][$col] = array('data' => 'Skaits', 'rowspan' => 1, 'center' => true, 'border' => true);
                $xls[1][$col + 1] = array('data' => 't.sk.', 'colspan' => 1, 'center' => true, 'border' => true);
                $xls[1][$col + 2] = array('data' => '', 'border' => true);

                $xls[2][$col] = array('data' => '', 'border' => true);
                $xls[2][$col + 1] = array('data' => 'jauni', 'center' => true, 'border' => true);
                $xls[2][$col + 2] = array('data' => 'izslēgti', 'center' => true, 'border' => true);

                $total_month = $this->ReportCarAvailableMonth($car, $month_use);
                $new_month = $this->ReportNewCarMonth($car, $month_use);
                $remove_month = $this->ReportRemovedCarMonth($car, $month_use);

                $xls[$row][$col] = array('data' => $total_month, 'right' => true, 'border' => true); // Skaits
                $xls[$row][$col + 1] = array('data' => $new_month, 'right' => true, 'border' => true); // Jauni
                $xls[$row][$col + 2] = array('data' => $remove_month, 'right' => true, 'border' => true); // Izlēgti

                $total[$col] += $total_month;
                $total[$col + 1] += $new_month;
                $total[$col + 2] += $remove_month;

                $col = $col + 3;
            }

            $row++;
        }

        foreach ($total as $k => $value) {
            $total[$k] = array('data' => $value, 'right' => true, 'border' => true);
        }
        $total[0] = array('data' => 'Pavisam', 'left' => true, 'border' => true);

        $xls[$row] = $total;

        return $xls;
    }

    public function CarsRentSum()
    {
        $intervals = $this->getInterval();

        $xls[0] = array(
            0 => array('data' => (($intervals['use'] == 'days') ? 'Datums' : 'Mēnesis'), 'center' => true, 'wrap' => true, 'border' => true),
            1 => array('data' => 'Darījumu skaits', 'center' => true, 'wrap' => true, 'border' => true),
            2 => array('data' => 'Nomas dienas', 'center' => true, 'wrap' => true, 'border' => true),
            3 => array('data' => 'Ieņēmumi', 'center' => true, 'wrap' => true, 'border' => true)
        );

        $row = 1;
        $total = array();
        foreach ($intervals['intervals'] as $day) {

            if ($intervals['use'] == 'days') $filter = "( DATE(`from`) = '{$day->format('Y-m-d')}' )";
            else $filter = "( MONTH(`from`) = '{$day->format('m')}' AND YEAR(`from`) = '{$day->format('Y')}' )";

            $filter .= " AND `t1`.`status` IN('confirmed', 'completed') ";

            if (!empty($this->filter['car_reg'])) {
                $filter .= " AND LOWER(`t2`.`registration_number`) = '{$this->filter['car_reg']}' ";
            } else if ($this->filter['car_class'] || $this->filter['car_make'] || $this->filter['car_model']) {
                if ($this->filter['car_class']) $filter .= " AND `t3`.`classId` = {$this->filter['car_class']} ";
                if ($this->filter['car_model']) $filter .= " AND LOWER(`t2`.`model`) = '{$this->filter['car_model']}' ";
                if ($this->filter['car_make']) $filter .= " AND LOWER(`t2`.`make`) = '{$this->filter['car_make']}' ";
            }

            $data = $this->ReportCarRez($filter);

            $xls[$row][0] = array(
                'data' => (($intervals['use'] == 'days') ? $day->format('d.m.Y') : $day->format('t.m.Y')),
                'left' => true,
                'date_format' => true,
                'border' => true
            );
            if($intervals['use'] == 'days') $xls[$row][0]['date'] = true;
            else $xls[$row][0]['month'] = true;

            $tmp = array();
            if (!empty($data)) {
                foreach ($data as $value) {
                    $tmp['total'] += 1;
                    $tmp['nomas'] += $value['rental_days'];
                    $tmp['money'] += $value['total_price'];
                }
            } else {
                $tmp['total'] = 0;
                $tmp['nomas'] = 0;
                $tmp['money'] = 0;
            }

            $total['total'] += $tmp['total'];
            $total['nomas'] += $tmp['nomas'];
            $total['money'] += $tmp['money'];

            $xls[$row][1] = array('data' => $tmp['total'], 'right' => true, 'border' => true);
            $xls[$row][2] = array('data' => $tmp['nomas'], 'right' => true, 'border' => true);
            $xls[$row][3] = array('data' => $this->numFormat($tmp['money']), 'right' => true, 'border' => true);

            $row++;
        }

        $xls[$row] = array(
            0 => array('data' => 'Kopā', 'left' => true, 'border' => true),
            1 => array('data' => $total['total'], 'right' => true, 'border' => true),
            2 => array('data' => $total['nomas'], 'right' => true, 'border' => true),
            3 => array('data' => $this->numFormat($total['money']), 'right' => true, 'border' => true)
        );

        return $xls;
    }

    public function CarsLoad() {
        $intervals = $this->getInterval();

        $xls[0] = array(
            0 => array('data' => (($intervals['use'] == 'days') ? 'Datums' : 'Mēnesis'), 'center' => true, 'wrap' => true, 'border' => true),
            1 => array('data' => 'Auto skaits', 'center' => true, 'wrap' => true, 'border' => true),
            2 => array('data' => 'Pieejamais dienu skaits', 'center' => true, 'wrap' => true, 'border' => true),
            3 => array('data' => 'Faktiski izmantoto dienu skaits', 'center' => true, 'wrap' => true, 'border' => true),
            4 => array('data' => 'Noslodze, %', 'center' => true, 'wrap' => true, 'border' => true),
        );

        $row = 1;
        $total = array();

        $total_days = 0;

        foreach ($intervals['intervals'] as $day) {
            $days_count = ($intervals['use'] == 'days')?1:$day->format('t');
            $total_days++;

            if ($intervals['use'] == 'days') {
                $filter = " ( DATE(`from`) <= '{$day->format('Y-m-d')}' AND DATE_ADD(`from`, INTERVAL rental_days DAY) > '{$day->format('Y-m-d')}' ) ";
            } else {
                $filter = " (('{$day->format('Y-m')}-01' <= DATE(`from`) AND '{$day->format('Y-m-t')}' >= DATE(DATE_ADD(`from`, INTERVAL rental_days DAY)) )
                OR ('{$day->format('Y-m')}-01' > DATE(`from`) AND ('{$day->format('Y-m-t')}' >= DATE(DATE_ADD(`from`, INTERVAL rental_days DAY))
                AND '{$day->format('Y-m')}-01' <= DATE(DATE_ADD(`from`, INTERVAL rental_days DAY))))
                OR ('{$day->format('Y-m')}-01' > DATE(`from`) AND '{$day->format('Y-m-t')}' < DATE(DATE_ADD(`from`, INTERVAL rental_days DAY)) ))";
            }

            $cars = $this->ReportCarsCount('', $day, $intervals['use']);
            if(!empty($this->filter['car_reg'])){
                $filter .= " AND LOWER(`t2`.`registration_number`) = '{$this->filter['car_reg']}' ";
                $cars = 1;
            }else if($this->filter['car_class'] || $this->filter['car_make'] || $this->filter['car_model']){
                $cars_filter = '';
                if($this->filter['car_class']) $cars_filter .= " AND `t3`.`classId` = {$this->filter['car_class']} ";
                if($this->filter['car_model']) $cars_filter .= " AND LOWER(`t2`.`model`) = '{$this->filter['car_model']}' ";
                if($this->filter['car_make']) $cars_filter .= " AND LOWER(`t2`.`make`) = '{$this->filter['car_make']}' ";

                $filter .= $cars_filter;

                $cars_filter = str_replace('t2', 't1', $cars_filter);
                $cars = $this->ReportCarsCount($cars_filter, $day, $intervals['use']);
            }

            $data = $this->ReportCarRez($filter);

            $xls[$row][0] = array(
                'data' => (($intervals['use'] == 'days') ? $day->format('d.m.Y') : $day->format('t.m.Y')),
                'left' => true,
                'date_format' => true,
                'border' => true
            );
            if($intervals['use'] == 'days') $xls[$row][0]['date'] = true;
            else $xls[$row][0]['month'] = true;

            $tmp = array();
            $tmp['total_cars'] = $cars;
            $tmp['available_days'] = $cars * $days_count;

            if(!empty($data)) {
                if ($intervals['use'] == 'month') {

                    foreach ($data as $value) {

                        $from = new DateTime($value['fromWork']);
                        $to = new DateTime($value['toWork']);
                        $now = new DateTime($day->format('Y-m') . '-01');

                        $from_month = date('Y-m', strtotime($value['fromWork']));
                        $to_month = date('Y-m', strtotime($value['toWork']));
                        $now_month = date('Y-m', strtotime($day->format('Y-m-d')));


                        if ($from_month < $now_month && $to_month > $now_month) {
                            $used_days = $day->format('t');
                        } else if ($from_month < $now_month && $to_month == $now_month) {
                            $used_days = $now->diff($to)->format('%a');
                            if ($used_days == 0) $used_days = 1;
                        } else if ($from_month == $now_month && $to_month > $now_month) {
                            $used_days = $now->diff($from)->format('%a');
                            if ($used_days == 0) $used_days = 1;
                        } else { // $from_month == $now_month && $to_month == $now_month
                            $used_days = $from->diff($to)->format('%a');
                        }

                        $tmp['faktiski'] += $used_days;
                    }

                    $tmp['noslodze'] = ($tmp['faktiski'] / $tmp['available_days']) * 100;

                } else {

                    $tmp['faktiski'] = count($data);
                    $tmp['noslodze'] = ($tmp['faktiski'] / $tmp['available_days']) * 100;

                }
            }else{
                $tmp['faktiski'] = 0;
                $tmp['noslodze'] = 0;
            }



            $total['total_cars'] += $tmp['total_cars'];
            $total['available_days'] += $tmp['available_days'];
            $total['faktiski'] += $tmp['faktiski'];

            $xls[$row][1] = array('data' => $tmp['total_cars'], 'right' => true, 'border' => true);
            $xls[$row][2] = array('data' => $tmp['available_days'], 'right' => true, 'border' => true);
            $xls[$row][3] = array('data' => $tmp['faktiski'], 'right' => true, 'border' => true);
            $xls[$row][4] = array('data' => $this->numFormat($tmp['noslodze']).'%', 'right' => true, 'border' => true);

            $row++;
        }

        $xls[$row] = array(
            0 => array('data' => 'Kopā', 'left' => true, 'border' => true),
            1 => array('data' => $total['total_cars'] / $total_days, 'right' => true, 'border' => true),
            2 => array('data' => $total['available_days'], 'right' => true, 'border' => true),
            3 => array('data' => $total['faktiski'], 'right' => true, 'border' => true),
            4 => array('data' => $this->numFormat(($total['faktiski'] / $total['available_days']) * 100).'%', 'right' => true, 'border' => true)
        );

        return $xls;
    }

}