{{-- resources/views/components/toast.blade.php --}}

{{-- ✅ Success from session('success') --}}
@if (session('success'))
<script>
(function () {
    const msg = @json(session('success'));

    Toastify({
        text: `
            <div class="flex items-center gap-3">
                <i class="text-green-600 fa-solid fa-check-circle"></i>
                <span class="text-green-600">${msg}</span>
            </div>
        `,
        duration: 3000,
        gravity: "top",
        position: "right",
        close: true,
        escapeMarkup: false,
        backgroundColor: "#ffffff",
        style: {
            border: "1px solid #16a34a",
            borderRadius: "10px",
            minWidth: "300px",
            display: "flex",
            alignItems: "center",
            boxShadow: "0 8px 20px rgba(0,0,0,0.08)"
        }
    }).showToast();
})();
</script>
@endif

{{-- ✅ Success from session('status') --}}
@if (session('status'))
<script>
(function () {
    const status = @json(session('status'));

    // Map status values to messages
    const map = {
        'profile-updated': 'Profile updated successfully!',
        'password-updated': 'Password updated successfully!',
        'verification-link-sent': 'Verification link sent successfully!',
    };

    // Only show toast if we know the status
    if (!map[status]) return;

    Toastify({
        text: `
            <div class="flex items-center gap-3">
                <i class="text-green-600 fa-solid fa-check-circle"></i>
                <span class="text-green-600">${map[status]}</span>
            </div>
        `,
        duration: 3000,
        gravity: "top",
        position: "right",
        close: true,
        escapeMarkup: false,
        backgroundColor: "#ffffff",
        style: {
            border: "1px solid #16a34a",
            borderRadius: "10px",
            minWidth: "300px",
            display: "flex",
            alignItems: "center",
            boxShadow: "0 8px 20px rgba(0,0,0,0.08)"
        }
    }).showToast();
})();
</script>
@endif

{{-- ✅ Error --}}
@if ($errors->any())
<script>
(function () {
    const msg = @json($errors->first());

    Toastify({
        text: `
            <div class="flex items-center gap-3">
                <i class="text-red-600 fa-solid fa-circle-xmark"></i>
                <span class="text-red-600">${msg}</span>
            </div>
        `,
        duration: 4000,
        gravity: "top",
        position: "right",
        close: true,
        escapeMarkup: false,
        backgroundColor: "#ffffff",
        style: {
            border: "1px solid #dc2626",
            borderRadius: "10px",
            minWidth: "300px",
            display: "flex",
            alignItems: "center",
            boxShadow: "0 8px 20px rgba(0,0,0,0.08)"
        }
    }).showToast();
})();
</script>
@endif
