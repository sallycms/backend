/**
 * SallyCMS - DateTime picker based on jQuery Tools Dateinput
 */

(function($) {
	'use strict';

	/* stuff that needs to be copied because it's not public API from jQuery Tools */

	var
		tmpTag = $('<a />'),

		zeropad = function(val) {
			return val < 10 ? '0'+val : val;
		},

		formatDate = function(date, text, labels) {
			var d = date.getDate(),
				D = date.getDay(),
				m = date.getMonth(),
				y = date.getFullYear(),
				h = date.getUTCHours(),
				M = date.getUTCMinutes(),
				s = date.getUTCSeconds(),

				flags = {
					d:    d,
					dd:   zeropad(d),
					ddd:  labels.shortDays[D],
					dddd: labels.days[D],
					m:    m + 1,
					mm:   zeropad(m + 1),
					mmm:  labels.shortMonths[m],
					mmmm: labels.months[m],
					yy:   String(y).slice(2),
					yyyy: y,
					H:    h,
					HH:   zeropad(h),
					M:    M,
					MM:   zeropad(M),
					S:    s,
					SS:   zeropad(s)
				};

			var ret = text.replace(/d{1,4}|m{1,4}|yy(?:yy)?|HH?|MM?|SS?|"[^"]*"|'[^']*'/g, function ($0) {
				return $0 in flags ? flags[$0] : $0;
			});

			// a small trick to handle special characters
			return tmpTag.html(ret).html();
		},

		/* own functions */

		hasNative = function(type) {
			var i = document.createElement('input');
			i.setAttribute('type', type);
			return i.type !== 'text';
		},

		isoFormats = {
			'date':           'yyyy-mm-dd',
			'datetime-local': 'yyyy-mm-ddTHH:MM'
		},

		natives = {
			'date':           hasNative('date'),
			'datetime-local': hasNative('datetime-local')
		},

		toggleElementStati = function(input, value) {
			/*
			http://www.w3.org/TR/html-markup/input.date.html#input.date.attrs.value
			value: A valid full-date as defined in [RFC 3339], with the additional
			qualification that the year component is four or more digits representing
			a number greater than 0.

			=> we can never 'reset' native date elements

			if (isNative) {
				if (required) {
					stati = value === '' ? [0,1,0] : [1,0,0];
				}
				else {
					stati = value === '' ? [0,1,0] : [1,0,1];
				}
			}
			else {
				if (required) {
					stati = [1,0,0];
				}
				else {
					stati = value === '' ? [1,0,0] : [1,0,1];
				}
			}
			*/

			// Dateinput does not handle types other than 'date'. That's why
			// we need to check both orig-type (if the input was a 'date')
			// and the real type (untouched if 'datetime-local').

			var
				type      = getType(input),
				isNative  = natives[type],
				required  = input.prop('required'),
				span      = input.nextAll('.sly-date-disabled'),
				link      = input.nextAll('.sly-date-delete'),
				empty     = value === '',
				needsSpan = isNative && empty;

			input.toggle(!needsSpan);
			span.toggle(needsSpan);
			link.toggle(!required && !empty);
		},

		getType = function(input) {
			return $(input).data('sly-orig-type');
		},

		newDate = function(d) {
			return d === '' ? new Date() : new Date(Date.parse(d));
		},

		findISOHolder = function(input) {
			return $(input).nextAll('.sly-date-iso');
		},

		findCurInput = function() {
			return $($('#calroot').data('sly-curelement'));
		},

		setValue = function(input, date) {
			var
				iso    = findISOHolder(input),
				type   = getType(input),
				labels = input.data('sly-labels');

			iso.val(date === '' ? '' : formatDate(date, isoFormats[type], labels));
			input.val(date === '' ? '' : formatDate(date, input.data('format'), labels));
		},

		randID = function() {
			var text = '', possible = 'abcdefghijklmnopqrstuvwxyz', i = 0, len = 15;

			for (; i < len; i++) {
				text += possible.charAt(Math.floor(Math.random() * possible.length));
			}

			return text;
		};

	/* and here goes the plugin itself */

	$.fn.slyDateTime = function(settings) {
		var
			opts   = $.extend({}, $.fn.slyDateTime.defaults, settings),
			inputs = $(this).filter('input[type*="date"]'),
			labels = {
				months:      opts.lngMonths,
				shortMonths: opts.lngShortMonths,
				days:        opts.lngDays,
				shortDays:   opts.lngShortDays
			};

		// splits up the language strings *in-place*
		$.tools.dateinput.localize('slydatetime', labels);

		return inputs.each(function() {
			var
				elem      = $(this),
				type      = elem.attr('type'),
				isNative  = natives[type],
				withTime  = elem.attr('type') === 'datetime-local',
				format    = elem.data('format'),
				required  = elem.prop('required'),
				name      = elem.attr('name'),
				link      = $('<a href="#" class="sly-date-delete"></a>').attr('title', opts.lngDeleteDate),
				curDate   = elem.val() === '' ? '' : newDate(elem.val()),
				isoHolder = $('<input type="hidden" value="" class="sly-date-iso" />'),
				span      = $('<span class="sly-date-disabled sly-form-read"><span>('+opts.lngNoDateSelected+')</span></span>'),
				uniqueID  = randID();

			// If the client supports a good datepicker and the user should
			// never remove a date, we do nothing.
			if (required && isNative) {
				return;
			}

			// init and inject helper elements
			if (isNative) {
				elem.after(span).after(link);

				// store the original type, since IE<9 will not let us access it later on
				elem.data('sly-orig-type', type);
			}
			else {
				elem.attr('name', opts.nameFrmt.replace('%s', name));
				isoHolder.attr('name', name);
				elem.after(isoHolder);

				if (!required) {
					span.attr('title', opts.lngClickToInputDate);
					elem.after(span).after(link);
				}

				// init Dateinput
				// Since Dateinput will replace the input with a clone, we have
				// to add some special identifier to find the new element (we
				// don't rely on given IDs or classes).
				elem.addClass(uniqueID);

				elem.dateinput({
					format:      format,
					selectors:   false, // seems to be buggy
					lang:        'slydatetime',
					offset:      [5,0],
					yearRange:   [-100, 100],
					firstDay:    1
				});

				// and re-select the element again
				elem = $('.'+uniqueID);
				elem.removeClass(uniqueID);

				// store the labels for later use
				// (must be done here because the dateinput() kills data on the node)
				elem.data('sly-labels', labels);

				// store the original type, since IE<9 will not let us access it later on
				elem.data('sly-orig-type', type);

				// overwrite Dateinput's value
				setValue(elem, curDate);
			}

			// set initial state
			toggleElementStati(elem, curDate);

			// init event handlers
			span.click(function() {
				toggleElementStati(elem, 'dummy');
			});

			link.click(function() {
				toggleElementStati(elem, '');
				setValue(elem, '');
				return false;
			});

			if (!isNative) {
				// store the well-formatted date in the hidden input with the
				// input's original name
				elem.on('change', function() {
					var date = elem.data('dateinput').getValue();

					setValue(elem, date);
					toggleElementStati(elem, date);
				});

				// make sure we update the UI when someone removes the form value by hand
				elem.on('keydown', function(e) {
					if (e.keyCode === 8 || e.keyCode === 46) { // backspace or delete
						setValue(elem, '');
						toggleElementStati(elem, '');
					}
				});

				//  hide the sliders if we're on a date element
				elem.on('onBeforeShow', function(e) {
					var hasTime = getType(e.target) !== 'date';

					$('.sly-date-sliders').toggle(hasTime);
					$('#calweeks').toggleClass('sly-has-time', hasTime);
				});

				if (withTime) {
					// make sure we can reach the API later when handling
					// change events on sliders (there is only on set of sliders
					// and all date elements have to share them).
					elem.on('onBeforeShow', function(e) {
						var
							input    = e.target,
							root     = $('#calroot'),
							hrSlider = $('.sly-date-range:first', root).data('rangeinput'),
							mnSlider = $('.sly-date-range:last', root).data('rangeinput'),
							date     = findISOHolder(input).val();

						root.data('sly-curelement', input);

						// update the sliders so that they show the correct values
						if (date === '') {
							hrSlider.setValue(0);
							mnSlider.setValue(0);
						}
						else {
							date = newDate(date);
							hrSlider.setValue(date.getUTCHours());
							mnSlider.setValue(date.getUTCMinutes());
						}
					});

					// don't close the picker on click
					elem.on('beforeChange', function(e, date) {
						// set HH:MM:SS of the selected date to the time of the isoHolder's local value
						var old = findISOHolder(findCurInput()).val();

						if (old !== '') {
							old = newDate(old);
							date.setHours(old.getHours());
							date.setMinutes(old.getMinutes());
							date.setSeconds(old.getSeconds());
						}

						// since the change event will not fire later on, we have to update the ISO holder ourselves
						setValue(elem, date);

						// and update the element stati
						toggleElementStati(elem, date);

						// yes, we actually have to do this...
						elem.data('dateinput').hide().show();

						// and stop here, please.
						return false;
					});

					// add timepicker sliders
					var root = $('#calbody');

					if (!root.is('.sly-has-time')) {
						root.addClass('sly-has-time');

						var div = $('<div class="sly-date-sliders"></div>');

						div
							.append($('<input type="range" min="0" max="23" step="1" data-type="hours" />').attr('name', name+'__slyhours'))
							.append($('<input type="range" min="0" max="59" step="1" data-type="minutes" />').attr('name', name+'__slyminutes'));

						root.append(div);

						// prepare sliders
						$(':range', root).rangeinput({css: {
							slider: 'sly-date-slider',
							input:  'sly-date-range sly-form-text',
							handle: 'sly-jqt-handle'
						}});

						// put some text after the inputs so people know what those numbers mean
						// (and make sure there are no keyboard events inside the inputs, or else
						// the calendar would react as well)
						$('.sly-date-range:first', root).prop('readonly', true).after('h');
						$('.sly-date-range:last', root).prop('readonly', true).after('m');

						// act when the time is changed
						$('.sly-date-range', root).change(function(e, value) {
							var
								root    = $('#calroot'),
								input   = findCurInput(),
								hours   = parseInt($('.sly-date-range:first', root).val(), 10),
								minutes = parseInt($('.sly-date-range:last', root).val(), 10),
								date    = newDate(findISOHolder(input).val());

							date.setUTCHours(hours);
							date.setUTCMinutes(minutes);

							setValue(input, date);
							toggleElementStati(input, date);
						});
					}
				}
			}
		});
	};

	$.fn.slyDateTime.defaults = {
		lngNoDateSelected:   'kein Datum angegeben',
		lngDeleteDate:       'löschen',
		lngClickToInputDate: 'hier klicken, um ein Datum anzugeben',
		nameFrmt:            'input_%s',
		lngMonths:           'Januar,Februar,März,April,Mai,Juni,Juli,August,September,Oktober,November,Dezember',
		lngShortMonths:      'Jan,Feb,Mär,Apr,Mai,Jun,Jul,Aug,Sep,Okt,Nov,Dez',
		lngDays:             'Sonntag,Montag,Dienstag,Mittwoch,Donnerstag,Freitag,Samstag',
		lngShortDays:        'So,Mo,Di,Mi,Do,Fr,Sa'
	};
})(jQuery);
