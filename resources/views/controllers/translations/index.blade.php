@extends('arbory::layout.main')

@section('content.header')
    <header>
        <h2 class="heading">@lang('arbory::translations.all_translations')</h2>
        {!! $searchField !!}
    </header>
@stop

@section('content')

    <section class="actions">
        <div class="breadcrumbs">
            {!! $breadcrumbs !!}

            <p class="count-text">
                @lang('arbory::translations.translations_count', [ 'count' => $translationsCount ])
            </p>

            <p class="notice-text">
                @lang('arbory::translations.translations_notice')
            </p>
        </div>

        <div class="button-actions">
            {!! $importButton !!}
            {!! $exportButton !!}
        </div>
    </section>

    <section>
        <div class="body">
            <table class="table">
                <thead>
                <tr>
                    <th>Group</th>
                    <th>Key</th>
                    @foreach($languages as $language)
                        <th>{{$language->name}}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($translations as $translation)
                    <tr>
                        <td>
                            <a href="{{$translation->edit_url}}">{!! $highlight($translation->namespace) !!}::{!! $highlight($translation->group) !!}</a>
                        </td>
                        <td><a href="{{$translation->edit_url}}">{!! $highlight($translation->item) !!}</a></td>
                        @foreach($languages as $language)
                            <td>
                                <a href="{{$translation->edit_url}}">{!! $highlight($translation->{$language->locale . '_text'}) !!}</a>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
@stop
