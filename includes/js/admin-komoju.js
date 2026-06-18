/* Admin behaviour for the KOMOJU settings page. */
(function () {
    'use strict';

    function enableEndpointField(event) {
        var button = event.currentTarget;
        var input = document.getElementById(button.dataset.target);
        if (input) {
            input.disabled = false;
        }
        button.remove();
    }

    function resetEndpointField(event) {
        var button = event.currentTarget;
        var input = document.getElementById(button.dataset.target);
        if (input) {
            input.value = input.dataset.default;
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.komoju-endpoint-edit').forEach(function (button) {
            button.addEventListener('click', enableEndpointField);
        });

        document.querySelectorAll('.komoju-endpoint-reset').forEach(function (button) {
            button.addEventListener('click', resetEndpointField);
        });
    });
})();
