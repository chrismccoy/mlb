<?php

function enum_array($arr) {
    foreach ($arr as $key => $val) {
        if (is_array($val)) {
            echo $key . ' = ' . implode(',', $val) . '<br />';    
        } else {
            echo $key . ' = ' . $val . '<br />';
        }
    }
}

function mlb_get_page($url) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_REFERER, 'http://mlb.mlb.com');

    $s = curl_exec($ch);
    curl_close($ch);
    
    return $s;
}

// gets array of video urls from content id

function mlb_get_vid_urls($cid) {
    $fnum = substr($cid, -3, 1);
    $snum = substr($cid, -2, 1);
    $lnum = substr($cid, -1);
    
    $url = 'http://mlb.mlb.com/gen/multimedia/detail/' . $fnum . '/' . $snum . '/' . $lnum . '/' . $cid . '.xml';
    $res = mlb_get_page($url);
    $xml = @simplexml_load_string($res);
    
    $urls = array();
    
    if ($xml) {
    foreach ($xml->url as $idx => $u) {
        $urls[(string)$u['playback_scenario']] = (string)$u;
    }
    }
    
    return $urls;
}

// get $howmany latest highlights, $doVidUrls set to false will prevent it from calling mlb_get_vid_urls

function mlb_get_highlights($howmany, $doVidUrls = true) {
    $s = mlb_get_page('http://mlb.mlb.com/ws/search/MediaSearchService?type=json&src=vpp&start=0&sort=desc&sort_type=mid&hitsPerPage=' . $howmany . '&text=highlight');
    
    $arr = json_decode($s);
    
    $results = array();
    
    foreach ($arr->mediaContent as $m) {
        $tmp = array('title' => $m->title, 'duration' => $m->duration, 'desc' => $m->bigBlurb, 
            'date_added' => $m->date_added, 'mid' => $m->mid, 'kicker' => $m->kicker, 'contentId' => $m->contentId);
        
        $tmp['thumbnails'] = array();
        foreach ($m->thumbnails as $tn) {
            $tmp['thumbnails'][] = $tn->src;    
        }
        
        $tmp['keywords'] = array();
        foreach ($m->keywords as $kw) {
            $tmp['keywords'][] = $kw->keyword;    
        }
        
        if ($doVidUrls) {
            $tmp['vid_urls'] = mlb_get_vid_urls($tmp['contentId']);
        }
        
        $results[$m->contentId] = $tmp;
    }
    
    return $results;
}

// does a search for $kw and returns $howmany search results. $doVidUrls set to false will prevent it from calling mlb_get_vid_urls

function mlb_get_search($kw, $howmany, $doVidUrls = true) {
    $s = mlb_get_page('http://mlb.mlb.com/ws/search/MediaSearchService?text=' . $kw . '&type=json&src=vpp&start=0&sort=desc&sort_type=mid&hitsPerPage=' . $howmany);
    $arr = json_decode($s);
    
    $results = array();

    foreach ($arr->mediaContent as $m) {
        $tmp = array('title' => $m->title, 'duration' => $m->duration, 'desc' => $m->bigBlurb, 
            'date_added' => $m->date_added, 'mid' => $m->mid, 'kicker' => $m->kicker, 'contentId' => $m->contentId);
        
        $tmp['thumbnails'] = array();
        foreach ($m->thumbnails as $tn) {
            $tmp['thumbnails'][] = $tn->src;    
        }
        
        $tmp['keywords'] = array();
        foreach ($m->keywords as $kw) {
            $tmp['keywords'][] = $kw->keyword;    
        }
        
        if ($doVidUrls) {
            $tmp['vid_urls'] = mlb_get_vid_urls($tmp['contentId']);
        }
        
        $results[$m->contentId] = $tmp;
    }
    
    return $results;
}

function mlb_crawl_vids($maxPages, $doVidUrls = true) {
    $start = 1;

    $base_url = 'http://mlb.mlb.com/ws/search/MediaSearchService?type=json&ns=1&start=%d&hitsPerPage=50&text=highlight';
    $recs = json_decode(mlb_get_page($base_url), true);
    
    $total = $recs['total'];
    $pages = ceil($recs['total']/50);

    if ($pages > $maxPages) {
        $pages = $maxPages;    
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_REFERER, 'http://mlb.mlb.com');
    $results = array();
    foreach (range(1, $pages) as $page) {
        $start = (($page-1)*50);
    
        curl_setopt($ch, CURLOPT_URL, sprintf($base_url, $start));
        $s = curl_exec($ch);
    
        $recs = json_decode($s);
    
        foreach ($recs->mediaContent as $m) {
            $tmp = array('title' => $m->title, 'duration' => $m->duration, 'desc' => $m->bigBlurb, 
                'date_added' => $m->date_added, 'mid' => $m->mid, 'kicker' => $m->kicker, 'contentId' => $m->contentId);
        
            $tmp['thumbnails'] = array();
            foreach ($m->thumbnails as $tn) {
                $tmp['thumbnails'][] = $tn->src;    
            }
        
            $tmp['keywords'] = array();
            foreach ($m->keywords as $kw) {
                $tmp['keywords'][] = $kw->keyword;    
            }    
        
            if ($doVidUrls) {
                $tmp['vid_urls'] = mlb_get_vid_urls($tmp['contentId']);
            }
        
            $results[$m->contentId] = $tmp;
        }
    }

    curl_close($ch);
    
    return $results;
}
