{{-- resources/views/components/toast.blade.php --}}

<style>
    .toastify .toast-close {
        display: none !important;
    }
</style>

@if (session('success'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    showSpaToast(@json(session('success')), 'success');
});
</script>
@endif

@if (session('error'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    showSpaToast(@json(session('error')), 'error');
});
</script>
@endif

@if ($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    showSpaToast(@json($errors->first()), 'error');
});
</script>
@endif

@if (session('status'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    const status = @json(session('status'));
    const map = {
        'profile-updated': 'Profile updated successfully!',
        'password-updated': 'Password updated successfully!',
        'verification-link-sent': 'Verification link sent successfully!',
    };
    if (map[status]) {
        showSpaToast(map[status], 'success');
    }
});
</script>
@endif
