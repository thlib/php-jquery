<?php

namespace ThinkHTML\jQuery;

use \Iterator,\ArrayAccess,\Countable,\Exception;
use \DOMNodeList,\DOMNode;
use ThinkHTML\jQuery\jQuery;

// The object returned by jQuery()
class jQueryObject implements Iterator, ArrayAccess, Countable
{
	const TYPE_TEXT = 3;
	public $dom, $stack=array(), $context, $jquery, $version = '1.0.0';
	public function __construct(jQuery $jquery, $selector, $context=null)
	{
		$this->jquery = $jquery;
		$this->dom = $jquery->dom;
		$this->stack = $jquery->getElementsFromSelector($selector, $context);
		$that = $this;
		$fn = $jquery->fn;
		// Take an array of elements and push it onto the stack
		// (returning the new matched element set)
		$fn->pushStack = function($that, $elems) use ($jquery) {

			// Build a new jQuery matched element set
			// $ret = $jquery->merge( new jQueryObject($jquery), $elems );
			$ret = new jQueryObject($jquery, $elems);

			// Add the old object onto the stack (as a reference)
			$ret->prevObject = $that;
			$ret->context = $that->context;

			// Return the newly-formed element set
			return $ret;
		};
		$fn->reverse = function($that, $callback=null) use ($jquery)
		{
			krsort($that->stack);
			return $that;
		};
		$fn->rename = function($that, $tag_name) use ($jquery)
		{
			foreach($that->stack as $k=>$node){
				$that->stack[$k] = $that->renameNode($node, $tag_name);
			}
			return $that;
		};
		$fn->find = function($that, $selector, $context=null) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				$nodes = $jquery->getElementsFromSelector($selector, $el);
				foreach($nodes as $node){
					$collection[] = $node;
				}
			}
			return $that->pushStack($collection);
		};
		$fn->prepend = function($that, $content) use ($jquery)
		{
			$nodelist = $jquery->getElementsFromContent($content);
			foreach($that->stack as $el)
			{
				foreach($nodelist as $child){
					$child2 = $el->ownerDocument->importNode($child, true);
					if($el->firstChild){
						$el->insertBefore($child2, $el->firstChild);
					}else{
						$el->appendChild($child2);
					}
				}
			}
		};
		$fn->prependTo = function($that, $selector) use ($jquery)
		{
			$q = new jQueryObject($that->jquery, $selector);
			$q->prepend($that->stack);
			return $q;
		};
		$fn->append = function($that, $content) use ($jquery)
		{
			$nodelist = $jquery->getElementsFromContent($content);
			foreach($that->stack as $el)
			{
				foreach($nodelist as $child){
					$child2 = $el->ownerDocument->importNode($child, true);
					$el->appendChild($child2);
				}
			}
		};
		$fn->appendTo = function($that, $selector) use ($jquery)
		{
			$q = new jQueryObject($that->jquery, $selector);
			$q->append($that->stack);
			return $q;
		};
		$fn->before = function($that, $content) use ($jquery)
		{
			$nodelist = $jquery->getElementsFromContent($content);
			foreach($that->stack as $el)
			{
				foreach($nodelist as $child){
					$child2 = $el->ownerDocument->importNode($child, true);
					$el->parentNode->insertBefore($child2, $el);
				}
			}
		};
		$fn->insertBefore = function($that, $selector) use ($jquery)
		{
			$q = new jQueryObject($that->jquery, $selector);
			$q->before($that->stack);
			return $q;
		};
		$fn->after = function($that, $content) use ($jquery)
		{
			$nodelist = $jquery->getElementsFromContent($content);
			foreach($that->stack as $el)
			{
				foreach($nodelist as $child){
					$new = $el->ownerDocument->importNode($child, true);
					$that->nodeInsertAfter($new, $el);
				}
			}
		};
		$fn->insertAfter = function($that, $selector) use ($jquery)
		{
			$q = new jQueryObject($that->jquery, $selector);
			$q->after($that->stack);
			return $q;
		};
		$fn->replaceWith = function($that, $content) use ($jquery)
		{
			$removed = array();
			$selection = array();
			$nodelist = $jquery->getElementsFromContent($content); // TODO: specify if we want all the nodes, all the non whitespace nodes or all the elements
			foreach($that->stack as $el){
				foreach($nodelist as $node){
					$new = $el->ownerDocument->importNode($node, true);
					$selection[] = $new;
					$el->parentNode->insertBefore($new, $el);
				}
			}
			foreach($that->stack as $el){
				$removed[] = $el;
				$el->parentNode->removeChild($el);
			}
			$that->stack = $selection;
			
			return $that->pushStack($removed);
		};
		//TODO: replace selection to set or collection?
		$fn->replaceAll = function($that, $target) use ($jquery)
		{
			$nodelist = $jquery->getElementsFromSelector($target); //TODO: should not accept html content
			foreach($nodelist as $el){
				foreach($that->stack as $node){
					$new = $el->ownerDocument->importNode($node, true);
					$el->parentNode->replaceChild($new, $el);
				}
			}
			return $that;
		};
		$fn->remove = function($that) use ($jquery)
		{
			foreach($that->stack as $el)
			{
				$el->parentNode->removeChild($el);
			}
		};
		$fn->html = function($that) use ($jquery)
		{
			$args = func_get_args();
			$that = array_shift($args);
			if(isset($args[0])){
				// Set html
				$nodelist = $jquery->getElementsFromContent($args[0]);
				foreach($that->stack as $node)
				{
					$that->removeChildren($node);
					foreach ($nodelist as $child)
					{
						$child2 = $node->ownerDocument->importNode($child, true);
						$node->appendChild($child2);
					}
				}
				
			}else{
				// Get html
				$html = null;
				$node = isset($that->stack[0])?$that->stack[0]:null;
				if($node){
					$children = $node->childNodes;
					if($children instanceof DOMNodeList){
						foreach ($children as $child)
						{
							$html .= $node->ownerDocument->saveHTML($child);
						}
					}
				}
				return $html;
			}
		};
		$fn->text = function($that) use ($jquery)
		{
			$args = func_get_args();
			$that = array_shift($args);
			if(isset($args[0])){
				// Set text
				foreach($that->stack as $i=>$el)
				{
					$text = (is_callable($args[0]))? call_user_func($i, $args[0]): $args[0];
					$text_node = $el->ownerDocument->createTextNode($text);
					$that->removeChildren($el);
					$el->appendChild($text_node);
				}
				return $that;
			}else{
				// Get text
				$text = '';
				foreach($that->stack as $el)
				{
					$text .= $el->nodeValue;
				}
				return $text;
			}
		};
		
		//TODO: test if jquery selects attributes with prop()
		//TODO: attr('checked') https://api.jquery.com/prop/#prop2
		$fn->attr = function($that, $name) use ($jquery)
		{
			$name = strtolower($name);
			$args = func_get_args();
			$that = array_shift($args);
			foreach($that->stack as $el){
				$attrs = $that->getAttributeNames($el);
				$attr_name = isset($attrs[$name])?$attrs[$name]:$name;
				if(isset($args[1])){
					//set
					$el->setAttribute($attr_name, $args[1]);
				}else{
					//get
					if(isset($attrs[$name])){
						return $el->getAttribute($attr_name);
					}
					return null;
				}
			}
			if(isset($args[1])){
				return $that;
			}else{
				return null;
			}
		};
		// test jquery().prop('tagName','div');
		// https://api.jquery.com/prop/#prop2
		// get/set: checked, selected, value
		// get: selectedIndex, tagName, nodeName, nodeType, ownerDocument, defaultChecked, and defaultSelected
		$fn->prop = function($that, $name) use ($jquery)
		{
			$name = strtolower($name);
			$args = func_get_args();
			$that = array_shift($args);
			foreach($that->stack as $el){
				if(isset($args[1])){
					//set
					$attrs = $that->getAttributeNames($el);
					$attr_name = isset($attrs[$name])?$attrs[$name]:$name;
					if(in_array($name, array('checked','selected','value','disabled'))){
						// this "false" check exists in jquery, not sure that it makes sense
						if($args[1]===false || (bool)$args[1]){
							$value = $args[1];
							if(is_bool($value)){
								$value = $value?'true':'false';
							}
							if($name==='value'){
								$el->setAttribute($attr_name, $value);
							}else{
								$el->setAttribute($attr_name, $name);
							}
						}else{
							$el->removeAttribute($attr_name);
						}
					}else{
						$el->$name = $args[1]; // night throw errors, will let it
					}
				}else{
					//get
					if(in_array($name, array('checked','selected','value','disabled'))){
						$attrs = $that->getAttributeNames($el);
						if($name==='value'){
							return isset($attrs[$name])? $attrs[$name]:false;
						}else{
							return isset($attrs[$name])? true:false;
						}
					}else{
						if(isset($el->$name)){
							return $el->$name;
						}
					}
					return null;
				}
			}
			return $that;
		};
		$fn->val = function($that) use ($jquery)
		{
			$args = func_get_args();
			$that = array_shift($args);
			$radio_sets = array();
			foreach($that->stack as $el){
				if(isset($args[0])){
					$that->elementSetVal($el, $args[0]);
				}else{
					return $that->elementGetVal($el);
				}
			}
			if(!isset($args[1])){
				return null;
			}
			return $that;
		};
		$fn->addClass = function($that, $class_name) use ($jquery)
		{
			foreach($that->stack as $el){
				$that->nodeAddClass($el, $class_name);
			}
			return $that;
		};
		$fn->hasClass = function($that, $class_name) use ($jquery)
		{
			$el = isset($that->stack[0])?$that->stack[0]:null;
			return $that->nodeHasClass($el, $class_name);
		};
		$fn->removeClass = function($that, $class_name) use ($jquery)
		{
			foreach($that->stack as $el){
				$that->nodeRemoveClass($el, $class_name);
			}
			return $that;
		};
		$fn->toggleClass = function($that, $class_name, $switch=null) use ($jquery)
		{
			foreach($that->stack as $el){
				if($switch){
					if ( $switch ) {
						$that->nodeAddClass($el, $class_name );
					} else {
						$that->nodeRemoveClass($el, $class_name );
					}
				}else{
					if($that->nodeHasClass($el, $class_name)){
						$that->nodeRemoveClass($el, $class_name );
					}else{
						$that->nodeAddClass($el, $class_name );
					}
				}
			}
		};
		$fn->each = function($that, $callback) use ($jquery)
		{
			foreach($that->stack as $i=>$el){
				if(call_user_func($callback, $i, $el)===false){
					break;
				}
			}
		};
		$fn->param = function($that, $arr) use ($jquery)
		{
			if(defined('PHP_QUERY_RFC3986')){
				return http_build_query($arr, '', '&', PHP_QUERY_RFC3986);
			}else{
				return http_build_query($arr, '', '&');
			}
		};
		$fn->data = function($that) use ($jquery)
		{
			$args = func_get_args();
			$that = array_shift($args);
			$c = count($args);
			if($c===0){
				// data()
				if(isset($that->stack[0])){
					$el = $that->stack[0];
					if(!isset($el->data)){
						$el->data = self::elementGetDataAttributes($el);
					}
					return $el->data;
				}
				return array();
			}else
			if($c===1){
				// data(key)
				if(isset($that->stack[0])){
					$el = $that->stack[0];
					if(!isset($el->data)){
						$el->data = $that->elementGetDataAttributes($el);
					}
					$key = $args[0];
					return isset($el->data[$key])? $el->data[$key]: null;
				}
				return null;
			}else{
				foreach($that->stack as $el){
					// Get the data-* attributes
					if(!isset($el->data)){
						$el->data = $that->elementGetDataAttributes($el);
					}
					// data(key, val)
					$key = $args[0];
					$el->data[$key] = $args[1];
				}
				return $that;
			}
		};
		$fn->removeData = function($that) use ($jquery)
		{
			$args = func_get_args();
			$that = array_shift($args);
			foreach($that->stack as $el){
				if(isset($args[0])){
					if(isset($el->data) && isset($el->data[$args[0]])){
						unset($el->data[$args[0]]);
					}
				}else{
					unset($el->data);
				}
			}
		};
		$fn->size = function($that) use ($jquery)
		{
			return count($that->stack);
		};
		$fn->toArray = function($that) use ($jquery)
		{
			return $that->stack;
		};
		$fn->get = function($that) use ($jquery)
		{
			$args = func_get_args();
			$that = array_shift($args);
			if(isset($args[0])){
				return isset($that->stack[$args[0]])?$that->stack[$args[0]]:null;
			}else{
				return $that->stack;
			}
		};
		//had to rename clone to copy due to php syntax
		$fn->copy = function($that, $with_data=false) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				$collection[] = $el->cloneNode(true);
			}
			return $that->pushStack($collection);
		};
		$fn->index = function($that) use ($jquery)
		{
			$args = func_get_args();
			$that = array_shift($args);
			if(isset($args[0])){
				// return the index of the element in the collection
				$obj = $args[0];
				if($obj instanceof DOMNodeList){
					$obj = $obj->item(0);
				}
				if($obj instanceof DOMNode){
					foreach($that->stack as $i=>$el){
						if($obj->isSameNode($el)){
							return $i;
						}
					}
				}
			}else{
				// return index of first element in the selection relative to its siblings in the dom
				$obj = $that->stack[0];
				foreach($obj->parentNode->childNodes as $i=>$el){
					if($obj->isSameNode($el)){
						return $i;
					}
				}
			}
			return -1;
		};
		$fn->css = function($that) use ($jquery)
		{
			$x = array();
			$args = func_get_args();
			$that = array_shift($args);
			if(isset($args[0]) && is_array($args[0])){
				$x = $args[0];
			}else
			if(isset($args[0]) && isset($args[1])){
				$x[$args[0]] = $args[1];
			}
			foreach($that->stack as $el){
				$style = $that->nodeGetAttribute($el, 'style');
				$style = strtolower($style);
				$style = explode(';',$style);
				$style = array_filter($style);
				$x2 = array();
				foreach($style as $block){
					list($k, $v) = explode(':',$block);
					$k = trim($k);
					$v = trim($v);
					$x2[$k] = $v;
				}
				$x2 += $x;
				$style = array();
				foreach($x2 as $k=>$v){
					$style[] = $k.':'.$v;
				}
				$that->nodeSetAttribute($el, 'style', implode(';',$style));
			}
		};
		$fn->wrap = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->unwrap = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->wrapAll = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->wrapInner = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->detach = function($that) use ($jquery)
		{
			foreach($that->stack as $el){
				$el->parentNode->removeChild($el);
			}
			return $that;
		};
		//had to rename to clear because of php syntax
		//empty()
		$fn->clear = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->addBack = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->add = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->andSelf = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->first = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->last = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->eq = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->map = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->slice = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->error = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->outerHTML = function($that) use ($jquery)
		{
			$html = null;
			$node = isset($that->stack[0])?$that->stack[0]:null;
			if($node){
				$html = $node->ownerDocument->saveHtml($node);
			}
			return $html;
		};
		$fn->closest = function($that) use ($jquery)
		{
			$arr = array();
			foreach($that->stack as $el)
			{
				foreach($el->childNodes as $node)
				{
					$arr[] = $node;
				}
			}
			$that->stack = $arr;
			return $that;
		};
		$fn->contents = function($that) use ($jquery)
		{
			$arr = array();
			foreach($that->stack as $el)
			{
				foreach($el->childNodes as $node)
				{
					// Skip whitespace node
					if($node->nodeType === $that->TYPE_TEXT && empty($node->nodeValue)){
						continue;
					}
					$arr[] = $node;
				}
			}
			$that->stack = $arr;
			return $that;
		};
		$fn->children = function($that) use ($jquery)
		{
			$arr = array();
			foreach($that->stack as $el)
			{
				foreach($el->childNodes as $node)
				{
					// Skip text nodes
					if($node->nodeType === $that->TYPE_TEXT){
						continue;
					}
					$arr[] = $node;
				}
			}
			$that->stack = $arr;
			return $that;
		};
		$fn->filter = function($that, $selector=array()) use ($jquery)
		{
			return $that->pushStack( $jquery->winnow($that, $selector, false) );
		};
		$fn->not = function($that, $selector=array()) use ($jquery)
		{
			return $that->pushStack( $jquery->winnow($that, $selector, true) );
		};
		$fn->is = function($that, $selector=array()) use ($jquery)
		{
			// Whitespace characters http://www.w3.org/TR/css3-selectors/#whitespace
			$whitespace = "[\\x20\\t\\r\\n\\f]";
			$rneedsContext = "^" . $whitespace . "{*[>+~]|:(even|odd|eq|gt|lt|nth|first|last)(?:\\(" .
			$whitespace + "*((?:-\\d)?\\d*)" . $whitespace . "*\\)|)(?=[^-]|$)}i";
			return (bool)count($jquery->winnow(
				$that,

				// If this is a positional/relative selector, check membership in the returned set
				// so $("p:first").is("p:last") won't return true for a doc with two "p".
				is_string($selector) && preg_match($rneedsContext, $selector) ?
					$jquery( $selector ) :
					$selector,
				false
			));
		};
		
		//TODO: selector
		$fn->siblings = function($that) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				foreach($el->parentNode->childNodes as $child){
					if(!$el->isSameNode($child)){
						$collection[] = $child;
					}
				}
			}
			return $that->pushStack($collection);
		};
		$fn->parent = function($that) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				$collection[] = $node->parentNode;
			}
			return $that->pushStack($collection);
		};
		$fn->parents = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->parentsUntil = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->next = function($that) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				$next = $node->nextSibling;
				if($next){
					$collection[] = $next;
				}
			}
			return $that->pushStack($collection);
		};
		$fn->prev = function($that) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				$prev = $node->previousSibling;
				if($prev){
					$collection[] = $prev;
				}
			}
			return $that->pushStack($collection);
		};
		$fn->nextAll = function($that) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				$next = $node->nextSibling;
				while($next){
					$collection[] = $next;
					$next = $next->nextSibling;
				}
			}
			return $that->pushStack($collection);
		};
		$fn->prevAll = function($that) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				$prev = $node->previousSibling;
				while($prev){
					$collection[] = $prev;
					$prev = $prev->previousSibling;
				}
			}
			return $that->pushStack($collection);
		};
		$fn->prevUntil = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		$fn->nextUntil = function($that) use ($jquery)
		{
			throw new Exception('Not implemented');
		};
		// Get the closest next sibling that matches the selector
		$fn->nextClosest = function($that, $selector) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				$collection[] = $that->elementNext($el, $selector);
			}
			return $that->pushStack($collection);
		};
		// Get the closest previous sibling that matches the selector
		$fn->prevClosest = function($that, $selector) use ($jquery)
		{
			$collection = array();
			foreach($that->stack as $el){
				$collection[] = $that->elementPrevious($el, $selector);
			}
			return $that->pushStack($collection);
		};
		$fn->textContents = function($that, $include_whitespace_nodes=false) use ($jquery)
		{
			$text_nodes = array();
			foreach($that->stack as $el){
				$text_nodes = array_merge($text_nodes, $that->nodeGetTextChildNodes($el, $include_whitespace_nodes));
			}
			return $that->pushStack($text_nodes);
		};
		
		return $that;
	}
	
	
	//http://stackoverflow.com/questions/775904/how-can-i-change-the-name-of-an-element-in-dom
	public function renameNode($node, $tag_name)
	{
		$childnodes = array();
		foreach ($node->childNodes as $child){
			$childnodes[] = $child;
		}
		$newnode = $node->ownerDocument->createElement($tag_name);
		foreach ($childnodes as $child){
			$child2 = $node->ownerDocument->importNode($child, true);
			$newnode->appendChild($child2);
		}
		foreach ($node->attributes as $attr_name => $attr_node) {
			$newnode->setAttribute($attr_name, $attr_node);
		}
		$node->parentNode->replaceChild($newnode, $node);
		return $newnode;
	}
	
	public static function removeChildren($node)
	{
		foreach($node->childNodes as $el)
		{
			$el->parentNode->removeChild($el);
		}
	}
	//TODO: differentiate between attributes and properties!
	// Function to handle case insensitive getAttribtue and setAttribute
	public static function getAttributeNames($node){
		$attributes = array();
		foreach ($node->attributes as $attr) {
			$name = $attr->nodeName;
			$attributes[strtolower($name)] = $name; //NOTE: strtolower does not handle unicode by default. Override it in php config so that it does.
		}
		return $attributes;
	}
	
	public static function elementNext($node){
		$args = func_get_args();
		$has_filter = array_key_exists(1, $args);
		if(!$node){
			return null;
		}
		$next = $node->nextSibling;
		if(!$next){
			return null;
		}
		if($next->nodeType === 3){
			if($has_filter){
				return self::elementNext($next, $args[1]);
			}else{
				return self::elementNext($next);
			}
		}
		if($has_filter && !$this->pushStack($next)->is($args[1])){
			return null;
		}
		return $next;
	}
	public static function elementPrevious($node){
		$args = func_get_args();
		$has_filter = array_key_exists(1, $args);
		$filter = null;
	
		if(!$node){
			return null;
		}
		$prev = $node->previousSibling;
		if(!$prev){
			return null;
		}
		if($prev->nodeType === 3){
			if($has_filter){
				return self::elementPrevious($prev, $filter);
			}else{
				return self::elementPrevious($prev);
			}
		}
		if($has_filter && !$this->pushStack($prev)->is($args[1])){
			return null;
		}
		return $prev;
	}
	public static function elementLastChild($node){
		if(!$node){
			return null;
		}
		$last = $node->lastChild;
		if(!$last){
			return null;
		}
		if($last->nodeType === 3){
			return self::elementPrevious($last);
		}
		return $last;
	}
	
	# http://stackoverflow.com/questions/298750/how-do-i-select-text-nodes-with-jquery
	public static function nodeGetTextChildNodes($node, $include_whitespace_nodes=false){
		$text_nodes = array();
		if ($node->nodeType == 3) {
			if ($include_whitespace_nodes || !preg_match('/\S/', $node->nodeValue)) {
				$text_nodes[] = $node;
			}
		} else {
			foreach ($node->childNodes as $child) {
				$text_nodes = array_merge($text_nodes, self::nodeGetTextChildNodes($child, $include_whitespace_nodes));
			}
		}
		return $text_nodes;
	}
	public static function nodeGetAttribute($node, $name, $default=null){
		$attrs = self::getAttributeNames($node);
		if(!isset($attrs[$name])){
			return $default;
		}
		return $node->getAttribute($attrs[$name]);
	}
	public static function nodeSetAttribute($node, $name, $value){
		$attrs = self::getAttributeNames($node);
		$name = isset($attrs[$name])?$attrs[$name]:$name;
		return $node->setAttribute($name, $value);
	}
	public static function nodeHasAttribute($node, $name){
		$attrs = self::getAttributeNames($node);
		$name = isset($attrs[$name])?$attrs[$name]:$name;
		return isset($attrs[$name]);
	}
	public static function nodeRemoveAttribute($node, $name){
		$attrs = self::getAttributeNames($node);
		$name = isset($attrs[$name])?$attrs[$name]:$name;
		return $node->removeAttribute($name);
	}
	// get the selected value of input, select and textarea
	public function elementGetVal($el, $default=null){
		$tag_name = strtolower($el->nodeName);
		if('input'===$tag_name){ //TODO: test checkbox returned value if the checkbox is not checked
			return self::nodeGetAttribute($el, 'value');
		}else
		if('select'===$tag_name){
			$attrs = self::getAttributeNames($el);
			$vals = array();
			foreach($el->childNodes as $opt){
				$opt_attrs = self::getAttributeNames($opt);
				if(!isset($opt_attrs['selected'])){
					continue;
				}
				if(isset($attrs['multiple'])){
					$vals[] = self::nodeGetAttribute($opt, 'value', $opt->nodeValue);
				}else{
					return self::nodeGetAttribute($opt, 'value', $opt->nodeValue);
				}
			}
			if(isset($attrs['multiple'])){
				return $vals;
			}else{
				return null;//TODO: should return first child option!
			}
		}else
		if('textarea'===$tag_name){
			return $el->nodeValue;
		}
	}
	public function elementSetVal($el, $value){
		$jquery = $this->jquery;
		//TODO: accept function callback
		$tag_name = strtolower($el->nodeName);
		$values = $value;
		if(!is_array($values)){
			$values = array($values);
			$is_arr = false;
		}else{
			$value = array_shift($value);
			$is_arr = true;
		}
		if('input'===$tag_name){ //TODO: test checkbox returned value if the checkbox is not checked
			$type = strtolower(self::nodeGetAttribute($el, 'type', 'text'));
			if($is_arr && $type==='checkbox'){
				$tag_value = self::nodeGetAttribute($el, 'value', 'on');
				if(in_array($tag_value, $values)){
					self::nodeSetAttribute($el, 'checked', true);
				}
			}else
			if($is_arr && $type==='radio'){
				$tag_value = self::nodeGetAttribute($el, 'value', 'on');
				$name = self::nodeGetAttribute($el, 'name');
				//get closest form?, then find all elements with the same name and remove their checked
				if(!isset($radio_sets[$name])){
					$related = $jquery->getElementsFromSelector('[name='.$name.']');
					foreach($related as $n){
						self::nodeRemoveAttribute($n, 'checked'); //TODO: inefficient to keep doing this, but works
					}
					if($tag_value===$value){
						self::nodeSetAttribute($el, 'checked', true);
						$radio_sets[$name] = true;
					}
				}
			}else{
				self::nodeSetAttribute($el, 'value', $value);
			}
		}else
		if('select'===$tag_name){
			$attrs = self::getAttributeNames($el);
			foreach($el->childNodes as $opt){
				$opt_value = self::nodeGetAttribute($opt, 'value', $opt->nodeValue);
				if(in_array($opt_value, $values)){
					self::nodeSetAttribute($opt, 'selected', true);
					if(!isset($attrs['multiple'])){
						break;
					}
				}else{
					self::nodeRemoveAttribute($el, 'selected');
				}
			}
		}else
		if('textarea'===$tag_name){
			$el->nodeValue = $value;
		}
	}
	public static function nodeAddClass($node, $class_name){
		if($node instanceof DOMNode){
			$classes = array();
			if(self::nodeHasAttribute($node, 'class')){
				$classes = explode(' ', self::nodeGetAttribute($node, 'class'));
			}
			$classes[] = $class_name;
			self::nodeSetAttribute($node, 'class', implode(' ', $classes));
		}
	}
	public static function nodeHasClass($node, $class_name){
		if($el instanceof DOMNode){
			$classes = array();
			if(self::nodeHasAttribute($node, 'class')){
				$classes = explode(' ', self::nodeGetAttribute($node, $attrs['class']));
				return in_array($class_name, $classes);
			}
		}
		return false;
	}
	public static function nodeRemoveClass($node, $class_name){
		if($el instanceof DOMNode){
			$classes = array();
			if(self::nodeHasAttribute($node, 'class')){
				$classes = explode(' ', self::nodeGetAttribute($node, 'class'));
				$key = array_search($class_name, $classes);
				if($key!==false){
					unset($classes[$key]);
					self::nodeSetAttribute($node, 'class', implode(' ', $classes));
				}
			}
		}
	}
	//http://snipplr.com/view/2107/
	public static function nodeInsertAfter($new, $target){
		//target is what you want it to go after. Look for this elements parent.
		$parent = $target->parentNode;
	 
		//if the parents lastchild is the targetElement...
		if( $target->isSameNode($parent->lastChild) ) {
			//add the newElement after the target element.
			$parent->appendChild($new);
		} else {
			// else the target has siblings, insert the new element between the target and it's next sibling.
			$parent->insertBefore($new, $target->nextSibling);
		}
	}
	public function elementGetDataAttributes($el){
		$jquery = $this->jquery;
		// Get the data-* attributes
		$data = array();
		foreach($el->attributes as $attr){
			if (preg_match('/^data-/', $attr->name)) {
				if($jquery->options['data_camel_case']){
					$camel_case_name = preg_replace_callback('/-(.)/', function ($m) {
						return strtoupper($m[1]);
					}, substr($attr->name, 5));
					$data[$camel_case_name] = $attr->value;
				}else{
					$data[substr($attr->name, 5)] = $attr->value;
				}
			}
		}
		return $data;
	}
	
	// Make this thing iterable
	public function offsetUnset($offset) {
		//throw new Exception('Data in HTMLData must be altered outside the object');
        unset($this->stack[$offset]);
    }
	public function offsetSet($offset, $value) {
		//throw new Exception('Data in HTMLData must be altered outside the object');
        if (is_null($offset)) {
            $this->stack[] = $value;
        } else {
            $this->stack[$offset] = $value;
        }
    }
    public function offsetExists($offset) {
        return isset($this->stack[$offset]);
    }
    public function offsetGet($offset) {
		return isset($this->stack[$offset]) ? $this->stack[$offset] : null;
    }
	public function count(){
		return count($this->stack);
	}
	public function current(){
		return current($this->stack);
	}
	public function next(){
		return next($this->stack);
	}
	public function key(){
		return key($this->stack); //is this a good idea? looks like a bad idea!
	}
	public function valid(){
		return key($this->stack) !== null;
	}
	public function rewind(){
		return reset($this->stack);
	}
	
		
	/*
	http://www.php.net/manual/en/closure.bindto.php
	*/
	public function __call($name, $args){
		$q = $this->jquery;
		if(isset($q->fn->$name)){
			$callback = $q->fn->$name;
			if(is_callable($callback)){
				if(method_exists($callback, 'bindTo')){
					$callback = $callback->bindTo($this);
				}
				array_unshift($args, $this); // Inject $this for php 5.3 compatibility
				return call_user_func_array($callback, $args);
			}
		}else{
			throw new RuntimeException('Method '.$name.' does not exist in jQueryObject', E_USER_ERROR);
		}
	}
}

