/**
 * Vidsoe Helper
 *
 * A collection of useful methods for your WordPress plugins and themes.
 *
 * @author Vidsoe
 * @copyright Vidsoe
 * @license GPL2 https://www.gnu.org/licenses/gpl-2.0.html
 * @link https://github.com/vidsoe/helper
 * @version 0.8.30
 *
 * Do not forget to rename 'your_namespace' to whatever you want!
 */
var your_namespace = {

    // ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

    /**
     * @return string
     */
	add_query_arg: function(key, value, url){
		'use strict';
        var $this = this, a = {}, href = '';
		a = $this.get_a(url);
        if(a.protocol){
            href += a.protocol + '//';
        }
        if(a.hostname){
            href += a.hostname;
        }
        if(a.port){
            href += ':' + a.port;
        }
        if(a.pathname){
            if(a.pathname[0] !== '/'){
                href += '/';
            }
            href += a.pathname;
        }
        if(a.search){
            var search = [], search_object = $this.parse_str(a.search);
            jQuery.each(search_object, function(k, v){
                if(k != key){
                    search.push(k + '=' + v);
                }
            });
            if(search.length > 0){
                href += '?' + search.join('&') + '&';
            } else {
                href += '?';
            }
        } else {
            href += '?';
        }
        href += key + '=' + value;
        if(a.hash){
            href += a.hash;
        }
        return href;
	},

    /**
     * @return string
     */
	add_query_args: function(args, url){
		'use strict';
        var $this = this, a = {}, href = '';
		a = $this.get_a(url);
        if(a.protocol){
            href += a.protocol + '//';
        }
        if(a.hostname){
            href += a.hostname;
        }
        if(a.port){
            href += ':' + a.port;
        }
        if(a.pathname){
            if(a.pathname[0] !== '/'){
                href += '/';
            }
            href += a.pathname;
        }
        if(a.search){
            var search = [], search_object = $this.parse_str(a.search);
            jQuery.each(search_object, function(k, v){
                if(!(k in args)){
                    search.push(k + '=' + v);
                }
            });
            if(search.length > 0){
                href += '?' + search.join('&') + '&';
            } else {
                href += '?';
            }
        } else {
            href += '?';
        }
        jQuery.each(args, function(k, v){
            href += k + '=' + v + '&';
        });
		href = href.slice(0, -1);
        if(a.hash){
            href += a.hash;
        }
        return href;
	},

    /**
     * @return object
     */
	get_a: function(url){
		'use strict';
        var $this = this, a = document.createElement('a');
        if('undefined' !== typeof(url) && '' !== url){
			a.href = url;
        } else {
            a.href = jQuery(location).attr('href');
        }
        return a;
	},

    /**
     * @return string
     */
	get_query_arg: function(key, url){
		'use strict';
        var $this = this, search_object = {};
        search_object = $this.get_query_args(url);
        if('undefined' !== typeof(search_object[key])){
			return search_object[key];
		}
		return '';
	},

    /**
     * @return object
     */
	get_query_args: function(url){
		'use strict';
        var $this = this, a = {};
		a = $this.get_a(url);
        if(a.search){
            return $this.parse_str(a.search);
        }
        return {};
	},

    /**
     * @return string
     */
    page_visibility_event: function(){
        'use strict';
        var $this = this, visibilityChange = '';
        if(typeof document.hidden !== 'undefined'){ // Opera 12.10 and Firefox 18 and later support
            visibilityChange = 'visibilitychange';
        } else if(typeof document.webkitHidden !== 'undefined'){
            visibilityChange = 'webkitvisibilitychange';
        } else if(typeof document.msHidden !== 'undefined'){
            visibilityChange = 'msvisibilitychange';
        } else if(typeof document.mozHidden !== 'undefined'){ // Deprecated
            visibilityChange = 'mozvisibilitychange';
        }
        return visibilityChange;
    },

    /**
     * @return string
     */
    page_visibility_state: function(){
        'use strict';
        var $this = this, hidden = '';
        if(typeof document.hidden !== 'undefined'){ // Opera 12.10 and Firefox 18 and later support
            hidden = 'hidden';
        } else if(typeof document.webkitHidden !== 'undefined'){
            hidden = 'webkitHidden';
        } else if(typeof document.msHidden !== 'undefined'){
            hidden = 'msHidden';
        } else if(typeof document.mozHidden !== 'undefined'){ // Deprecated
            hidden = 'mozHidden';
        }
        return document[hidden];
    },

    /**
     * @return object
     */
	parse_str: function(str){
		'use strict';
        var $this = this, i = 0, search_object = {}, search_array = str.replace('?', '').split('&');
        for(i = 0; i < search_array.length; i ++){
            search_object[search_array[i].split('=')[0]] = search_array[i].split('=')[1];
        }
        return search_object;
	},

    /**
     * @return object|string
     */
	parse_url: function(url, component){
		'use strict';
        var $this = this, a = {}, components = {}, keys = ['protocol', 'hostname', 'port', 'pathname', 'search', 'hash'];
        a = $this.get_a(url);
        if(typeof component === 'undefined' || component === ''){
            jQuery.map(keys, function(c){
                components[c] = a[c];
            });
            return components;
        } else if(jQuery.inArray(component, keys) !== -1){
            return a[component];
        } else {
            return '';
        }
	},

    /**
     * @return int
     */
	rem_to_px: function(count){
		'use strict';
        var $this = this, unit = jQuery('html').css('font-size');
    	if(typeof count !== 'undefined' && count > 0){
    		return (parseInt(unit) * count);
    	} else {
    		return parseInt(unit);
    	}
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

};
