<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The public contact form.
 *
 * Unauthenticated and on the open internet, so validation is doing two jobs:
 * keeping the data usable, and making the form unrewarding to a bot.
 */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],

            // Honeypot. A person never sees this field, so anything in it came
            // from something filling every input on the page.
            'website' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'website.prohibited' => 'That submission looked automated. Please try again.',
            'message.min' => 'Please tell us a little more so we can actually help.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // A form filled faster than it can be read was not read by a
            // person. Three seconds is well below a genuine submission and
            // well above an instant one.
            $started = (int) $this->input('started_at', 0);

            if ($started > 0 && (time() - $started) < 3) {
                $validator->errors()->add('message', 'That was submitted very quickly. Please try again.');
            }
        });
    }
}
