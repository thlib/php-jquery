<?php
/*
$htmldoc = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><h1>Error none</h1><p>Page found</p></body></html>';
$parser = new HTML5Parser();
$parser->error(function($err){
	// $err is an Exception
});
// Example plugins
$parser->extend(function($dom, $q){
	// http://learn.jquery.com/plugins/basic-plugin-creation/
	$q->fn->greenify = function($that) use($q){
		$that->css( "color", "green" );
	};
	$q->fn->showLinkLocation = function($that) use($q){
		$that = isset($this)?$this:($q::$instance);
		return $that->each(function($i, $el) use($q) {
			$q( $el )->append( " (" . $q( $el )->attr( "href" ) . ")" );
		});
	};
	$q->sum = function() use($q){
		return array_sum(func_get_args());
	};
});
$parser->parse($htmldoc, function($dom, $jQuery){
	// Works with unicode (was surprisingly hard to achieve)
	// $dom holds the document DOM
	// Run utility functions with $jQuery->parseHTML($data, $context, $keep_scripts);
	// Query the DOM with CSS3 and manipulate the selection: $jQuery('.class-name')->attr('rel', 'external');
	// Query the DOM with XPATH and manipulate the selection: $jQuery('descendant::select')->val('');
	// Get the <body> content html: $jQuery('body')->html();
});
*/

namespace ThinkHTML\jQuery;

use HTML5_Parser;
use ThinkHTML\jQuery\jQuery;

// HTML parser and document scope creation for jQuery
class HTML5Parser
{
	public $dom, $err, $jquery, $error_handle, $extensions = array();
	public function __construct($options=array())
	{
		$default = array(
			'encoding'=>null,
			'default_encoding'=>'utf-8',
			'data_camel_case'=>true,
		);
		$this->opts = array_replace($default, $options);
	}
	// Error handle
	public function error($callback)
	{
		$this->error_handle = $callback;
	}
	// Make this class extendable in the same way as jQuery
	public function extend($callback){
		$this->extensions[] = $callback;
	}
	public function parse($html, $callback)
	{
		$dom = $err = null;
		try {
			if(is_string($html)){
			
				// To know what to save
				$this->is_fragment = (strpos($html,'<html')===false) ? true : false;
			
				// Add support for <meta charset="utf-8">
				$html = $this->handleEncoding($html);
				
				// Parse the document
				//if(strpos($html,'<!DOCTYPE')===false){
				//	$dom = HTML5_Parser::parseFragment($html);
				//}else{
					$dom = HTML5_Parser::parse($html);
				//}
			}else{
				$dom = $html;
			}
		}catch(Exception $err) { }
		
		$this->err = $err;
		$this->dom = $dom;
		$this->jquery = new jQuery($dom, $this->opts);
		
		foreach($this->extensions as $ext_cb){
			call_user_func($ext_cb, $this->dom, $this->jquery);
		}
		
		if($err){
			if(isset($this->error_handle)){
				call_user_func($this->error_handle, $err);
			}
		}else{
			try {
				call_user_func($callback, $dom, $this->jquery);
			}catch(Exception $err){
				if(isset($this->error_handle)){
					call_user_func($this->error_handle, $err);
				}
			}
		}
		return $dom;
	}
	
	public function handleEncoding($html)
	{
		$encoding = $this->opts['default_encoding'];
	
		// Get encoding and the position of the tag
		$content_type = self::getContentType($html);
		
		// Determine the encoding to be used
		$encoding = ($content_type===false)? $encoding: $content_type['encoding'];
		
		// Override the encoding if set
		$encoding = empty($this->opts['encoding'])?$encoding:$this->opts['encoding'];
		
		// Remove the old encoding tag, which might be valid but still not work for DOMDocument
		$html = substr_replace($html, '', $content_type['start'], $content_type['length']);
		
		// Set or re-set the proper encoding
		$html = self::setEncoding($html, $encoding);
		
		return $html;
	}
	public static function setEncoding($html, $encoding)
	{
		// Remember <meta charset="utf-8"> has no effect on php DOMDocument
		// Remember the <head> tag is optional
		// The first meta has priority, the later ones are ignored
		// Stupid way to inject unicode charset that works and is reliable
		$tag = '<meta http-equiv="Content-Type" content="text/html; charset='.$encoding.'">';
		return $tag.$html;
	}
	public static function getContentType($html)
	{
		//http://stackoverflow.com/questions/4696499/meta-charset-utf-8-vs-meta-http-equiv-content-type
		//http://stackoverflow.com/a/10769573/175071
		// get the first 512 bytes (for performance)
		$block = substr($html, 0, 512);
		if(preg_match('@<meta(?!\s*(?:name|value)\s*=)(?:[^>]*?content\s*=[\s"\']*)?([^>]*?)[\s"\';]*charset\s*=[\s"\']*([^\s"\'/>]*)[^>]*>\s*@i', $block, $match, PREG_OFFSET_CAPTURE)){
			$data = array();
			$data['start'] = $match[0][1];
			$data['end'] = $match[0][1] + strlen($match[0][0]);
			$data['length'] = strlen($match[0][0]);
			$data['content-type'] = $match[1][0];
			$data['encoding'] = $match[2][0];
			return $data;
		}
		return false;
	}
	public static function formatHtml($dom)
	{
		$dom->formatOutput = true;
		$dom->preserveWhitespace = false;
		return $dom->saveHTML();
	}
	public function html()
	{
		$self = $this;
		$this->ready(function($dom, $q) use($self){
			$meta = $q('meta[http-equiv="Content-Type"]');
			$content_type = $self->getContentType($meta->outerHTML());
			$meta_node = $self->dom->createElement('meta');
			$meta_node->setAttribute('charset', $content_type['encoding']);
			$meta->replaceWith($meta_node);
		});
		return "<!DOCTYPE html>\r\n".$this->dom->saveHTML($this->dom);
	}
}

