<?php
/*
	myWebspider
	
	@param $obj : object settings for crawling
			url : the url to crawl			| string
			drama: the series to find		| array
			tag : specific tag to find		| string
			class : specific class to find  | array
			page : no of page to crawl		| int
			attr : attr of tag to find		| array
			
*/
error_reporting(E_ALL);
ini_set('display_errors', 1);
class WebSpider{
	private $settings = array();
	private $links_found = array();
	private $episode = array();
	private $page_count = 1;
	public function __construct($obj){
		
		include_once('simple_html_dom.php');
		set_time_limit(0);
		$this->setVar($obj);
		if(file_exists($this->settings['record']."_record.txt")){
			$this->episode = json_decode(file_get_contents($this->settings['record']."_record.txt"), true);
		}
		
	}
	public function __destruct(){
		$fp = fopen ($this->settings['record']."_record.txt", "w");
		$log = json_encode($this->episode);
		fwrite($fp, $log);
		fclose($fp);
	}
	public function get_html($url = NULL){
		echo "get html...".$url.PHP_EOL;
		if($url !== NULL){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_FAILONERROR, 0);
			curl_setopt($ch, CURLOPT_ENCODING, "gzip"); 
			curl_setopt($ch, CURLOPT_URL, $url);
			$returned = curl_exec($ch);
			curl_close ($ch);
			return $returned;
		}
	}
	
	public function analyze_html($html){
		echo "start analyzing html...".PHP_EOL;
		$html = str_get_html($html);
		$tag = $this->get_tag(0);
		foreach($html->find($tag) as $link)
		{
			//echo strlen($link->innertext);
			//echo "<br>";
			if(strlen($link->innertext) == 0) continue;
			//echo "strlen > 0 ".$link->innertext."<br>";
			$str = strtolower($link->innertext);
			foreach($this->settings['drama'] AS $title)
			{
				//echo "matching ".$title."<br>";
				if(strpos(strtolower($str), $title) !== false)
				{
					
					$season = "/[sS][0-9][0-9]/";
					$ep = "/[eE][0-9][0-9]/";
					preg_match($season, $str, $season_ary);
					preg_match($ep, $str, $ep_ary);
					$s = substr($season_ary[0], 1, strlen($season_ary[0]));
					$e = substr($ep_ary[0], 1, strlen($ep_ary[0])); 
					if(!array_key_exists($title, $this->episode)){
						$this->episode[$title] = array();
					}
					
					if(!array_key_exists($s, $this->episode[$title])){
						$this->episode[$title][$s] = array($e);
					}
					
					if(!in_array($e, $this->episode[$title][$s])){
						$this->episode[$title][$s][] = $e;
						$this->links_found[$link->innertext] = $link->href;
						$this->write_log("[".date("Y-m-d H:i:s")."] episode - ".$link->innertext." added ".PHP_EOL);
					}
					
					break;
					
				}
				
			}
			
		}
		
		return count($this->links_found);
		
	}
	
	public function downLoad(){
		echo "downloading...".PHP_EOL;
		
		foreach($this->links_found AS $file => $link){
			echo "directing to download page ".PHP_EOL;
			$download_link = $this->settings['base_url'].$link;
			//echo "directing to download page ".$download_link."<br>";
			//echo "file name - ".$file."<br>";
			$returned = $this->get_html($download_link);
			$download_page = str_get_html($returned);
			if (!file_exists("dl") && !is_dir("dl")) {
				mkdir("dl");         
			} 
			$path = "dl/".$file.'.torrent';
			//echo "file name - ".$path."<br>";
			$tag = $this->get_tag(1);
			$dlink = $download_page->find($tag)[0];
			//echo "link - ".$dlink->href."<br/>";
			echo "text - ".$file.PHP_EOL;
			$url = "https:".$dlink->href;
			
			if(!$this->copySecureFile($url, $path)){
				$this->write_log("[".date("Y-m-d H:i:s")."] something went wrong with downloading ".$file.PHP_EOL);
			} 
			
		}
		echo "download ended".PHP_EOL;
		if($this->settings['page'] > 0 && $this->settings['page'] > $this->page_count){
			$this->page_count++;
			$this->start_crawling();
		}
		exit();
	}
	
	public function start_crawling(){
		
		$url = ($this->page_count > 1) ? $this->settings['url'].$this->page_count."/" : $this->settings['url'];
		echo "crawling ".$url.PHP_EOL;
		$html = $this->get_html($url);
		if($this->analyze_html($html) > 0){
			
			echo "new list found ".PHP_EOL;
			$this->downLoad();
			
		}else{
			
			if($this->settings['page'] > 0 && $this->settings['page'] > $this->page_count){
				
				$this->page_count ++;
				echo "start crawling page ".$this->page_count." ".PHP_EOL;
				$this->start_crawling();
			
			}else{
				
				echo "no new list...retry in 10 minutes".PHP_EOL;
				$this->page_count = 1;
				sleep(600);
				$this->start_crawling();
			}
			
		}
	}
	
	public function copySecureFile($FromLocation, $ToLocation){
		
		$c = file_get_contents($FromLocation);
		$File = fopen ($ToLocation, "w");
		fwrite($File, gzdecode($c));
		fclose($File);
		return file_exists($ToLocation);
		
	}
	
	public function write_log($str){
		
		echo 'logging new log '.$str.PHP_EOL;
		$fp  = file_get_contents('log.txt');
		$fp .= $str;
		file_put_contents('log.txt', $fp);
		
	}
	
	public function get_tag($index){
		return $this->settings['tag'].'['.$this->settings['col'][$index].'='.$this->settings['attr'][$this->settings['col'][$index]].']';
	}
	
	public function setVar($obj){
		$this->settings = $obj;
		$this->settings['col'] = array_keys($this->settings['attr']);
		$this->settings['current_depth'] = 1;
	}
}