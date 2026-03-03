{{-- resources/views/components/toast.blade.php --}}

@if (session('success'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    showToast(@json(session('success')), 'success');
});
</script>
@endif


@if (session('error'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    showToast(@json(session('error')), 'error');
});
</script>
@endif


@if ($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    showToast(@json($errors->first()), 'error');
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
        showToast(map[status], 'success');
    }

});
</script>
@endif
