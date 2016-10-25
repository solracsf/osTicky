/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: attachments.js
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
var attachments = {
	options : {
		files_limit : 0
	},
	
	init : function(options) {
		var $j = jQuery.noConflict();
		$j.extend(this.options, options);
		
		$j('input.multi').multifile({
			max_uploads: this.options.files_limit,
			file_types: this.options.file_types,
			max_file_size: this.options.max_file_size
		});
		
	}
};