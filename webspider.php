<?php
/*
	myWebspider
	
	@param $obj : object settings for crawling
		base_url : url to use for concating | string        
		url : the url to crawl          | string
		keyword: keyword to find        | array
		tag : specific tag to find      | array
		downLoad_tag : specific download tag    | array
		page : no of page to crawl      | int
		depth : how deep to crawl       | int
		pagination : attr to get pagination | array
		regexp : for filter strings     | string
		record: name of file to record which files have been downloaded | string
		action: just get link or download(only torrent) | string
			
*/
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_implicit_flush(true);
ob_end_flush();
class WebSpider{
	private $settings = array();
	private $page_count = 1;
	public function __construct($obj){
		include_once('simple_html_dom.php');
		set_time_limit(0);
		$this->setVar($obj);
		$this->links_found = array();
		if( ! is_writable(dirname("../webSpider"))){
			echo "no perm <br>".PHP_EOL;
			chown("../webSpider", 0755);
		}
		if(file_exists($this->settings['record']."_record.txt")){
			if(is_writable($this->settings['record']."_record.txt")){
				echo $this->settings['record']."_record.txt can write <br>".PHP_EOL;
			}
			$this->episode = json_decode(file_get_contents($this->settings['record']."_record.txt"), true);
			
		}else{
			$this->episode = array();
		}
		
		if(isset($this->settings['page']) && $this->settings['page'] > 1){
			$html = $this->get_html($this->settings['url']);
			$this->getPage($html);
		}
		if(!file_exists("dl") && !is_dir("dl")){
			mkdir("dl");         
		} 
		
	}
	public function __destruct(){
		$fp = fopen($this->settings['record']."_record.txt", "w")or die("can't open file");;
		$log = json_encode($this->episode);
		echo "saving record to ".$this->settings['record']."_record.txt...<br>".$log.PHP_EOL;
		fwrite($fp, $log);
		fclose($fp);
		
	}
	
