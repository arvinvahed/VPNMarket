<?php
$url = "http:// :80";
$parsed = parse_url($url);
var_dump($parsed);

$url2 = "http://example.com:65536"; // Invalid port?
$parsed2 = parse_url($url2);
var_dump($parsed2);

$url3 = "http://example_com:80"; // Underscore in domain?
$parsed3 = parse_url($url3);
var_dump($parsed3);
