@extends('layouts.app')

@section('title', __('site.nav_plans'))

@section('content')
    <section class="ksm-plans-hero">
        <div class="ksm-container">
            <p class="ksm-plans-hero__eyebrow">{{ __('site.nav_plans') }}</p>
            <h1>Il piano giusto per far crescere la tua attività</h1>
            <p class="ksm-plans-hero__lead">{{ __('site.step_plan_text') }}</p>
            <ul class="ksm-plans-hero__notes">
                <li><x-icon name="shield" :size="18" /> Pagamento sicuro</li>
                <li><x-icon name="check" :size="18" /> I piani una tantum non scadono</li>
                <li><x-icon name="sparkle" :size="18" /> Cambi piano quando vuoi</li>
            </ul>
        </div>
    </section>

    <section class="ksm-plans">
        <div class="ksm-container">
            @if ($plans->isEmpty())
                <p class="ksm-plans__empty">{{ __('site.no_results') }}</p>
            @else
                @php($compare = \App\Models\Plan::featureUnion($plans))
                <div class="ksm-plans__grid" style="--plans: {{ min($plans->count(), 4) }}">
                    @foreach ($plans as $plan)
                        @php($featured = $loop->first && $plans->count() > 1)
                        <x-plan-card :plan="$plan" :featured="$featured" :compare="$compare">
                            @auth
                                <a class="ksm-btn {{ $featured ? 'ksm-btn--primary' : 'ksm-btn--ghost' }} ksm-btn--block"
                                   href="{{ route('subscription.index') }}">
                                    Scegli {{ $plan->name }} <x-icon name="arrow" :size="18" />
                                </a>
                            @else
                                <a class="ksm-btn {{ $featured ? 'ksm-btn--primary' : 'ksm-btn--ghost' }} ksm-btn--block"
                                   href="{{ route('register.vendor', ['piano' => $plan->slug]) }}">
                                    Scegli {{ $plan->name }} <x-icon name="arrow" :size="18" />
                                </a>
                            @endauth
                        </x-plan-card>
                    @endforeach
                </div>
            @endif

            <ol class="ksm-plans__steps">
                <li>
                    <span>1</span>
                    <div>
                        <strong>{{ __('site.step_plan_title') }}</strong>
                        <p>{{ __('site.step_plan_text') }}</p>
                    </div>
                </li>
                <li>
                    <span>2</span>
                    <div>
                        <strong>{{ __('site.step_company_title') }}</strong>
                        <p>{{ __('site.step_company_text') }}</p>
                    </div>
                </li>
                <li>
                    <span>3</span>
                    <div>
                        <strong>{{ __('site.step_sell_title') }}</strong>
                        <p>{{ __('site.step_sell_text') }}</p>
                    </div>
                </li>
            </ol>
        </div>
    </section>
@endsection
