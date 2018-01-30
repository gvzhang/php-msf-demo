<?php
$obj1 = new \stdclass();
$obj1->start = "肃宁";
$obj1->end = "长沙";

$obj2 = new \stdclass();
$obj2->start = "沧州";
$obj2->end = "任丘";

$obj3 = new \stdclass();
$obj3->start = "任丘";
$obj3->end = "肃宁";

$obj4 = new \stdclass();
$obj4->start = "长沙";
$obj4->end = "武汉";

$obj5 = new \stdclass();
$obj5->start = "武汉";
$obj5->end = "上海";

$obj6 = new \stdclass();
$obj6->start = "北京";
$obj6->end = "沧州";

$arr = [$obj1, $obj2, $obj3, $obj4, $obj5, $obj6];
$count = count($arr);

$res = [$obj1];
unset($arr[0]);
while (count($res) < $count) {
    $objs = $res[0];
    $obje = $res[count($res)-1];
    $isMatch = false;
    foreach ($arr as $key=>$obj2) {
        if ($obj2->start == $obje->end) {
            array_push($res, $obj2);
            unset($arr[$key]);
            $isMatch = true;
        }
        if ($obj2->end == $objs->start) {
            array_unshift($res, $obj2);
            unset($arr[$key]);
            $isMatch = true;
        }
    }
    if (!$isMatch) {
        break;
    }
}

var_dump($res);
