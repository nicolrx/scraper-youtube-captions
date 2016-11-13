<?php

/**
 * Some helper functions to fetch "Closed Captions" for a YouTube video
 * 
 * 
 * Thanks to Jordan Skoblenick
 */
	
function getClosedCaptionsForVideo($videoId, $countUrls, $key) {
	$baseUrl = getBaseClosedCaptionsUrl($videoId);
	
	// find available languages. options vary widely from
	// video to video, and sometimes garbage is returned.
	// tracks are returned in order we think is best - 
	// try first, if its garbage, try 2nd, etc.
	$availableTracks = getAvailableTracks($baseUrl);
	
	$urlVid = "https://www.youtube.com/watch?v=".$videoId;
	$imgVid = "http://img.youtube.com/vi/".$videoId."/0.jpg";
	// 	Curl init
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $urlVid);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$result = curl_exec ($ch);
	curl_close($ch);
	
	// 	We get the html to scrape the video title
	$html = new simple_html_dom();
	$html->load($result);
	
		foreach($html->find('h1') as $title) {
			if(null!==($title->find('#eow-title', 0))) {
				$item['title'] = $title->find('#eow-title', 0)  ->plaintext;
			}
		}
	
	$text = null;
	
	foreach ($availableTracks as $key=>$track) {
	$text = getClosedCaptionText($baseUrl, $track);
		
	$xmlData = file_get_contents($text);
 	$xmlNode = simplexml_load_string($xmlData);
    $arrayData = xmlToArray($xmlNode);
    $countProp = count($arrayData['transcript']['text']);
		for($i=0; $i < $countProp; $i++) {
    $arrayData['transcript']['text']{$i}['url'] = $urlVid;
    $arrayData['transcript']['text']{$i}['title'] = addslashes(htmlspecialchars_decode(strip_tags((string)$item['title']), ENT_QUOTES));
    $arrayData['transcript']['text']{$i}['img'] = $imgVid;
    $arrayData['transcript']['text']{$i}['id'] = "clinton"; // change tag
    
    $data = json_encode($arrayData);
  
  		}
	}
	
       
    
    
    $data = str_replace('\n',' ',$data);
    $data = str_replace('"text"','"script"',$data); 
	$data = str_replace('{"transcript":','[',$data);
	$data = substr_replace($data,"",0,1);
	$data = substr_replace($data,"",-1,1);
	
    return $data;
}

/**
 * Returns base URL for TimedText. 
 * "List languages"/"retrive text" commands will be appended to this URL
 * 
 * Fetching this from the page saves us having to calculate a signature :)
 * 
 * @param string $videoId
 * @return string The base URL for TimedText requests
 */
function getBaseClosedCaptionsUrl($videoId) {
	$youtubeUrl = 'http://www.youtube.com/watch?v=';
	$pageUrl = $youtubeUrl.$videoId;
	if (!$responseText = file_get_contents($pageUrl)) {
		die('Failed to load youtube url '.$pageUrl);
	}
	
	$matches = [];
	if (!preg_match('/TTS_URL\': "(.+?)"/is', $responseText, $matches)) {
		die('Failed to find TTS_URL in page source for '.$pageUrl);
	}
	
	return str_replace(['\\u0026', '\\/'], ['&', '/'], $matches[1]);
}

/**
 * Given a base URL, queries for available tracks and 
 * returns them in a sorted array ("scored" from highest
 * to lowest based on things like `default_language` etc)
 * 
 * @param string $baseUrl Base URL found by calling getBaseClosedCaptionsUrl()
 * @return array An array of Closed Captions tracks available for this video
 */
function getAvailableTracks($baseUrl) {
	$tracks = [];
	
	// "request list" command
	$listUrl = $baseUrl.'&type=list&tlangs=1&fmts=1&vssids=1&asrs=1';
	if (!$responseText = file_get_contents($listUrl)) {
		die('Failed to load youtube TTS list url '.$listUrl);
	}
	if (!$responseXml = simplexml_load_string($responseText)) {
		die(' Failed to decode Xml for '.$responseText);
	}
	if (!$responseXml->track) {
		// no tracks found for this video (happens sometimes even though
		// YT search API says they do have captions)
		return $tracks;
	}
	
	foreach ($responseXml->track as $track) {
		$score = 0;
		if ((string)$track['lang_default'] === 'true') {
			// we like defaults
			$score += 50;
		}
		
		$tracks[] = [
		    'score' => $score,
		    'id' => (string)$track['id'],
		    'lang' => (string)$track['lang_code'],
		    'kind' => (string)$track['kind'],
		    'name' => (string)$track['name']
		];
	}
	
	// sort tracks by descending score
	usort($tracks, function($a, $b) {
		if ($a['score'] == $b['score']) {
			return 0;
		}
		return ($a['score'] > $b['score']) ? -1 : 1;
	});
	
	
	return $tracks;
}

/**
 * Given a base URL and a track, attempt to request Closed Captions
 * 
 * If found, decode and strip tags from response, and join each line
 * with a "<br />" and a "\n"
 * 
 * @param string $baseUrl Base URL found by calling getBaseClosedCaptionsUrl()
 * @param array $track Specific track to request
 * @return string Closed captions text for video & track combo
 */
