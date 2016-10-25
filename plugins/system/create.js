/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3 - 3.0
 * @subpackage plg_system_osticky
 * @version 2.1: create.js
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/

var plg_osticky2_ticket = plg_osticky2_ticket || {
	uri : '',
	
	check_modifier : function(event) {},

	init : function(uri, modifier) {
		this.uri = uri;
		
		switch (modifier) {
		case 'control+click':
			this.check_modifier = function(event) {
				return event.control && !event.alt && !event.shift;
			};
			break;
		case 'alt+click':
			this.check_modifier = function(event) {
				return !event.control && event.alt && !event.shift;
			};
			break;
		case 'shift+click':
			this.check_modifier = function(event) {
				return !event.control && !event.alt && event.shift;
			};
			break;
		case 'control+alt+click':
			this.check_modifier = function(event) {
				return event.control && event.alt && !event.shift;
			};
			break;
		case 'shift+alt+click':
			this.check_modifier = function(event) {
				return !event.control && event.alt && event.shift;
			};
			break;
		case 'shift+control+click':
			this.check_modifier = function(event) {
				return event.control && !event.alt && event.shift;
			};
			break;
		default:
			this.check_modifier = function(event) {
				return false;
			};
		}
	},

	create : function(el, event) {
		if (this.check_modifier(event) == false) {
			return;
		}
		// If SqueezeBox is already open just exit
		if(SqueezeBox.isOpen) {
			return;
		}
		event.stop();

		if ((oldInfo = document.id('__plg_osticky_info')) != null) {
			oldInfo.destroy();
		}
		
		var els = el.getParents();
		els.unshift(el);

		var fingerPrint = this.buildFingerPrint(els);

		els.reverse();
		var DOMpathStr = "\n";
		els.each(function(el) {
			DOMpathStr += "{" + el.get("tag")
					+ (el.get("id")		? " id: "		+ el.get("id")		: "")
					+ (el.get("class")	? " class: "	+ el.get("class")	: "")
					+ (el.get("style")	? " style: "	+ el.get("style")	: "")
				+ " /}\n";
		});
		var HTMLstr = "\n" + el.get("html").clean();
		HTMLstr = HTMLstr.replace(/}\s*{/g, "}\n{");

		if (HTMLstr.length > 2000) {
			HTMLstr = HTMLstr.substr(0, 2000);
			HTMLstr += "[...]";
		}
		var ElementStr = "{" + el.get("tag")
					+ (el.get("id")					? " id: "		+ el.get("id")		: "")
					+ (el.retrieve("class_store")	? " class: "	+ el.get("class")	: "")
					+ (el.retrieve("style_store")	? " style: "	+ el.get("style")	: "")
				+ "}\ninnerHTML:" + HTMLstr;
		var infoField = new Element("input", {
			id : "__plg_osticky_info",
			type : "hidden",
			value : JSON.encode({
				"url" : this.uri.replace(/&amp;/g, '&'), // unify uri to unsafe form
				"fingerPrint" : fingerPrint,
				"DOM Path" : DOMpathStr,
				"Element" : ElementStr
			})
		});
		document.body.adopt(infoField);

		// Reload page on Box Close event
		SqueezeBox.presets.onClose = function() { if(window.parent) window.parent.location.reload(); };
		SqueezeBox
				.open(
						"index.php?option=com_osticky2&view=ticket_modal&tmpl=component&infoid=__plg_osticky_info"
								+ "&function=plg_osticky2_ticket.close",
						{
							handler : "iframe",
							size : {
								x : 650,
								y : 600
							}
						});
	},

	close : function(reload) {
		SqueezeBox.close();
	},
	
	buildFingerPrint : function(els) {
		var fingerPrint = {
			baseId : null,
			path : []
		};
		if (els.length) {
			stopEach = {};
			try {
				els.each(function(el) {
					if ((id = el.getProperty("id")) != null) {
						fingerPrint.baseId = id;
						throw stopEach;
					}
					var thisTag = el.get('tag');
					var thisClass = el.retrieve('class_store');
					/* we use the value stored on mouseenteer event
					 * (so that if element's class changes on mouseenter
					 * it will not be incuded in the fingerprint)
					 * Fallback to actual class value if no value stored
					 */
					if(!thisClass ) {
						thisClass = el.get('class') || '';
					}
					var thisFP = {
						"tag" : thisTag,
						"class" : thisClass != null ? thisClass : "",
						"nthChild" : 0
					};

					var prevSiblings = el.getAllPrevious(thisTag);

					prevSiblings.each(function(ps, index) {
						// check if has right class
						var psClass = ps.get('class') || '';
						if (psClass != thisClass) {
							prevSiblings[index] = null;
						}
					});
					prevSiblings = prevSiblings.clean();

					thisFP.nthChild = prevSiblings.length;
					fingerPrint.path.push(thisFP);
				});
				// no element with id was not found in path
				// so baseId is left null, and will be assumed 'html'
				// actually path includes 'html' as last element
				// we have to pop it out...
				fingerPrint.path.pop();
			} catch (e) {
				if (e != stopEach)
					throw e;
			}
		}
		fingerPrint.path.reverse();
		return JSON.encode(fingerPrint);
	}
};
