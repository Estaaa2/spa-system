<?php

namespace App\Http\Controllers\HR\Concerns;

use App\Models\Spa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Shared by the Unit 5 setup controllers.
 *
 * Every form on these pages validates into its own named error bag so two forms that
 * share a field name (e.g. effective_from) never show each other's errors. The page's
 * hidden `_modal` / `_record` inputs come back as old input, which the page script
 * uses to reopen the modal the error belongs to. The first message is also flashed as
 * `error` so the layout's toast fires, same as the rest of the app.
 */
trait HandlesPayrollSetupForms
{
    /** Payroll is spa-wide (consolidated), never branch-scoped. */
    protected function currentSpa(): Spa
    {
        $spa = Auth::user()?->spa;
        abort_if($spa === null, 403, 'No spa is linked to this account.');

        return $spa;
    }

    /**
     * Validate $rules into $bag, then run $action with the validated data. Validation
     * failures and domain ValidationExceptions thrown by the service both go back to
     * the form with input.
     *
     * @param callable(array): (RedirectResponse|string) $action returns a redirect or a success message
     */
    protected function handleForm(Request $request, string $bag, array $rules, callable $action, array $messages = [], ?callable $after = null): RedirectResponse
    {
        $validator = Validator::make($request->all(), $rules, $messages);
        if ($after !== null) {
            $validator->after($after);
        }

        try {
            $data = $validator->validate();
            $result = $action($data);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors(), $bag)
                ->withInput($request->except(['tin', 'sss_no', 'philhealth_no', 'pagibig_no']))
                ->with('error', collect($e->errors())->flatten()->first() ?? 'Please check the form.');
        }

        return $result instanceof RedirectResponse ? $result : back()->with('success', (string) $result);
    }
}