<?php

set_time_limit(0);

$start = 1;

$base_url = 'http://mlb.mlb.com/ws/search/MediaSearchService?type=json&ns=1&start=%d&hitsPerPage=50&text=highlight';
$detail_url = 'http://mlb.mlb.com/gen/multimedia/detail/%d/%d/%d/%d.xml';

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_REFERER, 'http://mlb.mlb.com');
curl_setopt($ch, CURLOPT_URL, sprintf($base_url, $start));
$s = curl_exec($ch);

$recs = json_decode($s, true);

$total = $recs['total'];

$pages = 1;

//$pages = ceil($recs['total']/50);

$lines = array();

$lines[] = 'title|thumb|vid';

echo $pages . " total pages<br />";
flush();

foreach (range(1, $pages) as $page) {
	$start = (($page-1)*50);
	
	echo "spidering page " . $page . "<br />";
	flush();
		
	curl_setopt($ch, CURLOPT_URL, sprintf($base_url, $start));
	$s = curl_exec($ch);
	
	$recs = json_decode($s, true);
	
	foreach ($recs['mediaContent'] as $vid) {
		$cid = $vid['contentId'];
		$fnum = substr($cid, -3, 1);
		$snum = substr($cid, -2, 1);
		$lnum = substr($cid, -1);
		
		$detail_url = sprintf($detail_url, $fnum, $snum, $lnum, $cid);
		
		curl_setopt($ch, CURLOPT_URL, $detail_url);
		$s = curl_exec($ch);
		$xml = simplexml_load_string($s);
		
		$line = $vid['title'] . '|' . $vid['thumbnails'][0]['src'] . '|' . $xml->url[0];
		$lines[md5($line)] = $line;
	}
}

curl_close($ch);

$fp = fopen('./mlb-list.txt', 'w');
fwrite($fp, implode("\n", $lines));
fclose($fp);

echo "done, " . (count($lines)-1) . " videos found";
?>
