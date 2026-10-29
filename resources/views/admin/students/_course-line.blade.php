{{-- Under a course row: how the student got it, then its certificate (code + Take back, or Give certificate). --}}
@php
    $sources = ['payment' => 'Paid', 'manual' => 'Given by admin', 'free' => 'Free', 'certificate' => 'Certificate only'];
@endphp
<div class="row-sub">
    {{ $sources[$enrollment->source] ?? ucfirst($enrollment->source) }} ·
    @if ($enrollment->certificate_code)
        Certificate <a class="mono" href="{{ route('certificates.verify', $enrollment->certificate_code) }}" target="_blank" rel="noopener" title="Open the certificate">{{ $enrollment->certificate_code }}</a> ·
        <form method="post" action="{{ route('admin.enrollments.certificate.destroy', $enrollment) }}"
              data-confirm="Take back {{ $enrollment->user->name }}'s certificate {{ $enrollment->certificate_code }}? The code stops working.">
            @csrf @method('DELETE')
            <button class="link-btn">Take back</button>
        </form>
    @else
        <form method="post" action="{{ route('admin.students.certificates.store', $enrollment->user) }}"
              data-confirm="Give {{ $enrollment->user->name }} a certificate for {{ $enrollment->course->title }}, dated today?">
            @csrf
            <input type="hidden" name="course_id" value="{{ $enrollment->course_id }}">
            <button class="link-btn">Give certificate</button>
        </form>
    @endif
</div>
