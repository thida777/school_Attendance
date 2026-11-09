<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The rules a student form must pass before anything is saved. */
class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /** "012 345 678" is saved as "012345678". */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'guardian_phone' => preg_replace('/\D/', '', (string) $this->input('guardian_phone')),
            'student_code' => strtoupper(trim((string) $this->input('student_code'))),
        ]);
    }

    public function rules(): array
    {
        $student = $this->route('student');

        return [
            'name_km' => ['required', 'string', 'max:255'],
            'name_latin' => ['required', 'string', 'max:255'],
            'student_code' => ['required', 'string', 'max:20', Rule::unique('students')->ignore($student)],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'school_class_id' => ['required', 'exists:school_classes,id'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_phone' => ['required', 'digits_between:8,12'],
            'guardian_relation' => ['required', Rule::in(['father', 'mother', 'guardian'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_km' => __('Name (Khmer)'),
            'name_latin' => __('Name (Latin)'),
            'student_code' => __('Student code'),
            'school_class_id' => __('Class'),
            'date_of_birth' => __('Date of birth'),
            'guardian_name' => __('Parent name'),
            'guardian_phone' => __('Parent phone'),
            'guardian_relation' => __('Relation'),
        ];
    }
}
