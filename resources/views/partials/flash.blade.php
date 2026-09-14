@if (session('success') || session('error') || $errors->any())
    <div class="ksm-container" style="padding-top: 20px;">
        @if (session('success'))
            <div class="ksm-alert ksm-alert--success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="ksm-alert ksm-alert--error">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="ksm-alert ksm-alert--error">
                <ul style="margin: 0; padding-left: 18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif
