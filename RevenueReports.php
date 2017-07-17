<?php

Class RevenueReports extends ReportsModel
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

        if($days >= 32) {
            $intervals = $this->monthInterval($this->filter['date']['from'], $this->filter['date']['to']);
            $use = 'month';
        }

        return array(
            'intervals' => $intervals,
            'use' => $use
        );
    }

    public function RevPeriod() {
        $intervals = $this->getInterval();

        $xls[0] = array(
            0 => array('data' => (($intervals['use'] == 'days')?'Datums':'Mēnesis'), 'rowspan' => 1, 'center' => true, 'border' => true, 'wrap' => true),
            1 => array('data' => 'Apgrozījums', 'rowspan' => 1, 'center' => true, 'border' => true, 'wrap' => true),
            2 => array('data' => 'T.sk.', 'colspan' => 1, 'center' => true, 'border' => true, 'wrap' => true),
            3 => array('data' => '', 'border' => true)
        );

        $xls[1] = array(
            0 => array('data' => '', 'border' => true),
            1 => array('data' => '', 'border' => true),
            2 => array('data' => 'auto noma', 'center' => true, 'border' => true, 'wrap' => true),
            3 => array('data' => 'papildus pakalpojumi', 'center' => true, 'border' => true, 'wrap' => true)
        );

        $row = 2;
        $total = array();
        foreach($intervals['intervals'] as $day) {
            if($intervals['use'] == 'days') $filter = "( DATE(`from`) = '{$day->format('Y-m-d')}' )";
            else $filter = "( MONTH(`from`) = '{$day->format('m')}' AND YEAR(`from`) = '{$day->format('Y')}' )";

            $filter .= " AND `t1`.`status` IN('confirmed', 'completed')";

            if(!empty($this->filter['car_reg'])){
                $filter .= " AND LOWER(`t2`.`registration_number`) = '{$this->filter['car_reg']}' ";
            }else if($this->filter['car_class'] || $this->filter['car_make'] || $this->filter['car_model']){
                if($this->filter['car_class']) $filter .= " AND `t3`.`classId` = {$this->filter['car_class']} ";
                if($this->filter['car_model']) $filter .= " AND LOWER(`t2`.`model`) = '{$this->filter['car_model']}' ";
                if($this->filter['car_make']) $filter .= " AND LOWER(`t2`.`make`) = '{$this->filter['car_make']}' ";
            }

            $data = $this->ReportCarRez($filter);

            $xls[$row][0] = array(
                'data' => (($intervals['use'] == 'days')?$day->format('d.m.Y'):$day->format('t.m.Y')),
                'left' => true,
                'date_format' => true,
                'border' => true,
            );
            if($intervals['use'] == 'days') $xls[$row][0]['date'] = true;
            else $xls[$row][0]['month'] = true;

            $tmp = array();
            if(!empty($data)){
                foreach($data as $value){
                    $tmp['rev'] += $value['total_price'];
                    $tmp['noma'] += $value['car_rental_fee'];
                    $tmp['extra'] += $value['extra_price'];
                }
            }else{
                $tmp['rev'] = 0;
                $tmp['noma'] = 0;
                $tmp['extra'] = 0;
            }

            $total['rev'] += $tmp['rev'];
            $total['noma'] += $tmp['noma'];
            $total['extra'] += $tmp['extra'];

            $xls[$row][1] = array('data' => $this->numFormat($tmp['rev']), 'right' => true, 'border' => true);
            $xls[$row][2] = array('data' => $this->numFormat($tmp['noma']), 'right' => true, 'border' => true);
            $xls[$row][3] = array('data' => $this->numFormat($tmp['extra']), 'right' => true, 'border' => true);

            $row++;
        }

        $xls[$row] = array(
            0 => array('data' => 'Kopā', 'left' => true, 'border' => true),
            1 => array('data' => $this->numFormat($total['rev']), 'right' => true, 'border' => true),
            2 => array('data' => $this->numFormat($total['noma']), 'right' => true, 'border' => true),
            3 => array('data' => $this->numFormat($total['extra']), 'right' => true, 'border' => true)
        );

        return $xls;
    }

    public function RevAveragePeriod() {
        $intervals = $this->getInterval();

        $xls[0] = array(
            0 => array('data' => (($intervals['use'] == 'days')?'Datums':'Mēnesis'), 'rowspan' => 1, 'center' => true, 'wrap' => true),
            1 => array('data' => 'Vidējie ieņēmumi', 'rowspan' => 1, 'center' => true, 'wrap' => true),
            2 => array('data' => 'T.sk.', 'colspan' => 1, 'center' => true, 'wrap' => true),
            3 => array('data' => '', 'border' => true)
        );

        $xls[1] = array(
            0 => array('data' => '', 'border' => true),
            1 => array('data' => '', 'border' => true),
            2 => array('data' => 'auto noma', 'center' => true, 'border' => true, 'wrap' => true),
            3 => array('data' => 'papildus pakalpojumi', 'center' => true, 'border' => true, 'wrap' => true)
        );

        $row = 2;
        $total = array();
        $used_days = 0;
        foreach($intervals['intervals'] as $day) {
            if($intervals['use'] == 'days') $filter = "( DATE(`from`) = '{$day->format('Y-m-d')}' )";
            else $filter = "( MONTH(`from`) = '{$day->format('m')}' AND YEAR(`from`) = '{$day->format('Y')}' )";

            $filter .= " AND `t1`.`status` IN('confirmed', 'completed')";

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
                'data' => (($intervals['use'] == 'days')?$day->format('d.m.Y'):$day->format('t.m.Y')),
                'left' => true,
                'date_format' => true,
                'border' => true
            );
            if($intervals['use'] == 'days') $xls[$row][0]['date'] = true;
            else $xls[$row][0]['month'] = true;

            $tmp = array();
            if(!empty($data)){
                $used_days += 1;
                foreach($data as $value){
                    $tmp['rev'] += $value['total_price'];
                    $tmp['noma'] += $value['car_rental_fee'];
                    $tmp['extra'] += $value['extra_price'];
                }
            }else{
                $tmp['rev'] = 0;
                $tmp['noma'] = 0;
                $tmp['extra'] = 0;
            }

            $tmp['rev'] = $tmp['rev'] / $cars;
            $tmp['noma'] = $tmp['noma'] / $cars;
            $tmp['extra'] = $tmp['extra'] / $cars;

            $total['rev'] += $tmp['rev'];
            $total['noma'] += $tmp['noma'];
            $total['extra'] += $tmp['extra'];

            $xls[$row][1] = array('data' => $this->numFormat($tmp['rev']), 'right' => true, 'border' => true);
            $xls[$row][2] = array('data' => $this->numFormat($tmp['noma']), 'right' => true, 'border' => true);
            $xls[$row][3] = array('data' => $this->numFormat($tmp['extra']), 'right' => true, 'border' => true);

            $row++;
        }

        $xls[$row] = array(
            0 => array('data' => 'Kopā', 'left' => true, 'border' => true),
            1 => array('data' => $this->numFormat($total['rev'] / $used_days), 'right' => true, 'border' => true),
            2 => array('data' => $this->numFormat($total['noma'] / $used_days), 'right' => true, 'border' => true),
            3 => array('data' => $this->numFormat($total['extra'] / $used_days), 'right' => true, 'border' => true)
        );

        return $xls;
    }

    public function RevAverageOneDayPeriod() {
        $intervals = $this->getInterval();

        $xls[0] = array(
            0 => array('data' => (($intervals['use'] == 'days')?'Datums':'Mēnesis'), 'center' => true, 'border' => true, 'wrap' => true),
            1 => array('data' => 'Apgrozījums', 'center' => true, 'border' => true, 'wrap' => true),
            2 => array('data' => 'Nomas dienas', 'center' => true, 'border' => true, 'wrap' => true),
            3 => array('data' => 'Vidējie ieņēmumi par 1 nomas dienu', 'center' => true, 'border' => true, 'wrap' => true)
        );

        $row = 1;
        $total = array();
        foreach($intervals['intervals'] as $day) {
            if($intervals['use'] == 'days') $filter = "( DATE(`from`) = '{$day->format('Y-m-d')}' )";
            else $filter = "( MONTH(`from`) = '{$day->format('m')}' AND YEAR(`from`) = '{$day->format('Y')}' )";

            $filter .= " AND `t1`.`status` IN('confirmed', 'completed')";

            if(!empty($this->filter['car_reg'])){
                $filter .= " AND LOWER(`t2`.`registration_number`) = '{$this->filter['car_reg']}' ";
            }else if($this->filter['car_class'] || $this->filter['car_make'] || $this->filter['car_model']){
                if($this->filter['car_class']) $filter .= " AND `t3`.`classId` = {$this->filter['car_class']} ";
                if($this->filter['car_model']) $filter .= " AND LOWER(`t2`.`model`) = '{$this->filter['car_model']}' ";
                if($this->filter['car_make']) $filter .= " AND LOWER(`t2`.`make`) = '{$this->filter['car_make']}' ";
            }

            $data = $this->ReportCarRez($filter);

            $xls[$row][0] = array(
                'data' => (($intervals['use'] == 'days')?$day->format('d.m.Y'):$day->format('t.m.Y')),
                'left' => true,
                'date_format' => true,
                'border' => true
            );
            if($intervals['use'] == 'days') $xls[$row][0]['date'] = true;
            else $xls[$row][0]['month'] = true;

            $tmp = array();
            if(!empty($data)){
                foreach($data as $value){
                    $tmp['rev'] += $value['total_price'];
                    $tmp['noma'] += $value['rental_days'];
                }
            }else{
                $tmp['rev'] = 0;
                $tmp['noma'] = 0;
            }

            $tmp['aver'] = $tmp['rev'] / $tmp['noma'];

            $total['rev'] += $tmp['rev'];
            $total['noma'] += $tmp['noma'];

            $xls[$row][1] = array('data' => $this->numFormat($tmp['rev']), 'right' => true, 'border' => true);
            $xls[$row][2] = array('data' => $tmp['noma'], 'right' => true, 'border' => true);
            $xls[$row][3] = array('data' => $this->numFormat($tmp['aver']), 'right' => true, 'border' => true);

            $row++;
        }

        $xls[$row] = array(
            0 => array('data' => 'Kopā', 'left' => true, 'border' => true),
            1 => array('data' => $this->numFormat($total['rev']), 'right' => true, 'border' => true),
            2 => array('data' => $total['noma'], 'right' => true, 'border' => true),
            3 => array('data' => $this->numFormat($total['rev'] / $total['noma']), 'right' => true, 'border' => true)
        );

        return $xls;
    }
}