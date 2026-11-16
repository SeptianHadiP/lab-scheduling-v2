<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('courses/index', [
            'courses' => Course::orderBy('code')->get(['id', 'code', 'name', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $course = Course::create($this->validated($request));

        Audit::record('course.created', $course, ['code' => $course->code]);

        return back();
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $course->update($this->validated($request, $course));

        Audit::record('course.updated', $course, ['code' => $course->code]);

        return back();
    }

    public function toggleStatus(Course $course): RedirectResponse
    {
        $course->is_active = ! $course->is_active;
        $course->save();

        Audit::record($course->is_active ? 'course.activated' : 'course.archived', $course);

        return back();
    }

    private function validated(Request $request, ?Course $course = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')->ignore($course?->id)],
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}