function getClosedCaptionText($baseUrl, array $track) {
	$captionsUrl = $baseUrl."&type=track&lang={$track['lang']}&name=".urlencode($track['name'])."&kind={$track['kind']}&fmt=1";
	
	
	if (!$responseText = file_get_contents($captionsUrl)) {
		die('Failed to load youtube TTS captions track url '.$captionsUrl);
	}
	if (!$responseXml = simplexml_load_string($responseText)) {
		die(' Failed to decode Xml for '.$responseText);
	}
	if (!$responseXml->text) {
		die(' Bad XML structure for '.$captionsUrl.' : '.$responseText);
	}
	
	$videoText = [];
	foreach ($responseXml->text as $textNode) {
		if ($text = trim((string)$textNode)) {
			$videoText[] = addslashes(htmlspecialchars_decode(strip_tags((string)$textNode), ENT_QUOTES));
		}
	}

	return $captionsUrl;
	
}

// $url = ["pXXfiO4B5eA", "JIFf74zGwhw", "eJ5kNEL0KzI", "xSAyOlQuVX4", "x_fW7fbZ1qA", "_NiDay86Ft0", "qzEAyM0LK2U", "4Q_s6cXSv_8", "GyHjts09XCc", "Wk0wGhL4NqU", "lDRSITwAXQQ", "k-1Dqz8Hj8g", "M2RlwN7tvVQ", "8172bC179T4", "3MLsRBjkrVo", "2hptE3ewkD4", "Fs0pZ_GrTy8", "6LYgg3_Emy0", "VYj5hUnmTHc", "GwD-GxZhl1E", "_0qO3Xm9de8", "VZulo6aMtBw", "ai092-4JRYE", "bnd0uipQKO4", "ydydhlkCVSQ", "nXyTU4Qf6d0", "X-M8DQyxJJs", "hI9YO460YRU", "_NiDay86Ft0", "nu16SuKt2gY", "kPXwxHRqcpY", "66nT9vv4Ifo", "-ZoZelFVbLc", "s3njVYErHeI", "E9VGh-13yto", "4LUq0PBZAtA", "EwaVWdhxrWA", "yUuXueizmZY", "DUBbS4xa3LY", "D9rhRJ2u7Ec", "-3MkYFq3f-Y", "a-77QgKuzmo", "12gKUVXHbWw", "Ceb7Pp7e7u4", "OBB1BHsDTbI", "Fpzzmry6NIE", "bX2qnkO3eX8", "ue7hP7AOplo", "wN0QWV-6ozE", "EAEnBo9Uh1g", "o4xjA96A9G4", "Sxk3-k8tAIc", "6MQE9W1F1ys", "pV29MuEm3xo"]; // trump
$url = ["YxI6GV30yL0", "Fl6XFatFTjs", "5VE9nihee7o", "u3dhGy1-Z2k", "fy_WJEs71Gw", "-6m_0fOkQ3E", "D-5ZVHcm-2M", "-dHLd3DnkEI", "RD5qQhEy2L0", "VNc6oAnCOLs", "U2ytZcISqKY", "GOmwVXDdKRM", "g_wkSGLVpcE", "ZBpjqP3qrMQ", "SOtiKXvA-q0", "UbEalLMQvOw", "Z1wRoI-8dhg", "fsR8wK5XqUM", "nD1QaeeZ-gw", "DnEfPDOifc0", "75x6nb5NqYM", "BFQG0Bfqaoo", "rG8cGkbGbYQ", "VNSUxI-9vqI", "MWAJQEuYbTA", "iSdR5bgpVZc", "lXnA8-8lVbM", "hPJdGVPf9h0", "y03Sc0H3n8s", "_02BeByzqPY", "c_9ThARis10", "m_ZtgSTpv9Y", "-vOoPd3BUT8", "8oimjUWpWlU", "f3-13H3A2Y8", "FsYK_EOJsms", "nqaNtO27G9Q", "H1gfcakwieU", "BPIARhlQarw", "cG9IYu4TMj4", "MI6iFz14O3U", "9uVWXeCULAM", "rKV9nCadurE", "P2Jhepbbcjc", "Gu29J45xPZ8", "feJbR25IHj4", "Ecg3n1DhiJc", "dIA0NXmB3gU", "polD2-vVj8s", "aIB6Ac8Bl1w", "RjarHX2MGTQ", "s_HIEIiv8Ao", "j5Xv80YydnI", "TV7w1dnipnM", "U2IUU7lrRXQ", "cMRJjfhsTkY", "NUc4rQp4trI"]; // clinton
// $url = ["7BGYYaaLrTc"]; // first debate Clinton / Trump
$countUrls = count($url) - 1;

echo "[";
foreach ($url as $key=>$eachUrl) {
	echo getClosedCaptionsForVideo($eachUrl, $countUrls, $key);	
	if ($countUrls > $key) {
   		echo ",";
   	}
}
echo "]";