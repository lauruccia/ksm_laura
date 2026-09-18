<x-mail::message>
# Nuovo messaggio

**Da:** {{ $data['name'] }} ({{ $data['email'] }})
@isset($data['phone'])
**Telefono:** {{ $data['phone'] }}
@endisset

{{ $data['message'] }}

<x-mail::subcopy>
Messaggio inviato dal modulo di contatto di {{ $siteName }}.
</x-mail::subcopy>
</x-mail::message>