	public function get_html($url = NULL){
		//echo "get html...".$url.PHP_EOL;
		if($url !== NULL){
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_VERBOSE, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_FAILONERROR, 0);
			curl_setopt($ch, CURLOPT_ENCODING, "utf-8"); 
			curl_setopt($ch, CURLOPT_URL, $url);
			$returned = curl_exec($ch);
			curl_close ($ch);
			return $returned;
		}
	}
	/*
		find the links of current page
	*/
	public function analyze_html($html){
		echo "start analyzing html...".PHP_EOL;
		if(isset($this->settings['page']) && $this->settings['page'] > 1){
			$this->page_count ++;
			echo "refresh pagination...".PHP_EOL;
			$this->getPage($html);
		}
		$html = str_get_html($html);
		$tag = $this->get_tag($this->settings['tag']);
		//echo "tag to match ".$tag.PHP_EOL;
		foreach($html->find($tag) as $link)
		{
			$str = strtolower($link->innertext);
			
			foreach($this->settings['keyword'] AS $title)
			{
				echo "matching ".$title.$str."<br>".PHP_EOL;
				if(strpos(strtolower($str), $title) !== false)
				{
					//echo "inner text ".$link->outertext.PHP_EOL."<br>";
					if($this->settings['regexp'] != NULL){
						$season = $this->settings['regexp'];
						preg_match($season, $str, $season_ary);
						if(empty($this->episode) || !array_key_exists($title, $this->episode)){
							$this->episode[$title] = array();
							echo "array key added <br>".PHP_EOL; 
						}
						
						if(isset($season_ary[0]) && !in_array($season_ary[0], $this->episode[$title])){
							echo "href...".$link->href;
							echo "<br>".PHP_EOL;
							$this->episode[$title][] = $season_ary[0];
							$this->links_found[$link->innertext] = $link->href;
							$this->write_log("[".date("Y-m-d H:i:s")."] episode - ".$link->innertext." added ".PHP_EOL, 'log.txt');
						}
						
						break;
					}else{
						echo 'href found '.$link->href,PHP_EOL;
						$file = date("Y-m-d").".txt";
						if(!file_exists($file)){
							fopen($file, "w");
							//fclose($file);
						}
						$this->write_log("[".date("Y-m-d H:i:s")."] episode - ".$link->innertext." added ".PHP_EOL, $file);
					}
				}
				
			}
			
		}
		
		return count($this->links_found);
		
	}
	/*
		starting point of crawler
	*/
	public function start_crawling(){
		//if pages to crawl > 1 set url to next page
		echo 'start crawling...page '.$this->page_count.PHP_EOL;
		$url = ($this->page_count > 1) ? html_entity_decode($this->settings['base_url'].$this->pagination[$this->page_count]) : $this->settings['url'];
		echo 'url....'.$url.PHP_EOL;
		//get the html from url given
		$html = $this->get_html($url);
		if(!$html){
			echo 'connection fail'.PHP_EOL;
			sleep(1);
			$this->start_crawling();
		}
		if($this->analyze_html($html) > 0){
			
			echo "new list found ".PHP_EOL;
			$this->getData();
			
		}else{
			
			if(isset($this->settings['page']) && $this->settings['page'] > 0 && $this->settings['page'] > $this->page_count){
				echo "crawling next page".PHP_EOL;
				$this->start_crawling();
			
			}else{
				if($this->settings['retry']){
					echo "no new list...retry in 10 minutes".PHP_EOL;
					$this->page_count = 1;
					sleep(3);
					$this->start_crawling();
				}
			}
		}
		
	}
	
	public function getData(){
		
		echo "fetching data...".PHP_EOL;
		if(isset($this->settings['page']) && $this->settings['page'] > 0 && $this->settings['page'] > $this->page_count){
			$this->start_crawling();
		}
		$func = $this->settings['action'];
		if(!method_exists($this, $func)){
			$func = 'getLink';
		}
		
		foreach($this->links_found AS $file => $link){
			$this->$func($link, $file);
		}
		echo "download ended".PHP_EOL;
		exit();
	}
	
	public function downLoad($link, $file){
		echo "directing to download page ".PHP_EOL;
		$download_link = $this->settings['base_url'].$link;
		$returned = $this->get_html($download_link);
		$download_page = str_get_html($returned);
		$path = 'dl/'.$file.'.torrent';
		$tag = $this->get_tag($this->settings['downLoad_tag']);
		$dlink = $download_page->find($tag)[0];
		echo "text - ".$file.PHP_EOL;
		$url = "https:".$dlink->href;
		if(!$this->copySecureFile($url, $path)){
			$this->write_log("[".date("Y-m-d H:i:s")."] something went wrong with downloading ".$file.PHP_EOL, 'log.txt');
		} 
	}
	
	public function getLink($link, $file){
		echo "getLink...".$link.PHP_EOL;
		$filename =  $this->getFilename($this->settings['keyword'], $file);
		$_link = $this->settings['base_url'].$link;
		if($this->settings['depth'] > 1){
			$returned = $this->get_html($_link);
			if(!$returned){
				echo "connection fail...reconnect".PHP_EOL;
				sleep(1);
				$this->getLink($link, $file);
			}
			$_page = str_get_html($returned);
			$tag = $this->get_tag($this->settings['downLoad_tag']);
			$href = '';
			//$dlink = $_page->find($tag)[0];
			foreach($_page->find($tag) AS $dlink){
				
				$href .= $dlink->href." ";
				
				
			}
			$str = "[".date("Y-m-d H:i:s")."]".$file." ".$href.PHP_EOL;
		}else{
			$str = "[".date("Y-m-d H:i:s")."]".$file." ".$_link.PHP_EOL;
		}
		$this->write_log($str, $this->settings['record']."/".$this->settings['record']."_log_[".date("Y-m-d")."].txt");
	}
	
	public function getPage($html){
		echo 'get page...'.$this->page_count.PHP_EOL;
		unset($this->pagination);
		$this->pagination = array();
		$html = str_get_html($html);
	
		foreach($html->find($this->settings['pagination_attr']) AS $page){
			$this->pagination[$page->innertext] = $page->href;
		}
		
		return array_filter($this->pagination);
		
	}
	
	public function getFilename($keywords, $file){
		
		foreach($keywords As $keyword){
			
			if(strpos("bla ".strtolower($file), $keyword) !== false){
				
				return $keyword.".txt";
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
	
	public function write_log($str, $filename){
		
		echo 'logging new log '.$str.PHP_EOL;
		if(!file_exists($this->settings['record']) && !is_dir($this->settings['record'])){
			mkdir($this->settings['record']);
			
		}
		$fp = fopen($filename, 'a');
		fwrite($fp, $str);
		fclose($fp);
		
		
	}
	
	public function get_tag($ary){
		$str = '';
		foreach($ary AS $element){
			
			$str .= $element['tag'];
			if(count($element['attr']) > 1){
				$str .= "[".$element['attr'][0]."=".$element['attr'][1]."] ";
				
			}
		}
		return trim($str);
	
	}
	
	public function setVar($obj){
		$this->settings = $obj;
		//$this->settings['col'] = array_keys($this->settings['attr']);
		if(!isset($this->settings['depth']))
		{
			$this->settings['depth'] = 1;
		}
		$this->settings['pagination_attr'] = isset($this->settings['pagination'])? $this->get_tag($this->settings['pagination']) : NULL;
	}
}
