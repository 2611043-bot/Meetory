(function () {
    var completeBtn = document.getElementById('completeBtn');
    var overlay = document.getElementById('transactionCompleteOverlay');
    var modal = document.getElementById('transactionCompleteModal');
    var cancelBtn = document.getElementById('completeCancel');
    var confirmBtn = document.getElementById('completeConfirm');
    var form = document.getElementById('completeForm');
    var submitting = false;

    if (!completeBtn || !overlay || !modal || !cancelBtn || !confirmBtn || !form) {
        return;
    }

    function openModal() {
        overlay.classList.add('open');
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        overlay.classList.remove('open');
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }

    completeBtn.addEventListener('click', function () {
        openModal();
    });

    cancelBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        closeModal();
    });

    overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
            closeModal();
        }
    });

    confirmBtn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        if (submitting) {
            return;
        }
        submitting = true;
        confirmBtn.disabled = true;
        form.submit();
    });
})();
