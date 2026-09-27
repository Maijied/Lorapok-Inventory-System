<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ShopApplication;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * "Start your shop" — the form that becomes a tenant.
 *
 * The slug is validated against the same reserved list and the same uniqueness
 * the provisioner enforces, so an applicant is told immediately rather than
 * after an operator tries to create the shop and it fails.
 */
class ShopApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:120'],

            'slug' => [
                'required',
                'string',
                'min:3',
                'max:40',
                // Subdomain grammar: a leading or trailing hyphen is not a
                // valid hostname label and would produce a shop nobody can
                // reach.
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::notIn(Tenant::RESERVED_SLUGS),
                Rule::unique('tenants', 'slug'),
                // Two people applying for the same name is a race the operator
                // should never have to resolve by hand.
                Rule::unique('shop_applications', 'slug')
                    ->where('status', ShopApplication::PENDING),
            ],

            'owner_name' => ['required', 'string', 'max:120'],
            'owner_email' => ['required', 'email:rfc', 'max:190'],
            'owner_phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:80'],

            'website' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Applicants type "Karim Mobile" or "Karim-Mobile"; both should become
        // the same address rather than being rejected.
        if ($this->filled('slug')) {
            $this->merge([
                'slug' => strtolower(trim((string) $this->input('slug'))),
            ]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and hyphens — it becomes your web address.',
            'slug.not_in' => 'That address is reserved. Please choose another.',
            'slug.unique' => 'That address is already taken.',
            'website.prohibited' => 'That submission looked automated. Please try again.',
        ];
    }
}
