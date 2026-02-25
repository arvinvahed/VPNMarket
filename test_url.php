<?php
$url = "http://1.2.3.4:8080:2053/";
$parsed = parse_url($url);
var_dump($parsed);

$url2 = "http://:80/";
$parsed2 = parse_url($url2);
var_dump($parsed2);

$url3 = "http://192.168.1.1:8080";
$parsed3 = parse_url($url3);
var_dump($parsed3);
