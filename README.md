I recommend using this instead: https://github.com/technosophos/querypath

PHP jQuery
==========

PHP port of jQuery 2.0
pre-alpha version

I disliked phpQuery so I made a simple 1 to 1 port of jQuery 2.0 Source  
It started as a couple of helper functions and grew to what it is now

It is in an unfinished state because otherwise I would have never published it.  
Lots of stuff could be broken, so try it first. I provide no warranty what so ever.

Dependencies
-----------

- [Symfony CSS3 to XPath converter](https://github.com/symfony/CssSelector)
- [HTML5Lib](https://github.com/html5lib/html5lib-php)

Load it using autoload
-----------

Put the classes in /vendor/ThinkHTML/jQuery/ and use the [PSR autoloader](https://gist.github.com/jwage/221634)

    $loader = require 'vendor/autoload.php';

or load it using require
-----------

    require '/path/to/HTML5Parser.php';
    require '/path/to/JQuery.php';
    require '/path/to/JQueryObject.php';
    require '/path/to/SymfonyCSSSelector.php';
    SymfonyCSSSelector::import('/vendor/Symfony/Component/CssSelector/');

Example
-----------

    use ThinkHTML\jQuery\HTML5Parser;
    $options = array('default_encoding'=>'utf-8');
    $parser = new HTML5Parser($options);
    $val = null;
    $parser->parse($html, function($document, $jquery) use(&$val){
      $val = $jquery('input')->prop('disabled',true)->val();
      $nodes_array = $jquery->parseHTML('<div>');
    });
    
You can use the jquery wrapper separately
----------

You don't have to use HTML5Parser to use the jQuery class

    $dom = new \DOMDocument;
    $dom->loadHTML('<div></div>');
    $jquery = new jQuery($dom);
    $jquery('div')->append('<span>Hello world</span>');

Selector
----------

starts with `<` = html  
starts with `/` (or any of the xpath keywords) = xpath  
everything else = css3  

    $jquery('.myclass'); // selects all elements with the class name "myclass" with CSS
    $jquery('descendant::*[@id="myid"]'); // selects all elements with the id "myid" with XPath
    $jquery('<div></div>'); // creates a div element in the current document (but does not attach it)
    $document->getElementsById('myid'); // selects elements with id "myid" with a DOMDocument selector

Options
----------

    $options = array(
      'encoding'=>'utf-8', // force utf-8 encoding
      'default_encoding'=>'utf-8', // set fallback encoding if it is not defined in the document
      'data_camel_case'=>true, // convert data-* attributes when using $jquery().data() according to w3c spec 
    );
    $parser = new HTML5Parser($options);

- [jQuery Doc on data()](http://api.jquery.com/data/#data-html5)

Plugins / Extensions
----------

[jQuery function](http://api.jquery.com/jquery.each/) in JavaScript

    jQuery.each

[jQuery Collection function](http://api.jquery.com/each/) in JavaScript

    jQuery().each

###jQuery Collection plugin

attach

    <script>
    jQuery.fn.color = function(color){
        this.css( "color", color );
    };
    </scrpt>
    
use

    <script>
    jQuery(function(){
        jQuery('a').color('red');
    });
    </script>
    
###PHP jQuery Collection plugin

attach

    $parser->extend(function($dom, $jquery){
        $jquery->fn->color = function($that, $color){
            $that->css( "color", $color);
        };
    });

use

    $parser->parse($html, function($dom, $jquery){
        $jquery('a')->color('red');
    });

In order to support php5.3 the first argument passed to the jquery collection extension function will be an instance of the current jquery collection object (called `$that` in the example below).  
All the other arguments are passed to the function are passed after `$that` (as normal)  
While the jQuery class extensions do not need $that

    $htmldoc = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><h1>Error none</h1><p>Page found</p></body></html>';
    $parser = new HTML5Parser($htmldoc);
    $parser->error(function($err){
    	// $err is an Exception
    });
    // Example plugins
    $parser->extend(function($dom, $q){
      // jQuery fn extension
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
    	// jQuery class extension
    	$q->sum = function() use($q){
    		return array_sum(func_get_args());
    	};
    });
    $parser->parse($html, function($document, $jquery){
    	// $document holds an instance of DOMDocument
    	// $jquery holds an instance of jQuery
    	// Run utility functions with $jQuery->parseHTML($data, $context, $keep_scripts);
    	// Query the DOM with CSS3 and manipulate the selection: $jquery('.class-name')->attr('rel', 'external');
    	// Query the DOM with XPATH and manipulate the selection: $jquery('descendant::select')->val('');
    	// Get the <body> content html: $jquery('body')->html();
    });

NOTES
----------

- Works with unicode (was surprisingly hard to achieve)
- Supports working with multiple documents at the same time, even within each others callbacks
- `HTML5Parser::parse()` creates a context for the jquery object to work with

TODO
----------

- Test setting attribute on detached elements
- Add remaining functions
- Add it to composer
- Add unit tests
- Can't use some forms of xpath when searching within a context, added option to disable css and treat everything as xpath
- Simplify code by duplicating the functions attached to fn in jquery, for example `$.after($node, $node2);`
- $jquery()->filter(); not working properly
- Implement `$keep_scripts` param in `parseHTML($html, $keep_scripts)`
- Make the css_converter customizable  

        $options = array('css_converter'=>'SymfonyCSSSelector::toXPath');
        $parser = new HTML5Parser($options);


