<?php
/*
Made in the likeness of http://ajax.googleapis.com/ajax/libs/jquery/2.1.0/jquery.js
 - handles prop() and attr() properly

$dom = new DOMDocument;
$dom->load('doc.html');
$jQuery = new jQuery($dom);
Run utility functions with $jQuery->parseHTML($data, $context, $keep_scripts);
Query the DOM with CSS3 and manipulate the selection: $jQuery('.class-name')->attr('rel', 'external');
Query the DOM with XPATH and manipulate the selection: $jQuery('descendant::select')->val('');
Get the <body> content html: $jQuery('body')->html();

Example plugins:
http://learn.jquery.com/plugins/basic-plugin-creation/

		$jQuery->fn->greenify = function($that) use($jQuery){
			$that->css( "color", "green" );
		};
		$jQuery->fn->showLinkLocation = function($that) use($jQuery){
			return $that->each(function($i, $el) use($jQuery) {
				$jQuery( $el )->append( " (" . $jQuery( $el )->attr( "href" ) . ")" );
			});
		};
		$q->sum = function() use($jQuery){
			return array_sum(func_get_args());
		};
		
*/

namespace ThinkHTML\jQuery;

use \stdClass;
use \Exception;
use \DOMDocument;
use \DOMXPath;
use ThinkHTML\jQuery\jQueryObject;
use ThinkHTML\jQuery\SymfonyCSSSelector;
use HTML5_Parser as HTML5_Parser;

