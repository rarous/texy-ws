<?php

/**
 * Texy-WS 2.0
 * Creative Commons License 2.5
 * 
 */
ini_set("soap.wsdl_cache_enabled", "0");
require_once("texy.compact.php");
require_once('NDebug.php');
NDebug::handleErrors();

class TexyWS extends Object {
	var $texy;
	
	function TexyWS() {
		$this->texy = new Texy();
	}
	
	public function getVersion() {
		return Texy::VERSION;
	}
	
	public function process($text) {
		return $this->texy->process($text);
	}
	
	private function allowedInline() {
		$this->texy->allowed['phrase/ins'] = TRUE;
		$this->texy->allowed['phrase/del'] = TRUE;
		$this->texy->allowed['phrase/sup'] = TRUE;
		$this->texy->allowed['phrase/sub'] = TRUE;
		$this->texy->allowed['phrase/cite'] = TRUE;
		$this->texy->allowed['figure'] = TRUE;
	}
	
	public function PrevedDoXhtml($text) {
		$this->allowedInline();
		
		$this->texy->allowedTags = TRUE;
		
		$this->texy->headingModule->top = 3;
		$this->texy->headingModule->generateID = TRUE;
		
		$this->texy->figureModule->class = 'image';
		
		$this->texy->addHandler('script', 'insertFlash');
		
		return $this->process($text);
	}
	
	public function PrevedDoXhtmlS($text) {
		$this->allowedInline();
		
		$this->texy->allowedTags = FALSE;
		
		$this->texy->headingModule->top = 4;
		
		return $this->process($text);
	}
	
	public function PrevedDoXhtmlR($text, $utf, $trust, $headingLevel) {
		$this->texy->utf = $utf;
		if($trust) $this->texy->trustMode();
		else $this->texy->safeMode();
		$this->texy->headingModule->top = $headingLevel;
		$html = $this->texy->process($text);
		return $html;
	}
}

function insertFlash($invocation, $cmd, $args, $raw) {
	switch ($cmd) {
		case 'flash':
			$movie = Texy::escapeHtml($args[0]);
			$width = $args[1];
			$height = $args[2];
			$vars = Texy::escapeHtml($args[3]);
			$output = '<!--[if !IE]> -->
			<object type="application/x-shockwave-flash" data="'.$movie.'" width="'.$width.'" height="'.$height.'">
			<!-- <![endif]-->
			<!--[if IE]>
			<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"
			  codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,0,0"
			  width="'.$width.'" height="'.$height.'">
			  <param name="movie" value="'.$movie.'" />
			<!--><!--dgx-->
			  <param name="loop" value="true" />
			  <param name="menu" value="false" />
			  <param name="allowfullscreen" value="true" />
			  <param name="flashvars" value="'.$vars.'" />
			</object>
			<!-- <![endif]-->';
			return $invocation->texy->protect($output, Texy::CONTENT_MARKUP);
		default: // neumime zpracovat, zavolame dalsi handler v rade
			return $invocation->proceed();
	}
}

$server = new SoapServer(null, array('uri' => "http://texy.info"));
$server->setClass('TexyWS');
$server->handle(); 
