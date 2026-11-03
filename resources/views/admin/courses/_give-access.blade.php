{{--
    Course › Students › Give access: 1) who (pick an existing student from a searchable list, or a new
    one by name + email), 2) how long (the course's plans as one-click buttons, or days), 3) what was
    paid (goes on the invoice). The form posts "email" (+ "name" for a new student) as before.
--}}
@php
    $hadBefore = $course->enrollments()->pluck('user_id')->flip();
    $people = $pickable->map(fn ($s) => [
        'name' => $s->name,
        'email' => $s->email,
        'phone' => (string) $s->phone,
        'initials' => mb_strtoupper(collect(preg_split('/\s+/u', trim($s->name)))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('')),
        'renew' => $hadBefore->has($s->id),
    ])->values();
    $newMode = old('mode') === 'new';
@endphp
<section class="card">
    <div class="card-head"><div><h2>Give access</h2><p>Paid in cash, by ABA, or a scholarship.</p></div></div>
    <form class="card-body grant" method="post" action="{{ route('admin.courses.students.grant', $course) }}" data-grant data-access-form>
        @csrf
        <input type="hidden" name="mode" value="{{ $newMode ? 'new' : 'existing' }}" data-grant-mode>

        {{-- 1 · Who --}}
        <div class="grant-step">
            <p class="grant-label"><span>1</span> Student</p>
            <div class="segmented grant-tabs" role="tablist" aria-label="Student">
                <button type="button" role="tab" aria-selected="{{ $newMode ? 'false' : 'true' }}" data-grant-tab="existing" @class(['on' => ! $newMode])>Existing student</button>
                <button type="button" role="tab" aria-selected="{{ $newMode ? 'true' : 'false' }}" data-grant-tab="new" @class(['on' => $newMode])>New student</button>
            </div>

            <div class="grant-pane" data-grant-pane="existing" @if ($newMode) hidden @endif>
                <input type="hidden" name="email" value="{{ $newMode ? '' : old('email') }}" data-grant-email @disabled($newMode)>
                <div class="picked" data-grant-picked hidden>
                    <span class="avatar" data-picked-initials></span>
                    <span class="picked-text"><b data-picked-name></b><small data-picked-meta></small></span>
                    <button type="button" class="btn btn-ghost btn-sm" data-grant-change>Change</button>
                </div>
                <div class="picker" data-grant-picker>
                    <div class="picker-search">
                        @include('admin.partials.icon', ['name' => 'search'])
                        <input type="search" placeholder="Search name, email or phone…" autocomplete="off" aria-label="Search students" aria-controls="grant-list" data-grant-search>
                    </div>
                    <ul class="picker-list" id="grant-list" role="listbox" aria-label="Students" data-grant-list></ul>
                    <div class="picker-empty" data-grant-empty hidden>
                        <p>No student matches “<b data-grant-query></b>”.</p>
                        <button type="button" class="btn btn-ghost btn-sm" data-grant-new>@include('admin.partials.icon', ['name' => 'plus']) Add as a new student</button>
                    </div>
                </div>
                <p class="picker-count">{{ $people->count() }} {{ str('student')->plural($people->count()) }} can be given this course. Students who already have it are not listed.</p>
                @error('email')<span class="error-text">{{ $message }}</span>@enderror
            </div>

            <div class="grant-pane" data-grant-pane="new" @unless ($newMode) hidden @endunless>
                <div class="field">
                    <label class="field-label" for="g-name"><span>Full name<em>*</em></span></label>
                    <input id="g-name" type="text" name="name" value="{{ old('name') }}" autocomplete="off" data-grant-new-name @disabled(! $newMode)>
                    @error('name')<span class="error-text">{{ $message }}</span>@enderror
                </div>
                <div class="field">
                    <label class="field-label" for="g-email"><span>Email<em>*</em></span></label>
                    <input id="g-email" type="email" name="email" value="{{ $newMode ? old('email') : '' }}" autocomplete="off" data-grant-new-email @disabled(! $newMode)>
                    <span class="help">They sign in with this email. A password is made up for them.</span>
                    @if ($newMode) @error('email')<span class="error-text">{{ $message }}</span>@enderror @endif
                </div>
            </div>
        </div>

        {{-- 2 · How long --}}
        <div class="grant-step">
            <p class="grant-label"><span>2</span> Access</p>
            @include('admin.students._access-length', ['planCourses' => collect([$course]), 'formKey' => 'course'])
        </div>

        {{-- 3 · Payment --}}
        <div class="grant-step">
            <p class="grant-label"><span>3</span> Payment <small>shown on their invoice</small></p>
            @include('admin.students._payment-fields')
        </div>

        <label class="check grant-mail"><input type="checkbox" name="send_email" value="1" checked> Email the student the course link and sign-in</label>
        <button class="btn btn-primary btn-block" data-grant-submit>@include('admin.partials.icon', ['name' => 'check']) <span data-grant-submit-text>Give access</span></button>
        <script type="application/json" data-grant-people>@json($people)</script>
    </form>
</section>
