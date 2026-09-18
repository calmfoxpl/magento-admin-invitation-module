/**
 * Suggests a random password on the acceptance page. It is generated in the browser with the
 * Web Crypto API, so it never leaves the device until the form is submitted, and the indices
 * are drawn without modulo bias: a generator that quietly favours some characters is worse
 * than no generator at all.
 */
define(['jquery'], function ($) {
    'use strict';

    var SETS = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnopqrstuvwxyz',
            '23456789',
            '!@#$%^&*-_=+?'
        ],
        ALL = SETS.join('');

    /** Uniform index in [0, max) — values from the biased tail of the range are redrawn. */
    function randomIndex(max) {
        var limit = Math.floor(0x100000000 / max) * max,
            buffer = new Uint32Array(1);

        do {
            window.crypto.getRandomValues(buffer);
        } while (buffer[0] >= limit);

        return buffer[0] % max;
    }

    function generate(length) {
        var chars = SETS.map(function (set) {
                return set[randomIndex(set.length)];
            }),
            i, j, swap;

        while (chars.length < length) {
            chars.push(ALL[randomIndex(ALL.length)]);
        }

        for (i = chars.length - 1; i > 0; i--) {
            j = randomIndex(i + 1);
            swap = chars[i];
            chars[i] = chars[j];
            chars[j] = swap;
        }

        return chars.join('');
    }

    return function (options) {
        var $root = $(options.root),
            length = parseInt($root.data('length'), 10) || 20;

        if (!window.crypto || !window.crypto.getRandomValues) {
            $(options.button).hide();

            return;
        }

        $(options.button).on('click', function () {
            var password = generate(length);

            $.each(options.fields, function (index, selector) {
                var $field = $(selector);

                $field.val(password);
                // the previous "too weak" message no longer applies
                $field.removeClass('mage-error').parent().find('.mage-error').remove();
            });

            $(options.value).val(password);
            $(options.result).prop('hidden', false);
        });
    };
});
