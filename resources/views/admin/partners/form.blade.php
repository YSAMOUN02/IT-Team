@extends('admin.layout')

@section('title', $partner->exists ? $partner->name : 'New partner')
@section('crumbs')
    <span aria-hidden="true">/</span><a href="{{ route('admin.partners.index') }}">Partners</a>
    <span aria-hidden="true">/</span><span>{{ $partner->exists ? 'Edit' : 'New' }}</span>
@endsection
@section('actions')
    @if ($partner->exists)
        <form method="post" action="{{ route('admin.partners.destroy', $partner) }}" data-confirm="Remove {{ $partner->name }} from the partners?">
            @csrf @method('DELETE')
            <button class="btn btn-danger">@include('admin.partials.icon', ['name' => 'trash']) Delete</button>
        </form>
    @endif
@endsection

@section('content')
    <form method="post" enctype="multipart/form-data" data-main-form
          action="{{ $partner->exists ? route('admin.partners.update', $partner) : route('admin.partners.store') }}">
        @csrf
        @if ($partner->exists) @method('PUT') @endif

        <div class="form-grid reveal">
            <section class="card">
                <div class="card-head"><div><h2>Partner</h2><p>Name, link and logo.</p></div></div>
                <div class="card-body fields">
                    @include('admin.fields.input', ['name' => 'name', 'label' => 'Name', 'value' => $partner->name, 'required' => true])
                    @include('admin.fields.input', ['name' => 'url', 'label' => 'Website', 'type' => 'url', 'value' => $partner->url, 'placeholder' => 'https://', 'help' => 'The logo links here.'])
                    @include('admin.fields.image', ['name' => 'logo', 'remove' => 'remove_logo', 'label' => 'Logo', 'value' => $partner->logo, 'help' => 'A transparent PNG or SVG looks best.'])
                </div>
            </section>
            <div class="form-side">
                @include('admin.fields.publish', ['published' => $partner->is_published, 'sort' => $partner->sort_order ?? 0, 'model' => $partner])
            </div>
        </div>
    </form>
@endsection
