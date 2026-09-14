@extends('layouts.app')

@section('title', __('site.nav_plans'))

@section('content')
    <section class="ksm-section">
        <div class="ksm-container">
            <div class="ksm-section-head">
                <div>
                    <h2>{{ __('site.nav_plans') }}</h2>
                    <p>{{ __('site.step_plan_text') }}</p>
                </div>
            </div>

            @if ($plans->isEmpty())
                <p class="ksm-muted">{{ __('site.no_results') }}</p>
            @else
                <div class="ksm-grid ksm-grid--4">
                    @foreach ($plans as $plan)
                        <x-plan-card :plan="$plan">
                            @auth
                                <a class="ksm-btn ksm-btn--primary ksm-btn--block"
                                   href="{{ route('subscription.index') }}">Scegli</a>
                            @else
                                <a class="ksm-btn ksm-btn--primary ksm-btn--block"
                                   href="{{ route('register', ['piano' => $plan->slug]) }}">
                                    {{ __('site.register_company') }}
                                </a>
                            @endauth
                        </x-plan-card>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
