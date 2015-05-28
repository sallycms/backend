/**
 * SallyCMS - JavaScript-Bibliothek
 */

/*global window:false, screen:false, document:false, navigator:false, Modernizr:false, jQuery:false, confirm:false, escape:false*/
/*jshint smarttabs:true */

var sly = sly || {};

(function($, sly, win, undef) {
	/////////////////////////////////////////////////////////////////////////////
	// Popups

	var openPopups = [], tokenName = 'sly-csrf-token';

	sly.Popup = function(name, url, posx, posy, width, height, extra) {
		// ensure names are somewhat unique
		name += (new Date()).getTime();

		// expand relative url to absolute. hopefully helps IE10
		if (url.substr(0, 2) === './') {
			var base = $('base').attr('href');
			if (base) {
				url = base + (base.substr(base.length - 1) === '/' ? '' : '/') + url.substr(2);
			}
		}

		this.name = name;
		this.url  = url;
		this.obj  = win.open(url, name, 'width='+width+',height='+height+extra);

		openPopups[name] = this;

		this.close = function() {
			this.obj.close();
		};

		this.setGlobal = function(name, value) {
			this.obj[name] = value;
		};
	};

	sly.closeAllPopups = function() {
		// @edge more comfortable popups
		// for (var name in openPopups) {
		// 	if (openPopups.hasOwnProperty(name)) {
		// 		openPopups[name].close();
		// 	}
		// }

		$.magnificPopup.close();
	};

	sly.openCenteredPopup = function(name, link, width, height, extra) {
		if (width === 0)  width  = 550;
		if (height === 0) height = 400;

		if (extra === undef) {
			extra = ',scrollbars=yes,toolbar=no,status=yes,resizable=yes';
		}

		var posx = parseInt((screen.width-width) / 2, 10);
		var posy = parseInt((screen.height-height) / 2, 10) - 24;

		return new sly.Popup(name, link, posx, posy, width, height, extra);
	};

	/////////////////////////////////////////////////////////////////////////////
	// Mediapool

	sly.openMediapool = function(subpage, value, callback, filetypes, categories) {
		var controller = 'mediapool', args = {args: {}};

		NProgress.start();

		if (value) {
			controller += '_detail';
			args.file_name = value;
		}
		else if (subpage && (subpage !== 'detail' || value)) {
			controller += '_' + subpage;
		}

		if (callback) {
			args.callback = callback;
		}

		if ($.isArray(filetypes) && filetypes.length > 0) {
			args.args.types = filetypes.join('|');
		}

		if ($.isArray(categories) && categories.length > 0) {
			args.args.categories = categories.join('|');
		}

		// @edge more comfortable popups
		$.magnificPopup.open({
			type: 'iframe',
			preloader: false,
			showCloseBtn: false,
			// closeMarkup: '<button title="%title%" class="mfp-close"></button>',
			items: {
				src: sly.getUrl(controller, null, args)
			}
		});

		// return sly.openCenteredPopup('slymediapool', sly.getUrl(controller, null, args), 857, 600);
	};

	sly.openLinkmap = function(value, callback, articletypes, categories) {
		var controller = 'linkmap', args = {args: {}};

		NProgress.start();

		if (value) {
			args.category_id = value;
		}

		if (callback) {
			args.callback = callback;
		}

		if ($.isArray(articletypes) && articletypes.length > 0) {
			args.args.types = articletypes.join('|');
		}

		if ($.isArray(categories) && categories.length > 0) {
			args.args.categories = categories.join('|');
		}

		// @edge more comfortable popups
		$.magnificPopup.open({
			type: 'iframe',
			preloader: false,
			showCloseBtn: false,
			// closeMarkup: '<button title="%title%" class="mfp-close"></button>',
			items: {
				src: sly.getUrl(controller, null, args)
			}
		});

		// return sly.openCenteredPopup('slylinkmap', sly.getUrl(controller, null, args), 760, 600);
	};

	/////////////////////////////////////////////////////////////////////////////
	// Helper

	sly.inherit = function(subClass, baseClass) {
		var tmpClass = function() {};
		tmpClass.prototype = baseClass.prototype;
		subClass.prototype = new tmpClass();
	};

	var getCallbackName = function(base) {
		return base + (new Date()).getTime();
	};

	var readLists = function(el, name) {
		var values = ((el.data(name) || '')+'').split('|'), len = values.length, i = 0, res = [];

		for (; i < len; ++i) {
			if (values[i].length > 0) {
				res.push(values[i]);
			}
		}

		return res;
	};

	/////////////////////////////////////////////////////////////////////////////
	// Abstract Widget

	sly.AbstractWidget = function(elem) {
		this.element    = $(elem);
		this.valueInput = this.element.find('input.value');
		this.nameInput  = this.element.find('input.name');

		// register events
		var icons = this.element.find('.sly-icons');
		icons.delegate('a.fct-open',   'click', $.proxy(this.onOpen,   this));
		icons.delegate('a.fct-add',    'click', $.proxy(this.onAdd,    this));
		icons.delegate('a.fct-delete', 'click', $.proxy(this.onDelete, this));
	};

	sly.AbstractWidget.prototype = {
		getValue: function() {
			return this.valueInput.val();
		},

		setValue: function(identifier, title) {
			this.valueInput.val(identifier);
			this.nameInput.val(title);

			// notify listeners
			this.valueInput.change();

			return true; // signalize the popup to close itself
		},

		clear: function() {
			this.setValue('', '');
		},

		onOpen: function() {
			return false;
		},

		onAdd: function() {
			return false;
		},

		onDelete: function() {
			this.clear();
			return false;
		}
	};

	/////////////////////////////////////////////////////////////////////////////
	// Media Widgets

	sly.MediaWidget = function(elem) {
		sly.AbstractWidget.call(this, elem);

		this.filetypes  = readLists(this.element, 'filetypes');
		this.categories = readLists(this.element, 'categories');
	};

	sly.inherit(sly.MediaWidget, sly.AbstractWidget);

	sly.MediaWidget.prototype.onOpen = function() {
		var cb = getCallbackName('slymediawidget');
		sly.openMediapool('detail', this.getValue(), cb, this.filetypes, this.categories);
		win[cb] = $.proxy(this.setValue, this);
		return false;
	};

	sly.MediaWidget.prototype.onAdd = function() {
		var cb = getCallbackName('slymediawidget');
		sly.openMediapool('upload', '', cb, this.filetypes, this.categories);
		win[cb] = $.proxy(this.setValue, this);
		return false;
	};

	/////////////////////////////////////////////////////////////////////////////
	// Link Widgets

	sly.LinkWidget = function(elem) {
		sly.AbstractWidget.call(this, elem);

		this.articletypes = readLists(this.element, 'articletypes');
		this.categories   = readLists(this.element, 'categories');
	};

	sly.inherit(sly.LinkWidget, sly.AbstractWidget);

	sly.LinkWidget.prototype.onOpen = function() {
		var catID = this.element.data('catid'), cb = getCallbackName('slylinkwidget');

		sly.openLinkmap(catID, cb, this.articletypes, this.categories);
		win[cb] = $.proxy(this.setValue, this);

		return false;
	};

	/////////////////////////////////////////////////////////////////////////////
	// Generic lists

	sly.AbstractListWidget = function(elem) {
		this.element = $(elem);
		this.input   = this.element.find('input[type=hidden]');
		this.list    = this.element.find('select');
		this.min     = this.element.data('min') || 0;
		this.max     = this.element.data('max') || -1;

		// register events
		var icons = this.element.find('.sly-icons.move');
		icons.delegate('a', 'click', $.proxy(this.onMove, this));

		icons = this.element.find('.sly-icons.edit');
		icons.delegate('a.fct-open',   'click', $.proxy(this.onOpen,   this));
		icons.delegate('a.fct-add',    'click', $.proxy(this.onAdd,    this));
		icons.delegate('a.fct-delete', 'click', $.proxy(this.onDelete, this));
	};

	sly.AbstractListWidget.prototype = {
		getElements: function() {
			var options = this.list.find('option');
			var result  = [];

			for (var i = 0, len = options.length; i < len; ++i) {
				result.push(options[i].value);
			}

			return result;
		},

		getCount: function() {
			return this.list.find('option').length;
		},

		getSelected: function() {
			var selected = this.list.find('option:selected');
			return selected.length ? selected.val() : null;
		},

		clear: function() {
			this.list.find('option').remove();
			this.input.val('').change();
		},

		addValue: function(identifier, title) {
			var count = this.getCount(), max = this.max;

			if (count === max) {
				return true; // close the popup
			}

			this.list.append($('<option>').val(identifier).text(title));
			this.createList();

			var full = (count+1) === max;

			if (full) {
				this.element.addClass('at-max');
			}

			this.element.toggleClass('at-min', (count+1) <= this.min);

			// close if the maximum number of elements is reached
			return full;
		},

		onDelete: function() {
			var selected = this.list.find('option:selected');

			if (selected.length === 0) {
				return false;
			}

			// only delete as many elements as we are allowed to
			var possible = this.getCount() - this.min;

			if (possible === 0) {
				return false;
			}

			selected = selected.slice(0, possible);

			// find the element to select
			var toSelect = selected.next();

			if (toSelect.length === 0) {
				toSelect = selected.prev();
			}

			// remove element and mark the next/prev one
			selected.remove();
			toSelect.prop('selected', true);

			// update classes
			this.element.removeClass('at-max').toggleClass('at-min', this.getCount() === this.min);

			// re-create the <input>
			this.createList();

			// done
			return false;
		},

		onMove: function(event) {
			var direction = event.target.className.replace('fct-', '');
			var list      = this.list;
			var selected  = list.find('option:selected');

			if (selected.length === 0) {
				return false;
			}

			if (direction === 'top') {
				list.prepend(selected);
			}
			else if (direction === 'up') {
				selected.prev().insertAfter(selected);
			}
			else if (direction === 'down') {
				selected.next().insertBefore(selected);
			}
			else if (direction === 'bottom') {
				list.append(selected);
			}

			this.createList();
			return false;
		},

		createList: function() {
			this.input.val(this.getElements().join(',')).change();
		},

		onOpen: function() {
			return false;
		},

		onAdd: function() {
			return false;
		}
	};

	/////////////////////////////////////////////////////////////////////////////
	// Medialist Widgets

	sly.MedialistWidget = function(elem) {
		sly.AbstractListWidget.call(this, elem);

		this.filetypes  = readLists(this.element, 'filetypes');
		this.categories = readLists(this.element, 'categories');
	};

	sly.inherit(sly.MedialistWidget, sly.AbstractListWidget);

	sly.MedialistWidget.prototype.onOpen = function() {
		if (this.getCount() === this.max) return false;
		var cb = getCallbackName('slymedialistwidget');
		sly.openMediapool('detail', this.getSelected(), cb, this.filetypes, this.categories);
		win[cb] = $.proxy(this.addValue, this);
		return false;
	};

	sly.MedialistWidget.prototype.onAdd = function() {
		if (this.getCount() === this.max) return false;
		var cb = getCallbackName('slymedialistwidget');
		sly.openMediapool('upload', '', cb, this.filetypes, this.categories);
		win[cb] = $.proxy(this.addValue, this);
		return false;
	};

	/////////////////////////////////////////////////////////////////////////////
	// Linklist Widgets

	sly.LinklistWidget = function(elem) {
		sly.AbstractListWidget.call(this, elem);

		this.articletypes = readLists(this.element, 'articletypes');
		this.categories   = readLists(this.element, 'categories');
	};

	sly.inherit(sly.LinklistWidget, sly.AbstractListWidget);

	sly.LinklistWidget.prototype.onOpen = function() {
		if (this.getCount() === this.max) return false;
		var catID = this.element.data('catid'), cb = getCallbackName('slylinklistwidget');

		sly.openLinkmap(catID, cb, this.articletypes, this.categories);
		win[cb] = $.proxy(this.addValue, this);

		return false;
	};

	/////////////////////////////////////////////////////////////////////////////
	// Misc functions

	sly.disableLogin = function(timerElement) {
		var nextTime = parseInt(timerElement.html(), 10) - 1;

		timerElement.html(nextTime + '');

		if (nextTime > 0) {
			win.setTimeout(sly.disableLogin, 1000, timerElement);
		}
		else {
			$('div.sly-message p span').html($('#sly_login_form').data('message'));
			$('#sly_login_form input:not(:hidden)').prop('disabled', false);
		}
	};

	sly.startLoginTimer = function(message) {
		var timerElement = $('div.sly-message p span strong');

		if (timerElement.length === 1) {
			$('#sly_login_form input:not(:hidden)').prop('disabled', true);
			$('#sly_login_form').data('message', message);
			win.setTimeout(sly.disableLogin, 1000, timerElement);
		}
	};

	sly.setModernizrCookie = function() {
		if (typeof Modernizr === 'undefined') return false;

		// Audio and video elements get squashed together and are stored as
		// just true or false. We have to manually copy all elements to make
		// them available inside of our cookie. Sigh.
		var m = Modernizr, copy = JSON.parse(JSON.stringify(m)), key;

		copy.audio = {};
		copy.video = {};

		for (key in m.audio) {
			copy.audio[key] = m.audio[key];
		}

		for (key in m.video) {
			copy.video[key] = m.video[key];
		}

		// Keep the cookie small and remove all underscore properties.
		copy._version       = undef;
		copy._prefixes      = undef;
		copy._domPrefixes   = undef;
		copy._cssomPrefixes = undef;

		document.cookie = 'sly_modernizr='+escape(JSON.stringify(copy));
	};

	sly.initWidgets = function(context) {
		$('.sly-widget:not(.sly-initialized)', context).each(function() {
			var self = $(this), init = false;

			if (self.is('.sly-link')) {
				new sly.LinkWidget(this); init = true;
			}
			else if (self.is('.sly-media')) {
				new sly.MediaWidget(this); init = true;
			}
			else if (self.is('.sly-linklist')) {
				new sly.LinklistWidget(this); init = true;
			}
			else if (self.is('.sly-medialist')) {
				new sly.MedialistWidget(this); init = true;
			}

			if (init) {
				self.addClass('sly-initialized');
			}
		});
	};

	sly.assetBaseUri = function(addon) {
		return '../assets/addon/' + (addon ? (addon+'/') : '');
	};

	sly.getUrl = function(controller, action, params, sep) {
		var url = './';

		action     = action || 'index';
		action     = action.toLowerCase();
		controller = controller.toLowerCase();

		url += encodeURI(controller);

		if (action.length > 0 && action !== 'index') {
			url += '/' + encodeURI(action);
		}

		if (typeof params === 'string') {
			// trim ? and & from the start and end
			params = params.replace(/^[?&]*(.*?)[?&]*$/, '$1');
		}
		else if (typeof params === 'object') {
			params = $.param(params); // always uses '&' as a separator

			// ... fix that if needed
			if (typeof sep === 'string' && sep !== '&') {
				params = params.replace(/&/g, sep);
			}
		}
		else {
			params = '';
		}

		url += '?' + params;

		// trim ? and & from the end
		return url.replace(/[?&]+$/, '');
	};

	/////////////////////////////////////////////////////////////////////////////
	// dom:loaded handler

	$(function() {
		// Init widgets

		sly.initWidgets();

		// "check all" function for mediapool

		// @edge fix select all to work with icheck

		// $('.sly-check-all').click(function() {
		// var target = $(this).data('target');
		// $('input[name=\'' + target + '\']').prop('checked', this.checked);
		// });

		$('.sly-check-all').on('ifChecked', function() {
			var target = $(this).data('target');
			$('input[name=\'' + target + '\']').iCheck('check');
		});

		$('.sly-check-all').on('ifUnchecked', function() {
			var target = $(this).data('target');
			$('input[name=\'' + target + '\']').iCheck('uncheck');
		});

		// Lösch-Links

		$('a.sly-delete, input.sly-button-delete').click(function() {
			return confirm('Sicher?');
		});

		// use a distinct class to make sure when we change the delete question to
		// something like 'Are you sure you want to delete...?', existing code does
		// not look weird.

		$('a.sly-confirm-me, input.sly-confirm-me').click(function() {
			return confirm('Sicher?');
		});

		// Filter-Funktionen in sly_Table

		$('.sly-table-extras-filter input[class*=filter_input_]').keyup(function(event) {
			// Klassen- und Tabellennamen ermitteln

			var
				className = $(this).attr('class'),
				tableName = className.match(/filter_input_([a-zA-Z0-9_-]+)/)[1],
				table     = $('#' + tableName),
				c         = event.keyCode;

			// Wert auch in allen anderen Suchfeldern übernehmen

			$('input.' + className).val($(this).val());

			// Tabelle filtern

			event.preventDefault();

			if (c === 8 || c === 46 || c === 109 || c === 189 || (c >= 65 && c <= 90) || (c >= 48 && c <= 57)) {
				var keyword = new RegExp($(this).val(), 'i');

				$('tbody tr', table).each(function() {
					var
						row  = $(this),
						rows = $('td', row).filter(function() {
							return keyword.test($(this).text());
						});

					row.toggle(rows.length > 0);
				});
			}
		});

		// Links in neuem Fenster öffnen

		$('a.sly-blank').attr('target', '_blank');

		// open mediapool in popup

		$('#sly-navi-page-mediapool a').click(function() {
			sly.openMediapool();
			return false;
		});

		// form framework

		// @edge fix for icheck

		$('.sly-form .sly-select-checkbox-list a[rel]').on('click', function() {			
			var rel   = $(this).attr('rel');
			var boxes = $(this).closest('div').find('input');
			// boxes.prop('checked', rel === 'all');

			if (rel === 'all') {
				boxes.iCheck('check');
			}
			else {
				boxes.iCheck('uncheck');
			}

			return false;
		});

		// allen vom Browser unterstützten Elementen die Klasse ua-supported geben

		if (typeof Modernizr !== 'undefined') {
			var types = Modernizr.inputtypes;

			for (var type in types) {
				if (types.hasOwnProperty(type) && types[type]) {
					$('input[type='+type+']').addClass('ua-supported');
				}
			}

			// Fallback-Implementierung für autofocus
			if (!Modernizr.input.autofocus) {
				$('*[autofocus]').focus();
			}
		}

		if (typeof $.fn.rangeinput !== 'undefined') {
			// Fallback-Implementierung für type=range via jQuery Tools Slider
			$('input[type="range"]:not(.ua-supported)').rangeinput({
				css: {
					input:    'sly-range-range',
					slider:   'sly-range-slider',
					progress: 'sly-range-progress',
					handle:   'sly-jqt-handle'
				}
			});

			// Fallback-Implementierung für type=date
			$('input[type*="date"]').slyDateTime({
				lngNoDateSelected:   sly.locale.noDateSelected,
				lngDeleteDate:       sly.locale.deleteDate,
				lngClickToInputDate: sly.locale.clickToInputDate,
				lngMonths:           sly.locale.months.join(','),
				lngShortMonths:      sly.locale.shortMonths.join(','),
				lngDays:             sly.locale.days.join(','),
				lngShortDays:        sly.locale.shortDays.join(',')
			});
		}

		var sly_apply_select2 = function(container) {
			// run Select2, but transform manual indentation (aka prefixing values with '&nbsp;'s)
			// into lvl-N classes, or else the quick filter function of Chosen will not work
			// properly.
			if (typeof $.fn.select2 !== 'undefined') {
				var options = $('select:not(.sly-no-select2):not(.sly-no-chosen) option', container), len = options.length, i = 0, depth, option;

				for (; i < len; ++i) {
					option = $(options[i]);
					depth  = option.html().match(/^(&nbsp;)*/)[0].length / 6;

					if (depth > 0) {
						option.addClass('sly-lvl-'+depth).html(option.html().substr(depth*6));
					}
				}

				$('.sly-form-select:not(.sly-no-select2):not(.sly-no-chosen)', container).each(function() {
					var select = $(this);
					if (select.data('placeholder') === undefined) {
						select.data('placeholder', 'Bitte auswählen');
					}
					select.select2({
						// width: 'resolve'
					});
				});
			}
		};

		sly_apply_select2($('body'));

		// listen to rowAdded event
		$('body').bind('rowAdded', function(event) {
			sly_apply_select2(event.currentTarget);
		});

		$('body').delegate('a.sly-postlink', 'click', function() {
			var
				parts  = $(this).attr('href').split('?'),
				action = parts[0],
				params = parts[1] || '',
				form   = $('<form>').attr('action', action).attr('method', 'post'),
				token  = $('meta[name="'+tokenName+'"]'),
				i, tmp, key, value;

				$('body').append(form);

			if (params.indexOf('#') >= 0) {
				params = params.split('#')[0];
			}

			params = params.split('&');

			for (i in params) {
				tmp   = params[i].split('=');
				key   = tmp[0];
				value = tmp[1];

				$('<input>')
					.attr('type', 'hidden')
					.attr('name', key)
					.attr('value', value)
					.appendTo(form);
			}

			if (token.length > 0 && !$(this).is('.sly-no-csrf')) {
				$('<input>')
					.attr('type', 'hidden')
					.attr('name', tokenName)
					.attr('value', token.attr('content'))
					.appendTo(form);
			}

			$(form).submit();
			return false;
		});

		// Mehrsprachige Formulare initialisieren

		// Checkboxen erzeugen

		$('.sly-form-row.sly-form-multilingual').each(function() {
			var
				$this   = $(this),
				equal   = $this.is(':visible'),
				id      = $this.attr('rel'),
				checked = equal ? ' checked="checked"': '',
				span    = '<span class="sly-form-i18n-switch"><input type="checkbox" name="equal__'+id+'" id="equal__'+id+'" value="1"'+checked+' /><\/span>';

			if (equal) {
				$('label:first', this).after(span);
			}
			else {
				var container = $this.next('div.sly-form-i18n-container');
				$('label:first', container).after(span);
			}
		});

		// Checkboxen initialisieren

		// @edge replace with a special icheck component

		// var checkboxes = $('.sly-form-i18n-switch input[id^=equal__]');

		// if (checkboxes.length > 0) {
		// 	checkboxes.imgCheckbox('off.png', 'on.png', '../assets/app/backend/form-i18n-switch-').next().click(function() {
		// 		var
		// 			checkbox       = $(this).prev('input'),
		// 			shown          = !checkbox[0].checked, // was already changed before this event handler
		// 			id             = checkbox.attr('id').replace(/^equal__/, ''),
		// 			container      = $('div[rel="'+id+'"]'),
		// 			span           = checkbox.parent('span'),
		// 			equalcontainer = $('div.sly-form-i18n-container.c-'+id);

		// 		if (shown) {
		// 			container.hide();
		// 			equalcontainer.show();
		// 			$('label:first', equalcontainer).after(span);
		// 		}
		// 		else {
		// 			container.show();
		// 			equalcontainer.hide();
		// 			$('label:first', container).after(span);
		// 		}
		// 	});
		// }

		// Module selection on content page

		$('.sly-module-select').change(function() {
			if ($(this).val()) {
				$(this).closest('form').submit();
			}
		});

		// transform overly long slot lists into dropdowns

		var slots = $('.sly-navi-slots li:not(:first-child) a');

		if (slots.length > 5) {
			var select = $('<select name="sly-slot" id="sly-slot" class="sly-form-select"></select>');

			for (var i = 0; i < slots.length; ++i) {
				var
					slot     = $(slots[i]),
					active   = slot.is('.sly-active'),
					slotName = slot.data('slyslot'),
					title    = slot.text(),
					option   = $('<option></option>').text(title).val(slotName);

				if (active) {
					option.prop('selected', true);
				}

				select.append(option);
			}

			select.change(function() {
				var
					slot      = select.val(),
					header    = $('.sly-content-header'),
					articleID = header.data('slyid'),
					clang     = header.data('slyclang'),
					revision  = header.data('slyrevision');

				window.location = sly.getUrl('content', null, {article_id: articleID, clang: clang, revision: revision, slot: slot}, '&');
				return false;
			});

			slots.parent().remove();
			$('.sly-navi-slots').append($('<li>').append(select));
			sly_apply_select2(select.parent());
		}

		// toggle cache options
		$('#sly-system-toggle-cache').click(function() {
			$('#sly-form-system-caches p').slideToggle();
			return false;
		});

		// use ajax to install/activate addOns
		var errorHider = null;

		$('#sly-page-addon .sly-addonlist').delegate('a:not(.sly-blank)', 'click', function() {
			var
				link     = $(this),
				list     = $('.sly-addonlist'),
				rows     = $('.pkg', list),
				row      = link.closest('.pkg'),
				token    = $('meta[name="'+tokenName+'"]').attr('content'),
				href     = link.attr('href'),
				func     = decodeURI((RegExp('addon/(.+?)(\\?|$)').exec(href)||[,null])[1]),
				errorrow = $('.error', list);

			// build POST data
			var postData = {
				addon: row.data('key'),
				json: 1
			};

			postData[tokenName] = token;

			// hide error row
			errorrow.hide();

			// clear timeout
			if (errorHider) {
				win.clearTimeout(errorHider);
			}

			// show extra div that will contain the loading animation
			// @edge disable buttons and use versatile loader
			// rows.prepend('<div class="blocker"></div>');
			// row.addClass('working');

			rows.find('.sly-button').addClass('disabled');

			NProgress.start();

			var updateAddOnStatus = function(stati) {
				for (var key in stati) {
					if (!stati.hasOwnProperty(key)) continue;
					var status = stati[key], comp = $('.pkg[data-key="' + key + '"]');
					comp.attr('class', status.classes + ' pkg');
					$('.deps', comp).html(status.deps);
				}
			};

			var successHandler = function(xhr) {
				if (xhr.finished === false) {
					performRequest();
				}
				else {
					updateAddOnStatus(xhr.stati);
					// @edge disable buttons and use versatile loader
					// row.removeClass('working');
					// $('.blocker').remove();

					rows.find('.sly-button').removeClass('disabled');

					NProgress.done();

					if (xhr.status !== true) {
						row.after(errorrow);
						$('span', errorrow).html(xhr.message);
						errorrow.show();
						errorHider = win.setTimeout(function() { errorrow.slideUp(); }, 10000);
					}
				}
			};

			var performRequest = function() {
				$.ajax({
					url: sly.getUrl('addon', func),
					data: postData,
					cache: false,
					dataType: 'json',
					type: 'POST',
					success: successHandler
				});
			};

			performRequest();
			return false;
		});

		$('body.sly-popup').unload(sly.closeAllPopups);

		////////////////////////////////////////////////////
		// @edge
		////////////////////////////////////////////////////

		////////////////////////////////////////////////////
		// mediapool and linkmap iframe preloading
		var isMediapool = !!$('body[id^=sly-page-mediapool]').length;
		var isLinkmap   = !!$('body[id^=sly-page-linkmap]').length;

		window.onload = function () {
			if (isMediapool) {
				window.parent.NProgress.start();

				setTimeout(function () {
					$('body[id^=sly-page-mediapool]').addClass('sly-iframe-ready');
					// window.parent.$('.mfp-close').addClass('sly-iframe-ready');

					window.parent.NProgress.done();
				}, 1000);
			}

			if (isLinkmap) {
				window.parent.NProgress.start();

				setTimeout(function () {
					$('body[id^=sly-page-linkmap]').addClass('sly-iframe-ready');
					// window.parent.$('.mfp-close').addClass('sly-iframe-ready');

					window.parent.NProgress.done();
				}, 1000);
			}
		};

		////////////////////////////////////////////////////
		// fix labels (should be done in the core)

		$.map($('label'), function (label) {
			var value = $(label).text().replace(/&nbsp;/g, '');

			// remove empty labels
			if ($.trim(value) === '') {
				$(label).remove();
			}

			// colons should be set in lang.yml
			if (value.indexOf(':') > -1) {
				$(label).text(value.substr(0, value.length - 1));
			}
		});

		////////////////////////////////////////////////////
		// checkbox replacement

		$('.sly-form-checkbox').iCheck({
			checkboxClass: 'sly-icheckbox'
		});

		$('.sly-form-radio').iCheck({
			radioClass: 'sly-iradio'
		});

		////////////////////////////////////////////////////
		// fix annotations (should be done in the core)

		// findTextNodes
		// @author: http://stackoverflow.com/a/4399718

		function findTextNodes(node) {
			var nodes = [], pattern = /\S/;

			function getTextNode(node) {
				if (node.nodeType === 3 && pattern.test(node.nodeValue)) {
					nodes.push(node);
				}
				else {
					for (var i = 0, len = node.childNodes.length; i < len; ++i) {
						getTextNode(node.childNodes[i]);
					}
				}
			}

			getTextNode(node);

			return nodes;
		}

		$.map($('.sly-form-text-row, .sly-form-number-row'), function (row) {
			var textNodes = findTextNodes(row);

			if (textNodes.length > 1) {
				var textNode = textNodes[1];
				textNode.nodeValue = '(' + $.trim(textNode.nodeValue) + ')';
				$(textNode).wrap('<span class="sly-form-annotation"/>');
			}
		});

		////////////////////////////////////////////////////
		// fix file inputs (should be done in the core)

		// formatFileSize
		// @author http://stackoverflow.com/a/18650828

		function formatFileSize(bytes) {
			if (bytes === 0) {
				return '0 Byte';
			}

			var k     = 1000,
				sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'],
				i     = Math.floor(Math.log(bytes) / Math.log(k));

			return (bytes / Math.pow(k, i)).toPrecision(3) + ' ' + sizes[i];
		}

		var input  = $('input[type=file]'),
			button = $('<span class="sly-button sly-button-file"/>');

		input.wrap(button).before('<span class="sly-button-file-info">Auswählen &hellip;</span>');

		$(document).on('change', '.sly-button-file :file', function () {
			var input    = $(this),
				files    = input.get(0).files ? input.get(0).files : null,
				filename = input.val().replace(/\\/g, '/').replace(/.*\//, '');

			input.trigger('fileselect', [files, filename]);
		});

		$('.sly-button-file :file').on('fileselect', function (event, files, filename) {
			if (files) {
				$('.sly-button-file-info').text(files[0].name + ' (' + formatFileSize(files[0].size) + ')');
			}
			else {
				$('.sly-button-file-info').text(filename);
			}
		});

		////////////////////////////////////////////////////
		// responsive tables (should be done in the core)

		$('.sly-table').wrap('<div class="table-responsive"/>');

		////////////////////////////////////////////////////
		// remove pager label (should be done in the core)

		$('.sly-table-extras-pager').contents().filter(function () {
			var pattern = /\S/;

			if (this.nodeType === 3 && pattern.test(this.nodeValue)) {
				$(this).remove();
			}
		});
	});
})(jQuery, sly, window);
