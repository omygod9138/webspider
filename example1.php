<?php
include_once("webspider.php");
echo "initializing class...<br>";
$settings = array(	
					'base_url'=> 'https://kat.cr', 
					'url' 	=> 'https://kat.cr/tv/', 
					'drama' => array('limitless', 'lucifer', 'flash', 'arrow', 'big bang theory'),
					'tag' 	=> 'a',
					'attr'	=> array('class' => 'cellMainLink', 'title' => 'Download verified torrent file'),
					'page'  => 3,
					'record' => 'kat'
				);
$spider = new WebSpider($settings);
echo "start crawling ".PHP_EOL;
$spider->start_crawling();
echo "function started and running".PHP_EOL; 