// The jQuery object
class jQuery
{
	public $dom, $fn, $options=array();
	public static $instance;
	public function __construct(DOMDocument $dom, $options=array())
	{
		$this->options = $options;
		$this->fn = new stdClass;
		$this->dom = $dom;
		$this->error = function($msg){
			throw new Exception($msg);
		};
		$jquery = $this;
		$this->parseHTML = function($html, $context=null, $keep_scripts=false, $encoding=null) use($jquery) {
			$encoding = $encoding ? $encoding : $jquery->dom->actualEncoding;
			$context = $context ? $context : $jquery->dom;
			$html = HTML5Parser::setEncoding($html, $encoding);
			$dom = HTML5_Parser::parse($html);
			/*
			TODO: figure out and test
			if(!$keep_scripts){
				$scripts = $dom->getElementsByTagName('script');
				if($scripts && $scripts->length){
					$i = $scripts->length;
					while($i){
						$el = $scripts->item($i);
						$el->parentNode->removeChild($el);
						$i--;
					}
				}
			}
			*/
			$children = $dom->getElementsByTagName('body')->item(0)->childNodes;
			
			//$children = HTML5_Parser::parseFragment($html);
			$nodes = array();
			if($children && $children->length){
				for($i=0; $i<$children->length; $i++){
					$node = $children->item($i);
					$nodes[] = $node;
				}
			}
			return $nodes;
		};
		$this->merge = function($first, $second) use($jquery){
			$len = count($second);
			$j = 0;
			$i = count($first);

			for ( ; $j < $len; $j++ ) {
				$first[ $i++ ] = $second[ $j ];
			}

			return $first;
		};
		$this->grep = function( $elems, $callback, $invert=false ) use($jquery){
			$callbackInverse;
			$matches = array();
			$i = 0;
			$length = count($elems);
			$callbackExpect = !$invert;

			// Go through the array, only saving the items
			// that pass the validator function
			for ( ; $i < $length; $i++ ) {
				$callbackInverse = !$callback( $elems[ $i ], $i );
				if ( $callbackInverse !== $callbackExpect ) {
					$matches[] = $elems[ $i ];
				}
			}

			return $matches;
		};
		$this->filter = function( $expr, $elems, $not=false ) use($jquery) {
			$elem = $elems[ 0 ];

			if ( $not ) {
				$expr = ":not(" . $expr . ")";
			}

			return count($elems) === 1 && $elem->nodeType === 1 ?
				($jquery->findMatchesSelector( $elem, $expr ) ? array( $elem ) : array()) :
				($jquery->findMatches( $expr, $jquery->grep( $elems, function( $elem ) {
					return $elem->nodeType === 1;
				})));
		};
	}
	public function findMatchesSelector($elem, $expr){
		$matches = $this->getElementsFromSelector($expr);
		return $this->arrayHasNode($elem, $matches);
	}
	public function findMatches($expr, $elems){
		$collection = array();
		$matches = $this->getElementsFromSelector($expr);
		foreach($matches as $node){
			foreach($elems as $elem){
				if($node->isSameNode($elem)){
					$collection[] = $node;
				}
			}
		}
		return $collection;
	}
	public static function firstSlug($str){
		$parts = explode('/', $str);
		return array_shift($parts);
	}
	public static function isXPath($str){
		if(strpos($str,'/')===0){
			return true;
		}
		$first = self::firstSlug($str);
		$parts = explode('::', $first);
		$first = array_shift($parts);
		return in_array($first, array(
		'body','child',
		'ancestor',
		'ancestor-or-self',
		'attribute','@',
		'descendant',
		'descendant-or-self',
		'following',
		'following-sibling',
		'namespace',
		'parent',
		'preceding',
		'preceding-sibling',
		'self'));
	}
	public static function toXPath($css3)
	{
		return SymfonyCSSSelector::toXPath($css3);
	}
	public function getElementsFromSelector($content, $context=null)
	{
		$jquery = $this;
		$nodes = array();
		if(is_string($content) && strpos($content,'<')===false){
			$xpath = self::isXPath($content)? $content : self::toXPath($content);
			$domxpath = new DOMXPath($this->dom);
			if($context===null){
				$nodelist = $domxpath->query($xpath);
			}else{
				$context = new jQueryObject($jquery, $context);
				$context = $context->get(0);
				$nodelist = $domxpath->query($xpath, $context);
			}
			if(!$nodelist){
				return array();
			}
			if(!$nodelist->length){
				return array();
			}
			foreach($nodelist as $node){
				$nodes[] = $node;
			}
		}else{
			$nodes = $this->getElementsFromContent($content, $context);
		}
		return $nodes;
	}
	public function getElementsFromContent($content){
		$jquery = $this;
		$nodes = array();
		if(is_string($content)){
			$nodes = $jquery->parseHTML($content);
		}else
		if(is_array($content)){
			$nodes = $content;
		}else
		if($content instanceof self){
			$nodes = $content->stack;
		}else
		if($content instanceof DOMNodeList){
			foreach($content as $node){
				$nodes[] = $node;
			}
		}else
		if($content instanceof DOMDocument){
			$nodes = array($content);
		}else
		if($content instanceof DOMNode){
			$nodes = array($content);
		}
		return $nodes;
	}
	// Implement the identical functionality for filter and not
	public function winnow( $elements, $qualifier, $not=false ){
		$jquery = $this;
		
		$risSimple = '/^.[^:#\[\.,]*$/';
		if ( is_callable( $qualifier ) ) {
			return $jquery->grep( $elements, function( $elem, $i ) use($qualifier, $not) {
				return call_user_func($qualifier, $elem, $i, $elem ) !== $not;
			});
		}

		if ( $qualifier instanceof DOMNode ) {
			return $jquery->grep( $elements, function( $elem ) use($qualifier, $not) {
				return $elem->isSameNode($qualifier) !== $not;
			});
		}

		if ( is_string($qualifier) ) {
			if ( preg_match( $risSimple, $qualifier ) ) {
				return $jquery->filter( $qualifier, $elements, $not );
			}

			$qualifier = $jquery->filter( $qualifier, $elements );
		}

		return $jquery->grep( $elements, function( $elem ) use($qualifier, $not, $jquery) {
			return ( $jquery->arrayHasNode($elem, $qualifier) ) !== $not;
		});
	}
	public function arrayHasNode($elem, $elems){
		foreach($elems as $node){
			if($node->isSameNode($elem)){
				return true;
			}
		}
		return false;
	}
	public function __invoke($selector, $context=null){
		return new jQueryObject($this, $selector, $context);
	}
	public function __call($name, $args){
		if(isset($this->$name)){
			$callback = $this->$name;
			if(is_callable($callback)){
				if(method_exists($callback, 'bindTo')){
					$callback = $callback->bindTo($this);
				}
				return call_user_func_array($callback, $args);
			}
		}else{
			throw new Exception('Method '.$name.' does not exist in jQuery');
			//trigger_error('Method '.$name.' does not exist in jQuery', E_USER_ERROR);
		}
	}
}

