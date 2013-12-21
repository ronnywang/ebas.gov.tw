<?php

class Crawler
{
    public function getListFromURL($url)
    {
        // http://ebas1.ebas.gov.tw/pxweb/dialog/varval.asp?ma=NA0101A1A&ti=Wahaha&path=%2E%2E%2FPXfile%2FNationalIncome%2F&xu=&yp=&lang=9
        $ret = parse_url($url);
        parse_str($ret['query'], $params);

        // ma, ti, path, xu, yp, lang
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $ret = curl_exec($curl);
        $doc = new DOMDocument;
        @$doc->loadHTML($ret);
        $lists = array();
        foreach ($doc->getElementsByTagName('select') as $select_dom) {
            $list = array();
            foreach ($select_dom->getElementsByTagName('option') as $option_dom) {
                $list[$option_dom->getAttribute('value')] = trim($option_dom->nodeValue);
            }
            $lists[$select_dom->getAttribute('name')] = $list;
        }

        return $lists;
    }

    public function getData($url, $years, $topic_id, $type)
    {
        $ret = parse_url($url);
        parse_str($ret['query'], $url_params);

        $params = array();
        // http://ebas1.ebas.gov.tw/pxweb/Dialog/varval.asp?ma=NA0101A1A&ti=%B0%EA%A5%C1%A9%D2%B1o%B2%CE%ADp%B1`%A5%CE%B8%EA%AE%C6-%A6~&path=../PXfile/NationalIncome/&lang=9&strList=L
        // http://ebas1.ebas.gov.tw/pxweb/Dialog/varval.asp?ma=NA0101A1A&ti=Wahaha&path=../PXfile/NationalIncome/&lang=9&strList=L
        $params[] = 'strList=L';
        $params[] = 'var1=' . urlencode(iconv('utf-8', 'big5', '期間'));
        $params[] = 'var2=' . urlencode(iconv('utf-8', 'big5', '指標'));
        $params[] = 'var3=' . urlencode(iconv('utf-8', 'big5', '種類'));
        $params[] = 'Valdavarden1=62'; // 已選幾筆
        $params[] = 'Valdavarden2=1'; // 已選幾筆
        $params[] = 'Valdavarden3=1'; // 已選幾筆
        // 第一欄，年分
        foreach ($years as $id => $year) {
            $params[] = 'values1=' . intval($id);
        }
        // 第二欄
        $params[] = 'values2=' . intval($topic_id);
        // 第三欄
        $params[] = 'values3=' . intval($type);
        $params[] = 'context1=';
        $params[] = 'begin1=';
        $params[] = 'context2=';
        $params[] = 'begin2=';
        $params[] = 'context3=';
        $params[] = 'begin3=';
        $params[] = 'matrix=' . urlencode($url_params['ma']);
        $params[] = 'root=' . urlencode($url_params['path']);
        $params[] = 'classdir=' . urlencode($url_params['path']);
        $params[] = 'noofvar=3';
        $params[] = 'elim=NNN';
        $params[] = 'numberstub=1';
        $params[] = 'lang=9';
        $params[] = 'infofile=';
        $params[] = 'mapname=';
        $params[] = 'multilang=';
        $params[] = 'mainlang=';
        $params[] = 'timevalvar=';
        $params[] = 'hasAggregno=0';
        $params[] = 'stubceller=62';
        $params[] = 'headceller=1';
        $params[] = 'pxkonv=asp1';
        $url = 'http://ebas1.ebas.gov.tw/pxweb/Dialog/Saveshow.asp';
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POSTFIELDS, implode('&', $params));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $ret = curl_exec($curl);

        $doc = new DOMDocument;
        @$doc->loadHTML($ret);
        $choosed_table_dom = null;
        foreach ($doc->getElementsByTagName('table') as $table_dom) {
            if ($table_dom->getAttribute('class') == 'pxtable') {
                $choosed_table_dom = $table_dom;
                break;
            }
        }

        $ret = array();
        if ($choosed_table_dom) {
            foreach ($choosed_table_dom->getElementsByTagName('tr') as $tr_dom) {
                $td_doms = $tr_dom->getElementsByTagName('td');
                if (!$td_doms->item(0) or $td_doms->item(0)->getAttribute('class') != 'stub1') {
                    continue;
                }
                $key = trim($tr_dom->getElementsByTagName('td')->item(0)->nodeValue);
                $value = trim(str_replace(',', '', $tr_dom->getElementsByTagName('td')->item(1)->nodeValue));
                $ret[$key] = $value;
            }
        }
        return $ret;
    }

    public function init()
    {
        //$this->getData();
        //$url = 'http://ebas1.ebas.gov.tw/pxweb/dialog/varval.asp?ma=NA0101A1A&ti=Wahaha&path=%2E%2E%2FPXfile%2FNationalIncome%2F&xu=&yp=&lang=9';
        $url = $_SERVER['argv'][1];

        $list =  $this->getListFromURL($url);
        $years = $list['values1'];
        $topic = $list['values2'];
        $types = $list['values3'];

        $ret = parse_url($url);
        parse_str($ret['query'], $params);
        $ti = iconv('big5', 'utf-8', $params['ti']);
        mkdir('outputs/' . $ti . '/');

        foreach ($topic as $topic_id => $topic_name) {
            $topic_name = str_replace('/', '／', $topic_name);
            $output = fopen('outputs/' . $ti . '/' . $topic_name . '.csv', 'w');
            fputcsv($output, array('年分', '原始值', '年增率'));
            $ret = array();
            foreach ($types as $type_id => $type) {
                $ret[$type_id] = $this->getData($url, $years, $topic_id, $type_id);
            }
            foreach ($years as $year_id => $year) {
                fputcsv($output, array(
                    $year, $ret[1][$year], $ret[2][$year],
                ));
            }
            fclose($output);
        }
    }
}

$c = new Crawler;
$c->init();
