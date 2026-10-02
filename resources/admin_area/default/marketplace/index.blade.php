@extends('admin::layouts.wrapper', [
    'activePage' => 'marketplace',
])

@section('title', 'Marketplace')

@section('actions')

@endsection

@section('content')
    @livewire(admin_view_path('marketplace.livewire.browse-marketplace'), ['lazy' => true])
@endsection
