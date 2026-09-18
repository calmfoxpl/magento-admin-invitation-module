/**
 * Turns the acceptance form into two steps: who you are, then your password.
 *
 * Everything stays in one form and is posted once, so the two steps are only a way of showing
 * the fields. Until this runs, both steps are visible and the form submits as an ordinary one:
 * an invitee with JavaScript switched off still gets an account.
 *
 * Moving forward validates the fields of the first step with Magento's own validator, so the
 * messages and the styling are the panel's.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (options) {
        var $form = $(options.form),
            $container = $(options.container),
            $steps = $container.find('[data-step]'),
            $markers = $(options.markers),
            $next = $(options.next),
            $back = $(options.back),
            $backToLogin = $(options.backToLogin),
            initial = parseInt($container.attr('data-initial-step'), 10) || 1;

        if (!$form.length || $steps.length < 2) {
            return;
        }

        function show(step) {
            $steps.each(function () {
                $(this).prop('hidden', parseInt($(this).attr('data-step'), 10) !== step);
            });
            $markers.find('[data-step-marker]').each(function () {
                var marker = parseInt($(this).attr('data-step-marker'), 10);

                $(this).toggleClass('_current', marker === step)
                    .toggleClass('_done', marker < step);
            });
            // on the second step "Back to sign in" gives way to "Back to your details"
            $back.prop('hidden', step !== 2);
            $backToLogin.prop('hidden', step === 2);
        }

        /** Every field of the step has to pass before the step is left. */
        function stepIsValid($step) {
            var valid = true;

            $step.find('input, select').each(function () {
                var $field = $(this);

                if (typeof $field.valid === 'function' && !$field.valid()) {
                    valid = false;
                }
            });

            return valid;
        }

        $markers.prop('hidden', false);
        $container.find('[data-step-actions]').prop('hidden', false);

        $next.on('click', function () {
            var $first = $container.find('[data-step="1"]');

            if (!stepIsValid($first)) {
                return;
            }
            show(2);
            $container.find('[data-step="2"]').find('input').first().trigger('focus');
        });

        $back.on('click', function (event) {
            event.preventDefault();
            show(1);
            $container.find('[data-step="1"]').find('input').first().trigger('focus');
        });

        show(initial);
    };
});
