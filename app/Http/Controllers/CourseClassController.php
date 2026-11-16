<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourseClassController extends Controller
{
    public function index(): Response
    {
        $classes = CourseClass::with(['course:id,code,name', 'dosen:id,name'])
            ->orderByDesc('academic_year')
            ->orderBy('code')
            ->get()
            ->map(fn (CourseClass $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'academic_year' => $c->academic_year,
                'course_id' => $c->course_id,
                'course' => "{$c->course->code} - {$c->course->name}",
                'dosen_id' => $c->dosen_id,
                'dosen' => $c->dosen->name,
                'is_active' => $c->is_active,
            ]);

        return Inertia::render('classes/index', [
            'classes' => $classes,
            'courses' => Course::where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'dosens' => User::where('role', Role::Dosen->value)->where('is_active', true)
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $class = CourseClass::create($this->validated($request));

        Audit::record('class.created', $class, ['code' => $class->code, 'academic_year' => $class->academic_year]);

        return back();
    }

    public function update(Request $request, CourseClass $courseClass): RedirectResponse
    {
        $courseClass->update($this->validated($request, $courseClass));

        Audit::record('class.updated', $courseClass, ['code' => $courseClass->code]);

        return back();
    }

    public function toggleStatus(CourseClass $courseClass): RedirectResponse
    {
        $courseClass->is_active = ! $courseClass->is_active;
        $courseClass->save();

        Audit::record($courseClass->is_active ? 'class.activated' : 'class.archived', $courseClass);

        return back();
    }

    private function validated(Request $request, ?CourseClass $class = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'course_id' => [
                'required', 'integer',
                Rule::exists('courses', 'id')->where('is_active', true),
            ],
            'dosen_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('role', Role::Dosen->value)->where('is_active', true),
            ],
            'academic_year' => [
                'bail', 'required', 'string', 'regex:/^\d{4}\/\d{4}$/',
                function (string $attribute, mixed $value, \Closure $fail) {
                    [$a, $b] = array_map('intval', explode('/', $value));
                    if ($b !== $a + 1) {
                        $fail('Tahun akademik harus berurutan, misalnya 2026/2027.');
                    }
                },
            ],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('course_classes', 'code')
                    ->where('course_id', $request->input('course_id'))
                    ->where('academic_year', $request->input('academic_year'))
                    ->ignore($class?->id),
            ],
        ]);
    }
}