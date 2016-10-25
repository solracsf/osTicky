/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3 - 3.0
 * @subpackage plg_system_osticky
 * @version 2.1: note.js
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/

var plg_osticky2_note = {
	data : [],
	options : {
		'toggle_visibility' : 'control+alt+h'
	},
	init_done : false,

	init : function(data, options) {
		if (!this.init_done) {
			try {
				this.data = data;
				this.options = Object.merge(this.options, options || {});
				window.addEvent('keydown:keys(' + this.options.toggle_visibility + ')',
						plg_osticky2_note.toggle);
				window.setInterval(function(){ plg_osticky2_note.update(); }, 500);
				this.init_done = true;
			} catch (e) {
				this.data = [];
				this.init_done = false;
				alert(e);
				return;
			}
		}
	},
	
	show : function() {
		if (this.init_done) {
			var a = this.data;
			var baseId = a.fp.baseId;
			var path = a.fp.path;

			var targetEl = null;
			if (baseId != null) {
				// we have the base id - so find the element
				targetEl = document.id(baseId);
			} else {
				targetEl = document.html;
				// here the first element if path will always be 'html'
				// effective path must start from html children
			}

			var stopEach = {};
			try {
				path.each(function(props) {
					var children = targetEl.getChildren(props.tag);

					children.each(function(cl, index) { // check if has right class
						var clClass = cl.get('class');
						if (clClass == null) {
							clClass = '';
						}
						if (clClass != props['class']) {
							children[index] = null;
						}
					});
					children = children.clean();

					if (children.length <= props.nthChild) {
						throw stopEach;
					}

					targetEl = children[props.nthChild];
				});
			} catch (stopEach) {
				// target element not found on page
				targetEl = null;
			}

			var aElWrapper = new Element("div", {
				"id" : "plg_osticky2_note",
				"class" : "plg_osticky2_note"+(a.isanswered != 0 ? ' answered' : '')
			});
			var aElSubject = new Element("div", {
				"html" : "<a href='"+a.link+"'>"+'#'+a.ticketID+"</a>: "+a.subject,
				"class" : "plg_osticky2_note_subject"
			});
			var closeBtn = new Element("a", {
				"href": "#",
				"html": '&nbsp;',
				"onclick": "plg_osticky2_note.hide();return false;",
				"class": "plg_osticky2_note_close"
			});
			
			var aElActivity = new Element("div", {
				"html" : (a.isanswered != 0 ? 
					Joomla.JText._('PLG_OSTICKY_TICKET_LASTRESPONSE')+a.lastresponse : 
					Joomla.JText._('PLG_OSTICKY_TICKET_LASTMESSAGE')+a.lastmessage),
				"class" : "plg_osticky2_note_activity"
			});
			var statusName, statusDate;
			if(a.status == 'open') {
				if(a.reopened) {
					statusName = Joomla.JText._('PLG_OSTICKY_TICKET_REOPENED');
					statusDate = a.reopened;					
				} else {
					statusName = Joomla.JText._('PLG_OSTICKY_TICKET_CREATED');
					statusDate = a.created;										
				}
			} else {
				statusName = Joomla.JText._('PLG_OSTICKY_TICKET_CLOSED');
				statusDate = a.closed;
			}
			var aElStatus = new Element("div", {
				"html" : statusName + ' <span class="date">' + statusDate + '</span>',
				"class" : "plg_osticky2_note_status_"+(a.status == 'open' ? "open" : "closed")
			});
			aElWrapper.adopt(aElSubject);
			aElWrapper.adopt(closeBtn);
			aElWrapper.adopt(aElActivity);
			aElWrapper.adopt(aElStatus);
			
			document.body.adopt(aElWrapper);
			
			if (targetEl != null && targetEl.isVisible()) {
				// class to find target on page
				targetEl.addClass('plg_osticky_target');
				// class to show border around target element
				targetEl.addClass('plg_osticky_target_show');
			}
		}
	},

	update : function() {
		var note = $('plg_osticky2_note');
		
		if(note && note.isDisplayed()) {
			var targetEl = $$('.plg_osticky_target').pop();
			// last check is for body tag - never position note
			// at bottom of body because of infinite scroll effect
			if(targetEl && targetEl.isVisible() && targetEl != document.body) {
				note.position({
					"relativeTo" : targetEl,
					"position" : "bottomRight"
				});
				note.removeClass('floating').addClass('sticky');
			} else {
				note.position({
					"relativeTo" : document.body,
					"position" : "topLeft"
				});
				note.removeClass('sticky').addClass('floating');
			}
		}
	},

	toggle : function() {
		$('plg_osticky2_note').toggle();
		$$('.plg_osticky_target').toggleClass('plg_osticky_target_show');
	},
	hide : function() {
		$('plg_osticky2_note').hide();
		$$('.plg_osticky_target').removeClass('plg_osticky_target_show');
	}
};
